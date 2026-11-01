<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    public function issue(
        User $user,
        Course $course,
        ?string $batchId = null,
        ?string $issuedAt = null
    ): Certificate {
        $this->assertEligible($user, $course, $batchId);
        $date = $issuedAt ? Carbon::parse($issuedAt) : now();
        $year = $date->format('Y');
        $month = (int) $date->format('m');

        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];
        $romanMonth = $romanMonths[$month];

        $countThisYear = Certificate::whereYear('issued_at', $year)->count() + 1;
        $sequence = str_pad($countThisYear, 4, '0', STR_PAD_LEFT);

        $formalNumber = $sequence.'/PT LAPI ITB/Sert/'.$romanMonth.'/'.$year;
        $certificateCode = 'CERT-'.strtoupper(substr($user->name, 0, 3)).'-'.$sequence.'-'.$year.'-'.$course->id;

        return Certificate::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'course_id' => $course->id,
            'batch_id' => $batchId,
            'certificate_code' => $certificateCode,
            'formal_number' => $formalNumber,
            'issued_at' => $date,
            'file_url' => 'certificates/placeholder.pdf',
        ]);
    }

    public function assertEligible(User $user, Course $course, ?string $batchId = null): void
    {
        $lessons = Lesson::with('quiz')->whereHas('section', fn ($query) => $query->where('course_id', $course->id))->get();
        $completed = DB::table('lesson_completions')->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessons->pluck('id'))->pluck('lesson_id');
        if ($lessons->isEmpty() || $lessons->contains(function ($lesson) use ($completed, $user, $batchId) {
            return ! $completed->contains($lesson->id)
                || ($lesson->quiz_id && (! $lesson->quiz || ! app(QuizService::class)->hasPassed($user, $lesson->quiz, $batchId)));
        })) {
            throw ValidationException::withMessages(['completion' => 'Selesaikan seluruh materi dan lulus seluruh kuis setelah penilaian esai sebelum menerima sertifikat.']);
        }
    }

    public function verify(string $code): ?Certificate
    {
        return Certificate::where('certificate_code', $code)->first();
    }
}

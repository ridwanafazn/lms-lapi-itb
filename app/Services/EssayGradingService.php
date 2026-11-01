<?php

namespace App\Services;

use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EssayGradingService
{
    public function authorize(User $grader, QuizAttempt $attempt): void
    {
        $course = $attempt->quiz->course;
        abort_unless($grader->hasRole('admin') || ($grader->hasRole('instructor') && $course
            && (string) $course->instructor_id === (string) $grader->id), 403);
    }

    public function grade(User $grader, QuizAnswer $answer, mixed $score, mixed $feedback): QuizAttempt
    {
        return DB::transaction(function () use ($grader, $answer, $score, $feedback) {
            // Use the same lock order as submission and answer writes.
            $attempt = QuizAttempt::whereKey($answer->quiz_attempt_id)->lockForUpdate()->firstOrFail();
            $this->authorize($grader, $attempt);
            $answer = $attempt->answers()->whereKey($answer->id)->lockForUpdate()->firstOrFail();
            $question = $attempt->quiz->questions()->findOrFail($answer->question_id);
            abort_unless($question->isEssay(), 422);
            if (! $attempt->isPendingGrading()) {
                throw ValidationException::withMessages(['score' => 'Hanya kuis yang sudah dikumpulkan dan menunggu penilaian yang dapat dinilai.']);
            }

            $data = Validator::make(['score' => $score, 'feedback' => $feedback], [
                'score' => ['required', 'numeric', 'min:0', 'max:'.max(0, (float) $question->score_weight), 'decimal:0,2'],
                'feedback' => ['required', 'string', 'max:10000'],
            ])->validate();

            $answer->update([
                'score' => $data['score'],
                'instructor_feedback' => $data['feedback'],
                'graded_by' => $grader->id,
                'graded_at' => now(),
            ]);

            return app(QuizGrader::class)->grade($attempt);
        });
    }
}

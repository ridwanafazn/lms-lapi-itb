<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Aturan sesi kuis: berapa kali boleh mencoba, attempt mana yang dilanjutkan,
 * dan urutan soal yang dipakai.
 *
 * Sebelumnya `max_attempts` dan `is_randomized` tersimpan di database tetapi
 * tidak pernah dibaca oleh komponen mana pun.
 */
class QuizService
{
    /** Nilai `max_attempts` = 0 berarti tanpa batas. */
    public const UNLIMITED = 0;

    public function completedAttempts(User $user, Quiz $quiz, ?string $batchId = null): int
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
            ->where(function ($query) {
                $query->whereNotNull('submitted_at')->orWhereNotNull('end_time')->orWhereNotNull('score');
            })
            ->count();
    }

    /**
     * @return int|null Null berarti tanpa batas.
     */
    public function remainingAttempts(User $user, Quiz $quiz, ?string $batchId = null): ?int
    {
        $max = (int) ($quiz->max_attempts ?? self::UNLIMITED);

        if ($max <= self::UNLIMITED) {
            return null;
        }

        return max(0, $max - $this->completedAttempts($user, $quiz, $batchId));
    }

    public function canAttempt(User $user, Quiz $quiz, ?string $batchId = null): bool
    {
        $remaining = $this->remainingAttempts($user, $quiz, $batchId);

        return $remaining === null || $remaining > 0;
    }

    public function findInProgress(User $user, Quiz $quiz, ?string $batchId = null): ?QuizAttempt
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
            ->whereNull('score')
            ->whereNull('submitted_at')
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();
    }

    /**
     * Lanjutkan attempt yang belum selesai, atau buat attempt baru.
     * Null berarti jatah percobaan sudah habis.
     */
    public function startOrResume(User $user, Quiz $quiz, ?string $batchId = null): ?QuizAttempt
    {
        $this->authorizeBatchContext($user, $quiz, $batchId);

        return DB::transaction(function () use ($user, $quiz, $batchId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($inProgress = $this->findInProgress($user, $quiz, $batchId)) {
                return $inProgress;
            }

            if (! $this->canAttempt($user, $quiz, $batchId)) {
                return null;
            }

            return QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'batch_id' => $batchId,
                'start_time' => now(),
                'current_question_index' => 0,
                'remaining_seconds' => $this->durationSeconds($quiz),
                'is_timeout' => false,
            ]);
        });
    }

    /**
     * Urutan soal untuk satu attempt.
     *
     * Urutan acak diturunkan dari ID attempt sehingga siswa yang menyegarkan
     * halaman tetap melihat urutan yang sama (tidak bisa "mengulang undian").
     *
     * @return Collection<int, \App\Models\Question>
     */
    public function questionsFor(QuizAttempt $attempt): Collection
    {
        $quiz = $attempt->quiz()->with('questions.options')->firstOrFail();
        $questions = $quiz->questions;

        if (! $quiz->is_randomized) {
            return $questions->values();
        }

        return $questions
            ->sortBy(fn ($question) => crc32($attempt->id.':'.$question->id))
            ->values();
    }

    public function latestSubmitted(User $user, Quiz $quiz, ?string $batchId = null): ?QuizAttempt
    {
        return $quiz->attempts()->where('user_id', $user->id)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
            ->where(function ($query) {
                $query->whereNotNull('submitted_at')->orWhereNotNull('end_time')->orWhereNotNull('score');
            })
            ->latest('start_time')->first();
    }

    public function hasPassed(User $user, Quiz $quiz, ?string $batchId = null): bool
    {
        return $quiz->attempts()->where('user_id', $user->id)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
            ->whereNotNull('graded_at')->whereNotNull('score')
            ->where('score', '>=', $quiz->passing_grade ?? 70)->exists();
    }

    public function authorizeBatchContext(User $user, Quiz $quiz, ?string $batchId): void
    {
        if ($batchId === null) {
            return;
        }
        $batch = Batch::find($batchId);
        abort_unless($batch && $quiz->course_id, 404);
        $containsCourse = (string) $batch->course_id === (string) $quiz->course_id
            || $batch->program?->courses()->where('courses.id', $quiz->course_id)->exists();
        abort_unless($containsCourse, 404);
        $isOwner = $user->hasRole('instructor')
            && (string) $quiz->course?->instructor_id === (string) $user->id;
        abort_unless($isOwner || $user->canAccessBatch($batch), 403);
    }

    public function saveEssay(User $user, QuizAttempt $attempt, string $questionId, string $text): void
    {
        if (mb_strlen($text) > 50000) {
            throw ValidationException::withMessages(['essayAnswer' => 'Jawaban maksimal 50.000 karakter.']);
        }
        $this->editAnswer($user, $attempt, function ($locked) use ($questionId, $text) {
            $question = $locked->quiz->questions()->findOrFail($questionId);
            abort_unless($question->isEssay(), 422);
            $locked->answers()->updateOrCreate(
                ['question_id' => $question->id, 'question_option_id' => null],
                ['essay_answer' => $text, 'score' => null]
            );
        });
    }

    public function saveChoice(User $user, QuizAttempt $attempt, string $questionId, string $optionId, bool $toggleMultiple = false): void
    {
        $this->editAnswer($user, $attempt, function ($locked) use ($questionId, $optionId, $toggleMultiple) {
            $question = $locked->quiz->questions()->with('options')->findOrFail($questionId);
            abort_if($question->isEssay() || ! $question->options->contains('id', $optionId), 422);
            $answers = $locked->answers()->where('question_id', $questionId);
            if ($toggleMultiple && $question->isMultipleSelect()) {
                $selected = (clone $answers)->where('question_option_id', $optionId)->first();
                if ($selected) {
                    $selected->delete();

                    return;
                }
            } else {
                $answers->delete();
            }
            $locked->answers()->firstOrCreate(['question_id' => $questionId, 'question_option_id' => $optionId]);
        });
    }

    private function editAnswer(User $user, QuizAttempt $attempt, callable $save): void
    {
        DB::transaction(function () use ($user, $attempt, $save) {
            $locked = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $user->id, 403);
            Gate::forUser($user)->authorize('submit', $locked->quiz);
            $this->authorizeBatchContext($user, $locked->quiz, $locked->batch_id);
            abort_if($locked->isSubmitted(), 403, 'Kuis sudah dikumpulkan.');
            if ($this->isExpired($locked)) {
                app(QuizGrader::class)->grade($locked, true);

                return;
            }
            $save($locked);
        });
    }

    public function durationSeconds(Quiz $quiz): int
    {
        return max(0, (int) ($quiz->duration_minutes ?? 0) * 60);
    }

    /**
     * Sisa waktu berdasarkan jam mulai di server (bukan state klien).
     */
    public function secondsRemaining(QuizAttempt $attempt): int
    {
        $duration = $this->durationSeconds($attempt->quiz()->firstOrFail());

        if (! $attempt->start_time) {
            return $duration;
        }

        $elapsed = (int) $attempt->start_time->diffInSeconds(now(), false);

        return max(0, $duration - $elapsed);
    }

    /**
     * True bila durasi kuis (ditambah toleransi) sudah terlampaui.
     */
    public function isExpired(QuizAttempt $attempt, int $graceSeconds = 15): bool
    {
        $duration = $this->durationSeconds($attempt->quiz()->firstOrFail());

        if ($duration <= 0 || ! $attempt->start_time) {
            return false;
        }

        $elapsed = (int) $attempt->start_time->diffInSeconds(now(), false);

        return $elapsed > ($duration + $graceSeconds);
    }
}

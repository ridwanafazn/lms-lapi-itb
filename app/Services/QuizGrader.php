<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

class QuizGrader
{
    public function grade(QuizAttempt $attempt, bool $timedOut = false): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $timedOut) {
            $attempt = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->score !== null) {
                return $attempt;
            }

            $quiz = $attempt->quiz()->with('questions.options')->firstOrFail();
            if (! $attempt->isSubmitted()) {
                $attempt->update([
                    'submitted_at' => now(),
                    'end_time' => now(),
                    'remaining_seconds' => 0,
                    'is_timeout' => $timedOut || app(QuizService::class)->isExpired($attempt),
                ]);
            }

            $earnedWeight = 0;
            $totalWeight = 0;
            $pending = false;
            $hasEssays = false;
            $savedAnswers = $attempt->answers()->get()->groupBy('question_id');

            foreach ($quiz->questions as $question) {
                $weight = $question->isEssay() ? max(0, (float) $question->score_weight) : (int) ($question->score_weight ?: 1);
                $totalWeight += $weight;
                $answers = $savedAnswers->get($question->id, collect());

                if ($question->isEssay()) {
                    $hasEssays = true;
                    $answer = $answers->first();
                    if (! $answer) {
                        // Unanswered essays (including timeouts) still require a manual decision.
                        $answer = $attempt->answers()->create([
                            'question_id' => $question->id,
                            'essay_answer' => '',
                            'score' => null,
                        ]);
                    }
                    if ($answer->score === null || $answer->graded_at === null) {
                        $pending = true;
                    } else {
                        $earnedWeight += $answer->score;
                    }

                    continue;
                }

                $correct = $question->options->where('is_correct', true)->pluck('id')->sort()->values()->all();
                $selected = $answers->pluck('question_option_id')->filter()->sort()->values()->all();
                if ($correct !== [] && $selected === $correct) {
                    $earnedWeight += $weight;
                }
            }

            if ($pending) {
                return $attempt->refresh();
            }

            $attempt->update([
                'score' => $totalWeight > 0 ? round($earnedWeight / $totalWeight * 100, 2) : 0.0,
                'graded_at' => now(),
            ]);

            if ($hasEssays && $attempt->hasPassed()) {
                foreach (Lesson::where('quiz_id', $quiz->id)->get() as $lesson) {
                    DB::table('lesson_completions')->updateOrInsert(
                        ['user_id' => $attempt->user_id, 'lesson_id' => $lesson->id],
                        ['completed_at' => now()]
                    );
                }
            }

            return $attempt->refresh();
        });
    }
}

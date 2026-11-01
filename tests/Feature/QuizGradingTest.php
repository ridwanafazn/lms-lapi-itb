<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizGrader;
use App\Services\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizGradingTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::create([
            'name' => 'Siswa Kuis',
            'email' => 'kuis-'.uniqid().'@example.test',
            'password' => 'password',
        ]);
    }

    private function quiz(array $attributes = []): Quiz
    {
        return Quiz::create(array_merge([
            'title' => 'Kuis Uji',
            'duration_minutes' => 10,
            'passing_grade' => 70,
            'max_attempts' => 0,
            'is_randomized' => false,
        ], $attributes));
    }

    /**
     * @return array{question: Question, correct: QuestionOption, wrong: QuestionOption}
     */
    private function question(Quiz $quiz, bool $correct = true, int $weight = 1): array
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Pilih jawaban benar',
            'question_type' => 'multiple_choice',
            'score_weight' => $weight,
        ]);

        $correctOption = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Benar',
            'is_correct' => $correct,
            'order_index' => 1,
        ]);

        $wrongOption = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Salah',
            'is_correct' => false,
            'order_index' => 2,
        ]);

        return ['question' => $question, 'correct' => $correctOption, 'wrong' => $wrongOption];
    }

    private function attempt(User $user, Quiz $quiz): QuizAttempt
    {
        return QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'start_time' => now(),
            'current_question_index' => 0,
        ]);
    }

    private function answer(QuizAttempt $attempt, Question $question, QuestionOption $option): void
    {
        QuizAnswer::create([
            'quiz_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'question_option_id' => $option->id,
        ]);
    }

    public function test_all_correct_answers_score_one_hundred(): void
    {
        $user = $this->student();
        $quiz = $this->quiz();
        $q1 = $this->question($quiz);
        $q2 = $this->question($quiz);

        $attempt = $this->attempt($user, $quiz);
        $this->answer($attempt, $q1['question'], $q1['correct']);
        $this->answer($attempt, $q2['question'], $q2['correct']);

        $graded = app(QuizGrader::class)->grade($attempt);

        $this->assertSame(100.0, (float) $graded->score);
        $this->assertNotNull($graded->graded_at);
        $this->assertFalse($graded->is_timeout);
    }

    public function test_partially_correct_answers_score_proportionally(): void
    {
        $user = $this->student();
        $quiz = $this->quiz();
        $q1 = $this->question($quiz);
        $q2 = $this->question($quiz);

        $attempt = $this->attempt($user, $quiz);
        $this->answer($attempt, $q1['question'], $q1['correct']);
        $this->answer($attempt, $q2['question'], $q2['wrong']);

        $graded = app(QuizGrader::class)->grade($attempt);

        $this->assertSame(50.0, (float) $graded->score);
    }

    public function test_score_is_weighted_by_question_weight(): void
    {
        $user = $this->student();
        $quiz = $this->quiz();
        $heavy = $this->question($quiz, true, 3);
        $light = $this->question($quiz, true, 1);

        $attempt = $this->attempt($user, $quiz);
        // Hanya soal berbobot 3 yang benar => 3 / 4 = 75.
        $this->answer($attempt, $heavy['question'], $heavy['correct']);

        $graded = app(QuizGrader::class)->grade($attempt);

        $this->assertSame(75.0, (float) $graded->score);
        $this->assertNotNull($light);
    }

    public function test_multi_select_requires_every_correct_option(): void
    {
        $user = $this->student();
        $quiz = $this->quiz();

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Pilih semua yang benar',
            'question_type' => 'multiple_choice',
            'score_weight' => 1,
        ]);

        $correctA = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'A', 'is_correct' => true, 'order_index' => 1]);
        $correctB = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'B', 'is_correct' => true, 'order_index' => 2]);

        $partial = $this->attempt($user, $quiz);
        $this->answer($partial, $question, $correctA);

        $this->assertSame(0.0, (float) app(QuizGrader::class)->grade($partial)->score);

        $full = $this->attempt($user, $quiz);
        $this->answer($full, $question, $correctA);
        $this->answer($full, $question, $correctB);

        $this->assertSame(100.0, (float) app(QuizGrader::class)->grade($full)->score);
    }

    public function test_grading_is_idempotent(): void
    {
        $user = $this->student();
        $quiz = $this->quiz();
        $q = $this->question($quiz);

        $attempt = $this->attempt($user, $quiz);
        $this->answer($attempt, $q['question'], $q['correct']);

        $first = app(QuizGrader::class)->grade($attempt);
        $second = app(QuizGrader::class)->grade($first->refresh());

        $this->assertSame(100.0, (float) $first->score);
        $this->assertSame(100.0, (float) $second->score);
    }

    public function test_attempt_limit_blocks_new_attempts(): void
    {
        $user = $this->student();
        $quiz = $this->quiz(['max_attempts' => 1]);
        $q = $this->question($quiz);

        $attempt = $this->attempt($user, $quiz);
        $this->answer($attempt, $q['question'], $q['correct']);
        app(QuizGrader::class)->grade($attempt);

        $service = app(QuizService::class);

        $this->assertSame(0, $service->remainingAttempts($user, $quiz));
        $this->assertFalse($service->canAttempt($user, $quiz));
        $this->assertNull($service->startOrResume($user, $quiz));
    }

    public function test_attempt_limit_of_zero_means_unlimited(): void
    {
        $user = $this->student();
        $quiz = $this->quiz(['max_attempts' => 0]);
        $q = $this->question($quiz);

        $first = $this->attempt($user, $quiz);
        $this->answer($first, $q['question'], $q['correct']);
        app(QuizGrader::class)->grade($first);

        $service = app(QuizService::class);

        $this->assertNull($service->remainingAttempts($user, $quiz));
        $this->assertTrue($service->canAttempt($user, $quiz));
        $this->assertNotNull($service->startOrResume($user, $quiz));
    }

    public function test_unfinished_attempt_is_resumed_instead_of_created(): void
    {
        $user = $this->student();
        $quiz = $this->quiz();

        $service = app(QuizService::class);

        $first = $service->startOrResume($user, $quiz);
        $second = $service->startOrResume($user, $quiz);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, QuizAttempt::where('user_id', $user->id)->count());
    }

    public function test_randomized_order_is_stable_for_the_same_attempt(): void
    {
        $user = $this->student();
        $quiz = $this->quiz(['is_randomized' => true]);

        foreach (range(1, 8) as $index) {
            $this->question($quiz);
        }

        $service = app(QuizService::class);
        $attempt = $service->startOrResume($user, $quiz);

        $firstOrder = $service->questionsFor($attempt)->pluck('id')->all();
        $secondOrder = $service->questionsFor($attempt->refresh())->pluck('id')->all();

        $this->assertSame($firstOrder, $secondOrder);
        $this->assertCount(8, $firstOrder);
    }

    public function test_expired_attempt_is_detected_from_server_time(): void
    {
        $user = $this->student();
        $quiz = $this->quiz(['duration_minutes' => 10]);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'start_time' => now()->subMinutes(30),
            'current_question_index' => 0,
        ]);

        $service = app(QuizService::class);

        $this->assertTrue($service->isExpired($attempt));
        $this->assertSame(0, $service->secondsRemaining($attempt));

        $graded = app(QuizGrader::class)->grade($attempt, true);

        $this->assertTrue($graded->is_timeout);
    }
}

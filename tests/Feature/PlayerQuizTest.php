<?php

namespace Tests\Feature;

use App\Livewire\Student\Learning\Player;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerQuizTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Admin Kuis',
            'email' => 'admin-quiz-'.uniqid().'@example.test',
            'password' => 'password',
        ]);

        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array{0: Course, 1: Lesson, 2: Question, 3: QuestionOption, 4: QuestionOption}
     */
    private function courseWithQuiz(int $maxAttempts = 0): array
    {
        $course = Course::create(['title' => 'Kursus Kuis']);
        $section = Section::create([
            'course_id' => $course->id,
            'title' => 'Bab 1',
            'order_index' => 1,
        ]);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'title' => 'Kuis Bab 1',
            'duration_minutes' => 10,
            'passing_grade' => 70,
            'max_attempts' => $maxAttempts,
            'status' => 'active',
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Ibu kota Indonesia?',
            'question_type' => 'multiple_choice',
            'score_weight' => 1,
        ]);

        $correct = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Jakarta',
            'is_correct' => true,
            'order_index' => 1,
        ]);

        $wrong = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Bandung',
            'is_correct' => false,
            'order_index' => 2,
        ]);

        $lesson = Lesson::create([
            'section_id' => $section->id,
            'item_type' => 'quiz',
            'quiz_id' => $quiz->id,
            'sort_order' => 1,
            'is_required' => true,
        ]);

        return [$course, $lesson, $question, $correct, $wrong];
    }

    private function player(Course $course, Lesson $lesson)
    {
        return Livewire::withQueryParams(['lesson_id' => $lesson->id])
            ->test(Player::class, ['course' => $course]);
    }

    public function test_changing_a_single_choice_answer_replaces_the_previous_one(): void
    {
        $this->actingAs($this->admin());

        [$course, $lesson, $question, $correct, $wrong] = $this->courseWithQuiz();

        $component = $this->player($course, $lesson)
            ->call('startQuiz')
            ->call('saveAnswer', $question->id, $wrong->id)
            ->call('saveAnswer', $question->id, $correct->id);

        $attempt = QuizAttempt::firstOrFail();

        $this->assertSame(1, QuizAnswer::where('quiz_attempt_id', $attempt->id)->count());
        $component->call('finishQuiz');

        $this->assertSame(100.0, (float) $attempt->fresh()->score);
    }

    public function test_finishing_the_quiz_marks_the_lesson_complete_when_passed(): void
    {
        $this->actingAs($this->admin());

        [$course, $lesson, $question, $correct] = $this->courseWithQuiz();

        $this->player($course, $lesson)
            ->call('startQuiz')
            ->call('saveAnswer', $question->id, $correct->id)
            ->call('finishQuiz');

        $this->assertDatabaseHas('lesson_completions', ['lesson_id' => $lesson->id]);
    }

    public function test_reaching_the_attempt_limit_blocks_starting_again(): void
    {
        $this->actingAs($this->admin());

        [$course, $lesson, $question, $correct] = $this->courseWithQuiz(1);

        $this->player($course, $lesson)
            ->call('startQuiz')
            ->call('saveAnswer', $question->id, $correct->id)
            ->call('finishQuiz');

        $this->player($course, $lesson)
            ->call('startQuiz')
            ->assertSet('isQuizActive', false);

        $this->assertSame(1, QuizAttempt::count());
    }
}

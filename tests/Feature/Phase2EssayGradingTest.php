<?php

namespace Tests\Feature;

use App\Livewire\Instructor\EssayGrading;
use App\Livewire\Student\Learning\Player;
use App\Livewire\Student\Learning\QuizPlayer;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\User;
use App\Services\CertificateService;
use App\Services\EssayGradingService;
use App\Services\QuizGrader;
use App\Services\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase2EssayGradingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('grading.essays')) {
            require base_path('routes/phase2-essays.php');
        }
    }

    private function user(string $role = 'student'): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function fixture(int $maxAttempts = 1, int $essays = 2): array
    {
        $owner = $this->user('instructor');
        $student = $this->user();
        $course = Course::create(['title' => 'Essay Course', 'instructor_id' => $owner->id, 'delivery_mode' => 'self_paced']);
        $program = Program::create(['title' => 'Essay Program', 'type' => 'self_paced', 'price' => 100, 'is_active' => true]);
        $program->courses()->attach($course->id, ['order_index' => 1]);
        Enrollment::create(['user_id' => $student->id, 'enrollable_type' => Program::class, 'enrollable_id' => $program->id, 'status' => 'active', 'enrolled_at' => now()]);
        $section = Section::create(['course_id' => $course->id, 'title' => 'Section', 'order_index' => 1]);
        $quiz = Quiz::create(['course_id' => $course->id, 'title' => 'Essay Quiz', 'status' => 'active', 'duration_minutes' => 10, 'passing_grade' => 70, 'max_attempts' => $maxAttempts]);
        $lesson = Lesson::create(['section_id' => $section->id, 'quiz_id' => $quiz->id, 'item_type' => 'quiz', 'sort_order' => 1, 'is_required' => true]);
        $questions = collect();
        for ($i = 0; $i < $essays; $i++) {
            $questions->push(Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Explain '.$i, 'question_type' => 'essay', 'score_weight' => 4]));
        }
        $attempt = app(QuizService::class)->startOrResume($student, $quiz);

        return compact('owner', 'student', 'program', 'course', 'quiz', 'lesson', 'questions', 'attempt');
    }

    private function submit(array $fixture, bool $timeout = false): QuizAttempt
    {
        return app(QuizGrader::class)->grade($fixture['attempt'], $timeout);
    }

    private function gradeAll(array $fixture, float $score = 4): QuizAttempt
    {
        $attempt = $this->submit($fixture);
        foreach ($attempt->answers()->whereNull('graded_at')->get() as $answer) {
            $attempt = app(EssayGradingService::class)->grade($fixture['owner'], $answer, $score, 'Feedback');
        }

        return $attempt;
    }

    private function player(array $fixture)
    {
        return Livewire::actingAs($fixture['student'])->withQueryParams(['lesson_id' => $fixture['lesson']->id])
            ->test(Player::class, ['course' => $fixture['course']]);
    }

    public function test_submitted_essays_remain_pending_until_every_essay_is_graded(): void
    {
        $f = $this->fixture();
        app(QuizService::class)->saveEssay($f['student'], $f['attempt'], $f['questions'][0]->id, 'My answer');
        $attempt = $this->submit($f);
        $this->assertTrue($attempt->isPendingGrading());
        $this->assertNotNull($attempt->submitted_at);
        $this->assertNotNull($attempt->end_time);
        $this->assertNull($attempt->graded_at);
        $this->assertFalse($attempt->hasPassed());
        $this->assertCount(2, $attempt->answers);
        $answers = $attempt->answers()->orderBy('id')->get();
        $attempt = app(EssayGradingService::class)->grade($f['owner'], $answers[0], 4, 'Good');
        $this->assertNull($attempt->score);
        $this->assertDatabaseMissing('lesson_completions', ['lesson_id' => $f['lesson']->id]);
        $attempt = app(EssayGradingService::class)->grade($f['owner'], $answers[1], 2, 'Needs detail');
        $this->assertSame(75.0, $attempt->score);
        $this->assertTrue($attempt->hasPassed());
        $this->assertDatabaseHas('lesson_completions', ['lesson_id' => $f['lesson']->id, 'user_id' => $f['student']->id]);
        $this->assertDatabaseHas('quiz_answers', ['id' => $answers[1]->id, 'score' => 2, 'instructor_feedback' => 'Needs detail', 'graded_by' => $f['owner']->id]);
    }

    public function test_final_score_combines_automatic_and_manual_weights(): void
    {
        $f = $this->fixture(1, 1);
        $question = Question::create(['quiz_id' => $f['quiz']->id, 'question_text' => 'Choose', 'question_type' => 'multiple_choice', 'score_weight' => 6]);
        $option = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Correct', 'is_correct' => true, 'order_index' => 1]);
        app(QuizService::class)->saveChoice($f['student'], $f['attempt'], $question->id, $option->id);
        $attempt = $this->submit($f);
        $attempt = app(EssayGradingService::class)->grade($f['owner'], $attempt->answers()->where('question_id', $f['questions'][0]->id)->firstOrFail(), 1.5, 'Partial credit');
        $this->assertSame(75.0, $attempt->score);
    }

    public function test_zero_is_a_real_grade_and_does_not_complete_a_failed_quiz(): void
    {
        $f = $this->fixture(1, 1);
        $attempt = $this->gradeAll($f, 0);
        $this->assertSame(0.0, $attempt->score);
        $this->assertNotNull($attempt->graded_at);
        $this->assertFalse($attempt->isPendingGrading());
        $this->assertFalse($attempt->hasPassed());
        $this->assertDatabaseMissing('lesson_completions', ['lesson_id' => $f['lesson']->id]);
    }

    public function test_pending_submissions_count_towards_limits_and_cannot_resume(): void
    {
        $f = $this->fixture();
        $service = app(QuizService::class);
        $this->assertSame($f['attempt']->id, $service->startOrResume($f['student'], $f['quiz'])->id);
        $this->submit($f);
        $this->assertSame(1, $service->completedAttempts($f['student'], $f['quiz']));
        $this->assertSame(0, $service->remainingAttempts($f['student'], $f['quiz']));
        $this->assertNull($service->findInProgress($f['student'], $f['quiz']));
        $this->assertNull($service->startOrResume($f['student'], $f['quiz']));
    }

    public function test_unlimited_retries_create_a_new_attempt_instead_of_resuming_pending(): void
    {
        $f = $this->fixture(0);
        $this->submit($f);
        $next = app(QuizService::class)->startOrResume($f['student'], $f['quiz']);
        $this->assertNotSame($f['attempt']->id, $next->id);
        $this->assertFalse($next->isSubmitted());
    }

    public function test_timeout_creates_blank_essay_answers_that_can_be_manually_graded(): void
    {
        $f = $this->fixture();
        $f['attempt']->update(['start_time' => now()->subHour()]);
        $attempt = $this->submit($f);
        $this->assertTrue($attempt->is_timeout);
        $this->assertTrue($attempt->isPendingGrading());
        $this->assertSame('', $attempt->answers()->first()->essay_answer);
        $attempt = $this->gradeAll($f);
        $this->assertSame(100.0, $attempt->score);
        $this->assertTrue($attempt->is_timeout);
        $this->assertDatabaseHas('lesson_completions', ['lesson_id' => $f['lesson']->id]);
    }

    public function test_submission_is_idempotent_even_with_a_stale_attempt_instance(): void
    {
        $f = $this->fixture();
        $attempt = $this->submit($f);
        $answer = $attempt->answers()->firstOrFail();
        app(EssayGradingService::class)->grade($f['owner'], $answer, 4, 'Good');
        $again = $this->submit($f, true);
        $this->assertEquals($attempt->submitted_at, $again->submitted_at);
        $this->assertFalse($again->is_timeout);
        $this->assertSame(4.0, $answer->fresh()->score);
        $this->assertSame(2, $again->answers()->count());
        $final = $this->gradeAll($f);
        $this->assertSame($final->score, $this->submit($f)->score);
    }

    public function test_submitted_essay_answers_are_immutable(): void
    {
        $f = $this->fixture();
        $this->submit($f);
        $this->expectException(HttpException::class);
        app(QuizService::class)->saveEssay($f['student'], $f['attempt'], $f['questions'][0]->id, 'Tampered');
    }

    public function test_only_course_owner_or_admin_can_grade(): void
    {
        $f = $this->fixture(1, 1);
        $attempt = $this->submit($f);
        $this->expectException(HttpException::class);
        app(EssayGradingService::class)->grade($this->user('instructor'), $attempt->answers()->firstOrFail(), 4, 'Not owner');
    }

    public function test_student_cannot_grade_their_own_attempt(): void
    {
        $f = $this->fixture(1, 1);
        $attempt = $this->submit($f);
        $this->expectException(HttpException::class);
        app(EssayGradingService::class)->grade($f['student'], $attempt->answers()->firstOrFail(), 4, 'Self grade');
    }

    public function test_admin_can_grade_another_instructors_course(): void
    {
        $f = $this->fixture(1, 1);
        $attempt = $this->submit($f);
        $admin = $this->user('admin');
        $graded = app(EssayGradingService::class)->grade($admin, $attempt->answers()->firstOrFail(), 4, 'Admin feedback');
        $this->assertSame(100.0, $graded->score);
    }

    public function test_invalid_scores_and_missing_feedback_are_rejected_without_writes(): void
    {
        $f = $this->fixture(1, 1);
        $answer = $this->submit($f)->answers()->firstOrFail();
        foreach ([[-1, 'Feedback'], [5, 'Feedback'], ['bad', 'Feedback'], [1.234, 'Feedback'], [null, 'Feedback'], [1, '']] as [$score, $feedback]) {
            try {
                app(EssayGradingService::class)->grade($f['owner'], $answer, $score, $feedback);
                $this->fail('Invalid grade accepted');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
            $this->assertNull($answer->fresh()->graded_at);
            $this->assertNull($answer->fresh()->score);
        }
    }

    public function test_in_progress_attempts_cannot_be_manually_graded(): void
    {
        $f = $this->fixture();
        app(QuizService::class)->saveEssay($f['student'], $f['attempt'], $f['questions'][0]->id, 'Draft');
        $this->expectException(ValidationException::class);
        app(EssayGradingService::class)->grade($f['owner'], $f['attempt']->answers()->firstOrFail(), 4, 'Premature');
    }

    public function test_finalized_grades_are_not_overwritten(): void
    {
        $f = $this->fixture(1, 1);
        $attempt = $this->gradeAll($f);
        $this->expectException(ValidationException::class);
        app(EssayGradingService::class)->grade($f['owner'], $attempt->answers()->firstOrFail(), 0, 'Changed');
    }

    public function test_quiz_player_saves_and_resumes_essay_text_and_shows_pending_on_reload(): void
    {
        $f = $this->fixture(1, 1);
        $component = Livewire::actingAs($f['student'])->test(QuizPlayer::class, ['course' => $f['course']->id, 'quiz' => $f['quiz']->id])
            ->set('answers.'.$f['questions'][0]->id, 'Saved essay');
        $this->assertDatabaseHas('quiz_answers', ['quiz_attempt_id' => $f['attempt']->id, 'essay_answer' => 'Saved essay']);
        Livewire::test(QuizPlayer::class, ['course' => $f['course']->id, 'quiz' => $f['quiz']->id])
            ->assertSet('answers.'.$f['questions'][0]->id, 'Saved essay');
        $component->call('submitQuiz')->assertSet('isPendingGrading', true)->assertSet('isPassed', false)->assertSet('finalScore', null)->assertSee('Menunggu Penilaian Esai');
        Livewire::test(QuizPlayer::class, ['course' => $f['course']->id, 'quiz' => $f['quiz']->id])->assertSet('isPendingGrading', true);
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_student_can_view_final_feedback_without_starting_a_new_attempt(): void
    {
        $f = $this->fixture(0, 1);
        $this->gradeAll($f);
        Livewire::actingAs($f['student'])->test(QuizPlayer::class, ['course' => $f['course']->id, 'quiz' => $f['quiz']->id])
            ->assertSet('isPassed', true)->assertSet('finalScore', 100.0)->assertSee('Feedback');
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_inline_player_cannot_complete_pending_quiz_even_when_content_type_is_spoofed(): void
    {
        $f = $this->fixture();
        $this->submit($f);
        $this->player($f)->set('contentType', 'video')->call('markAsComplete')
            ->set('contentType', 'text')->call('autoCompleteContent')->call('completeByScroll');
        $this->assertDatabaseMissing('lesson_completions', ['lesson_id' => $f['lesson']->id]);
    }

    public function test_inline_player_submits_essays_without_premature_completion(): void
    {
        $f = $this->fixture();
        $this->player($f)->call('startQuiz')->call('saveEssayAnswer', $f['questions'][0]->id, 'Inline essay')->call('finishQuiz');
        $this->assertTrue($f['attempt']->fresh()->isPendingGrading());
        $this->assertDatabaseHas('quiz_answers', ['quiz_attempt_id' => $f['attempt']->id, 'essay_answer' => 'Inline essay']);
        $this->assertDatabaseMissing('lesson_completions', ['lesson_id' => $f['lesson']->id]);
    }

    public function test_certificate_service_rejects_pending_grades_despite_forged_completion(): void
    {
        $f = $this->fixture();
        $this->submit($f);
        DB::table('lesson_completions')->insert(['lesson_id' => $f['lesson']->id, 'user_id' => $f['student']->id, 'completed_at' => now()]);
        $this->expectException(ValidationException::class);
        app(CertificateService::class)->issue($f['student'], $f['course']);
    }

    public function test_direct_testimonial_action_cannot_issue_certificate_with_pending_grades(): void
    {
        $f = $this->fixture();
        $this->submit($f);
        $this->player($f)->set('overallProgress', 100)->set('publicTestimonial', 'Testimonial bypass attempt')
            ->call('submitTestimonial')->assertHasErrors(['completion']);
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('course_testimonials', 0);
    }

    public function test_certificate_is_available_after_all_essays_pass(): void
    {
        $f = $this->fixture();
        $this->gradeAll($f);
        $certificate = app(CertificateService::class)->issue($f['student'], $f['course']);
        $this->assertSame($f['student']->id, $certificate->user_id);
    }

    public function test_grading_page_filters_ownership_and_reauthorizes_each_action(): void
    {
        $f = $this->fixture(1, 1);
        $other = $this->fixture(1, 1);
        $attempt = $this->submit($f);
        $otherAttempt = $this->submit($other);
        Livewire::actingAs($f['owner'])->test(EssayGrading::class)
            ->assertSee($f['student']->name)->assertDontSee($other['student']->name)
            ->call('selectAttempt', $otherAttempt->id)->assertForbidden();
        $answer = $attempt->answers()->firstOrFail();
        Livewire::actingAs($f['owner'])->test(EssayGrading::class, ['attempt' => $attempt->id])
            ->set('scores.'.$answer->id, 4)->set('feedback.'.$answer->id, 'Reviewed in UI')
            ->call('saveGrade', $answer->id)->assertHasNoErrors()->assertSee('Nilai akhir: 100.00');
        $this->assertSame(100.0, $attempt->fresh()->score);
    }

    public function test_grading_route_requires_instructor_or_admin(): void
    {
        $student = $this->user();
        $this->actingAs($student)->get('/grading/essays')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/grading/essays')->assertOk();
    }

    public function test_grading_page_cannot_save_an_answer_from_another_attempt(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $other['course']->update(['instructor_id' => $f['owner']->id]);
        $attempt = $this->submit($f);
        $otherAnswer = $this->submit($other)->answers()->firstOrFail();
        Livewire::actingAs($f['owner'])->test(EssayGrading::class, ['attempt' => $attempt->id])
            ->set('scores.'.$otherAnswer->id, 4)->set('feedback.'.$otherAnswer->id, 'Wrong attempt')
            ->call('saveGrade', $otherAnswer->id)->assertNotFound();
        $this->assertNull($otherAnswer->fresh()->score);
        $this->assertNull($otherAnswer->fresh()->graded_at);
        $this->assertNull($attempt->fresh()->score);
        $this->assertDatabaseCount('lesson_completions', 0);
    }

    public function test_grading_page_returns_404_for_an_unknown_answer(): void
    {
        $f = $this->fixture();
        $attempt = $this->submit($f);
        Livewire::actingAs($f['owner'])->test(EssayGrading::class, ['attempt' => $attempt->id])
            ->call('saveGrade', '00000000-0000-4000-8000-000000000000')->assertNotFound();
        $this->assertNull($attempt->fresh()->score);
    }

    public function test_quiz_player_rejects_a_batch_from_another_course_even_for_admin(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $batch = Batch::create(['course_id' => $other['course']->id, 'batch_name' => 'Unrelated Batch', 'status' => 'open']);
        Livewire::actingAs($this->user('admin'))->test(QuizPlayer::class, [
            'course' => $f['course']->id, 'quiz' => $f['quiz']->id, 'batch' => $batch->id,
        ])->assertNotFound();
        $this->assertSame(2, QuizAttempt::count());
    }

    public function test_unenrolled_batch_cannot_be_used_to_bypass_submitted_attempt_limits(): void
    {
        $f = $this->fixture();
        $this->submit($f);
        $batch = Batch::create(['course_id' => $f['course']->id, 'batch_name' => 'Unenrolled Batch', 'status' => 'open']);
        Livewire::actingAs($f['student'])->test(QuizPlayer::class, [
            'course' => $f['course']->id, 'quiz' => $f['quiz']->id, 'batch' => $batch->id,
        ])->assertForbidden();
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_authorized_program_batch_keeps_its_own_submitted_attempt_limit(): void
    {
        $f = $this->fixture();
        $batch = Batch::create(['program_id' => $f['program']->id, 'batch_name' => 'Program Batch', 'status' => 'open']);
        Enrollment::create(['user_id' => $f['student']->id, 'enrollable_type' => Batch::class, 'enrollable_id' => $batch->id, 'status' => 'active', 'enrolled_at' => now()]);
        $service = app(QuizService::class);
        $attempt = $service->startOrResume($f['student'], $f['quiz'], $batch->id);
        $this->assertSame($batch->id, $attempt->batch_id);
        app(QuizGrader::class)->grade($attempt);
        $this->assertSame(0, $service->remainingAttempts($f['student'], $f['quiz'], $batch->id));
        $this->assertNull($service->startOrResume($f['student'], $f['quiz'], $batch->id));
    }

    public function test_batch_access_is_rechecked_before_quiz_submission(): void
    {
        $f = $this->fixture();
        $batch = Batch::create(['course_id' => $f['course']->id, 'batch_name' => 'Revoked Batch', 'status' => 'open']);
        $enrollment = Enrollment::create(['user_id' => $f['student']->id, 'enrollable_type' => Batch::class, 'enrollable_id' => $batch->id, 'status' => 'active', 'enrolled_at' => now()]);
        $component = Livewire::actingAs($f['student'])->test(QuizPlayer::class, [
            'course' => $f['course']->id, 'quiz' => $f['quiz']->id, 'batch' => $batch->id,
        ]);
        $attempt = QuizAttempt::where('batch_id', $batch->id)->firstOrFail();
        $enrollment->update(['status' => 'dropped']);
        $component->call('submitQuiz')->assertForbidden();
        $this->assertNull($attempt->fresh()->submitted_at);
        $this->assertNull($attempt->fresh()->score);
        $this->assertDatabaseCount('lesson_completions', 0);
    }

    public function test_zero_passing_grade_still_requires_all_essays_to_be_graded(): void
    {
        $f = $this->fixture();
        $f['quiz']->update(['passing_grade' => 0]);
        $attempt = $this->submit($f);
        $this->assertFalse($attempt->hasPassed());
        $this->assertFalse(app(QuizService::class)->hasPassed($f['student'], $f['quiz']));
        $this->assertDatabaseMissing('lesson_completions', ['lesson_id' => $f['lesson']->id]);
    }

    public function test_a_score_exactly_at_the_passing_grade_completes_the_lesson(): void
    {
        $f = $this->fixture(1, 1);
        $f['questions'][0]->update(['score_weight' => 10]);
        $attempt = $this->gradeAll($f, 7);
        $this->assertSame(70.0, $attempt->score);
        $this->assertTrue($attempt->hasPassed());
        $this->assertDatabaseHas('lesson_completions', ['lesson_id' => $f['lesson']->id]);
    }

    public function test_failed_final_grades_cannot_issue_a_certificate_even_with_completion(): void
    {
        $f = $this->fixture(1, 1);
        $this->gradeAll($f, 0);
        DB::table('lesson_completions')->insert(['lesson_id' => $f['lesson']->id, 'user_id' => $f['student']->id, 'completed_at' => now()]);
        $this->expectException(ValidationException::class);
        app(CertificateService::class)->issue($f['student'], $f['course']);
    }

    public function test_essay_save_rejects_questions_from_a_different_quiz(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(QuizService::class)->saveEssay($f['student'], $f['attempt'], $other['questions'][0]->id, 'Wrong quiz');
    }

    public function test_another_student_cannot_write_an_attempt(): void
    {
        $f = $this->fixture();
        $this->expectException(HttpException::class);
        app(QuizService::class)->saveEssay($this->user(), $f['attempt'], $f['questions'][0]->id, 'Not mine');
    }

    public function test_automatic_multi_select_quiz_still_scores_and_completes_immediately(): void
    {
        $f = $this->fixture(1, 0);
        $question = Question::create(['quiz_id' => $f['quiz']->id, 'question_text' => 'Choose both', 'question_type' => 'multiple_choice', 'score_weight' => 2]);
        $first = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'First', 'is_correct' => true, 'order_index' => 1]);
        $second = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Second', 'is_correct' => true, 'order_index' => 2]);
        Livewire::actingAs($f['student'])->test(QuizPlayer::class, ['course' => $f['course']->id, 'quiz' => $f['quiz']->id])
            ->call('selectAnswer', $question->id, $first->id)->call('selectAnswer', $question->id, $second->id)
            ->call('submitQuiz')->assertSet('isPendingGrading', false)->assertSet('isPassed', true)->assertSet('finalScore', 100.0);
        $this->assertDatabaseHas('lesson_completions', ['lesson_id' => $f['lesson']->id]);
    }

    public function test_quiz_player_rejects_mismatched_course_and_quiz(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        Livewire::actingAs($this->user('admin'))->test(QuizPlayer::class, ['course' => $other['course']->id, 'quiz' => $f['quiz']->id])->assertNotFound();
    }
}

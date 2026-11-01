<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use App\Services\AssignmentSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::create([
            'name' => 'Siswa Tugas',
            'email' => 'siswa-' . uniqid() . '@example.test',
            'password' => 'password',
        ]);
    }

    private function assignment(): Assignment
    {
        $course = Course::create(['title' => 'Kursus Uji']);

        return Assignment::create([
            'course_id' => $course->id,
            'title' => 'Tugas Pertama',
            'deadline_days' => 3,
        ]);
    }

    public function test_submitting_a_file_persists_the_submission_and_the_file(): void
    {
        Storage::fake('public');

        $user = $this->student();
        $assignment = $this->assignment();

        $submission = app(AssignmentSubmissionService::class)->submit(
            $user,
            $assignment,
            UploadedFile::fake()->create('jawaban.pdf', 120, 'application/pdf'),
            null,
            'Ini catatan saya'
        );

        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'notes' => 'Ini catatan saya',
        ]);

        $this->assertNotNull($submission->submitted_at);
        Storage::disk('public')->assertExists($submission->file_url);
    }

    public function test_submission_without_batch_is_allowed(): void
    {
        Storage::fake('public');

        $submission = app(AssignmentSubmissionService::class)->submit(
            $this->student(),
            $this->assignment(),
            UploadedFile::fake()->create('jawaban.pdf', 10, 'application/pdf')
        );

        $this->assertNull($submission->batch_id);
    }

    public function test_resubmitting_replaces_the_file_and_resets_the_grade(): void
    {
        Storage::fake('public');

        $user = $this->student();
        $assignment = $this->assignment();
        $service = app(AssignmentSubmissionService::class);

        $first = $service->submit($user, $assignment, UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf'));
        $first->update(['grade' => 80, 'feedback' => 'Bagus']);

        $second = $service->submit($user, $assignment, UploadedFile::fake()->create('v2.pdf', 10, 'application/pdf'));

        $this->assertSame($first->id, $second->id);
        $this->assertNull($second->grade);
        $this->assertNull($second->feedback);
        $this->assertSame(1, Submission::count());

        Storage::disk('public')->assertMissing($first->getOriginal('file_url'));
        Storage::disk('public')->assertExists($second->file_url);
    }

    public function test_graded_submission_cannot_be_deleted(): void
    {
        Storage::fake('public');

        $service = app(AssignmentSubmissionService::class);
        $submission = $service->submit(
            $this->student(),
            $this->assignment(),
            UploadedFile::fake()->create('jawaban.pdf', 10, 'application/pdf')
        );

        $submission->update(['grade' => 90]);

        $this->assertFalse($service->delete($submission->refresh()));
        $this->assertDatabaseHas('submissions', ['id' => $submission->id]);
    }

    public function test_ungraded_submission_is_deleted_with_its_file(): void
    {
        Storage::fake('public');

        $service = app(AssignmentSubmissionService::class);
        $submission = $service->submit(
            $this->student(),
            $this->assignment(),
            UploadedFile::fake()->create('jawaban.pdf', 10, 'application/pdf')
        );

        $path = $submission->file_url;

        $this->assertTrue($service->delete($submission));
        $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
        Storage::disk('public')->assertMissing($path);
    }
}

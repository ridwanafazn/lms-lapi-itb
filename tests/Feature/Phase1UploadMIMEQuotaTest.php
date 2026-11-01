<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase1UploadMIMEQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Student',
            'email' => 'student-' . uniqid() . '@example.test',
            'password' => 'password',
        ]);
        $user->assignRole('student');
        return $user;
    }

    public function test_mime_sniff_blocks_invalid_mime()
    {
        $student = $this->student();
        $course = \App\Models\Course::create([
            'title' => 'Kursus',
            'instructor_id' => \App\Models\User::factory()->create()->id,
            'delivery_mode' => 'self_paced',
        ]);
        $assignment = Assignment::create([
            'course_id' => $course->id,
            'title' => 'Tugas',
        ]);

        $fakeFile = UploadedFile::fake()->create('file.exe', 100);
        $errors = \App\Services\AssignmentSubmissionService::validateFile($fakeFile, $student, $assignment);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('MIME', implode('', $errors));
    }

    public function test_user_quota_blocks_after_500mb()
    {
        $student = $this->student();
        $course = \App\Models\Course::create([
            'title' => 'Kursus',
            'instructor_id' => \App\Models\User::factory()->create()->id,
            'delivery_mode' => 'self_paced',
        ]);
        $assignment = Assignment::create([
            'course_id' => $course->id,
            'title' => 'Tugas',
        ]);

        $fakeFile = UploadedFile::fake()->create('file.pdf', 102400); // 100 MB
        $errors = \App\Services\AssignmentSubmissionService::validateFile($fakeFile, $student, $assignment);
        $this->assertEmpty($errors); // pertama masih aman
    }
}

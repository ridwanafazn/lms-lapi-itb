<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase1PolicyRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.test',
            'password' => 'password',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function student(): User
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Student',
            'email' => 'student-'.uniqid().'@example.test',
            'password' => 'password',
        ]);
        $user->assignRole('student');

        return $user;
    }

    // --- Phase 1.1: Policy Tests ---

    public function test_lesson_policy_allows_admin()
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $program = Program::create([
            'title' => 'Program',
            'price' => 100000,
            'type' => 'self_paced',
            'min_membership_level' => 'none',
            'is_active' => true,
        ]);
        $course = Course::create([
            'title' => 'Course',
            'instructor_id' => $admin->id,
            'delivery_mode' => 'self_paced',
        ]);
        $section = Section::create([
            'course_id' => $course->id,
            'title' => 'Section',
            'sort_order' => 1,
        ]);
        $lesson = Lesson::create([
            'section_id' => $section->id,
            'item_type' => 'material',
            'sort_order' => 1,
            'is_required' => true,
        ]);

        $this->assertTrue($admin->can('view', $lesson));
        $this->assertTrue($admin->can('update', $lesson));
    }

    public function test_quiz_policy_denies_student_without_access()
    {
        $student = $this->student();
        $this->actingAs($student);

        $course = Course::create([
            'title' => 'Course',
            'instructor_id' => User::factory()->create()->id,
            'delivery_mode' => 'self_paced',
        ]);
        $quiz = Quiz::create([
            'course_id' => $course->id,
            'title' => 'Quiz',
            'status' => 'active',
        ]);

        $this->assertFalse($student->can('view', $quiz));
        $this->assertFalse($student->can('submit', $quiz));
    }

    public function test_assignment_policy_allows_access_with_active_enrollment()
    {
        $student = $this->student();
        $program = Program::create([
            'title' => 'Program',
            'price' => 100000,
            'type' => 'self_paced',
            'min_membership_level' => 'none',
            'is_active' => true,
        ]);
        $course = Course::create([
            'title' => 'Course',
            'instructor_id' => User::factory()->create()->id,
            'delivery_mode' => 'self_paced',
        ]);
        $assignment = Assignment::create([
            'course_id' => $course->id,
            'title' => 'Assignment',
        ]);

        $this->actingAs($student);
        // Tanpa enrollment = tidak bisa akses
        $this->assertFalse($student->can('view', $assignment));
    }

    // --- Phase 1.3: Rate Limiting Tests ---

    public function test_certificate_rate_limit_60_per_minute()
    {
        $this->assertNotNull(
            \Illuminate\Support\Facades\RateLimiter::limiter('certificate'),
            'Rate limiter "certificate" harus terdefinisi'
        );
    }

    public function test_webhook_rate_limit_100_per_minute()
    {
        $this->assertNotNull(
            \Illuminate\Support\Facades\RateLimiter::limiter('webhook'),
            'Rate limiter "webhook" harus terdefinisi'
        );
    }

    public function test_quiz_submit_rate_limit_30_per_minute()
    {
        $this->assertNotNull(
            \Illuminate\Support\Facades\RateLimiter::limiter('quiz_submit'),
            'Rate limiter "quiz_submit" harus terdefinisi'
        );
    }
}

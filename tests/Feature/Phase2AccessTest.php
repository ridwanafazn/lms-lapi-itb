<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_program_is_not_accessible_or_listed_and_null_expiry_remains_valid(): void
    {
        $user = User::factory()->create();
        $program = Program::create(['title' => 'Expiring access', 'is_active' => true]);
        $enrollment = Enrollment::create(['user_id' => $user->id, 'enrollable_type' => Program::class,
            'enrollable_id' => $program->id, 'enrolled_at' => now(), 'status' => 'active', 'access_expires_at' => now()->subSecond()]);
        $this->assertFalse($user->canAccessProgram($program));
        $this->assertFalse($user->accessible_programs->contains($program));
        $enrollment->update(['access_expires_at' => null]);
        $this->assertTrue($user->canAccessProgram($program));
        $this->assertTrue($user->accessible_programs->contains($program));
    }

    public function test_batch_for_a_program_grants_only_its_courses_and_honors_expiry(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $course = Course::create(['title' => 'Batch course', 'instructor_id' => $owner->id]);
        $other = Course::create(['title' => 'Unrelated course', 'instructor_id' => $owner->id]);
        $program = Program::create(['title' => 'Batch program']);
        $program->courses()->attach($course->id, ['order_index' => 0]);
        $batch = Batch::create(['program_id' => $program->id, 'batch_name' => 'Program cohort', 'quota' => 2]);
        $enrollment = Enrollment::create(['user_id' => $user->id, 'enrollable_type' => Batch::class,
            'enrollable_id' => $batch->id, 'status' => 'active', 'enrolled_at' => now(), 'access_expires_at' => now()->addDay()]);
        $this->assertTrue($user->hasAccessToCourse($course->id));
        $this->assertFalse($user->hasAccessToCourse($other->id));
        $enrollment->update(['access_expires_at' => now()->subSecond()]);
        $this->assertFalse($user->canAccessBatch($batch));
        $this->assertFalse($user->hasAccessToCourse($course->id));
    }
}

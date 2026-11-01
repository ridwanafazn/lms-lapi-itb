<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Program;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_complete_demo_environment(): void
    {
        $this->seed(PortfolioDemoSeeder::class);

        $admin = User::where('email', 'demo.admin@lapi.test')->firstOrFail();
        $instructor = User::where('email', 'demo.instruktur@lapi.test')->firstOrFail();
        $student = User::where('email', 'demo.peserta@lapi.test')->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($instructor->hasRole('instructor'));
        $this->assertTrue($student->hasRole('student'));
        $this->assertNotNull($admin->profile);
        $this->assertSame('demo.admin@lapi.test', $admin->email);

        $program = Program::where('enrollment_code', 'DEMO-DA-01')->firstOrFail();
        $course = Course::where('course_code', 'DEMO-CRS-DA')->firstOrFail();

        $this->assertSame(CourseStatus::PUBLISHED, $course->status);
        $this->assertSame($instructor->id, $course->instructor_id);
        $this->assertTrue($program->courses->contains($course));

        $this->assertSame(4, Lesson::whereHas('section', fn ($query) => $query->where('course_id', $course->id))->count());
        $this->assertSame(2, Material::where('course_id', $course->id)->count());

        $quiz = Quiz::where('course_id', $course->id)->firstOrFail();
        $this->assertSame(2, Question::where('quiz_id', $quiz->id)->count());
        $this->assertSame(1, Question::where('quiz_id', $quiz->id)->where('question_type', 'essay')->count());

        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $this->assertSame('open', $batch->status);
        $this->assertSame(30, $batch->quota);

        $enrollment = Enrollment::where('user_id', $student->id)
            ->where('enrollable_type', Program::class)
            ->where('enrollable_id', $program->id)
            ->firstOrFail();

        $this->assertSame('active', $enrollment->status);
        $this->assertTrue($student->canAccessProgram($program));
        $this->assertTrue($student->hasAccessToCourse($course->id));
        $this->assertSame(0, Certificate::count());
    }

    public function test_running_the_seeder_twice_does_not_duplicate_data(): void
    {
        $this->seed(PortfolioDemoSeeder::class);
        $this->seed(PortfolioDemoSeeder::class);

        $this->assertSame(3, User::whereIn('email', [
            'demo.admin@lapi.test',
            'demo.instruktur@lapi.test',
            'demo.peserta@lapi.test',
        ])->count());

        $this->assertSame(1, Program::where('enrollment_code', 'DEMO-DA-01')->count());
        $this->assertSame(1, Course::where('course_code', 'DEMO-CRS-DA')->count());
        $this->assertSame(1, Quiz::count());
        $this->assertSame(4, Lesson::count());
        $this->assertSame(1, Batch::count());
        $this->assertSame(1, Enrollment::count());
    }
}

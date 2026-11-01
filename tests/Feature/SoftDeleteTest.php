<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_course_keeps_the_row_but_hides_it(): void
    {
        $course = Course::create(['title' => 'Kursus Dihapus']);

        $course->delete();

        $this->assertNull(Course::find($course->id));
        $this->assertNotNull(Course::withTrashed()->find($course->id));
        $this->assertNotNull(DB::table('courses')->where('id', $course->id)->value('deleted_at'));
    }

    public function test_deleted_course_can_be_restored(): void
    {
        $course = Course::create(['title' => 'Kursus Pulih']);
        $course->delete();

        Course::withTrashed()->find($course->id)->restore();

        $this->assertNotNull(Course::find($course->id));
    }

    public function test_program_batch_and_user_also_use_soft_deletes(): void
    {
        $program = Program::create(['title' => 'Program Dihapus']);
        $batch = Batch::create(['batch_name' => 'Batch Dihapus']);
        $user = User::create([
            'name' => 'Pengguna Dihapus',
            'email' => 'hapus-' . uniqid() . '@example.test',
            'password' => 'password',
        ]);

        $program->delete();
        $batch->delete();
        $user->delete();

        $this->assertNull(Program::find($program->id));
        $this->assertNull(Batch::find($batch->id));
        $this->assertNull(User::find($user->id));

        $this->assertNotNull(DB::table('programs')->where('id', $program->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('batches')->where('id', $batch->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('deleted_at'));
    }
}

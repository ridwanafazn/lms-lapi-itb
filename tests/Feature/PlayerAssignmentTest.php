<?php

namespace Tests\Feature;

use App\Livewire\Student\Learning\Player;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end: tombol "Kirim Tugas" di Player harus benar-benar menyimpan berkas.
 * Sebelum perbaikan Phase 0.5, aksi ini hanya menampilkan notifikasi sukses.
 */
class PlayerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin-'.uniqid().'@example.test',
            'password' => 'password',
        ]);

        $user->assignRole('admin');

        return $user;
    }

    private function courseWithAssignment(): array
    {
        $course = Course::create(['title' => 'Kursus Tugas']);
        $section = Section::create([
            'course_id' => $course->id,
            'title' => 'Bab 1',
            'order_index' => 1,
        ]);

        $assignment = Assignment::create([
            'course_id' => $course->id,
            'title' => 'Tugas Akhir',
            'description' => 'Kerjakan ya',
            'deadline_days' => 7,
        ]);

        $lesson = Lesson::create([
            'section_id' => $section->id,
            'item_type' => 'assignment',
            'assignment_id' => $assignment->id,
            'sort_order' => 1,
            'is_required' => true,
        ]);

        return [$course, $assignment, $lesson];
    }

    public function test_player_stores_the_uploaded_assignment_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin());

        [$course, $assignment, $lesson] = $this->courseWithAssignment();

        Livewire::withQueryParams(['lesson_id' => $lesson->id])
            ->test(Player::class, ['course' => $course])
            ->set('assignmentFile', UploadedFile::fake()->create('jawaban.pdf', 50, 'application/pdf'))
            ->set('assignmentNotes', 'Mohon diperiksa')
            ->call('submitAssignment')
            ->assertHasNoErrors();

        $submission = Submission::where('assignment_id', $assignment->id)->first();

        $this->assertNotNull($submission, 'Submission harus tersimpan setelah menekan Kirim Tugas.');
        $this->assertSame('Mohon diperiksa', $submission->notes);
        Storage::disk('public')->assertExists($submission->file_url);

        $this->assertDatabaseHas('lesson_completions', ['lesson_id' => $lesson->id]);
    }

    public function test_submitting_without_a_file_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin());

        [$course, , $lesson] = $this->courseWithAssignment();

        Livewire::withQueryParams(['lesson_id' => $lesson->id])
            ->test(Player::class, ['course' => $course])
            ->call('submitAssignment')
            ->assertHasErrors('assignmentFile');

        $this->assertSame(0, Submission::count());
    }
}

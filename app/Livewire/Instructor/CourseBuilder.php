<?php

namespace App\Livewire\Instructor;

use App\Models\Course;
use App\Models\Section;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\Assignment;
use App\Enums\CourseStatus;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.course-workspace')]
class CourseBuilder extends Component
{
    public Course $course;

    public $editingSectionId = null;
    public $tempSectionTitle = '';

    public function mount(Course $course)
    {
        // Otorisasi Manual: Memastikan hanya instruktur pemilik/ditugaskan yang bisa masuk
        $this->authorize('update', $course);

        $this->course = $course;
    }

    public function render()
    {
        return view('livewire.instructor.course-builder', [
            'sections' => $this->course->sections()
                ->with(['lessons.material', 'lessons.quiz', 'lessons.assignment'])
                ->orderBy('order_index')
                ->get(),
            'backUrl' => $this->getBackUrl()
        ]);
    }

    private function getBackUrl()
    {
        $program = $this->course->programs->first();
        return $program
            ? route('instructor.program.courses', $program->id)
            : route('instructor.programs');
    }

    public function addSection()
    {
        $maxOrder = $this->course->sections()->max('order_index') ?? 0;

        $this->course->sections()->create([
            'title' => 'Bab Baru (Untitled)',
            'order_index' => $maxOrder + 1
        ]);
    }

    public function startEditSection($sectionId, $currentTitle)
    {
        $this->editingSectionId = $sectionId;
        $this->tempSectionTitle = $currentTitle;
    }

    public function saveSectionTitle()
    {
        $this->validate(['tempSectionTitle' => 'required|min:3|max:255']);
        Section::where('id', $this->editingSectionId)->update(['title' => $this->tempSectionTitle]);
        $this->editingSectionId = null;
    }

    public function deleteSection($sectionId)
    {
        Section::find($sectionId)?->delete();
    }

    public function addMaterial($sectionId, $type)
    {
        $section = Section::findOrFail($sectionId);
        $maxOrder = $section->lessons()->max('sort_order') ?? 0;

        $typeLabels = [
            'article' => 'Teks / Artikel',
            'video' => 'Video Youtube',
            'google_drive_video' => 'Video GDrive',
            'google_drive_file' => 'File GDrive',
            'live_session' => 'Live Session'
        ];

        $material = Material::create([
            'course_id' => $this->course->id,
            'title' => 'Untitled ' . ($typeLabels[$type] ?? 'Materi'),
            'type' => $type,
            'status' => 'draft'
        ]);

        Lesson::create([
            'section_id' => $sectionId,
            'item_type' => 'material',
            'material_id' => $material->id,
            'sort_order' => $maxOrder + 1,
            'is_required' => true
        ]);
    }

    public function addLesson($sectionId, $type)
    {
        $section = Section::findOrFail($sectionId);
        $maxOrder = $section->lessons()->max('sort_order') ?? 0;

        $title = 'Untitled ' . ucfirst($type);
        $content = null;

        if ($type === 'quiz') {
            $content = Quiz::create([
                'course_id' => $this->course->id,
                'title' => $title,
                'status' => 'draft',
                'duration_minutes' => 10
            ]);
        } elseif ($type === 'assignment') {
            $content = Assignment::create([
                'course_id' => $this->course->id,
                'title' => $title,
                'deadline_days' => 3
            ]);
        }

        if (!$content) return;

        Lesson::create([
            'section_id' => $sectionId,
            'item_type' => $type,
            'quiz_id' => $type === 'quiz' ? $content->id : null,
            'assignment_id' => $type === 'assignment' ? $content->id : null,
            'sort_order' => $maxOrder + 1,
            'is_required' => true
        ]);
    }

    public function deleteLesson($lessonId)
    {
        Lesson::find($lessonId)?->delete();
    }

    public function updateSectionOrder($list)
    {
        foreach ($list as $index => $id) {
            Section::where('id', $id)->update(['order_index' => $index + 1]);
        }
    }

    public function updateItemOrder($list)
    {
        foreach ($list as $index => $id) {
            Lesson::where('id', $id)->update(['sort_order' => $index + 1]);
        }
    }

    public function submitForReview()
    {
        if (empty($this->course->description)) {
            session()->flash('error', 'Gagal: Deskripsi Course masih kosong. Silakan masuk ke tab "Course Identity" untuk melengkapinya terlebih dahulu.');
            return;
        }

        if ($this->course->sections()->count() === 0) {
            session()->flash('error', 'Gagal: Course harus memiliki minimal 1 Bab sebelum diajukan.');
            return;
        }

        $hasContent = $this->course->materials()->exists() || $this->course->quizzes()->exists() || $this->course->assignments()->exists();

        if (!$hasContent) {
            session()->flash('error', 'Gagal: Belum ada materi (Video/Teks/Zoom) yang ditambahkan ke dalam kurikulum.');
            return;
        }

        $this->course->update(['status' => CourseStatus::PENDING]);

        \App\Models\ActivityLog::create([
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()->name,
            'action' => 'submit_curriculum',
            'description' => "Mengajukan course '{$this->course->title}' untuk direview.",
            'ip_address' => request()->ip(),
        ]);

        session()->flash('message', 'Course berhasil diajukan untuk review Admin.');
    }
}

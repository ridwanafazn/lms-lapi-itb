<?php

namespace App\Livewire\Instructor;

use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Quizzes extends Component
{
    public $showModal = false;

    public $isEdit = false;

    public $editingId = null;

    // Form Properties
    #[Validate('required')]
    public $course_id = ''; // Tambahan: Kuis harus milik course

    #[Validate('required|min:3')]
    public $title = '';

    #[Validate('required|integer|min:1')]
    public $duration_minutes = 30;

    #[Validate('required|numeric|min:0|max:100')]
    public $passing_grade = 70;

    public function render()
    {
        // LOGIC BARU: Ambil kuis dari course yang dimiliki instruktur ini
        $quizzes = Quiz::whereHas('course', function ($query) {
            $query->where('instructor_id', Auth::id());
        })
            ->with('course') // Eager load untuk menampilkan nama course di tabel
            ->latest()
            ->get();

        // Ambil daftar course untuk dropdown create/edit
        $myCourses = Course::where('instructor_id', Auth::id())->get();

        return view('livewire.instructor.quizzes', [
            'quizzes' => $quizzes,
            'courses' => $myCourses,
        ]);
    }

    public function create()
    {
        $this->reset(['title', 'duration_minutes', 'passing_grade', 'isEdit', 'editingId', 'course_id']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        // Cari kuis dan pastikan pemilik course-nya adalah user yg login
        $quiz = Quiz::where('id', $id)
            ->whereHas('course', function ($q) {
                $q->where('instructor_id', Auth::id());
            })->firstOrFail();

        $this->isEdit = true;
        $this->editingId = $quiz->id;
        $this->course_id = $quiz->course_id;
        $this->title = $quiz->title;
        $this->duration_minutes = $quiz->duration_minutes;
        $this->passing_grade = $quiz->passing_grade;

        $this->showModal = true;
    }

    public function store()
    {
        $this->validate();

        // Security Check: Pastikan course_id yang dipilih memang milik instruktur ini
        $courseOwner = Course::where('id', $this->course_id)
            ->where('instructor_id', Auth::id())
            ->exists();

        if (! $courseOwner) {
            $this->addError('course_id', 'Invalid Course selection.');

            return;
        }

        if ($this->isEdit) {
            $quiz = Quiz::find($this->editingId);
            $quiz->update([
                'course_id' => $this->course_id,
                'title' => $this->title,
                'duration_minutes' => $this->duration_minutes,
                'passing_grade' => $this->passing_grade,
            ]);
        } else {
            Quiz::create([
                'course_id' => $this->course_id, // New Relation
                'title' => $this->title,
                'duration_minutes' => $this->duration_minutes,
                'passing_grade' => $this->passing_grade,
                'status' => 'draft',
                'is_randomized' => false,
                'max_attempts' => 0,
            ]);
        }

        $this->showModal = false;
        $this->reset(['title', 'duration_minutes', 'passing_grade', 'isEdit', 'editingId', 'course_id']);
    }

    public function delete($id)
    {
        // Delete dengan validasi kepemilikan
        $quiz = Quiz::where('id', $id)
            ->whereHas('course', function ($q) {
                $q->where('instructor_id', Auth::id());
            })->first();

        if ($quiz) {
            $quiz->delete();
        }
    }
}

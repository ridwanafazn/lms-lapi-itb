<?php

namespace App\Livewire\Instructor;

use App\Models\QuizAttempt;
use App\Services\EssayGradingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class EssayGrading extends Component
{
    use WithPagination;

    #[Locked]
    public ?string $attemptId = null;

    public array $scores = [];

    public array $feedback = [];

    public function mount(?string $attempt = null): void
    {
        $this->authorizeRole();
        if ($attempt) {
            $this->selectAttempt($attempt);
        }
    }

    private function authorizeRole(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'instructor']), 403);
    }

    public function selectAttempt(string $id): void
    {
        $this->authorizeRole();
        $attempt = QuizAttempt::findOrFail($id);
        app(EssayGradingService::class)->authorize(auth()->user(), $attempt);
        abort_unless($attempt->isSubmitted(), 404);
        $this->attemptId = $id;
        $this->scores = [];
        $this->feedback = [];
        $this->resetValidation();
        foreach ($attempt->answers()->whereHas('question', fn ($query) => $query->where('question_type', 'essay'))->get() as $answer) {
            $this->scores[$answer->id] = $answer->graded_at ? $answer->score : null;
            $this->feedback[$answer->id] = $answer->instructor_feedback ?? '';
        }
    }

    public function saveGrade(string $answerId): void
    {
        $this->authorizeRole();
        $attempt = QuizAttempt::findOrFail($this->attemptId);
        app(EssayGradingService::class)->authorize(auth()->user(), $attempt);
        $answer = $attempt->answers()->find($answerId);
        abort_unless($answer, 404);
        app(EssayGradingService::class)->grade(auth()->user(), $answer, $this->scores[$answerId] ?? null, $this->feedback[$answerId] ?? null);
        $this->selectAttempt($attempt->id);
        session()->flash('grading-status', 'Nilai esai berhasil disimpan.');
    }

    public function render()
    {
        $this->authorizeRole();
        $attempt = $this->attemptId ? QuizAttempt::with(['user', 'quiz.course', 'answers.question'])->findOrFail($this->attemptId) : null;
        if ($attempt) {
            app(EssayGradingService::class)->authorize(auth()->user(), $attempt);
        }
        $query = QuizAttempt::with(['user', 'quiz.course'])
            ->where(function ($query) {
                $query->whereNotNull('submitted_at')->orWhereNotNull('end_time')->orWhereNotNull('score');
            })
            ->whereHas('quiz.questions', fn ($query) => $query->where('question_type', 'essay'));
        if (! auth()->user()->hasRole('admin')) {
            $query->whereHas('quiz.course', fn ($query) => $query->where('instructor_id', auth()->id()));
        }

        return view('livewire.instructor.essay-grading', [
            'attempts' => $query->orderByRaw('score IS NULL DESC')->latest('start_time')->paginate(20),
            'selectedAttempt' => $attempt,
        ]);
    }
}

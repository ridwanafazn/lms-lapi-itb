<?php

namespace App\Livewire\Student\Learning;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizGrader;
use App\Services\QuizService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuizPlayer extends Component
{
    #[Locked]
    public $course;

    #[Locked]
    public $quiz;

    #[Locked]
    public $batchId = null;

    #[Locked]
    public $quizAttempt;

    public $questions = [];

    public $currentQuestionIndex = 0;

    public $answers = [];

    public $flaggedQuestions = [];

    public $timeRemaining = 0;

    public $isFinished = false;

    public $finalScore = null;

    public $isPassed = false;

    public $isPendingGrading = false;

    public $passingGrade = 70;

    public $remainingAttempts = null;

    public $attemptsExhausted = false;

    public function mount($course, $quiz, $batch = null)
    {
        $this->batchId = $batch;
        $this->course = Course::findOrFail($course);
        $this->quiz = Quiz::with('questions.options')->findOrFail($quiz);
        abort_unless((string) $this->quiz->course_id === (string) $this->course->id, 404);
        $this->authorize('view', $this->quiz);
        $this->passingGrade = $this->quiz->passing_grade ?? 70;

        $service = app(QuizService::class);
        $service->authorizeBatchContext(auth()->user(), $this->quiz, $this->batchId);
        $latest = $service->latestSubmitted(auth()->user(), $this->quiz, $this->batchId);
        if ($latest && ! $service->findInProgress(auth()->user(), $this->quiz, $this->batchId)) {
            $this->showResult($latest);

            return;
        }
        $this->initializeAttempt();
    }

    private function initializeAttempt(): void
    {
        $this->authorize('submit', $this->quiz);
        $service = app(QuizService::class);
        $attempt = $service->startOrResume(auth()->user(), $this->quiz, $this->batchId);
        $this->remainingAttempts = $service->remainingAttempts(auth()->user(), $this->quiz, $this->batchId);
        if (! $attempt) {
            $this->questions = collect();
            $this->isFinished = true;
            $this->attemptsExhausted = true;

            return;
        }
        $this->quizAttempt = $attempt;
        $this->questions = $service->questionsFor($attempt);
        $this->currentQuestionIndex = min($attempt->current_question_index ?? 0, max(0, $this->questions->count() - 1));
        $this->loadAnswers();
        $this->timeRemaining = $service->secondsRemaining($attempt);
        if ($service->isExpired($attempt)) {
            $this->submitQuiz(true);
        }
    }

    private function loadAnswers(): void
    {
        $saved = $this->quizAttempt->answers()->get()->groupBy('question_id');
        foreach ($this->questions as $question) {
            $rows = $saved->get($question->id, collect());
            $this->answers[$question->id] = $question->isEssay()
                ? ($rows->first()?->essay_answer ?? '')
                : ($question->isMultipleSelect() ? $rows->pluck('question_option_id')->all() : $rows->first()?->question_option_id);
        }
    }

    private function ownedAttempt(): QuizAttempt
    {
        $this->authorize('submit', $this->quiz);
        $attempt = QuizAttempt::whereKey($this->quizAttempt?->id)
            ->where('user_id', auth()->id())->where('quiz_id', $this->quiz->id)->firstOrFail();
        app(QuizService::class)->authorizeBatchContext(auth()->user(), $this->quiz, $attempt->batch_id);

        return $attempt;
    }

    private function showResult(QuizAttempt $attempt): void
    {
        $this->quizAttempt = $attempt;
        $this->questions = app(QuizService::class)->questionsFor($attempt);
        $this->isFinished = true;
        $this->finalScore = $attempt->score;
        $this->isPendingGrading = $attempt->isPendingGrading();
        $this->isPassed = $attempt->hasPassed();
        $this->remainingAttempts = app(QuizService::class)->remainingAttempts(auth()->user(), $this->quiz, $this->batchId);
        $this->attemptsExhausted = $this->remainingAttempts === 0;
    }

    public function jumpToQuestion($index): void
    {
        $attempt = $this->ownedAttempt();
        if (! $attempt->isSubmitted() && $index >= 0 && $index < count($this->questions)) {
            $this->currentQuestionIndex = (int) $index;
            $attempt->update(['current_question_index' => $this->currentQuestionIndex]);
        }
    }

    public function nextQuestion(): void
    {
        $this->jumpToQuestion($this->currentQuestionIndex + 1);
    }

    public function prevQuestion(): void
    {
        $this->jumpToQuestion($this->currentQuestionIndex - 1);
    }

    public function toggleFlag($questionId): void
    {
        if (isset($this->flaggedQuestions[$questionId])) {
            unset($this->flaggedQuestions[$questionId]);
        } else {
            $this->flaggedQuestions[$questionId] = true;
        }
    }

    public function selectAnswer($questionId, $optionId): void
    {
        $attempt = $this->ownedAttempt();
        app(QuizService::class)->saveChoice(auth()->user(), $attempt, $questionId, $optionId, true);
        $this->loadAnswers();
        if ($attempt->refresh()->isSubmitted()) {
            $this->showResult($attempt);
        }
    }

    public function updatedAnswers($value, $questionId): void
    {
        $this->saveEssayAnswer($questionId, $value);
    }

    public function saveEssayAnswer($questionId, $text): void
    {
        $attempt = $this->ownedAttempt();
        app(QuizService::class)->saveEssay(auth()->user(), $attempt, $questionId, (string) $text);
        $this->loadAnswers();
        if ($attempt->refresh()->isSubmitted()) {
            $this->showResult($attempt);
        }
    }

    public function submitQuiz($isTimeout = false): void
    {
        $attempt = $this->ownedAttempt();
        $service = app(QuizService::class);
        $timedOut = $service->isExpired($attempt) || ($isTimeout && $service->secondsRemaining($attempt) === 0
            && $service->durationSeconds($this->quiz) > 0);
        $graded = app(QuizGrader::class)->grade($attempt, $timedOut);
        $this->showResult($graded);
        if ($graded->hasPassed()) {
            foreach (Lesson::where('quiz_id', $this->quiz->id)->get() as $lesson) {
                DB::table('lesson_completions')->updateOrInsert(
                    ['user_id' => auth()->id(), 'lesson_id' => $lesson->id],
                    ['completed_at' => now()]
                );
            }
        }
    }

    public function retakeQuiz(): void
    {
        $this->answers = [];
        $this->flaggedQuestions = [];
        $this->currentQuestionIndex = 0;
        $this->finalScore = null;
        $this->isPassed = false;
        $this->isPendingGrading = false;
        $this->isFinished = false;
        $this->attemptsExhausted = false;
        $this->initializeAttempt();
    }

    public function backToCourse()
    {
        $lesson = Lesson::where('quiz_id', $this->quiz->id)->first();

        return redirect()->route('student.learning.player', [
            'course' => $this->course->id,
            'batch_id' => $this->batchId,
            'lesson_id' => $lesson?->id,
        ]);
    }

    #[Layout('layouts.blank')]
    public function render()
    {
        $current = $this->questions[$this->currentQuestionIndex] ?? null;
        $answered = collect($this->answers)->filter(fn ($answer) => is_array($answer) ? count($answer) > 0 : strlen(trim((string) $answer)) > 0)->count();
        $total = count($this->questions);

        return view('livewire.student.learning.quiz-player', [
            'currentQuestion' => $current,
            'isMultipleChoice' => $current?->isMultipleSelect() ?? false,
            'totalQuestions' => $total,
            'answeredCount' => $answered,
            'progressPercent' => $total > 0 ? $answered / $total * 100 : 0,
            'essayResults' => $this->isFinished && $this->quizAttempt
                ? $this->quizAttempt->answers()->with('question')->whereHas('question', fn ($query) => $query->where('question_type', 'essay'))->get()
                : collect(),
        ]);
    }
}

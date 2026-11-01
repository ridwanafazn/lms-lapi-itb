<?php

namespace App\Livewire\Student\Learning;

use App\Services\QuizService;

class PlayerQuizEngine
{
    protected $quizService;

    public function __construct(QuizService $quizService)
    {
        $this->quizService = $quizService;
    }

    public function hasPassed($user, $quiz, ?int $batchId = null): bool
    {
        return $this->quizService->hasPassed($user, $quiz, $batchId);
    }

    public function findInProgress($user, $quiz, ?int $batchId = null)
    {
        return $this->quizService->findInProgress($user, $quiz, $batchId);
    }
}

<?php

namespace App\Livewire\Student\Learning;

class PlayerAssignmentEngine
{
    public function findSubmission($user, $assignment, ?int $batchId = null)
    {
        return app(\App\Services\AssignmentSubmissionService::class)->find($user, $assignment, $batchId);
    }
}

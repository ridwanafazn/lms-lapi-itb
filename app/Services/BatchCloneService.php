<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BatchCloneService
{
    public function __construct(private OperationsAccess $access) {}

    public function clone(User $actor, Batch $source, string $name): Batch
    {
        $this->access->admin($actor);
        $name = trim($name);
        Validator::make(['cloneName' => $name], ['cloneName' => ['required', 'string', 'max:255']])->validate();

        return DB::transaction(function () use ($actor, $source, $name) {
            $source = Batch::whereKey($source->id)->lockForUpdate()->firstOrFail();
            // Explicit configuration whitelist: never replicate operational relationships.
            $clone = Batch::create(array_merge($source->only([
                'course_id', 'program_id', 'price', 'min_membership_level', 'start_date',
                'end_date', 'registration_deadline', 'quota', 'location', 'timeline_detail',
                'whatsapp_group_link', 'status',
            ]), ['batch_name' => $name]));
            foreach ($source->instructors()->get() as $assignment) {
                $clone->instructors()->create($assignment->only(['user_id', 'is_primary', 'role_label']));
            }
            ActivityLog::create([
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => 'batch_cloned',
                'description' => json_encode(['source_batch_id' => $source->id, 'new_batch_id' => $clone->id], JSON_THROW_ON_ERROR),
            ]);

            return $clone;
        }, 3);
    }
}

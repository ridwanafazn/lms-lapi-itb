<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids as EloquentHasUuids;

trait HasUuids
{
    use EloquentHasUuids;

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EnrollmentPaymentSource extends Model
{
    use HasUuids;

    protected $fillable = ['transaction_id', 'enrollment_id', 'preserves_existing_access', 'previous_status', 'previous_access_expires_at', 'reversed_at'];

    protected $casts = [
        'preserves_existing_access' => 'boolean',
        'previous_access_expires_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}

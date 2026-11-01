<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasUuids;

class Media extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'media';

    // Konfigurasi Primary Key UUID
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'model_type',
        'model_id',
        'uuid',
        'collection_name',
        'name',
        'file_name',
        'mime_type',
        'disk',
        'conversions_disk',
        'size',
        'manipulations',
        'custom_properties',
        'generated_conversions',
        'responsive_images',
        'order_column',
    ];

    protected $casts = [
        'manipulations' => 'array',
        'custom_properties' => 'array',
        'generated_conversions' => 'array',
        'responsive_images' => 'array',
        'size' => 'integer',
    ];

    /**
     * Get the parent model (Discussion, DiscussionReply, etc).
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}

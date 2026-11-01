<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Material extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'course_id',
        // 'instructor_id', -> Dihapus karena tidak digunakan di struktur tabel fisik
        'title',
        'type',
        'content_url',
        'zoom_url',
        'meeting_id',
        'meeting_passcode',
        'meeting_platform',
        'is_embeddable',
        'start_time',
        'duration',
        'body_text',
        'status'
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // Fungsi relasi instructor() dihapus agar tidak menyebabkan error "Unknown Column" jika sewaktu-waktu terpanggil.

    public function lesson(): HasOne
    {
        return $this->hasOne(Lesson::class, 'material_id');
    }

    /**
     * Accessor: Mengubah URL Youtube biasa menjadi URL Embed agar bisa di-play di iframe.
     * Dipanggil dengan: $material->embed_url
     */
    public function getEmbedUrlAttribute()
    {
        $url = $this->content_url;

        // Regex untuk menangkap ID Video dari youtube.com atau youtu.be
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';

        if (preg_match($pattern, $url, $matches)) {
            // Return format embed standar + rel=0 (agar tidak menampilkan video dari channel lain setelah selesai)
            return 'https://www.youtube.com/embed/' . $matches[1] . '?rel=0';
        }

        return $url;
    }
}

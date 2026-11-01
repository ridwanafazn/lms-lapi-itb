<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'course_id',
        'batch_id',
        'certificate_code',
        'formal_number',
        'issued_at',
        'file_url',
    ];

    protected $casts = [
        'issued_at' => 'date',
    ];

    /**
     * Booted method untuk mendaftarkan model events.
     */
    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            // Jika formal_number masih kosong, kita generate otomatis
            if (empty($certificate->formal_number)) {

                // Gunakan tanggal issued_at jika ada, atau gunakan waktu saat ini
                $date = $certificate->issued_at ? Carbon::parse($certificate->issued_at) : now();
                $year = $date->format('Y');
                $month = (int) $date->format('m');

                // Konversi bulan ke format Romawi
                $romanMonths = [
                    1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
                    7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
                ];
                $romanMonth = $romanMonths[$month];

                // Hitung jumlah sertifikat yang diterbitkan pada tahun yang sama
                // Ditambah 1 untuk nomor urut sertifikat yang sedang dibuat ini
                $countThisYear = static::whereYear('issued_at', $year)->count() + 1;

                // Format nomor urut menjadi 4 digit (contoh: 0001, 0012, 0150)
                $sequence = str_pad($countThisYear, 4, '0', STR_PAD_LEFT);

                // Rakit format formal_number: 0001/PT LAPI ITB/Sert/XII/2026
                $certificate->formal_number = "{$sequence}/PT LAPI ITB/Sert/{$romanMonth}/{$year}";
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}

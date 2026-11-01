<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Satu-satunya tempat berkas tugas disimpan.
 *
 * Sebelumnya Player hanya menampilkan notifikasi "Tugas berhasil dikirim!"
 * tanpa menyimpan berkas apa pun. Sekarang Player dan komponen Volt
 * assignment-submission memakai service ini.
 */
class AssignmentSubmissionService
{
    public const DISK = 'public';

    public const MAX_KILOBYTES = 20480; // 20 MB

    public const USER_QUOTA_KILOBYTES = 512000; // 500 MB total per user

    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
        'zip', 'rar', 'txt', 'csv', 'png', 'jpg', 'jpeg',
    ];

    /**
     * @return array<string, string>
     */
    public static function fileRules(): array
    {
        return [
            'file' => 'required|file|max:'.self::MAX_KILOBYTES.'|mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
        ];
    }

    public static function validateFile(
        \Illuminate\Http\UploadedFile $file,
        \App\Models\User $user,
        ?\App\Models\Assignment $assignment = null
    ): array {
        $errors = [];
        $mime = $file->getMimeType();
        $allowedMimes = [
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', 'application/x-rar-compressed', 'text/plain', 'text/csv',
            'image/png', 'image/jpeg', 'image/jpg',
        ];
        if (! in_array($mime, $allowedMimes, true)) {
            $errors[] = 'Tipe file (MIME: '.$mime.') tidak diizinkan.';
        }
        $totalUsedBytes = \App\Models\Submission::where('user_id', $user->id)->get()->sum(function ($s) {
            $path = $s->file_url ? \Illuminate\Support\Facades\Storage::disk('public')->path($s->file_url) : null;

            return $path && file_exists($path) ? filesize($path) : 0;
        });
        $newFileBytes = $file->getSize();
        if (($totalUsedBytes + $newFileBytes) > self::USER_QUOTA_KILOBYTES * 1024) {
            $errors[] = 'Kuota upload total ('.number_format($totalUsedBytes / 1024 / 1024, 1).' MB / 500 MB) terlampaui.';
        }

        return $errors;
    }

    /**
     * Create or replace the submission for this user/assignment/batch.
     */
    public function submit(
        User $user,
        Assignment $assignment,
        UploadedFile $file,
        ?string $batchId = null,
        ?string $notes = null
    ): Submission {
        $existing = $this->find($user, $assignment, $batchId);
        $path = $file->store('assignments/'.$assignment->id, self::DISK);

        if ($existing) {
            $this->deleteStoredFile($existing);

            $existing->update([
                'file_url' => $path,
                'notes' => $notes,
                'submitted_at' => now(),
                // Upload ulang membatalkan penilaian sebelumnya.
                'grade' => null,
                'feedback' => null,
            ]);

            return $existing->refresh();
        }

        return Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'batch_id' => $batchId,
            'file_url' => $path,
            'notes' => $notes,
            'submitted_at' => now(),
        ]);
    }

    public function find(User $user, Assignment $assignment, ?string $batchId = null): ?Submission
    {
        return Submission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->when(
                $batchId,
                fn ($query) => $query->where('batch_id', $batchId),
                fn ($query) => $query->whereNull('batch_id')
            )
            ->first();
    }

    /**
     * Only allowed while the instructor has not graded the work.
     */
    public function delete(Submission $submission): bool
    {
        if ($submission->grade !== null) {
            return false;
        }

        $this->deleteStoredFile($submission);

        return (bool) $submission->delete();
    }

    private function deleteStoredFile(Submission $submission): void
    {
        if ($submission->file_url) {
            Storage::disk(self::DISK)->delete($submission->file_url);
        }
    }
}

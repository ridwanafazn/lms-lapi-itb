<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Assignment;
use App\Models\Submission;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public Assignment $assignment;
    public $batchId;

    // State
    public $submission; // Model submission yang sudah ada
    public $file; // File baru yang akan diupload
    public $notes; // Catatan siswa

    public function mount(Assignment $assignment, $batchId)
    {
        $this->assignment = $assignment;
        $this->batchId = $batchId;
        $this->loadSubmission();
    }

    public function loadSubmission()
    {
        $this->submission = Submission::where('assignment_id', $this->assignment->id)
            ->where('user_id', Auth::id())
            ->where('batch_id', $this->batchId)
            ->first();

        if ($this->submission) {
            $this->notes = $this->submission->notes;
        }
    }

    public function save()
    {
        $this->validate([
            'file' => 'required|file|max:' . \App\Services\AssignmentSubmissionService::MAX_KILOBYTES
                . '|mimes:' . implode(',', \App\Services\AssignmentSubmissionService::ALLOWED_EXTENSIONS),
            'notes' => 'nullable|string|max:1000',
        ], [
            'file.required' => 'Anda belum memilih file untuk diupload.',
            'file.max' => 'Ukuran file maksimal 20MB.',
        ]);

        app(\App\Services\AssignmentSubmissionService::class)->submit(
            Auth::user(),
            $this->assignment,
            $this->file,
            $this->batchId,
            $this->notes
        );

        $this->file = null;
        $this->loadSubmission();

        $this->dispatch('notify', message: 'Tugas berhasil dikirim!', type: 'success');
    }

    public function deleteSubmission()
    {
        if ($this->submission && app(\App\Services\AssignmentSubmissionService::class)->delete($this->submission)) {
            $this->submission = null;
            $this->notes = '';

            $this->dispatch('notify', message: 'Pengumpulan dibatalkan.', type: 'info');
        }
    }
}; ?>

<div class="space-y-8 font-sans">

    {{-- 1. INSTRUKSI TUGAS --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6 md:p-8 shadow-sm">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $assignment->title }}</h2>
                <div class="flex items-center gap-4 text-sm text-gray-500">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Deadline: {{ $assignment->deadline_days ? now()->addDays($assignment->deadline_days)->format('d M Y') : 'Tidak ada' }}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        100 Poin
                    </span>
                </div>
            </div>

            {{-- Status Badge --}}
            @if(!$submission)
                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-bold uppercase tracking-wide">Belum Submit</span>
            @elseif($submission->grade !== null)
                 <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold uppercase tracking-wide">Dinilai</span>
            @else
                 <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold uppercase tracking-wide">Menunggu Penilaian</span>
            @endif
        </div>

        <div class="prose max-w-none text-gray-700">
            {!! $assignment->description !!}
        </div>
    </div>

    {{-- 2. AREA SUBMISSION --}}

    {{-- A. JIKA SUDAH DINILAI (SHOW RESULT) --}}
    @if($submission && $submission->grade !== null)
        <div class="bg-gradient-to-br from-green-50 to-white border border-green-200 rounded-2xl p-6 md:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-green-100 rounded-full opacity-50 blur-xl"></div>

            <h3 class="text-lg font-bold text-green-900 mb-6 relative z-10">Hasil Penilaian</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
                <div class="text-center p-6 bg-white rounded-xl shadow-sm border border-green-100">
                    <span class="block text-sm font-medium text-gray-500 mb-1">Nilai Akhir</span>
                    <span class="text-5xl font-extrabold text-green-600">{{ $submission->grade }}<span class="text-xl text-gray-400 font-normal">/100</span></span>
                </div>

                <div class="space-y-4">
                    <div>
                        <span class="block text-sm font-bold text-gray-700 mb-2">Feedback Instruktur:</span>
                        <p class="text-gray-600 italic bg-white p-4 rounded-lg border border-gray-100">
                            "{{ $submission->feedback ?? 'Tidak ada catatan tambahan.' }}"
                        </p>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-gray-700 mb-2">File Anda:</span>
                        <a href="{{ Storage::url($submission->file_url) }}" target="_blank" class="inline-flex items-center gap-2 text-indigo-600 hover:underline">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 011.414.586l5.414 5.414a1 1 0 01.586 1.414V19a2 2 0 01-2 2z" /></svg>
                            Lihat Submission
                        </a>
                    </div>
                </div>
            </div>
        </div>

    {{-- B. JIKA SUDAH SUBMIT TAPI BELUM DINILAI (SHOW STATUS & DELETE) --}}
    @elseif($submission)
        <div class="bg-white border border-gray-200 rounded-2xl p-6 md:p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Tugas Terkirim</h3>
                    <p class="text-sm text-gray-500">Dikumpulkan pada {{ $submission->created_at->format('d M Y, H:i') }}</p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                    <div class="text-sm">
                        <p class="font-medium text-gray-900">File Tugas</p>
                        <a href="{{ Storage::url($submission->file_url) }}" target="_blank" class="text-indigo-600 hover:underline text-xs">Download / Lihat</a>
                    </div>
                </div>
                <button wire:click="deleteSubmission" wire:confirm="Batalkan pengumpulan tugas ini? Anda harus mengupload ulang." class="text-red-600 hover:text-red-800 text-sm font-medium">
                    Batalkan
                </button>
            </div>

            @if($submission->notes)
                <div class="text-sm text-gray-600 mb-4">
                    <strong>Catatan Anda:</strong> {{ $submission->notes }}
                </div>
            @endif

            <p class="text-xs text-center text-gray-400">Menunggu instruktur memberikan nilai.</p>
        </div>

    {{-- C. BELUM SUBMIT (FORM UPLOAD) --}}
    @else
        <div class="bg-white border border-gray-200 rounded-2xl p-6 md:p-8"
             x-data="{ dragging: false }">

            <h3 class="text-lg font-bold text-gray-900 mb-4">Upload Jawaban</h3>

            <form wire:submit="save">
                {{-- Drag & Drop Area --}}
                <div class="relative border-2 border-dashed rounded-xl p-10 text-center transition-all duration-200"
                     :class="{ 'border-indigo-500 bg-indigo-50': dragging, 'border-gray-300 hover:bg-gray-50': !dragging }"
                     x-on:dragover.prevent="dragging = true"
                     x-on:dragleave.prevent="dragging = false"
                     x-on:drop.prevent="dragging = false">

                    <input type="file" wire:model="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">

                    <div class="space-y-2 pointer-events-none">
                        <div class="mx-auto w-12 h-12 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                        </div>
                        <div class="text-gray-600">
                            <span class="font-bold text-indigo-600">Klik untuk upload</span> atau drag file ke sini
                        </div>
                        <p class="text-xs text-gray-500">PDF, DOCX, PPTX, ZIP (Max 20MB)</p>
                    </div>
                </div>

                {{-- File Preview --}}
                @if($file)
                    <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center justify-between">
                        <span class="text-sm font-medium text-green-700 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {{ $file->getClientOriginalName() }}
                        </span>
                        <button type="button" wire:click="$set('file', null)" class="text-gray-400 hover:text-red-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                @endif

                @error('file') <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span> @enderror

                {{-- Notes --}}
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Tambahan (Opsional)</label>
                    <textarea wire:model="notes" rows="3" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="Pesan untuk instruktur..."></textarea>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove>Kirim Tugas</span>
                        <span wire:loading>Mengirim...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

</div>

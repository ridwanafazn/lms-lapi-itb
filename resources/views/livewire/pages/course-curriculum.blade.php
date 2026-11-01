<?php

use App\Models\Course;
use App\Models\Section;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\Assignment;
use App\Enums\CourseStatus;
use App\Services\ZoomService;
use Illuminate\Support\Str;
use function Livewire\Volt\{state, mount, layout, with};

layout('layouts.app');

state([
    'course' => null,
    // State Moderation
    'showModerationModal' => false,
    'moderation_note' => '',
]);

mount(function ($courseId) {
    $this->course = Course::findOrFail($courseId);
});

with(function () {
    return [
        'sections' => $this->course->sections()
            ->with(['lessons.material', 'lessons.quiz', 'lessons.assignment'])
            ->orderBy('order_index')
            ->get(),
        'backUrl' => route('admin.courses')
    ];
});

// TAHAP 3: MESIN AUTO-GENERATE ZOOM
$approveCourse = function () {
    if ($this->course->sections()->count() === 0) {
        session()->flash('error', 'Course harus memiliki minimal 1 Bab.');
        return;
    }

    $zoomService = app(\App\Services\ZoomService::class);
    $zoomErrors = [];
    $generatedCount = 0;

    // 1. Cari semua materi live_session di course ini yang belum punya link Zoom
    $liveSessions = Material::where('course_id', $this->course->id)
        ->where('type', 'live_session')
        ->where('meeting_platform', 'zoom')
        ->whereNull('meeting_id')
        ->get();

    // 2. Looping untuk generate link via API
    foreach ($liveSessions as $session) {
        try {
            $hostEmail = config('services.zoom.host_email');

            // Format waktu untuk API
            $startTime = $session->start_time ? $session->start_time->format('Y-m-d\TH:i:s') : now()->addDay()->format('Y-m-d\TH:i:s');

            $meeting = $zoomService->createMeeting(
                $session->title,
                $startTime,
                $session->duration ?? 60,
                $hostEmail
            );

            // Update ke database lokal
            $session->update([
                'meeting_id' => (string) $meeting['id'],
                'meeting_passcode' => $meeting['password'],
                'zoom_url' => $meeting['join_url'],
                'content_url' => $meeting['start_url'], // Start URL untuk host
                'status' => 'published'
            ]);

            $generatedCount++;

        } catch (\Exception $e) {
            $zoomErrors[] = "Gagal generate sesi '{$session->title}': " . $e->getMessage();
        }
    }

    // 3. Jika ada error API, hentikan approval dan beri peringatan
    if (count($zoomErrors) > 0) {
        session()->flash('error', 'API Zoom Error. ' . implode(' | ', $zoomErrors));
        return;
    }

    // 4. Jika sukses (atau tidak ada live session), Approve course.
    $this->course->update([
        'status' => CourseStatus::PUBLISHED,
        'moderation_note' => null // Bersihkan catatan revisi jika ada
    ]);

    $message = 'Course berhasil disetujui dan di-publish.';
    if ($generatedCount > 0) {
        $message .= " {$generatedCount} link Zoom berhasil di-generate.";
    }

    session()->flash('message', $message);
};

// Fungsi Penolakan
$rejectCourse = function () {
    $this->validate([
        'moderation_note' => 'required|min:10|max:500'
    ]);

    $this->course->update([
        'status' => CourseStatus::REJECTED,
        'moderation_note' => $this->moderation_note
    ]);

    $this->showModerationModal = false;
    session()->flash('error', 'Course dikembalikan ke instruktur untuk revisi.');
};

?>

<div class="min-h-screen bg-gray-50/50 pb-20">
    <div class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm transition-all duration-300">
        <div class="max-w-5xl px-4 mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between py-4 gap-4">
                <div class="flex flex-col gap-1">
                    <nav class="flex items-center text-xs font-medium text-gray-500 mb-1">
                        <a href="{{ $backUrl }}" class="flex items-center transition-colors hover:text-indigo-600 group">
                            <svg class="w-3.5 h-3.5 mr-1 text-gray-400 group-hover:text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            Kembali ke Daftar Kelas
                        </a>
                        <span class="mx-2 text-gray-300">/</span>
                        <span class="text-gray-900 font-semibold">Review Kurikulum</span>
                    </nav>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl md:text-2xl font-bold tracking-tight text-gray-900 truncate">{{ $course->title }}</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $course->status->color() }}-100 text-{{ $course->status->color() }}-800 border border-{{ $course->status->color() }}-200">
                            {{ $course->status->label() }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @if($course->status === \App\Enums\CourseStatus::PENDING)
                        <button wire:click="$set('showModerationModal', true)" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                            Tolak (Revisi)
                        </button>
                        <button wire:click="approveCourse" wire:loading.attr="disabled" wire:confirm="Setujui course ini? Sistem akan menggenerate Zoom link secara otomatis jika ada." class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white transition-all transform bg-green-600 rounded-lg shadow-md hover:bg-green-700 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600">
                            <span wire:loading.remove wire:target="approveCourse">Setujui & Publish</span>
                            <span wire:loading wire:target="approveCourse">Memproses API...</span>
                        </button>
                    @elseif($course->status === \App\Enums\CourseStatus::PUBLISHED)
                         <span class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-500 bg-gray-100 border border-gray-200 rounded-lg">
                            <svg class="w-4 h-4 mr-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Sudah Live
                        </span>
                    @else
                         <span class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-500 bg-gray-100 border border-gray-200 rounded-lg">
                            Masih Draf (Oleh Instruktur)
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-5xl px-4 mx-auto sm:px-6 lg:px-8 py-8">

        @if (session()->has('message'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 p-4 rounded-xl shadow-sm flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('message') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl shadow-sm flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-r-xl">
             <div class="flex">
                 <div class="flex-shrink-0">
                     <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                 </div>
                 <div class="ml-3">
                     <p class="text-sm text-blue-700">
                         Ini adalah tampilan <b>Read-Only</b>. Anda tidak bisa mengedit atau mengurutkan materi. Jika ada jadwal Live Session yang belum memiliki link, sistem akan membuatkannya otomatis saat Anda menekan tombol <b>Setujui & Publish</b>.
                     </p>
                 </div>
             </div>
        </div>

        <div class="space-y-6">
            <div class="space-y-6">
                @forelse($sections as $section)
                    <div wire:key="section-{{ $section->id }}" x-data="{ expanded: true }" class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden group/section">
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50/80 border-b border-gray-100 select-none">
                            <div class="flex items-center flex-1 gap-3">
                                <button @click="expanded = !expanded" class="text-gray-400 hover:text-indigo-600 transition-transform duration-200" :class="{ '-rotate-90': !expanded }">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                                <div class="flex-1">
                                     <div class="flex items-center gap-2">
                                         <h3 class="text-base font-bold text-gray-800">{{ $section->title }}</h3>
                                     </div>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <span class="text-xs text-gray-400 font-medium">{{ $section->lessons->count() }} Item</span>
                            </div>
                        </div>

                        <div x-show="expanded" x-collapse>
                            <ul class="divide-y divide-gray-100">
                                @foreach($section->lessons as $lesson)
                                    <li wire:key="lesson-{{ $lesson->id }}" class="flex items-center justify-between p-3 pl-4 hover:bg-gray-50 transition-colors">
                                        <div class="flex items-center flex-1 gap-4 overflow-hidden pl-6">
                                            <div class="shrink-0">
                                                @php
                                                    $isZoom = ($lesson->item_type == 'material' && optional($lesson->content)->type == 'live_session');
                                                    $zoomPending = ($isZoom && empty($lesson->content->meeting_id) && $lesson->content->meeting_platform === 'zoom');

                                                    $colors = ['material' => 'bg-sky-100 text-sky-600', 'quiz' => 'bg-purple-100 text-purple-600', 'assignment' => 'bg-orange-100 text-orange-600'];
                                                    if($isZoom) $colors['material'] = 'bg-blue-100 text-blue-700';

                                                    $icons = [
                                                        'material' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                                        'quiz' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                                        'assignment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 01-2-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />'
                                                    ];
                                                    if($isZoom) $icons['material'] = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>';
                                                @endphp
                                                <div class="flex items-center justify-center w-9 h-9 rounded-lg {{ $colors[$lesson->item_type] ?? 'bg-gray-100' }}">
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">{!! $icons[$lesson->item_type] ?? '' !!}</svg>
                                                </div>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-baseline justify-between mb-0.5">
                                                    <div class="flex items-center gap-2">
                                                        <p class="text-sm font-medium text-gray-700 truncate">
                                                            {{ $lesson->content->title ?? 'Untitled Content' }}
                                                        </p>
                                                        @if($zoomPending)
                                                            <span class="px-2 py-0.5 text-[10px] bg-yellow-100 text-yellow-700 border border-yellow-300 rounded-md font-bold flex items-center gap-1">
                                                                <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse"></span>
                                                                Menunggu API
                                                            </span>
                                                        @elseif($isZoom && !empty($lesson->content->meeting_id))
                                                            <span class="px-2 py-0.5 text-[10px] bg-green-100 text-green-700 border border-green-300 rounded-md font-bold flex items-center gap-1">
                                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                                Link Siap
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-3 mt-1">
                                                    @if($isZoom)
                                                        <span class="text-[10px] text-gray-500 font-medium flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                            {{ \Carbon\Carbon::parse($lesson->content->start_time)->format('d M Y H:i') }}
                                                        </span>
                                                        <span class="text-[10px] text-gray-500 font-medium">| Durasi: {{ $lesson->content->duration ?? 60 }} Menit</span>
                                                    @elseif($lesson->item_type == 'quiz')
                                                        <span class="text-[10px] text-gray-400 font-medium">• {{ $lesson->content->duration_minutes ?? 0 }} Menit</span>
                                                    @elseif($lesson->item_type == 'assignment')
                                                        <span class="text-[10px] text-gray-400 font-medium">• Deadline {{ $lesson->content->deadline_days ?? 0 }} Hari</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-16 bg-white border-2 border-dashed border-gray-200 rounded-2xl text-center">
                        <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Kurikulum Masih Kosong</h3>
                        <p class="text-gray-500 mb-6 max-w-sm">Instruktur belum menambahkan materi apa pun ke dalam kelas ini.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- MODAL PENOLAKAN / REVISI --}}
    @if($showModerationModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-900 opacity-60 transition-opacity" wire:click="$set('showModerationModal', false)"></div>
            <div class="bg-white rounded-2xl p-6 z-10 w-full max-w-md shadow-2xl transform transition-all">
                <form wire:submit.prevent="rejectCourse">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-bold text-red-600 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Tolak Kurikulum (Revisi)
                        </h3>
                        <button type="button" wire:click="$set('showModerationModal', false)" class="text-gray-400 hover:text-gray-500"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Catatan Perbaikan untuk Instruktur</label>
                            <textarea wire:model="moderation_note" rows="4" class="w-full border-gray-300 rounded-lg p-3 text-sm focus:ring-red-500 focus:border-red-500" placeholder="Jelaskan bagian mana yang harus diperbaiki (min. 10 karakter)..." autofocus></textarea>
                            @error('moderation_note') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button type="button" wire:click="$set('showModerationModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-bold text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 shadow-sm">
                            Kirim Catatan Penolakan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

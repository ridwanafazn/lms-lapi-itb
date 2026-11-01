<?php

use App\Models\Course;
use App\Models\ActivityLog;
use App\Enums\CourseStatus;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    // State untuk UI
    public $search = '';

    // State untuk Slide-Over (Drawer) Detail
    public $showDrawer = false;
    public $selectedCourse = null; // Menyimpan objek course yang sedang dilihat detailnya

    // State untuk Modal Reject
    public $rejectModalOpen = false;
    public $rejectionNote = '';

    public function with()
    {
        return [
            'pendingCourses' => Course::where('status', CourseStatus::PENDING)
                ->where('title', 'like', '%'.$this->search.'%')
                ->with(['owner', 'sections.lessons', 'sections']) // Eager load untuk performa dan stats
                ->latest('updated_at')
                ->paginate(9) // Grid 3x3
        ];
    }

    // --- ACTIONS ---

    // Membuka Slide-Over Detail
    public function openDrawer($courseId)
    {
        $this->selectedCourse = Course::with(['sections.lessons', 'owner'])->findOrFail($courseId);
        $this->showDrawer = true;
    }

    public function closeDrawer()
    {
        $this->showDrawer = false;
        $this->reset(['selectedCourse', 'rejectionNote']);
    }

    public function approve($id)
    {
        $course = Course::findOrFail($id);

        $course->update([
            'status' => CourseStatus::PUBLISHED,
            'published_at' => now(),
            'moderation_note' => null
        ]);

        ActivityLog::create([
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()->name,
            'action' => 'approve_course',
            'description' => "Menyetujui course '{$course->title}'",
            'ip_address' => request()->ip(),
        ]);

        $this->closeDrawer();
        $this->dispatch('notify', title: 'Published', message: 'Course diterbitkan.', type: 'success');
    }

    // Trigger Modal Reject (Bisa dari Card atau Drawer)
    public function openRejectModal($id)
    {
        // Pastikan kita punya data course yang mau direject (bisa jadi dari card langsung)
        if (!$this->selectedCourse || $this->selectedCourse->id !== $id) {
            $this->selectedCourse = Course::findOrFail($id);
        }

        $this->rejectionNote = '';
        $this->rejectModalOpen = true;
    }

    public function reject()
    {
        $this->validate(['rejectionNote' => 'required|min:10']);

        $this->selectedCourse->update([
            'status' => CourseStatus::REJECTED,
            'moderation_note' => $this->rejectionNote
        ]);

        ActivityLog::create([
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()->name,
            'action' => 'reject_course',
            'description' => "Menolak course '{$this->selectedCourse->title}'",
            'ip_address' => request()->ip(),
        ]);

        $this->rejectModalOpen = false;
        $this->closeDrawer();
        $this->dispatch('notify', title: 'Dikembalikan', message: 'Course dikembalikan untuk revisi.', type: 'info');
    }
};
?>

<div class="min-h-screen bg-gray-50/50 py-8 font-sans">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- HEADER SECTION --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Review Center</h1>
                <p class="mt-2 text-sm text-gray-500">
                    Menampilkan antrean course yang menunggu persetujuan kualitas.
                </p>
            </div>

            {{-- Search & Stats --}}
            <div class="flex items-center gap-3">
                <div class="relative">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari judul..."
                           class="pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent shadow-sm w-64 transition-all">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <div class="bg-indigo-600 text-white px-4 py-2 rounded-xl shadow-lg shadow-indigo-200 flex items-center gap-2">
                    <span class="text-xs font-medium opacity-80 uppercase tracking-wider">Pending</span>
                    <span class="text-lg font-bold">{{ $pendingCourses->total() }}</span>
                </div>
            </div>
        </div>

        {{-- CONTENT GRID --}}
        <div class="relative min-h-[400px]">
            {{-- Loading State Overlay --}}
            <div wire:loading.flex wire:target="search" class="absolute inset-0 z-10 bg-white/50 backdrop-blur-sm items-center justify-center rounded-2xl">
                <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>

            @if($pendingCourses->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($pendingCourses as $course)
                        <div wire:key="course-{{ $course->id }}"
                             class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col h-full">

                            {{-- Card Image --}}
                            <div class="relative h-48 bg-gray-100 overflow-hidden">
                                @if($course->thumbnail_url)
                                    <img src="{{ $course->thumbnail_url }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="">
                                @else
                                    <div class="flex items-center justify-center h-full bg-indigo-50 text-indigo-300">
                                        <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <div class="absolute top-3 right-3">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white/90 backdrop-blur text-gray-800 shadow-sm">
                                        {{ ucfirst($course->level) }}
                                    </span>
                                </div>
                            </div>

                            {{-- Card Body --}}
                            <div class="p-5 flex-1 flex flex-col">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-3">
                                    <div class="h-6 w-6 rounded-full bg-gray-200 overflow-hidden">
                                        {{-- Tambahkan ?? 'Unknown' pada urlencode --}}
                                        <img src="{{ $course->owner->profile_photo_url ?? 'https://ui-avatars.com/api/?name='.urlencode($course->owner->name ?? 'Unknown') }}" alt="" class="h-full w-full object-cover">
                                    </div>
                                    {{-- Tambahkan ?? 'Tanpa Instruktur' pada nama --}}
                                    <span class="text-xs font-medium text-gray-500 truncate">{{ $course->owner->name ?? 'Tanpa Instruktur' }}</span>
                                </div>

                                    <h3 class="text-lg font-bold text-gray-900 leading-snug mb-2 line-clamp-2" title="{{ $course->title }}">
                                        {{ $course->title }}
                                    </h3>

                                    {{-- Quick Stats --}}
                                    <div class="flex items-center gap-4 text-xs text-gray-500 mt-4 pb-4 border-b border-gray-50">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                            {{ $course->sections->count() }} Bab
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ $course->updated_at->diffForHumans(null, true) }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Card Actions --}}
                                <div class="pt-4 flex items-center justify-between gap-3">
                                    <button wire:click="openRejectModal('{{ $course->id }}')"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Tolak Cepat">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>

                                    <button wire:click="openDrawer('{{ $course->id }}')"
                                            class="flex-1 flex items-center justify-center gap-2 bg-gray-900 hover:bg-indigo-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-md hover:shadow-lg">
                                        <span>Review Detail</span>
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $pendingCourses->links() }}
                </div>
            @else
                {{-- Modern Empty State --}}
                <div class="flex flex-col items-center justify-center py-24 text-center">
                    <div class="bg-indigo-50 rounded-full p-6 mb-6">
                        <svg class="w-12 h-12 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Semua Tugas Selesai!</h3>
                    <p class="text-gray-500 mt-2 max-w-sm mx-auto">Tidak ada pengajuan course baru saat ini. Silakan kembali lagi nanti.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- SLIDE-OVER (DRAWER) --}}
    <div x-data="{ show: @entangle('showDrawer') }"
         @keydown.window.escape="show = false"
         x-show="show"
         style="display: none;"
         class="relative z-50"
         aria-labelledby="slide-over-title"
         role="dialog"
         aria-modal="true">

        <div x-show="show"
             x-transition:enter="ease-in-out duration-500"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-500"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm"></div>

        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">

                    <div x-show="show"
                         x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full"
                         class="pointer-events-auto w-screen max-w-2xl">

                        <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-2xl">
                            @if($selectedCourse)
                                {{-- Drawer Header --}}
                                <div class="px-4 py-6 sm:px-6 bg-gray-50 border-b border-gray-200">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-semibold leading-6 text-gray-900" id="slide-over-title">
                                                Review Kurikulum
                                            </h2>
                                            <p class="mt-1 text-sm text-gray-500">Cek kelengkapan materi sebelum menerbitkan.</p>
                                        </div>
                                        <div class="ml-3 flex h-7 items-center">
                                            <button type="button" wire:click="closeDrawer" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                <span class="sr-only">Close panel</span>
                                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Drawer Content --}}
                                <div class="relative flex-1 px-4 py-6 sm:px-6">
                                    {{-- Metadata --}}
                                    <div class="flex items-center gap-4 mb-8">
                                        <div class="h-24 w-40 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 shrink-0">
                                            @if($selectedCourse->thumbnail_url)
                                                <img src="{{ $selectedCourse->thumbnail_url }}" class="h-full w-full object-cover">
                                            @endif
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-900">{{ $selectedCourse->title }}</h3>
                                            <div class="flex items-center gap-2 mt-2 text-sm text-gray-600">
                                                {{-- Tambahkan null safety di sini juga --}}
                                                <span class="font-medium text-gray-900">{{ $selectedCourse->owner->name ?? 'Tanpa Instruktur' }}</span>
                                                <span>•</span>
                                                <span class="capitalize">{{ $selectedCourse->level }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Curriculum Tree --}}
                                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                                            <h4 class="font-semibold text-gray-700 text-sm">Struktur Materi</h4>
                                            <span class="text-xs text-gray-500">{{ $selectedCourse->sections->count() }} Bab</span>
                                        </div>
                                        <div class="divide-y divide-gray-100">
                                            @forelse($selectedCourse->sections as $section)
                                                <div class="px-4 py-3 bg-white">
                                                    <h5 class="text-sm font-bold text-gray-800 mb-2">{{ $section->title }}</h5>
                                                    <ul class="space-y-2 pl-4 border-l-2 border-gray-100">
                                                        @foreach($section->lessons as $lesson)
                                                            <li class="flex items-center gap-2 text-sm text-gray-600">
                                                                @if($lesson->item_type == 'material')
                                                                    <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                @elseif($lesson->item_type == 'quiz')
                                                                    <svg class="w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                @else
                                                                    <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                                @endif
                                                                {{ $lesson->content->title ?? 'Untitled' }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @empty
                                                <div class="px-4 py-4 text-center text-sm text-gray-500">Tidak ada materi.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>

                                {{-- Drawer Footer (Actions) --}}
                                <div class="flex-shrink-0 border-t border-gray-200 px-4 py-5 sm:px-6 bg-gray-50 flex gap-3 justify-end">
                                    <button wire:click="openRejectModal('{{ $selectedCourse->id }}')" type="button" class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        Minta Revisi
                                    </button>
                                    <button wire:click="approve('{{ $selectedCourse->id }}')"
                                            wire:confirm="Terbitkan course ini sekarang?"
                                            type="button"
                                            class="inline-flex justify-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                        Approve & Publish
                                    </button>
                                </div>
                            @else
                                {{-- Loading Skeleton for Drawer --}}
                                <div class="p-6 animate-pulse space-y-6">
                                    <div class="h-8 bg-gray-200 rounded w-1/3"></div>
                                    <div class="h-40 bg-gray-200 rounded-xl"></div>
                                    <div class="space-y-3">
                                        <div class="h-4 bg-gray-200 rounded w-full"></div>
                                        <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL REJECT (Overlaying Drawer) --}}
    <div x-data="{ open: @entangle('rejectModalOpen') }"
         x-show="open"
         style="display: none;"
         class="fixed inset-0 z-[60] overflow-y-auto"
         aria-labelledby="modal-title" role="dialog" aria-modal="true">

        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="open"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-100">

                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg font-bold leading-6 text-gray-900" id="modal-title">Tolak Pengajuan</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-3">
                                    Catatan ini akan dikirim ke instruktur sebagai panduan revisi.
                                </p>
                                <textarea
                                    wire:model="rejectionNote"
                                    class="w-full rounded-xl border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-3"
                                    rows="4"
                                    placeholder="Jelaskan bagian yang perlu diperbaiki..."></textarea>
                                @error('rejectionNote') <span class="text-red-500 text-xs font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                    <button type="button"
                            wire:click="reject"
                            wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto transition-colors">
                        <span wire:loading.remove wire:target="reject">Kirim Revisi</span>
                        <span wire:loading wire:target="reject">Mengirim...</span>
                    </button>
                    <button type="button"
                            @click="open = false"
                            class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-bold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto transition-colors">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

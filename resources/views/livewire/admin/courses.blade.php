<?php

use App\Models\Course;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use function Livewire\Volt\{state, computed, usesPagination, layout, updated};

// [OPTIMASI] Gunakan Lazy Loading agar halaman terbuka instan
layout('layouts.app', ['lazy' => true]);
usesPagination();

state([
    'title' => '',
    'description' => '',
    'level' => 'beginner',
    'delivery_mode' => 'cohort_based',
    'selected_instructors' => [],
    'course_id' => null,
    'isModalOpen' => false,
    'search' => '',             // Search Halaman Utama
    'instructorSearch' => '',   // Search Modal Instruktur
    'perPage' => 4,
]);

updated(['search', 'perPage'], function () {
    $this->resetPage();
});

// Reset pencarian modal saat modal ditutup
$resetModalState = function() {
    $this->instructorSearch = '';
};

// [SKELETON LOADING] Tampilan sementara saat data sedang dimuat
$placeholder = function () {
    return <<<'HTML'
    <div class="min-h-screen bg-slate-50/50 p-6">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="h-8 w-48 bg-slate-200 rounded animate-pulse"></div>
            <div class="bg-white border border-slate-200 rounded-2xl p-5 h-20 animate-pulse"></div>
            <div class="space-y-4">
                @for ($i = 0; $i < 4; $i++)
                    <div class="bg-white rounded-xl p-5 border border-slate-200 h-32 animate-pulse"></div>
                @endfor
            </div>
        </div>
    </div>
    HTML;
};

$courses = computed(function () {
    // [FIX] Eager Load 'instructors.user' agar bisa akses nama
    return Course::with('instructors.user')
        ->where('title', 'like', '%' . $this->search . '%')
        ->latest()
        ->paginate($this->perPage);
});

// LOGIC BARU: Filter Instruktur berdasarkan Search
$availableInstructors = computed(function () {
    $query = User::role('instructor')->orderBy('name');

    if (!empty($this->instructorSearch)) {
        $query->where('name', 'like', '%' . $this->instructorSearch . '%')
              ->orWhere('email', 'like', '%' . $this->instructorSearch . '%');
    }

    return $query->get();
});

$toggleInstructor = function ($instructorId) {
    $id = (string) $instructorId;
    if (!is_array($this->selected_instructors)) {
        $this->selected_instructors = [];
    }
    if (in_array($id, $this->selected_instructors)) {
        $this->selected_instructors = array_diff($this->selected_instructors, [$id]);
    } else {
        $this->selected_instructors[] = $id;
    }
    $this->selected_instructors = array_values($this->selected_instructors);
};

$save = function () {
    $this->validate([
        'title' => 'required|min:3',
        'level' => 'required',
        'delivery_mode' => 'required|in:self_paced,cohort_based',
        'selected_instructors' => 'required|array|min:1',
    ]);

    $data = [
        'title' => $this->title,
        'description' => $this->description ?: null,
        'level' => $this->level,
        'delivery_mode' => $this->delivery_mode,
    ];

    if ($this->course_id) {
        $course = Course::findOrFail($this->course_id);
        $course->update($data);
    } else {
        $course = Course::create($data);
    }

    // [FIX CRITICAL] Menggunakan MorphMany, TIDAK BISA pakai sync().
    // Solusi: Hapus semua instruktur lama, buat baru.
    $course->instructors()->delete();

    foreach (($this->selected_instructors ?? []) as $index => $userId) {
        $course->instructors()->create([
            'user_id' => $userId,
            'is_primary' => ($index === 0), // Yang pertama dipilih jadi primary
            'role_label' => ($index === 0) ? 'Lead Author' : 'Contributor'
        ]);
    }

    session()->flash('message', 'Pelatihan berhasil disimpan.');

    $this->isModalOpen = false;
    $this->reset(['title', 'description', 'level', 'delivery_mode', 'selected_instructors', 'course_id', 'instructorSearch']);
};

$edit = function ($id) {
    $course = Course::with('instructors')->findOrFail($id);
    $this->course_id = $id;
    $this->title = $course->title;
    $this->description = $course->description;
    $this->level = $course->level;
    $this->delivery_mode = $course->delivery_mode;

    // [FIX] Ambil 'user_id' dari tabel instructor, BUKAN id tabel instructor
    $this->selected_instructors = $course->instructors->pluck('user_id')->map(fn($id) => (string) $id)->toArray();

    $this->instructorSearch = ''; // Reset search
    $this->isModalOpen = true;
};

$create = function () {
    $this->reset(['title', 'description', 'selected_instructors', 'course_id', 'instructorSearch']);
    $this->level = 'beginner';
    $this->delivery_mode = 'cohort_based';
    $this->selected_instructors = [];
    $this->isModalOpen = true;
};
?>

<div class="min-h-screen bg-slate-50/50 font-sans text-slate-900">
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-800 leading-tight tracking-tight">{{ __('Course Management') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                     class="fixed top-5 right-5 z-50 px-6 py-3 rounded-xl shadow-lg flex items-center gap-2 transition-all duration-500 {{ Str::contains(session('message'), 'Gagal') ? 'bg-red-500' : 'bg-emerald-500' }} text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="font-medium">{{ session('message') }}</span>
                </div>
            @endif

            {{-- HEADER ACTIONS --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4 w-full md:w-auto">
                    <div class="relative w-full md:w-64">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input wire:model.live.debounce.300ms="search" type="text" class="block w-full pl-10 pr-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sm:text-sm transition-all" placeholder="Cari course...">
                    </div>
                </div>

                <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                    <button wire:click="create" class="flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow transition-all">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Course Baru
                    </button>
                </div>
            </div>

            {{-- LIST COURSES --}}
            <div class="grid grid-cols-1 gap-4">
                @forelse($this->courses as $course)
                    <div wire:key="course-{{ $course->id }}" class="group bg-white rounded-xl p-5 border border-slate-200 hover:border-indigo-300 hover:shadow-md transition-all duration-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4 flex-1">
                            <div class="flex-shrink-0">
                                <div class="h-12 w-12 rounded-xl flex items-center justify-center border shadow-sm transition-colors {{ $course->delivery_mode == 'self_paced' ? 'bg-purple-50 border-purple-100 text-purple-600' : 'bg-blue-50 border-blue-100 text-blue-600' }}">
                                    @if($course->delivery_mode == 'self_paced')
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    @else
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-base font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">{{ $course->title }}</h4>
                                    @if($course->delivery_mode == 'self_paced')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 border border-purple-200 uppercase tracking-wide">Self Paced</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200 uppercase tracking-wide">Cohort</span>
                                    @endif
                                </div>
                                <p class="text-sm text-slate-500 line-clamp-1 mb-2">{{ $course->description ?? 'Tidak ada deskripsi.' }}</p>
                                <div class="flex items-center gap-3 text-xs text-slate-500">
                                    <span class="flex items-center gap-1 bg-slate-100 px-2 py-1 rounded border border-slate-200">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        {{ ucfirst($course->level) }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        {{-- [FIX] Tampilkan nama instruktur via user --}}
                                        {{ $course->instructors->map(fn($i) => $i->user->name ?? 'User Deleted')->join(', ') ?: 'Belum ada instruktur' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-row md:flex-col items-center md:items-end justify-between gap-3 md:w-48 border-t md:border-t-0 border-slate-100 pt-3 md:pt-0">
                            <a href="{{ route('admin.course.curriculum', $course->id) }}" class="flex items-center justify-center w-full md:w-auto px-3 py-1.5 text-xs font-semibold text-white bg-slate-800 hover:bg-slate-900 rounded-lg transition-all shadow-sm">
                                <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                Kurikulum
                            </a>
                            <button wire:click="edit('{{ $course->id }}')" class="flex items-center justify-center w-full md:w-auto px-3 py-1.5 text-xs font-semibold text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-all">
                                Edit Detail
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center bg-white rounded-xl border border-dashed border-slate-300">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-4">
                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900">Belum ada course</h3>
                        <p class="text-sm text-slate-500 mt-1">Mulai dengan membuat materi pembelajaran baru.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $this->courses->links() }}
            </div>
        </div>
    </div>

    {{-- MODAL FORM --}}
    <div x-show="$wire.isModalOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">

        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" aria-hidden="true" wire:click="$set('isModalOpen', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100">
                <form wire:submit="save">
                    <div class="bg-white px-6 py-5 border-b border-slate-100 flex items-center justify-between sticky top-0 z-10">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ $course_id ? 'Edit Pelatihan' : 'Buat Pelatihan Baru' }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Lengkapi informasi dasar course.</p>
                        </div>
                        <button type="button" wire:click="$set('isModalOpen', false)" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="px-6 py-6 space-y-5 max-h-[70vh] overflow-y-auto">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Judul Course</label>
                            <input type="text" wire:model="title" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5" placeholder="Contoh: Master Laravel 11">
                            @error('title') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Deskripsi Singkat</label>
                            <textarea wire:model="description" rows="3" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5" placeholder="Jelaskan secara singkat tentang materi ini..."></textarea>
                            @error('description') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Level</label>
                                <select wire:model="level" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Mode Belajar</label>
                                <select wire:model.live="delivery_mode" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm bg-slate-50 py-2.5">
                                    <option value="cohort_based">Cohort</option>
                                    <option value="self_paced">Self Paced</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold text-slate-700">Pilih Instruktur</label>
                                <span class="text-xs font-medium bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded border border-indigo-100">
                                    {{ count($selected_instructors ?? []) }} Dipilih
                                </span>
                            </div>
                            @error('selected_instructors') <span class="text-red-500 text-xs font-medium block mb-2">{{ $message }}</span> @enderror

                            <div class="border border-slate-200 rounded-lg overflow-hidden">
                                {{-- Sticky Search Bar --}}
                                <div class="sticky top-0 bg-white z-10 px-2 py-2 border-b border-slate-100">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input wire:model.live.debounce.300ms="instructorSearch" type="text"
                                               class="block w-full pl-8 pr-8 py-1.5 border border-slate-200 rounded text-xs bg-slate-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-slate-400 transition-all"
                                               placeholder="Cari instruktur...">

                                        @if(!empty($instructorSearch))
                                            <button wire:click="$set('instructorSearch', '')" class="absolute inset-y-0 right-0 pr-2 flex items-center text-slate-400 hover:text-slate-600">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- List Instruktur --}}
                                <div class="grid grid-cols-1 gap-1 max-h-40 overflow-y-auto p-2 bg-slate-50/50 custom-scrollbar">
                                    @forelse($this->availableInstructors as $inst)
                                        <div wire:key="inst-select-{{ $inst->id }}"
                                             wire:click="toggleInstructor('{{ $inst->id }}')"
                                             class="cursor-pointer group relative p-2 rounded-md border transition-all duration-200 flex items-center justify-between select-none
                                             {{ in_array($inst->id, $selected_instructors ?? []) ? 'border-indigo-500 bg-indigo-50/50 shadow-sm' : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-white' }}">

                                            <div class="flex items-center gap-3">
                                                <div class="w-4 h-4 rounded border flex items-center justify-center transition-colors flex-shrink-0 {{ in_array($inst->id, $selected_instructors ?? []) ? 'bg-indigo-600 border-indigo-600' : 'border-slate-300 group-hover:border-indigo-400' }}">
                                                    @if(in_array($inst->id, $selected_instructors ?? []))
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                    @endif
                                                </div>
                                                <div class="overflow-hidden">
                                                    <p class="text-sm font-medium text-slate-700 truncate">{{ $inst->name }}</p>
                                                    <p class="text-[10px] text-slate-400 truncate">{{ $inst->email }}</p>
                                                </div>
                                            </div>

                                            @if(in_array($inst->id, $selected_instructors ?? []))
                                                <span class="text-[10px] font-bold text-indigo-600 bg-indigo-100 px-1.5 py-0.5 rounded">Selected</span>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="text-center py-4">
                                            <p class="text-xs text-slate-400">Instruktur tidak ditemukan.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 flex flex-row-reverse gap-3 border-t border-slate-100 rounded-b-2xl">
                        <button type="submit" class="inline-flex justify-center items-center rounded-lg shadow-sm px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all w-full sm:w-auto">
                            Simpan Perubahan
                        </button>
                        <button type="button" wire:click="$set('isModalOpen', false)" class="inline-flex justify-center rounded-lg px-5 py-2.5 bg-white border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all w-full sm:w-auto">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

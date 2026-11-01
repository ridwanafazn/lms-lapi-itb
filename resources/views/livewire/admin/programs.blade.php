<?php

use App\Models\Program;
use App\Models\Course;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use function Livewire\Volt\{state, computed, usesPagination, layout, updated};

layout('layouts.app');
usesPagination();

state([
    'title' => '',
    'description' => '',
    'price' => 0,
    'program_type' => 'self_paced',
    'min_membership_level' => 'none',
    'program_id' => null,
    'selected_courses' => [],
    'isModalOpen' => false,
    'search' => '',         // Search untuk Halaman Utama (Daftar Program)
    'courseSearch' => '',   // Search untuk Modal (Daftar Course) -- BARU
    'perPage' => 4,
]);

updated(['search', 'perPage'], function () {
    $this->resetPage();
});

// Reset pencarian course saat modal ditutup atau dibuka baru
$resetModalSearch = function() {
    $this->courseSearch = '';
};

// -- LOGIC: Auto-Uncheck saat tipe berubah --
$updatedProgramType = function ($value) {
    if ($value === 'self_paced' && !empty($this->selected_courses)) {
        $cohortCourseIds = Course::whereIn('id', $this->selected_courses)
            ->where('delivery_mode', '!=', 'self_paced')
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        if (!empty($cohortCourseIds)) {
            $this->selected_courses = array_values(array_diff($this->selected_courses, $cohortCourseIds));
            session()->flash('warning', 'Beberapa course tipe Cohort otomatis dihapus dari seleksi karena tidak kompatibel.');
        }
    }
};

$programs = computed(function () {
    // [FIX] Eager load .user
    return Program::with(['courses.instructors.user'])
        ->where(function($q) {
            $q->where('title', 'like', '%' . $this->search . '%')
              ->orWhere('description', 'like', '%' . $this->search . '%');
        })
        ->latest()
        ->paginate($this->perPage);
});

// -- LOGIC BARU: Filter tampilan list course + PENCARIAN --
$availableCourses = computed(function () {
    $query = Course::with('instructors.user') // [FIX] Eager load .user
        ->select('id', 'title', 'level', 'delivery_mode')
        ->orderBy('title');

    // 1. Filter Tipe Program
    if ($this->program_type === 'self_paced') {
        $query->where('delivery_mode', 'self_paced');
    }

    // 2. Filter Pencarian (Title, Level, Instructor) -- UPDATE DISINI
    if (!empty($this->courseSearch)) {
        $searchTerm = '%' . $this->courseSearch . '%';
        $query->where(function($subQ) use ($searchTerm) {
            $subQ->where('title', 'like', $searchTerm)
                 ->orWhere('level', 'like', $searchTerm)
                 ->orWhereHas('instructors.user', function($instQ) use ($searchTerm) { // [FIX] Filter via user->name
                     $instQ->where('name', 'like', $searchTerm);
                 });
        });
    }

    return $query->get();
});

$toggleCourse = function ($courseId) {
    $id = (string) $courseId;
    if (!is_array($this->selected_courses)) {
        $this->selected_courses = [];
    }
    if (in_array($id, $this->selected_courses)) {
        $this->selected_courses = array_diff($this->selected_courses, [$id]);
    } else {
        $this->selected_courses[] = $id;
    }
    $this->selected_courses = array_values($this->selected_courses);
};

$save = function () {
    $this->validate([
        'title' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'program_type' => 'required|in:self_paced,cohort',
        'min_membership_level' => 'required|in:none,standard,pro',
        'selected_courses' => 'array',
    ]);

    if ($this->program_type === 'self_paced') {
        $invalidCourses = Course::whereIn('id', $this->selected_courses)
                                ->where('delivery_mode', '!=', 'self_paced')
                                ->count();

        if ($invalidCourses > 0) {
            session()->flash('error', 'Program Self-Paced HANYA boleh berisi course bertipe Self-Paced.');
            return;
        }
    }

    try {
        $program = Program::updateOrCreate(['id' => $this->program_id], [
            'id' => $this->program_id ?? (string) Str::uuid(),
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'type' => $this->program_type,
            'min_membership_level' => $this->min_membership_level ?? 'none',
            'is_active' => true,
        ]);

        $program->courses()->sync($this->selected_courses ?? []);

        session()->flash('message', 'Program berhasil disimpan.');
        $this->isModalOpen = false;
        $this->reset(['title', 'description', 'price', 'program_type', 'min_membership_level', 'selected_courses', 'program_id', 'courseSearch']);
    } catch (\Exception $e) {
        session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
    }
};

$edit = function ($id) {
    $program = Program::with('courses')->findOrFail($id);
    $this->program_id = $id;
    $this->title = $program->title;
    $this->description = $program->description;
    $this->price = $program->price;
    $this->program_type = $program->type ?? 'self_paced';
    $this->min_membership_level = $program->min_membership_level ?? 'none';
    $this->selected_courses = $program->courses->pluck('id')->map(fn($id) => (string) $id)->toArray();
    $this->courseSearch = ''; // Reset search modal
    $this->isModalOpen = true;
};

$duplicate = function ($id) {
    try {
        $original = Program::with('courses')->findOrFail($id);

        $newProgram = $original->replicate();
        $newProgram->id = (string) Str::uuid();
        $newProgram->title = $original->title . ' (Copy)';
        $newProgram->is_active = false;
        $newProgram->save();

        $newProgram->courses()->attach($original->courses->pluck('id'));

        session()->flash('message', 'Program berhasil diduplikat.');
    } catch (\Exception $e) {
        session()->flash('error', 'Gagal duplikasi: ' . $e->getMessage());
    }
};

$delete = function ($id) {
    try {
        $program = Program::findOrFail($id);
        $program->courses()->detach();
        $program->delete();
        session()->flash('message', 'Program berhasil dihapus.');
    } catch (\Exception $e) {
        session()->flash('error', 'Gagal menghapus: ' . $e->getMessage());
    }
};

$create = function () {
    $this->reset(['title', 'description', 'price', 'program_type', 'min_membership_level', 'selected_courses', 'program_id', 'courseSearch']);
    $this->selected_courses = [];
    $this->program_type = 'self_paced';
    $this->isModalOpen = true;
};

$closeModal = function () {
    $this->isModalOpen = false;
    $this->reset(['title', 'description', 'price', 'program_type', 'min_membership_level', 'selected_courses', 'program_id', 'courseSearch']);
};

?>

<div class="min-h-screen bg-slate-50/50 font-sans text-slate-900">
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-800 leading-tight tracking-tight">{{ __('Programs Management') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash Message Global --}}
            @if (session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                     class="fixed top-5 right-5 z-[60] bg-emerald-500 text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-2 transition-all duration-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="font-medium">{{ session('message') }}</span>
                </div>
            @endif

            @if (session()->has('error'))
                <div x-data="{ show: true }" x-show="show" class="fixed top-5 right-5 z-[60] bg-red-500 text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-2">
                    <span class="font-medium">{{ session('error') }}</span>
                    <button @click="show = false" class="ml-2 text-white hover:text-red-100">&times;</button>
                </div>
            @endif

            {{-- Header Action --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4 w-full md:w-auto">
                    <div class="relative w-full md:w-64">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input wire:model.live.debounce.300ms="search" type="text"
                            class="block w-full pl-10 pr-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sm:text-sm transition-all"
                            placeholder="Cari program...">
                    </div>
                </div>

                <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                    <button wire:click="create" class="flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow transition-all">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Program Baru
                    </button>
                </div>
            </div>

            {{-- Table Grid --}}
            <div class="grid grid-cols-1 gap-4">
                @forelse($this->programs as $program)
                    <div wire:key="program-{{ $program->id }}" class="group bg-white rounded-xl p-5 border border-slate-200 hover:border-indigo-300 hover:shadow-md transition-all duration-200 flex flex-col md:flex-row justify-between gap-4">

                        <div class="flex items-start gap-4 flex-1">
                            <div class="flex-shrink-0">
                                <div class="h-12 w-12 rounded-xl {{ ($program->type ?? 'self_paced') === 'cohort' ? 'bg-indigo-50 text-indigo-600' : 'bg-purple-50 text-purple-600' }} flex items-center justify-center border border-opacity-50">
                                    @if(($program->type ?? 'self_paced') === 'cohort')
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    @else
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-base font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">{{ $program->title }}</h4>
                                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded border {{ ($program->type ?? 'self_paced') === 'cohort' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-purple-50 text-purple-700 border-purple-200' }}">
                                        {{ str_replace('_', ' ', $program->type ?? 'self_paced') }}
                                    </span>
                                </div>
                                <p class="text-sm text-slate-500 mt-0.5 line-clamp-1">{{ $program->description ?? '-' }}</p>

                                <div class="flex flex-wrap items-center gap-2 mt-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                        <svg class="w-3 h-3 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                        {{ $program->courses->count() }} Courses
                                    </span>

                                    {{-- Derived Instructors Badge (FIXED LOGIC) --}}
                                    @php
                                        // Pluck 'instructors' (morphMany) -> flatten -> pluck 'user_id' -> unique -> count
                                        $instructorCount = $program->courses->pluck('instructors')->flatten()->pluck('user_id')->unique()->count();
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                        {{ $instructorCount }} Mentor
                                    </span>

                                    @if($program->price == 0)
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Gratis</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                            Rp {{ number_format($program->price, 0, ',', '.') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-row md:flex-col items-center md:items-end justify-between gap-3 md:w-40 border-t md:border-t-0 border-slate-100 pt-3 md:pt-0">
                            <span class="text-[10px] text-slate-400 font-medium bg-slate-50 px-2 py-1 rounded-full">
                                {{ $program->updated_at->diffForHumans() }}
                            </span>

                            <div class="flex items-center gap-1">
                                <button wire:click="duplicate('{{ $program->id }}')" wire:confirm="Duplikat program ini?" class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Duplikat">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                                </button>
                                <button wire:click="edit('{{ $program->id }}')" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <button wire:click="delete('{{ $program->id }}')" wire:confirm="Hapus program ini secara permanen?" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center bg-white rounded-xl border border-dashed border-slate-300">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-4">
                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900">Tidak ada program ditemukan</h3>
                        <p class="text-sm text-slate-500 mt-1">Coba ubah kata kunci pencarian atau buat program baru.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $this->programs->links() }}
            </div>
        </div>
    </div>

    {{-- MODAL FORM --}}
    <div x-show="$wire.isModalOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">

        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" aria-hidden="true" wire:click="closeModal"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-100">

                <form wire:submit="save">
                    <div class="bg-white px-6 py-5 border-b border-slate-100 flex items-center justify-between sticky top-0 z-10">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ $program_id ? 'Edit Program' : 'Buat Program Baru' }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Isi detail program dan kurikulum pembelajaran.</p>
                        </div>
                        <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    {{-- PESAN ERROR VALIDASI (Di Dalam Modal) --}}
                    @if (session()->has('error'))
                        <div class="mx-6 mt-4 bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 text-sm font-medium">
                            {{ session('error') }}
                        </div>
                    @endif

                    {{-- Warning Auto-Uncheck --}}
                    @if (session()->has('warning'))
                        <div class="mx-6 mt-4 bg-amber-50 text-amber-700 p-3 rounded-lg border border-amber-200 text-sm font-medium">
                            {{ session('warning') }}
                        </div>
                    @endif

                    <div class="px-8 py-6 space-y-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Program</label>
                                <input type="text" wire:model="title" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5 placeholder-slate-400" placeholder="Contoh: Laravel Mastery 2026">
                                @error('title') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Deskripsi</label>
                                <textarea wire:model="description" rows="2" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5 placeholder-slate-400" placeholder="Jelaskan tujuan program ini..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Tipe Program</label>
                                <select wire:model.live="program_type" class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5">
                                    <option value="self_paced">Self Paced (Mandiri)</option>
                                    <option value="cohort">Cohort (Terjadwal)</option>
                                </select>
                                <p class="text-[10px] text-slate-500 mt-1">
                                    {{ $program_type == 'self_paced' ? 'Hanya bisa memilih Course bertipe Self-Paced.' : 'Boleh memilih Course tipe apa saja (Hybrid).' }}
                                </p>
                                @error('program_type') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Harga (IDR)</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="text-slate-500 sm:text-sm">Rp</span>
                                    </div>
                                    <input type="number" wire:model="price" class="block w-full rounded-lg border-slate-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5" placeholder="0">
                                </div>
                                @error('price') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Membership Level Diperlukan</label>
                                <select wire:model="min_membership_level" class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5">
                                    <option value="none">None (tidak termasuk membership)</option>
                                    <option value="standard">Standard</option>
                                    <option value="pro">Pro</option>
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1">Menentukan apakah user membership bisa akses program ini.</p>
                                @error('min_membership_level') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-6">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-bold text-slate-900">Kurikulum (Pilih Course)</label>
                                <span class="text-xs font-medium px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-md border border-indigo-100">
                                    {{ count($selected_courses ?? []) }} Dipilih
                                </span>
                            </div>

                            {{-- CONTAINER LIST COURSE DENGAN SEARCH --}}
                            <div class="border border-slate-200 rounded-lg overflow-hidden">
                                {{-- STICKY SEARCH BAR --}}
                                <div class="sticky top-0 bg-white z-10 px-3 pt-3 pb-2 border-b border-slate-100">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input wire:model.live.debounce.300ms="courseSearch" type="text"
                                               class="block w-full pl-9 pr-8 py-2 border border-slate-200 rounded-md text-xs bg-slate-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-slate-400 transition-all"
                                               placeholder="Cari course, level, atau nama instruktur...">

                                        @if(!empty($courseSearch))
                                            <button wire:click="$set('courseSearch', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- SCROLLABLE LIST --}}
                                <div class="grid grid-cols-1 gap-1 max-h-60 overflow-y-auto p-2 bg-slate-50/50 custom-scrollbar">
                                    @forelse($this->availableCourses as $c)
                                        <div wire:key="course-select-{{ $c->id }}"
                                             wire:click="toggleCourse('{{ $c->id }}')"
                                             class="cursor-pointer group relative p-3 rounded-md border transition-all duration-200 flex items-center justify-between select-none
                                             {{ in_array($c->id, $selected_courses ?? []) ? 'border-indigo-500 bg-indigo-50/50 shadow-sm' : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-white' }}">

                                            <div class="flex items-center gap-3">
                                                <div class="w-5 h-5 rounded border flex items-center justify-center transition-colors flex-shrink-0 {{ in_array($c->id, $selected_courses ?? []) ? 'bg-indigo-600 border-indigo-600' : 'border-slate-300 group-hover:border-indigo-400' }}">
                                                    @if(in_array($c->id, $selected_courses ?? []))
                                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-800">{{ $c->title }}</p>
                                                    <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                                        <span class="capitalize">{{ str_replace('_', ' ', $c->delivery_mode) }}</span>
                                                        <span>•</span>
                                                        <span class="capitalize {{ $c->level == 'beginner' ? 'text-emerald-600' : ($c->level == 'intermediate' ? 'text-amber-600' : 'text-red-600') }} font-medium">
                                                            {{ ucfirst($c->level ?? 'General') }}
                                                        </span>
                                                        <span>•</span>
                                                        {{-- [FIX] Tampilkan Nama Instruktur dari relasi user --}}
                                                        <span class="truncate max-w-[100px]" title="{{ $c->instructors->map(fn($i) => $i->user->name ?? 'User Deleted')->join(', ') }}">
                                                            {{ $c->instructors->first()->user->name ?? 'No Instructor' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            @if(in_array($c->id, $selected_courses ?? []))
                                                <span class="text-[10px] font-bold text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded-full">Dipilih</span>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="text-center py-8">
                                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 mb-2">
                                                <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                            <p class="text-slate-500 text-sm">Tidak ditemukan course.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 flex flex-row-reverse gap-3 border-t border-slate-100 rounded-b-2xl">
                        <button type="submit" wire:loading.attr="disabled" class="inline-flex justify-center items-center rounded-lg shadow-sm px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-wait transition-all w-full sm:w-auto">
                            <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ $program_id ? 'Simpan Perubahan' : 'Buat Program' }}</span>
                        </button>
                        <button type="button" wire:click="closeModal" class="inline-flex justify-center rounded-lg px-5 py-2.5 bg-white border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all w-full sm:w-auto">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

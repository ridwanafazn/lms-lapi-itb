<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    public Program $program;

    // State untuk filter pencarian
    public $search = '';

    public function mount(Program $program)
    {
        $this->program = $program;
    }

    // Mengambil daftar course yang sudah ditetapkan Admin ke program ini
    #[Computed]
    public function courses()
    {
        return $this->program->courses()
            ->withCount(['sections', 'enrollments'])
            ->with('instructors') // Load relasi instruktur untuk cek hak akses
            ->when($this->search, function(Builder $q) {
                $q->where('title', 'like', '%' . $this->search . '%');
            })
            ->orderBy('pivot_order_index', 'asc')
            ->get();
    }
}; ?>

<div class="min-h-screen py-8 bg-gray-50/50">
    <div class="max-w-5xl px-4 mx-auto sm:px-6 lg:px-8">

        {{-- Breadcrumb & Header --}}
        <div class="flex flex-col gap-6 mb-8">
            <nav class="flex text-sm font-medium text-gray-500">
                <a href="{{ route('instructor.programs') }}" class="hover:text-indigo-600 transition-colors">Program</a>
                <span class="mx-2 text-gray-300">/</span>
                <span class="text-gray-900 line-clamp-1">{{ $program->title }}</span>
            </nav>

            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Kurikulum Program</h1>
                    <p class="mt-2 text-base text-gray-500">
                        Daftar mata kuliah yang telah disusun oleh Admin untuk program ini.
                    </p>
                </div>

                {{-- INFO BADGE: Memberitahu instruktur bahwa ini managed by Admin --}}
                <div class="hidden md:flex items-center px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-100 rounded-full">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Struktur Program Dikelola Admin
                </div>
            </div>
        </div>

        {{-- Toolbar: Search --}}
        <div class="mb-6">
            <div class="relative max-w-md">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       class="block w-full py-2.5 pl-10 pr-3 leading-5 text-gray-900 placeholder-gray-400 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm shadow-sm transition-shadow"
                       placeholder="Cari mata kuliah...">
            </div>
        </div>

        {{-- Content Area --}}
        <div class="space-y-4">

            {{-- Skeleton Loading --}}
            <div wire:loading class="w-full space-y-4">
                @for($i=0; $i<3; $i++)
                    <div class="flex p-4 bg-white border border-gray-200 rounded-xl animate-pulse">
                        <div class="w-24 h-16 bg-gray-200 rounded-lg shrink-0"></div>
                        <div class="flex-1 ml-4 space-y-2">
                            <div class="w-1/3 h-4 bg-gray-200 rounded"></div>
                            <div class="w-1/4 h-3 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                @endfor
            </div>

            {{-- Course List --}}
            <div wire:loading.remove class="space-y-4">
                @forelse($this->courses as $course)
                    {{-- LOGIC: Check Permissions --}}
                    @php
                        // Cek apakah user login adalah Instruktur Utama (di tabel courses)
                        $isOwner = $course->instructor_id === auth()->id();

                        // Cek apakah user login ditugaskan sebagai Instruktur Pendamping (di tabel pivot instructors)
                        $isAssigned = $course->instructors->contains('user_id', auth()->id());

                        // Jika salah satu benar, maka boleh mengelola
                        $canManage = $isOwner || $isAssigned;
                    @endphp

                    <div wire:key="{{ $course->id }}"
                         class="group relative flex flex-col sm:flex-row items-start sm:items-center p-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">

                        {{-- Thumbnail --}}
                        <div class="relative w-full sm:w-32 h-32 sm:h-20 mb-4 sm:mb-0 shrink-0 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg overflow-hidden flex items-center justify-center border border-gray-100">
                            @if($course->thumbnail_url)
                                <img src="{{ $course->thumbnail_url }}" alt="" class="w-full h-full object-cover">
                            @else
                                <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            @endif
                        </div>

                        {{-- Main Info --}}
                        <div class="flex-1 min-w-0 sm:ml-5 w-full">
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="text-lg font-bold text-gray-900 truncate group-hover:text-indigo-600 transition-colors">
                                    {{ $course->title }}
                                </h3>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500 font-medium">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    {{ $course->sections_count ?? 0 }} Bab
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                    {{ $course->enrollments_count ?? 0 }} Siswa
                                </span>
                                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200">
                                    {{ ucfirst($course->level) }}
                                </span>

                                {{-- Label Hak Akses --}}
                                @if(!$canManage)
                                    <span class="px-2 py-0.5 rounded-full bg-yellow-50 text-yellow-700 border border-yellow-100 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        Read Only
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-green-50 text-green-700 border border-green-100 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Assigned
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Right Side: Action --}}
                        <div class="flex items-center justify-between w-full sm:w-auto mt-4 sm:mt-0 gap-4 sm:ml-6">
                            @if($canManage)
                                {{-- PERBAIKAN: Mengubah route('instructor.course.builder') menjadi route('instructor.course.curriculum') --}}
                                <a href="{{ route('instructor.course.curriculum', $course->id) }}"
                                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all">
                                    <span>Kelola Ruang Kerja</span>
                                    <svg class="w-4 h-4 ml-2 text-indigo-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                            @else
                                <button type="button" disabled
                                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-50 border border-gray-200 rounded-lg cursor-not-allowed">
                                    <span>Akses Dibatasi</span>
                                    <svg class="w-4 h-4 ml-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </button>
                            @endif
                        </div>

                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-16 text-center bg-white border-2 border-dashed border-gray-200 rounded-xl">
                        <div class="p-4 bg-gray-50 rounded-full mb-4">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $search ? 'Mata kuliah tidak ditemukan' : 'Program ini belum memiliki mata kuliah' }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 max-w-sm">
                            Hubungi Administrator jika Anda merasa seharusnya ada mata kuliah di sini.
                        </p>
                        @if($search)
                             <button wire:click="$set('search', '')" class="mt-4 text-sm font-medium text-gray-500 hover:text-gray-700 underline">
                                Reset Pencarian
                            </button>
                        @endif
                    </div>
                @endforelse
            </div>

             <div class="mt-4 text-center">
                 <p class="text-xs text-gray-400">Menampilkan daftar mata kuliah dari database.</p>
            </div>
        </div>
    </div>
</div>

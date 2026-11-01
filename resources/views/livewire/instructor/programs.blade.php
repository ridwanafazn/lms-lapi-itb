<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    public string $search = '';

    #[Computed]
    public function programs()
    {
        return Program::query()
            ->where('is_active', true)
            ->when($this->search, fn($q) => $q->where('title', 'like', '%' . $this->search . '%'))
            ->whereHas('courses', function (Builder $query) {
                $query->where('instructor_id', auth()->id())
                      ->orWhereHas('instructors', fn($q) => $q->where('user_id', auth()->id()));
            })
            ->withCount('courses')
            ->latest()
            ->get();
    }
}; ?>

<div class="min-h-screen py-8 bg-gray-50/50">
    <div class="max-w-5xl px-4 mx-auto sm:px-6 lg:px-8">

        {{-- Header Section --}}
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-end">
            <div class="flex-1">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Program Studi</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Kelola kurikulum dan materi ajar Anda.
                </p>
            </div>

            <div class="relative w-full md:w-64">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    class="w-full py-2 pl-9 pr-4 text-sm text-gray-900 bg-white border border-gray-200 rounded-lg shadow-sm focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400 transition-all"
                    placeholder="Cari program..."
                >
            </div>
        </div>

        {{-- Content Area --}}
        <div class="space-y-4">

            {{-- Loading State --}}
            <div wire:loading class="w-full space-y-4">
                @for($i=0; $i<2; $i++)
                    <div class="w-full h-40 bg-white border border-gray-200 rounded-xl animate-pulse"></div>
                @endfor
            </div>

            {{-- Data List --}}
            <div wire:loading.remove class="grid grid-cols-1 gap-4">
                @forelse($this->programs as $program)
                    <div
                        wire:key="{{ $program->id }}"
                        class="group relative flex flex-col overflow-hidden transition-all duration-300 bg-white border border-gray-200 shadow-sm md:flex-row rounded-xl hover:shadow-md hover:border-indigo-200"
                    >
                        {{-- Bagian Kiri: Visual Indicator --}}
                        {{-- Dimensi diperkecil (md:w-48) agar lebih compact --}}
                        <div class="relative w-full md:w-48 shrink-0 h-32 md:h-auto overflow-hidden
                            {{ $program->type === 'cohort'
                                ? 'bg-gradient-to-br from-purple-600 to-indigo-700'
                                : 'bg-gradient-to-br from-blue-500 to-cyan-600' }}">

                            {{-- Pattern Overlay --}}
                            <div class="absolute inset-0 opacity-20"
                                 style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 12px 12px;">
                            </div>

                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-white opacity-90">
                                    @if($program->type === 'cohort')
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                    @else
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                    @endif
                                </span>
                            </div>
                        </div>

                        {{-- Bagian Kanan: Content --}}
                        {{-- Padding diperkecil (p-5) --}}
                        <div class="flex flex-col flex-1 p-5">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    {{-- Logic Teks Label --}}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border
                                        {{ $program->type === 'cohort'
                                            ? 'bg-purple-50 text-purple-700 border-purple-100'
                                            : 'bg-blue-50 text-blue-700 border-blue-100' }}">
                                        {{ $program->type === 'self_paced' ? 'Self Paced Learning' : ucfirst($program->type) }}
                                    </span>
                                </div>
                                <span class="text-xs text-gray-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    {{ $program->courses_count ?? 0 }} Courses
                                </span>
                            </div>

                            {{-- Title diperkecil (text-lg) --}}
                            <h3 class="text-lg font-bold text-gray-900 mb-1 group-hover:text-indigo-600 transition-colors line-clamp-1">
                                {{ $program->title }}
                            </h3>

                            <p class="text-sm text-gray-500 leading-snug mb-4 line-clamp-2 flex-1">
                                {{ $program->description ?: 'Tidak ada deskripsi singkat untuk program ini.' }}
                            </p>

                            <div class="mt-auto flex items-center justify-between pt-3 border-t border-gray-50">
                                <div class="flex -space-x-2 overflow-hidden">
                                    <div class="inline-block h-6 w-6 rounded-full ring-2 ring-white bg-gray-200 flex items-center justify-center text-[10px] font-bold text-gray-500">ME</div>
                                    <div class="inline-block h-6 w-6 rounded-full ring-2 ring-white bg-gray-100 flex items-center justify-center text-[10px] text-gray-400">+</div>
                                </div>

                                {{-- Tombol lebih ringkas --}}
                                <a href="{{ route('instructor.program.courses', $program->id) }}" wire:navigate
                                   class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold text-white transition-all duration-200 bg-gray-900 rounded-lg hover:bg-indigo-600 hover:shadow-md">
                                    Kelola
                                    <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-center bg-white border border-gray-200 border-dashed rounded-xl">
                        <div class="p-3 mb-3 bg-gray-50 rounded-full">
                            <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">
                            {{ $this->search ? 'Tidak ditemukan' : 'Belum ada Program' }}
                        </h3>

                        @if($this->search)
                            <button wire:click="$set('search', '')" class="mt-2 text-xs font-medium text-indigo-600 hover:text-indigo-500">
                                Reset
                            </button>
                        @endif
                    </div>
                @endforelse
            </div>

            <div class="mt-4 text-center">
                 <p class="text-[10px] text-gray-400">Total {{ count($this->programs) }} program aktif</p>
            </div>
        </div>
    </div>
</div>

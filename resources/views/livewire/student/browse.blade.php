<?php

use function Livewire\Volt\{state, layout, with, usesPagination};
use App\Models\Program;
use App\Models\Course;

layout('layouts.app');

// Menggunakan trait pagination bawaan Livewire
usesPagination();

state([
    'search' => '',
    'selectedTopics' => [],
    'level' => '',
    'type' => '',
    'price' => '',
    'rating' => '',
    'sortBy' => 'popular', 
]);

$toggleTopic = function ($topic) {
    if (in_array($topic, $this->selectedTopics)) {
        $this->selectedTopics = array_diff($this->selectedTopics, [$topic]);
    } else {
        $this->selectedTopics[] = $topic;
    }
    $this->resetPage(); // Reset pagination saat filter berubah
};

$resetFilters = function () {
    $this->search = '';
    $this->selectedTopics = [];
    $this->level = '';
    $this->type = '';
    $this->price = '';
    $this->rating = '';
    $this->sortBy = 'popular';
    $this->resetPage();
};

with(function () {
    $query = Program::query()
        ->where('is_active', true)
        ->with([
            'courses' => fn ($query) => $query->withCount(['materials', 'quizzes', 'assignments']),
            'batches',
        ]) // Load relasi untuk tag dan data tambahan (counts untuk lessons_count)
        ->withCount('enrollments'); // Menghitung jumlah yang daftar

    // 1. Filter Search
    if (!empty($this->search)) {
        $query->where('title', 'like', '%' . $this->search . '%')
              ->orWhere('description', 'like', '%' . $this->search . '%');
    }

    // 2. Filter Topic (Multi-select)
    if (!empty($this->selectedTopics)) {
        $query->whereHas('courses', function ($q) {
            $q->where(function ($subQ) {
                // Loop setiap topik yang dipilih user, cari course yang memiliki topik tersebut
                foreach ($this->selectedTopics as $selectedTopic) {
                    $subQ->orWhereJsonContains('topics', $selectedTopic);
                }
            });
        });
    }

    // 3. Filter Level (Dari tabel courses)
    if (!empty($this->level)) {
        $query->whereHas('courses', function ($q) {
            $q->where('level', strtolower($this->level));
        });
    }

    // 4. Filter Type Program
    if (!empty($this->type)) {
        $dbType = $this->type === 'Program Mandiri' ? 'self_paced' : 'cohort';
        $query->where('type', $dbType);
    }

    // 5. Filter Price
    if ($this->price === 'Free') {
        $query->where('price', 0);
    } elseif ($this->price === 'Premium') {
        $query->where('price', '>', 0);
    }

    // 6. Filter Rating
    if (!empty($this->rating)) {
        $query->where('rating_avg', '>=', floatval($this->rating));
    }

    // 7. Sorting
    match ($this->sortBy) {
        'newest' => $query->orderByDesc('created_at'),
        'title_asc' => $query->orderBy('title', 'asc'),
        'title_desc' => $query->orderByDesc('title'),
        'rating_desc' => $query->orderByDesc('rating_avg'),
        'rating_asc' => $query->orderBy('rating_avg', 'asc'),
        'price_asc' => $query->orderBy('price', 'asc'),
        'price_desc' => $query->orderByDesc('price'),
        default => $query->orderByDesc('enrollments_count'), // popular
    };

    $programs = $query->paginate(6);

    // Ambil semua array topics dari database, lebur, dan ambil yang unik
    $availableTopics = Course::whereNotNull('topics')
                             ->pluck('topics')
                             ->flatten()
                             ->unique()
                             ->values()
                             ->toArray();

    // Fallback jika database masih kosong
    if (empty($availableTopics)) {
        $availableTopics = ['Programming', 'Business', 'Marketing', 'Design'];
    }

    return [
        'programs' => $programs,
        'availableTopics' => $availableTopics,
    ];
});

?>

<div>
    <div class="-mt-6 -mx-6 bg-text-pastel pt-24 pb-20 px-6 flex flex-col items-center text-center text-white mb-8">
        <h2 class="text-4xl font-bold font-heading mb-2">Find Your Next Skills!</h2>
        <p class="text-wite font-caption mb-8 max-w-lg">Masters new skills with our top-rated instructors and structured lessons</p>
        
        <div class="w-full max-w-2xl relative mb-6">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input wire:model.live.debounce.300ms="search" wire:keyup="$set('paginators.page', 1)" type="text" class="block w-full pl-12 pr-24 py-3.5 rounded-2xl border-0 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 shadow-sm" placeholder="What do you want to learn today ?">
            <button class="absolute inset-y-1.5 right-1.5 bg-primary font-caption hover:bg-blue-600 text-white px-6 rounded-xl text-sm font-semibold transition">
                Search
            </button>
        </div>

        <div class="flex flex-wrap justify-center items-center gap-3 text-sm">
            <span class="text-blue-200">Popular Topic :</span>
            @foreach(array_slice($availableTopics, 0, 3) as $popTopic)
            <button wire:click="toggleTopic('{{ $popTopic }}')" class="flex items-center px-4 py-1.5 rounded-full border {{ in_array($popTopic, $selectedTopics) ? 'border-white bg-white/30 font-bold' : 'border-blue-400 bg-white/10 hover:bg-white/20' }} transition focus:outline-none">
                {{ $popTopic }}
            </button>
            @endforeach
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        @if($search)
        <div class="mb-4 bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-center justify-between shadow-sm animate-fade-in">
            <div class="flex items-center text-slate-700">
                <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <span>Search results for: <span class="font-bold text-blue-900">"{{ $search }}"</span></span>
            </div>
            <button wire:click="$set('search', '')" class="text-slate-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-full transition focus:outline-none" title="Clear search">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        @endif

        <div class="py-4 mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <h3 class="font-bold text-lg text-blue-900">Filter <span class="text-sm font-normal text-slate-500 ml-2">({{ $programs->total() }} results)</span></h3>
                    <button wire:click="resetFilters" class="text-sm text-blue-600 hover:text-blue-800 focus:outline-none transition">Reset all</button>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 mt-4 border-b border-slate-200 pb-6">
                
                <div class="flex items-center gap-2" x-data="{ open: false }">
                    <label class="text-sm text-slate-500 w-16 flex-shrink-0">Topic</label>
                    <div class="relative w-full">
                        <button @click="open = !open" type="button" class="flex justify-between items-center w-full bg-white border border-slate-200 rounded-md text-sm text-slate-600 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2 px-3 focus:outline-none text-left">
                            <span class="truncate pr-4">
                                {{ count($selectedTopics) > 0 ? implode(', ', $selectedTopics) : 'All Topic' }}
                            </span>
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-cloak style="display: none;" class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-md shadow-lg py-1 max-h-60 overflow-auto">
                            @foreach($availableTopics as $item)
                            <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                                <input type="checkbox" wire:model.live="selectedTopics" value="{{ $item }}" class="rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-500 mr-3 w-4 h-4">
                                {{ $item }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-500 w-16">Level</label>
                    <select wire:model.live="level" class="form-select block w-full border-slate-200 rounded-md text-sm text-slate-600 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2 px-3">
                        <option value="">All Level</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-500 w-16">Type</label>
                    <select wire:model.live="type" class="form-select block w-full border-slate-200 rounded-md text-sm text-slate-600 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2 px-3">
                        <option value="">All Type</option>
                        <option value="Program Mandiri">Program Mandiri</option>
                        <option value="Program Angkatan">Program Angkatan</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-500 w-16">Price</label>
                    <select wire:model.live="price" class="form-select block w-full border-slate-200 rounded-md text-sm text-slate-600 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2 px-3">
                        <option value="">All Price</option>
                        <option value="Free">Free</option>
                        <option value="Premium">Premium</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-500 w-16">Rating</label>
                    <select wire:model.live="rating" class="form-select block w-full border-slate-200 rounded-md text-sm text-slate-600 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2 px-3">
                        <option value="">All Rating</option>
                        <option value="4.5">4.5 & up</option>
                        <option value="4.0">4.0 & up</option>
                    </select>
                </div>
                
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-500 w-16">Sort by</label>
                    <select wire:model.live="sortBy" class="form-select block w-full border-slate-200 rounded-md text-sm text-slate-600 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2 px-3 bg-slate-50">
                        <option value="popular">Most Popular</option>
                        <option value="newest">Newest Batch</option>
                        <option value="title_asc">Title (A to Z)</option>
                        <option value="title_desc">Title (Z to A)</option>
                        <option value="rating_desc">Highest Rating</option>
                        <option value="rating_asc">Lowest Rating</option>
                        <option value="price_asc">Lowest Price</option>
                        <option value="price_desc">Highest Price</option>
                    </select>
                </div>

            </div>
        </div>

        <div class="pb-8 mt-4">
            @if($programs->isEmpty())
                <div class="py-12 text-center text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-lg font-medium">No programs found</p>
                    <p class="text-sm">Try adjusting your filters or search query.</p>
                </div>
            @else
                <div class="grid grid-cols-1 min-[600px]:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-16">
                    @foreach($programs as $program)
                    <div class="bg-white mt-2 w-full max-w-[360px] mx-auto rounded-2xl shadow-sm border border-slate-100 flex flex-col pt-4 relative hover:-translate-y-2 duration-300 hover:shadow-[0_20px_40px_rgba(59,130,246,0.15)]">
                        
                        <div class="absolute -top-0 left-1/2 transform -translate-x-1/2 bg-blue-50 text-blue-700 font-bold text-[13px] uppercase tracking-wider px-5 py-1.5 rounded-b-lg border border-blue-200 shadow-[0_4px_10px_rgba(59,130,246,0.25)] z-10 whitespace-nowrap">
                            {{ $program->type === 'self_paced' ? 'Program Mandiri' : 'Program Angkatan' }}
                        </div>

                        <a href="{{ route('student.program.detail', $program->id) }}" wire:navigate class="mx-4 mt-2 h-40 bg-[#9ea7d3] rounded-xl flex-shrink-0 relative overflow-hidden group block">
                            <div class="absolute inset-0 bg-blue-900/0 group-hover:bg-blue-900/10 transition duration-300"></div>
                        </a>

                        <div class="p-5 flex flex-col flex-1">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm font-semibold text-blue-600">
                                    {{ optional($program->courses->first())->level ?? 'General' }}
                                </span>
                                <div class="flex items-center text-sm font-bold text-slate-700">
                                    <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                    {{ number_format($program->rating_avg ?? 0, 1) }}
                                </div>
                            </div>

                            <a href="{{ route('student.program.detail', $program->id) }}" wire:navigate>
                                <h4 class="text-lg font-bold text-blue-900 leading-tight mb-3 line-clamp-2 hover:text-blue-600 cursor-pointer">
                                    {{ $program->title }}
                                </h4>
                            </a>

                            <div class="flex flex-wrap gap-2 mb-4">
                                @foreach($program->topics as $tag)
                                    <span class="px-2.5 py-0.5 rounded border border-blue-200 text-xs text-blue-600 bg-blue-50/50">{{ $tag }}</span>
                                @endforeach
                            </div>

                            <p class="text-xs text-slate-500 mb-4 line-clamp-3">
                                {{ $program->short_description ?? $program->description }}
                            </p>

                            <div class="flex flex-col gap-1 mt-auto mb-4 text-xs text-slate-500">
                                <span>{{ $program->lessons_count }} Lessons</span>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    {{ number_format($program->enrollments_count, 0, ',', '.') }} Learner Enrolled
                                </div>
                            </div>

                            <div class="flex justify-between items-center pt-4 border-t border-slate-100">
                                <span class="text-lg font-bold text-blue-800">
                                    {{ $program->price == 0 ? 'Free' : 'Rp. ' . number_format($program->price, 0, ',', '.') }}
                                </span>
                                <button class="text-red-400 hover:text-red-500 focus:outline-none transition">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-12 mb-8">
                {{ $programs->links() }}
            </div>
        </div>
    </div>
</div>
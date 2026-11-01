<div>
    {{-- MENGIRIM TOMBOL ACTIONS KE HEADER GLOBAL WORKSPACE --}}
    <x-slot:actions>
        <div class="flex items-center gap-3">
            <div class="hidden md:flex text-xs text-slate-400 mr-2">
                <span class="flex items-center gap-1 font-medium">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Auto-saved
                </span>
            </div>

            @if($course->status === \App\Enums\CourseStatus::DRAFT || $course->status === \App\Enums\CourseStatus::REJECTED)
                <button wire:click="submitForReview" wire:loading.attr="disabled" wire:confirm="Pastikan seluruh materi sudah siap. Ajukan sekarang?" class="inline-flex items-center px-5 py-2.5 text-sm font-bold text-white transition-all transform bg-indigo-600 rounded-xl shadow-md hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                    <span wire:loading.remove wire:target="submitForReview">Ajukan Review</span>
                    <span wire:loading wire:target="submitForReview" class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Memproses...
                    </span>
                </button>
            @elseif($course->status === \App\Enums\CourseStatus::PENDING)
                <button disabled class="inline-flex items-center px-5 py-2.5 text-sm font-bold text-slate-500 bg-slate-100 border border-slate-200 rounded-xl cursor-not-allowed">
                    <svg class="w-4 h-4 mr-2 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Menunggu Review
                </button>
            @endif
        </div>
    </x-slot:actions>

    {{-- KONTEN UTAMA BUILDER --}}
    <div class="max-w-5xl mx-auto space-y-6 pb-20">

        {{-- Flash Messages --}}
        @if (session()->has('message'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-xl shadow-sm flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div class="font-medium">{{ session('message') }}</div>
            </div>
        @endif
        @if (session()->has('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl shadow-sm flex items-start gap-3 animate-fade-in-down">
                <svg class="w-5 h-5 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div class="font-medium">{{ session('error') }}</div>
            </div>
        @endif

        {{-- AREA BUILDER --}}
        <div class="space-y-6" x-data x-init="Sortable.create($refs.sectionsList, { handle: '.section-drag-handle', animation: 200, ghostClass: 'opacity-50', onEnd: function (evt) { $wire.updateSectionOrder(this.toArray()) } })">
            <div x-ref="sectionsList" class="space-y-6">
                @forelse($sections as $section)
                    <div wire:key="section-{{ $section->id }}" data-id="{{ $section->id }}" x-data="{ expanded: true }" class="bg-white border border-slate-200 shadow-sm rounded-2xl overflow-hidden transition-shadow hover:shadow-md group/section">

                        {{-- Section Header --}}
                        <div class="flex items-center justify-between px-5 py-4 bg-slate-50/80 border-b border-slate-100 select-none">
                            <div class="flex items-center flex-1 gap-3">
                                <div class="cursor-grab active:cursor-grabbing text-slate-300 hover:text-slate-600 p-1 section-drag-handle transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                                </div>
                                <button @click="expanded = !expanded" class="text-slate-400 hover:text-indigo-600 transition-transform duration-200" :class="{ '-rotate-90': !expanded }">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                                <div class="flex-1">
                                    @if($editingSectionId === $section->id)
                                        <div class="flex items-center gap-2" x-trap.noscroll="true">
                                            <input type="text" wire:model="tempSectionTitle" class="w-full max-w-md px-0 py-0 text-base font-bold bg-transparent border-0 border-b-2 border-indigo-500 focus:ring-0 placeholder-slate-300" wire:keydown.enter="saveSectionTitle" wire:keydown.escape="$set('editingSectionId', null)" autofocus>
                                            <button wire:click="saveSectionTitle" class="text-emerald-600 hover:text-emerald-700"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg></button>
                                        </div>
                                    @else
                                        <div class="group/title flex items-center gap-2 cursor-pointer" wire:click="startEditSection('{{ $section->id }}', '{{ $section->title }}')">
                                            <h3 class="text-base font-bold text-slate-800">{{ $section->title }}</h3>
                                            <svg class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover/title:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center opacity-0 group-hover/section:opacity-100 transition-opacity duration-200">
                                <span class="text-xs text-slate-400 mr-3 font-medium">{{ $section->lessons->count() }} Item</span>
                                <button wire:click="deleteSection('{{ $section->id }}')" wire:confirm="Hapus bab ini?" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Section Content List --}}
                        <div x-show="expanded" x-collapse>
                            <ul class="divide-y divide-slate-100" wire:ignore.self x-init="Sortable.create($el, { group: 'lessons', handle: '.lesson-drag-handle', animation: 150, ghostClass: 'bg-indigo-50', onEnd: function (evt) { $wire.updateItemOrder(this.toArray()) } })">
                                @foreach($section->lessons as $lesson)
                                    <li data-id="{{ $lesson->id }}" wire:key="lesson-{{ $lesson->id }}" class="flex items-center justify-between p-4 pl-5 hover:bg-slate-50 transition-colors group/lesson">
                                        <div class="flex items-center flex-1 gap-4 overflow-hidden">
                                            <div class="lesson-drag-handle cursor-grab active:cursor-grabbing text-slate-300 hover:text-indigo-500">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                                            </div>
                                            <div class="shrink-0">
                                                @php
                                                    $isZoom = ($lesson->item_type == 'material' && optional($lesson->content)->type == 'live_session');
                                                    $colors = ['material' => 'bg-sky-100 text-sky-600', 'quiz' => 'bg-purple-100 text-purple-600', 'assignment' => 'bg-orange-100 text-orange-600'];
                                                    if($isZoom) $colors['material'] = 'bg-blue-100 text-blue-700';

                                                    $icons = [
                                                        'material' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                                        'quiz' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                                        'assignment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />'
                                                    ];
                                                    if($isZoom) $icons['material'] = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>';
                                                @endphp
                                                <div class="flex items-center justify-center w-10 h-10 rounded-xl {{ $colors[$lesson->item_type] ?? 'bg-slate-100' }}">
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">{!! $icons[$lesson->item_type] ?? '' !!}</svg>
                                                </div>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-baseline justify-between mb-0.5">
                                                    <p class="text-sm font-bold text-slate-700 truncate group-hover/lesson:text-indigo-600 transition-colors flex items-center gap-2">
                                                        {{ $lesson->content->title ?? 'Untitled Content' }}
                                                        @if($isZoom) <span class="px-1.5 py-0.5 text-[9px] bg-blue-500 text-white rounded-md font-bold uppercase tracking-wide">Live</span> @endif
                                                    </p>
                                                    <div class="flex items-center gap-1 opacity-0 group-hover/lesson:opacity-100 transition-opacity">
                                                        @if($lesson->content)
                                                            <a href="{{ route('instructor.'.$lesson->item_type.'.edit', $lesson->content->id) }}" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                            </a>
                                                        @endif
                                                        <button wire:click="deleteLesson('{{ $lesson->id }}')" wire:confirm="Hapus item ini?" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    @if(($lesson->content->status ?? 'draft') == 'published')
                                                        <span class="flex items-center gap-1 text-[10px] uppercase font-bold text-emerald-600"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Published</span>
                                                    @else
                                                        <span class="flex items-center gap-1 text-[10px] uppercase font-bold text-slate-400"><span class="w-1.5 h-1.5 bg-slate-300 rounded-full"></span> Draft</span>
                                                    @endif

                                                    @if($isZoom)
                                                        <span class="text-[10px] text-slate-400 font-medium">• Jadwal: {{ $lesson->content->start_time ? \Carbon\Carbon::parse($lesson->content->start_time)->format('d M Y H:i') : 'Belum diatur' }}</span>
                                                    @elseif($lesson->item_type == 'quiz')
                                                        <span class="text-[10px] text-slate-400 font-medium">• {{ $lesson->content->duration_minutes ?? 0 }} Min</span>
                                                    @elseif($lesson->item_type == 'assignment')
                                                        <span class="text-[10px] text-slate-400 font-medium">• Deadline {{ $lesson->content->deadline_days ?? 0 }} Hari</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Footer Section (Add Content) --}}
                            <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between overflow-visible relative">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Add New Lesson Item</span>
                                <div class="flex items-center gap-2">

                                    {{-- Dropdown Materi --}}
                                    <div x-data="{ open: false }">
                                        <button x-ref="dropdownButton"
                                                @click="open = !open"
                                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:border-sky-300 hover:text-sky-600 hover:shadow-sm transition-all">
                                            <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Materi
                                            <svg class="w-3 h-3 ml-1 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="open"
                                                 @click.away="open = false"
                                                 x-anchor.bottom-start.offset.4="$refs.dropdownButton"
                                                 x-transition.opacity
                                                 class="w-48 bg-white border border-slate-100 rounded-xl shadow-xl z-[100] overflow-hidden"
                                                 style="display: none;">
                                                <div class="py-1 flex flex-col">
                                                    <button wire:click="addMaterial('{{ $section->id }}', 'google_drive_file')" @click="open = false" class="text-left px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 transition-colors">📂 Dokumen</button>
                                                    <button wire:click="addMaterial('{{ $section->id }}', 'article')" @click="open = false" class="text-left px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 transition-colors">📄 Artikel</button>
                                                    <button wire:click="addMaterial('{{ $section->id }}', 'video')" @click="open = false" class="text-left px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 transition-colors">▶️ Youtube</button>
                                                    <button wire:click="addMaterial('{{ $section->id }}', 'google_drive_video')" @click="open = false" class="text-left px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 transition-colors">☁️ Upload Video</button>
                                                    <div class="border-t border-slate-100 my-1"></div>
                                                    <button wire:click="addMaterial('{{ $section->id }}', 'live_session')" @click="open = false" class="text-left px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 transition-colors">🎥 Live Session</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Kuis & Tugas --}}
                                    <button wire:click="addLesson('{{ $section->id }}', 'quiz')" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:border-purple-300 hover:text-purple-600 hover:shadow-sm transition-all">
                                        <svg class="w-3.5 h-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Kuis
                                    </button>
                                    <button wire:click="addLesson('{{ $section->id }}', 'assignment')" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:border-orange-300 hover:text-orange-600 hover:shadow-sm transition-all">
                                        <svg class="w-3.5 h-3.5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        Tugas
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-16 bg-white border-2 border-dashed border-slate-300 rounded-3xl text-center">
                        <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Kurikulum Kosong</h3>
                        <p class="text-slate-500 mb-6 max-w-sm mt-2">Mulai rancang alur pembelajaran Anda dengan membuat bab pertama.</p>
                        <button wire:click="addSection" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition-all hover:-translate-y-0.5">
                            Buat Bab Pertama
                        </button>
                    </div>
                @endforelse
            </div>

            @if($sections->isNotEmpty())
                <button wire:click="addSection" class="group w-full py-5 border-2 border-dashed border-slate-300 rounded-2xl flex items-center justify-center text-slate-500 hover:border-indigo-500 hover:text-indigo-600 hover:bg-indigo-50/50 transition-all duration-300">
                    <span class="flex items-center font-bold">
                        <svg class="w-5 h-5 mr-2 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Tambah Bab Baru
                    </span>
                </button>
            @endif
        </div>
    </div>
</div>

<div class="flex flex-col h-full bg-white border-r border-slate-100 relative">

    {{-- HEADER SIDEBAR --}}
    <div class="p-6 shrink-0 border-b border-slate-50">

        <h3 class="font-bold text-slate-900 text-sm leading-snug line-clamp-2 mb-4" title="{{ $course->title ?? '' }}">
            {{ $course->title ?? 'Course Content' }}
        </h3>

        {{-- PROGRESS BAR --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between text-[10px] uppercase tracking-wider font-bold">
                <span class="text-indigo-600">{{ $overallProgress ?? 0 }}% Selesai</span>
                <span class="text-slate-400">{{ count($completedLessonIds ?? []) }}/{{ $totalLessons ?? 0 }} Materi</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-700 ease-out"
                     style="width: {{ $overallProgress ?? 0 }}%"></div>
            </div>
        </div>
    </div>

    @php
        $allLessonIds = $course->sections->flatMap->lessons->pluck('id')->toArray();
        $isSequential = $course->is_sequential ?? false;
    @endphp

    {{-- LIST MATERI (ACCORDION) --}}
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-1 custom-scrollbar"
         x-data="{ activeSection: @entangle('activeSectionId') }">

        @if(isset($course->sections))
            @foreach($course->sections as $section)
                <div class="overflow-hidden rounded-2xl transition-colors"
                     wire:key="section-{{ $section->id }}"
                     :class="activeSection === '{{ $section->id }}' ? 'bg-slate-50' : 'bg-transparent'">

                    {{-- Section Toggle Button --}}
                    <button @click="activeSection = (activeSection === '{{ $section->id }}' ? null : '{{ $section->id }}')"
                            class="w-full py-3 px-4 flex items-center justify-between text-left group transition-all hover:bg-slate-50 rounded-2xl">
                        <div class="flex-1 min-w-0 pr-2">
                            <span class="block text-[10px] font-black uppercase text-slate-400 group-hover:text-indigo-500 transition-colors">
                                Section {{ $loop->iteration }}
                            </span>
                            <span class="font-bold text-sm text-slate-700 truncate block group-hover:text-slate-900">
                                {{ $section->title }}
                            </span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transform transition-transform duration-300"
                             :class="{'rotate-180': activeSection === '{{ $section->id }}'}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Lessons List --}}
                    <div x-show="activeSection === '{{ $section->id }}'"
                         x-collapse
                         x-cloak>
                        <div class="space-y-1 px-2 pb-3 pt-1">
                            @foreach($section->lessons as $lesson)
                                @php
                                    $isCompleted = in_array($lesson->id, $completedLessonIds ?? []);
                                    $isActive = !($isForumActive ?? false) && !($isTestimonialActive ?? false) && isset($currentLesson) && $currentLesson->id === $lesson->id;
                                    $isLocked = false;

                                    if ($isSequential) {
                                        $currentIndex = array_search($lesson->id, $allLessonIds);
                                        if ($currentIndex !== false && $currentIndex > 0) {
                                            $prevLessonId = $allLessonIds[$currentIndex - 1];
                                            if (!in_array($prevLessonId, $completedLessonIds ?? [])) {
                                                $isLocked = true;
                                            }
                                        }
                                    }
                                @endphp

                                <button
                                    @if(!$isLocked) wire:click="changeLesson('{{ $lesson->id }}')" @endif
                                    wire:key="lesson-btn-{{ $lesson->id }}"
                                    class="w-full flex items-center gap-3 p-2.5 rounded-xl transition-all text-left text-sm group
                                    {{ $isActive
                                        ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200'
                                        : ($isLocked
                                            ? 'cursor-not-allowed opacity-60 bg-transparent'
                                            : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:shadow-sm cursor-pointer')
                                    }}">

                                    {{-- Icon Status --}}
                                    <div class="shrink-0">
                                        @if($isActive)
                                            <div class="w-6 h-6 rounded-full border-2 border-indigo-600 flex items-center justify-center bg-white">
                                                <div class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></div>
                                            </div>
                                        @elseif($isCompleted)
                                            <div class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center text-white shadow-sm border-2 border-emerald-500">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                        @elseif($isLocked)
                                            <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 border border-slate-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            </div>
                                        @else
                                            <div class="w-6 h-6 rounded-full border-2 border-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-400 group-hover:border-slate-300 group-hover:text-slate-500 bg-white">
                                                {{ $loop->iteration }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Title --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="truncate font-medium {{ $isActive ? 'font-bold' : '' }} {{ $isLocked ? 'text-slate-400' : '' }}">
                                            {{ $lesson->material->title ?? $lesson->quiz->title ?? 'Untitled Lesson' }}
                                        </p>
                                    </div>

                                    @if($isLocked)
                                        <div class="shrink-0 text-slate-300">
                                             <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 15c-.55 0-1-.45-1-1v-4c0-.55.45-1 1-1s1 .45 1 1v4c0 .55-.45 1-1 1zm1-8h-2V7h2v2z"/></svg>
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        {{-- NEW: VIRTUAL LESSON - EVALUASI & SERTIFIKAT --}}
        <div class="mt-4 border-t border-slate-100 pt-4 px-2">
            <button wire:click="openTestimonial"
                    @if($overallProgress < 100) disabled @endif
                    class="w-full flex items-center justify-between p-3 rounded-xl transition-all border-2 text-sm font-bold
                    {{ ($isTestimonialActive ?? false)
                        ? 'bg-amber-50 border-amber-400 text-amber-700 shadow-sm'
                        : ($overallProgress >= 100
                            ? 'bg-white border-amber-200 text-amber-600 hover:bg-amber-50 hover:border-amber-300 shadow-sm'
                            : 'bg-slate-50 border-slate-100 text-slate-400 cursor-not-allowed opacity-70')
                    }}">

                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center {{ $overallProgress >= 100 ? 'bg-amber-100 text-amber-600' : 'bg-slate-200 text-slate-400' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <span class="text-left">
                        <span class="block">Evaluasi Akhir</span>
                        @if($overallProgress < 100)
                            <span class="block text-[10px] font-medium text-slate-400">Terkunci (Selesaikan Materi)</span>
                        @else
                            <span class="block text-[10px] font-medium text-amber-500">Buka Sertifikat</span>
                        @endif
                    </span>
                </div>

                @if($overallProgress < 100)
                    <svg class="w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 15c-.55 0-1-.45-1-1v-4c0-.55.45-1 1-1s1 .45 1 1v4c0 .55-.45 1-1 1zm1-8h-2V7h2v2z"/></svg>
                @elseif($hasTestimonial)
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                @endif
            </button>
        </div>
    </div>

    {{-- FOOTER SIDEBAR (FORUM BUTTON) --}}
    <div class="p-4 shrink-0 border-t border-slate-50 bg-white z-10">
        <button wire:click="openForum"
                class="w-full flex items-center gap-3 p-4 rounded-2xl transition-all border-2 group
                {{ ($isForumActive ?? false)
                    ? 'bg-indigo-600 border-indigo-600 text-white shadow-xl shadow-indigo-200'
                    : 'bg-white border-slate-100 text-slate-600 hover:border-indigo-100 hover:bg-indigo-50 hover:text-indigo-700'
                }}">
            <div class="{{ ($isForumActive ?? false) ? 'text-white' : 'text-slate-400 group-hover:text-indigo-600' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
            </div>
            <div class="text-left">
                <p class="text-sm font-black uppercase tracking-wide">Forum Diskusi</p>
                @if(!($isForumActive ?? false))
                    <p class="text-[10px] opacity-70 font-medium">Tanya jawab materi</p>
                @endif
            </div>
        </button>
    </div>
</div>

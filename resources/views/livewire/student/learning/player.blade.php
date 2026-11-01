<div class="flex flex-col h-screen bg-slate-50 overflow-hidden"
     x-data="{
         sidebarOpen: window.innerWidth >= 1024 ? true : false,
         init() {
             if (localStorage.getItem('playerSidebarOpen') !== null) {
                 this.sidebarOpen = JSON.parse(localStorage.getItem('playerSidebarOpen'));
             }
             this.$watch('sidebarOpen', val => localStorage.setItem('playerSidebarOpen', val));
         }
     }"
     x-init="init()">

    <header class="flex h-16 shrink-0 items-center justify-between bg-white border-b border-slate-200 px-4 lg:px-6 shadow-sm z-50 relative">
        <div class="flex items-center gap-4">
            <button @click="sidebarOpen = !sidebarOpen"
                    class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all focus:outline-none active:scale-95">
                <svg class="w-6 h-6 transform transition-transform duration-300"
                     :class="{'rotate-180': !sidebarOpen}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            <div class="flex items-center space-x-3 border-l pl-4 border-slate-200">
                <h1 class="text-base font-bold text-slate-800 truncate max-w-[200px] sm:max-w-md">
                    {{ $course->title ?? 'Learning' }}
                </h1>
            </div>
        </div>

        <a href="{{ $backUrl ?? route('student.my-courses') }}" class="inline-flex items-center px-4 py-2 bg-slate-100 text-slate-600 hover:bg-red-50 hover:text-red-600 text-sm font-bold rounded-xl transition-all border border-transparent hover:border-red-100">
            <span class="hidden sm:inline mr-2">Exit</span>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
        </a>
    </header>

    <div class="flex flex-1 overflow-hidden relative">

        {{-- Loading bar: muncul hanya saat Livewire memproses, memberi rasa responsif --}}
        <div wire:loading.delay.shortest class="absolute top-0 left-0 right-0 z-[60] h-0.5 overflow-hidden bg-indigo-100/60">
            <div class="h-full w-1/3 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-500 animate-[loading-slide_1s_ease-in-out_infinite] rounded-full"></div>
        </div>

        <aside class="fixed inset-y-0 left-0 top-16 z-40 bg-white border-r border-slate-200 overflow-hidden transition-all duration-300 ease-in-out lg:static lg:top-0"
               :class="sidebarOpen ? 'w-80 translate-x-0' : 'w-0 -translate-x-full lg:w-0 lg:translate-x-0 lg:border-none'">
            <div class="h-full w-80 flex flex-col bg-white">
                @include('livewire.student.learning.partials.sidebar')
            </div>
        </aside>

        <div x-show="sidebarOpen"
             x-cloak
             @click="sidebarOpen = false"
             x-transition.opacity.duration.300ms
             class="fixed inset-0 top-16 bg-slate-900/50 backdrop-blur-sm z-30 lg:hidden">
        </div>

        <main class="flex-1 overflow-y-auto bg-slate-50 relative w-full scroll-smooth"
              id="main-content"
              @scroll.debounce.1000ms="
                  if($el.scrollTop + $el.clientHeight >= $el.scrollHeight * 0.9) {
                      @this.completeByScroll();
                  }
              ">

            <div
                wire:key="content-{{ $currentLesson?->id ?? 'none' }}-{{ $isForumActive ? 'forum' : ($isTestimonialActive ? 'testimonial' : 'lesson') }}-{{ $contentType }}"
                class="content-enter w-full max-w-5xl mx-auto p-4 lg:p-8 space-y-6 pb-20">

                @if($isForumActive)
                    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden h-[calc(100vh-9rem)]">
                        <livewire:student.discussion.course-discussion :course="$course" />
                    </div>

                @elseif($isTestimonialActive)
                    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                        <div class="bg-gradient-to-br from-indigo-900 via-slate-900 to-black p-8 sm:p-12 text-white text-center relative overflow-hidden">
                            <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                            <div class="relative z-10">
                                <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-6 ring-1 ring-white/20">
                                    <span class="text-4xl">🎓</span>
                                </div>
                                <h2 class="text-3xl sm:text-4xl font-black mb-3">Selamat, Anda Telah Selesai!</h2>
                                <p class="text-indigo-200 font-medium text-lg max-w-xl mx-auto">Satu langkah lagi untuk mendapatkan sertifikat kelulusan. Bantu kami meningkatkan kualitas kelas dengan mengisi evaluasi singkat ini.</p>
                            </div>
                        </div>

                        <div class="p-6 sm:p-10 lg:p-14">
                            @if($hasTestimonial)
                                <div class="text-center py-10 max-w-md mx-auto">
                                    <div class="w-24 h-24 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-emerald-50/50">
                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <h3 class="text-2xl font-black text-slate-800 mb-3">Evaluasi Terkirim</h3>
                                    <p class="text-slate-500 font-medium mb-10 text-lg">Terima kasih atas ulasan Anda. Sertifikat Anda telah diterbitkan dan siap diunduh.</p>
                                    <a href="{{ route('student.certificate') }}" wire:navigate class="inline-flex justify-center items-center gap-2 w-full px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition-all text-lg">
                                        Lihat Sertifikat Saya
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                    </a>
                                </div>
                            @else
                                <div class="max-w-3xl mx-auto space-y-10">
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3 mb-6">1. Penilaian Kualitas (1-5 Bintang)</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 text-center" x-data="{ tempRating: @entangle('ratingMaterial'), setRating(val) { this.tempRating = val; $wire.set('ratingMaterial', val) } }">
                                                <p class="text-sm font-bold text-slate-600 mb-3 uppercase tracking-wider">Kualitas Materi</p>
                                                <div class="flex justify-center gap-1">
                                                    <template x-for="i in 5">
                                                        <button @click="setRating(i)" @mouseover="tempRating = i" @mouseleave="tempRating = $wire.ratingMaterial" class="transition-transform hover:scale-110 focus:outline-none">
                                                            <svg class="w-8 h-8 transition-colors" :class="i <= tempRating ? 'text-amber-400' : 'text-slate-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                        </button>
                                                    </template>
                                                </div>
                                                @error('ratingMaterial') <span class="text-rose-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 text-center" x-data="{ tempRating: @entangle('ratingInstructor'), setRating(val) { this.tempRating = val; $wire.set('ratingInstructor', val) } }">
                                                <p class="text-sm font-bold text-slate-600 mb-3 uppercase tracking-wider">Instruktur</p>
                                                <div class="flex justify-center gap-1">
                                                    <template x-for="i in 5">
                                                        <button @click="setRating(i)" @mouseover="tempRating = i" @mouseleave="tempRating = $wire.ratingInstructor" class="transition-transform hover:scale-110 focus:outline-none">
                                                            <svg class="w-8 h-8 transition-colors" :class="i <= tempRating ? 'text-amber-400' : 'text-slate-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                        </button>
                                                    </template>
                                                </div>
                                                @error('ratingInstructor') <span class="text-rose-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 text-center" x-data="{ tempRating: @entangle('ratingPlatform'), setRating(val) { this.tempRating = val; $wire.set('ratingPlatform', val) } }">
                                                <p class="text-sm font-bold text-slate-600 mb-3 uppercase tracking-wider">Aplikasi (UI/UX)</p>
                                                <div class="flex justify-center gap-1">
                                                    <template x-for="i in 5">
                                                        <button @click="setRating(i)" @mouseover="tempRating = i" @mouseleave="tempRating = $wire.ratingPlatform" class="transition-transform hover:scale-110 focus:outline-none">
                                                            <svg class="w-8 h-8 transition-colors" :class="i <= tempRating ? 'text-amber-400' : 'text-slate-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                        </button>
                                                    </template>
                                                </div>
                                                @error('ratingPlatform') <span class="text-rose-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3 mb-6">2. Ulasan Anda</h3>
                                        <div class="space-y-6">
                                            <div>
                                                <label class="block text-sm font-bold text-slate-700 mb-2">
                                                    Apa hal terbaik yang Anda pelajari dari kelas ini? <span class="text-rose-500">*</span>
                                                </label>
                                                <textarea wire:model="publicTestimonial" rows="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white p-4 text-slate-700" placeholder="Ceritakan pengalaman Anda..."></textarea>
                                                @error('publicTestimonial') <span class="text-rose-500 text-xs font-medium">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-sm font-bold text-slate-700 mb-2">
                                                    Kritik & Saran Perbaikan <span class="text-slate-400 font-normal">(Opsional)</span>
                                                </label>
                                                <textarea wire:model="privateFeedback" rows="2" class="w-full rounded-xl border-slate-300 shadow-sm focus:ring-slate-500 focus:border-slate-500 bg-slate-50 p-4 text-slate-700" placeholder="Apa yang bisa kami perbaiki?"></textarea>
                                                @error('privateFeedback') <span class="text-rose-500 text-xs font-medium">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pt-6 border-t border-slate-100">
                                        <button wire:click="submitTestimonial" wire:loading.class="opacity-50 cursor-wait" wire:loading.delay.shortest class="w-full py-4 bg-slate-900 hover:bg-black text-white font-black text-lg rounded-2xl shadow-xl shadow-slate-900/20 transition-all flex justify-center items-center gap-3 transform hover:-translate-y-1">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Kirim Evaluasi & Terbitkan Sertifikat
                                        </button>
                                        <p class="text-center text-xs text-slate-400 mt-4 font-medium flex items-center justify-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            Data tersimpan dengan aman
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                @elseif($currentLesson)
                    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden flex flex-col transition-all duration-500">
                        <div class="p-6 sm:p-8 border-b border-slate-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 bg-gradient-to-r from-white to-slate-50/50">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">
                                        {{ str_replace('_', ' ', $contentType) }}
                                    </span>
                                    @if(isset($currentContent->duration_minutes))
                                        <span class="text-xs font-bold text-slate-400 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            {{ $currentContent->duration_minutes }} Menit
                                        </span>
                                    @elseif(isset($currentContent->duration) && $contentType === 'live_session')
                                        <span class="text-xs font-bold text-slate-400 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            {{ $currentContent->duration }} Menit
                                        </span>
                                    @endif
                                </div>
                                <h2 class="text-xl sm:text-2xl font-black text-slate-800 leading-tight">
                                    {{ $currentContent->title ?? 'Untitled Lesson' }}
                                </h2>
                            </div>

                            @if(!in_array($contentType, ['quiz', 'text', 'article', 'google_drive_video', 'google_drive_file', 'live_session']))
                                <button wire:click="markAsComplete" wire:loading.class="opacity-50 cursor-wait" wire:loading.delay.shortest
                                    class="group flex items-center gap-3 px-5 py-2.5 rounded-xl border-2 border-slate-100 hover:border-indigo-600 hover:bg-indigo-600 transition-all shrink-0">
                                    <div class="w-6 h-6 rounded-full border-2 border-slate-200 text-transparent group-hover:border-white group-hover:text-indigo-600 flex items-center justify-center transition-all bg-white">
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M0 11l2-2 5 5L18 3l2 2L7 18z"/></svg>
                                    </div>
                                    <span class="text-sm font-bold text-slate-500 group-hover:text-white">Tandai Selesai</span>
                                </button>
                            @endif
                        </div>

                        <div class="flex-1 min-h-[400px]">
                            @if($contentType === 'text' || $contentType === 'article')
                                <div class="p-6 sm:p-12 prose prose-lg prose-slate max-w-none prose-indigo prose-headings:font-black">
                                    @if(!in_array($currentLesson->id, $completedLessonIds))
                                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8 text-sm text-yellow-700">
                                            <span class="font-bold">Info:</span> Silakan baca materi ini sampai habis (scroll ke bawah) untuk menandai selesai secara otomatis.
                                        </div>
                                    @endif
                                    {!! $currentContent->body_text !!}
                                </div>

                            @elseif($contentType === 'video' && isset($currentContent->content_url))
                                <div class="bg-slate-900 w-full aspect-video overflow-hidden group relative">
                                    <iframe src="{{ $currentContent->embed_url }}" class="absolute inset-0 w-full h-full" allowfullscreen frameborder="0"></iframe>
                                </div>
                                @if(isset($currentContent->body_text))
                                    <div class="p-6 sm:p-10 prose prose-slate max-w-none prose-headings:font-black prose-p:leading-relaxed">
                                        {!! $currentContent->body_text !!}
                                    </div>
                                @endif

                            @elseif(in_array($contentType, ['google_drive_video', 'google_drive_file']))
                                <div class="p-6 sm:p-12 bg-white">
                                    <div class="max-w-2xl mx-auto text-center space-y-8 py-10">
                                        <div class="w-24 h-24 bg-indigo-50 rounded-[2rem] flex items-center justify-center mx-auto shadow-sm text-indigo-600">
                                            @if($contentType == 'google_drive_video')
                                                <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            @else
                                                <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            @endif
                                        </div>
                                        <div>
                                            <h3 class="text-2xl font-black text-slate-800 mb-2">Materi Cloud Diperlukan</h3>
                                            <p class="text-slate-500 font-medium">Klik tombol di bawah ini untuk membuka materi. Sistem akan otomatis menandai pelajaran ini selesai setelah Anda mengaksesnya.</p>
                                        </div>

                                        <a href="{{ $currentContent->content_url }}"
                                           target="_blank"
                                           wire:click="autoCompleteContent"
                                           class="inline-flex items-center gap-3 px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-bold transition-all shadow-lg shadow-indigo-200 hover:-translate-y-1">
                                            Buka Materi Sekarang
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        </a>
                                    </div>

                                    @if(isset($currentContent->body_text))
                                        <div class="mt-8 pt-8 border-t border-slate-100 prose prose-slate max-w-none">
                                            {!! $currentContent->body_text !!}
                                        </div>
                                    @endif
                                </div>

                            @elseif($contentType === 'live_session')
                                <div class="bg-slate-50 text-slate-800">
                                    <div>
                                        <div class="py-12 w-full flex flex-col items-center justify-center bg-white border-b border-slate-200">
                                            <div class="text-center space-y-6 max-w-2xl px-4">
                                                <div class="w-24 h-24 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center mx-auto ring-4 ring-blue-50/50 shadow-sm">
                                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                </div>
                                                <div>
                                                    <h3 class="text-3xl font-black text-slate-800">Sesi Live Meeting</h3>
                                                    <p class="text-slate-500 mt-2 font-medium">Pilih metode yang paling nyaman untuk Anda bergabung ke dalam kelas.</p>
                                                </div>

                                                @if($currentContent->zoom_url)
                                                    <div class="flex flex-col sm:flex-row flex-wrap items-center justify-center gap-3 mt-6">
                                                        @if($currentContent->meeting_platform === 'zoom' && $currentContent->meeting_id)
                                                            <a href="zoomus://zoom.us/join?confno={{ $currentContent->meeting_id }}&pwd={{ $currentContent->meeting_passcode }}"
                                                               wire:click="autoCompleteContent"
                                                               class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition-all shadow-lg shadow-blue-200">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                                                Buka di App Zoom
                                                            </a>
                                                        @endif

                                                        <a href="{{ $currentContent->zoom_url }}"
                                                           target="_blank"
                                                           wire:click="autoCompleteContent"
                                                           class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-4 bg-white border-2 border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl font-bold transition-all">
                                                            Gabung via Web Browser
                                                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                        </a>
                                                    </div>
                                                @else
                                                    <div class="px-4 py-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-600 text-sm font-semibold">
                                                        Tautan rapat belum disediakan oleh instruktur.
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="p-6 sm:p-10 max-w-4xl mx-auto">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                                <div>
                                                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-200 pb-2">Jadwal Sesi</h4>
                                                    <div class="flex items-start gap-4">
                                                        <div class="p-3 bg-white border border-slate-200 rounded-xl shadow-sm text-indigo-600 mt-1">
                                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                        </div>
                                                        <div>
                                                            <div class="font-black text-slate-800 text-lg">
                                                                {{ $currentContent->start_time ? $currentContent->start_time->format('l, d M Y') : 'Jadwal belum diatur' }}
                                                            </div>
                                                            @if($currentContent->start_time)
                                                                <div class="text-slate-500 font-medium flex items-center gap-2 mt-1">
                                                                    <span class="inline-block w-2 h-2 bg-emerald-400 rounded-full"></span>
                                                                    Pukul {{ $currentContent->start_time->format('H:i') }} WIB
                                                                    @if($currentContent->duration)
                                                                        <span class="text-slate-300">|</span> Durasi {{ $currentContent->duration }} Menit
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <div>
                                                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-200 pb-2">Kredensial Masuk</h4>
                                                    <div class="space-y-4">
                                                        <div>
                                                            <span class="block text-xs font-semibold text-slate-500 mb-1">Meeting ID / Kode Rapat</span>
                                                            <div class="font-mono font-bold text-slate-800 bg-white border border-slate-200 px-4 py-2.5 rounded-xl shadow-sm inline-flex items-center gap-3">
                                                                {{ $currentContent->meeting_id ?? 'Tidak memerlukan ID' }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <span class="block text-xs font-semibold text-slate-500 mb-1">Passcode / Kata Sandi</span>
                                                            <div class="font-mono font-bold text-slate-800 bg-white border border-slate-200 px-4 py-2.5 rounded-xl shadow-sm inline-flex items-center gap-3">
                                                                {{ $currentContent->meeting_passcode ?? 'Tidak memerlukan sandi' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            @if(isset($currentContent->body_text) && !empty(trim(strip_tags($currentContent->body_text))))
                                                <div class="mt-8 pt-8 border-t border-slate-200 prose prose-slate max-w-none">
                                                    <h4 class="text-lg font-black text-slate-800 mb-4">Catatan Tambahan</h4>
                                                    {!! $currentContent->body_text !!}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                            @elseif($contentType === 'quiz')
                                <div class="text-center py-20 px-6 bg-gradient-to-b from-white to-slate-50">
                                    <div class="w-24 h-24 bg-indigo-50 rounded-[2rem] flex items-center justify-center mx-auto mb-8 shadow-xl shadow-indigo-100/50">
                                        <span class="text-5xl">⚡</span>
                                    </div>
                                    <h2 class="text-3xl font-black text-slate-800 mb-3">Evaluasi Materi</h2>
                                    <p class="text-slate-500 mb-8 max-w-md mx-auto">
                                        Kerjakan kuis ini untuk menguji pemahaman Anda. Anda harus mencapai nilai minimal <strong>{{ $currentContent->passing_grade ?? 70 }}</strong> untuk melanjutkan.
                                    </p>
                                    @if($quizResult?->isPendingGrading())
                                        <p class="mb-4 font-semibold text-amber-700">Menunggu penilaian esai. Nilai akhir dan kelulusan belum tersedia.</p>
                                    @elseif($quizResult)
                                        <p class="mb-4 font-semibold">Nilai terakhir: {{ number_format($quizResult->score, 2) }} — {{ $quizResult->hasPassed() ? 'Lulus' : 'Belum lulus' }}</p>
                                    @endif
                                    <a href="{{ route('student.learning.quiz', ['course' => $course->id, 'quiz' => $currentLesson->quiz->id, 'batch' => $batch->id ?? null]) }}"
                                       wire:navigate
                                       class="inline-flex items-center justify-center bg-indigo-600 text-white px-10 py-4 rounded-2xl font-black shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:-translate-y-1 transition-all">
                                         {{ $quizResult ? 'Lihat Hasil / Percobaan Berikutnya' : 'Mulai Kuis Sekarang' }}
                                    </a>
                                </div>

                            @elseif($contentType === 'assignment')
                                <div class="p-6 sm:p-12">
                                     <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-6 mb-8">
                                         <h3 class="font-bold text-indigo-900 text-lg mb-2">Tugas: {{ $currentContent->title }}</h3>
                                         <div class="prose prose-sm text-indigo-800/80 mb-6">
                                             {!! $currentContent->description !!}
                                         </div>

                                         @if($existingSubmission)
                                             <div class="bg-white p-4 rounded-xl border border-emerald-200 mb-4">
                                                 <div class="flex items-start justify-between gap-4">
                                                     <div class="text-sm">
                                                         <p class="font-bold text-emerald-700 mb-1">
                                                             {{ $existingSubmission->grade !== null ? 'Sudah dinilai' : 'Tugas sudah dikirim' }}
                                                         </p>
                                                         <p class="text-slate-500">
                                                             Dikumpulkan {{ $existingSubmission->created_at->format('d M Y, H:i') }}
                                                             @if($existingSubmission->grade !== null)
                                                                 &middot; Nilai: <strong>{{ $existingSubmission->grade }}</strong>/100
                                                             @endif
                                                         </p>
                                                         @if($existingSubmission->file_url)
                                                             <a href="{{ \Illuminate\Support\Facades\Storage::url($existingSubmission->file_url) }}"
                                                                target="_blank"
                                                                class="inline-block mt-2 text-indigo-600 hover:underline font-semibold">
                                                                 Lihat berkas yang dikirim
                                                             </a>
                                                         @endif
                                                         @if($existingSubmission->feedback)
                                                             <p class="mt-2 text-slate-600 italic">"{{ $existingSubmission->feedback }}"</p>
                                                         @endif
                                                     </div>

                                                     @if($existingSubmission->grade === null)
                                                         <button wire:click="deleteSubmission"
                                                                 wire:confirm="Batalkan pengumpulan tugas ini?"
                                                                 class="shrink-0 text-sm font-semibold text-red-600 hover:text-red-700">
                                                             Batalkan
                                                         </button>
                                                     @endif
                                                 </div>
                                             </div>
                                         @endif

                                         @if(! $existingSubmission || $existingSubmission->grade === null)
                                             <div class="bg-white p-4 rounded-xl border border-indigo-100">
                                                 <label class="block text-sm font-bold text-slate-700 mb-2">
                                                     {{ $existingSubmission ? 'Kirim ulang tugas' : 'Upload Tugas Anda' }}
                                                 </label>
                                                 <input type="file" wire:model="assignmentFile" class="block w-full text-sm text-slate-500
                                                     file:mr-4 file:py-2 file:px-4
                                                     file:rounded-full file:border-0
                                                     file:text-sm file:font-semibold
                                                     file:bg-indigo-50 file:text-indigo-700
                                                     hover:file:bg-indigo-100
                                                  "/>
                                                 @error('assignmentFile') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                                 <p class="text-xs text-slate-400 mt-2">PDF, DOCX, PPTX, XLSX, ZIP, gambar. Maksimal 20MB.</p>

                                                 <div class="mt-4">
                                                     <label class="block text-sm font-bold text-slate-700 mb-2">Catatan (opsional)</label>
                                                     <textarea wire:model="assignmentNotes" rows="3"
                                                               class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                               placeholder="Pesan untuk instruktur..."></textarea>
                                                     @error('assignmentNotes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                                 </div>

                                                 <div class="mt-4 flex justify-end">
                                                     <button wire:click="submitAssignment" wire:loading.class="opacity-50 cursor-wait" wire:loading.delay.shortest
                                                             wire:loading.attr="disabled"
                                                             class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                                                         <span wire:loading.remove wire:target="submitAssignment,assignmentFile">Kirim Tugas</span>
                                                         <span wire:loading wire:target="submitAssignment,assignmentFile">Mengirim...</span>
                                                     </button>
                                                 </div>
                                             </div>
                                         @endif
                                     </div>
                                </div>
                            @endif
                        </div>

                        <div class="bg-slate-50 p-6 border-t border-slate-100 flex items-center justify-between">
                            <div>
                                @if($prevLessonId)
                                    <button wire:click="goToPrev" wire:loading.class="opacity-50 cursor-wait" wire:loading.delay.shortest class="flex items-center gap-2 px-5 py-3 bg-white border border-slate-200 rounded-xl text-slate-600 hover:text-indigo-600 hover:border-indigo-100 transition shadow-sm text-sm font-black uppercase tracking-wider">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
                                        Prev
                                    </button>
                                @endif
                            </div>
                            <div class="flex gap-3">
                                @if($nextLessonId)
                                    <button wire:click="goToNext" wire:loading.class="opacity-50 cursor-wait" wire:loading.delay.shortest class="group flex items-center gap-3 px-8 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 text-sm font-black uppercase tracking-wider">
                                        Next
                                        <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                @else
                                    <button wire:click="goToNext" wire:loading.class="opacity-50 cursor-wait" wire:loading.delay.shortest class="flex items-center gap-3 px-8 py-3 bg-emerald-500 text-white rounded-xl hover:bg-emerald-600 transition shadow-lg shadow-emerald-100 text-sm font-black uppercase tracking-wider">
                                        Selesai Kelas
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                @else
                    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 h-[60vh] flex flex-col items-center justify-center p-12 text-center">
                        <div class="w-20 h-20 bg-slate-50 rounded-2xl flex items-center justify-center mb-6 text-4xl">🛸</div>
                        <h3 class="text-2xl font-black text-slate-800 mb-2">Pilih Materi Belajar</h3>
                        <p class="text-slate-400 font-medium max-w-xs">Gunakan navigasi di sebelah kiri untuk mulai menjelajahi kurikulum kelas.</p>
                    </div>
                @endif
            </div>
        </main>
    </div>

    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:notify.window="show = true; message = $event.detail[0].message; type = $event.detail[0].type; setTimeout(() => { show = false }, 3000)"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-10 left-1/2 transform -translate-x-1/2 z-[100] px-6 py-3 rounded-full shadow-2xl flex items-center gap-3 font-bold text-white"
         :class="type === 'success' ? 'bg-emerald-500' : (type === 'error' ? 'bg-rose-500' : 'bg-amber-500')"
         style="display: none;">

        <template x-if="type === 'success'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </template>
        <template x-if="type === 'error' || type === 'warning'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </template>

        <span x-text="message"></span>
    </div>
</div>

<div class="min-h-screen bg-[#F4F7F9] py-6 lg:py-8 font-sans text-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- HEADER SECTION --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-2xl lg:text-3xl font-black text-slate-900 tracking-tight">Sertifikat Saya</h1>
                    @if(!$certificates->isEmpty())
                        <span class="bg-indigo-100 text-indigo-700 py-0.5 px-2.5 rounded-full text-xs font-bold border border-indigo-200">
                            {{ $certificates->count() }} Pencapaian
                        </span>
                    @endif
                </div>
                <p class="text-slate-500 font-medium text-sm lg:text-base">Koleksi bukti pencapaian dan kelulusan kelas Anda.</p>
            </div>

            {{-- Fitur Search --}}
            @if(!$certificates->isEmpty())
                <div class="w-full md:w-64 relative">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Cari kelas atau ID..."
                           class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 shadow-sm text-sm transition-all bg-white font-medium text-slate-700 placeholder-slate-400">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            @endif
        </div>

        {{-- CERTIFICATE GRID / EMPTY STATE --}}
        @if($certificates->isEmpty())

            {{-- EMPTY STATE --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 lg:p-12 text-center flex flex-col items-center justify-center min-h-[40vh]">
                <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-4 ring-4 ring-slate-50/50">
                    <span class="text-5xl grayscale opacity-50">📜</span>
                </div>
                <h2 class="text-xl font-black text-slate-800 mb-2">Belum Ada Sertifikat</h2>
                <p class="text-slate-500 text-sm max-w-sm mx-auto mb-6 font-medium">Anda belum menyelesaikan pelatihan apapun. Ayo mulai perjalanan belajar Anda!</p>

                <a href="{{ route('student.my-courses') }}" wire:navigate class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow shadow-indigo-200 transition-all text-sm">
                    Mulai Belajar
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </div>

        @else

            {{-- GRID KARTU SERTIFIKAT --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($certificates as $cert)
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300 border border-slate-200 overflow-hidden flex flex-col group">

                        {{-- Bagian Atas: Visual Header (Lebih Rendah & Ramping) --}}
                        <div class="h-24 bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 relative flex items-center justify-center p-4 overflow-hidden border-b-2 border-indigo-500">
                            <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] mix-blend-overlay"></div>

                            {{-- Badge Verified (Lebih Kecil) --}}
                            <div class="absolute top-3 right-3 bg-white/10 backdrop-blur-md px-2 py-0.5 rounded border border-white/20 flex items-center gap-1 shadow-sm">
                                <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                <span class="text-[9px] font-bold text-white tracking-wider uppercase">Verified</span>
                            </div>

                            {{-- Ikon Center (Lebih Proporsional) --}}
                            <div class="relative z-10 w-12 h-12 bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg shadow-inner flex items-center justify-center transform group-hover:scale-105 transition-transform duration-300">
                                <span class="text-2xl filter drop-shadow">🎓</span>
                            </div>
                        </div>

                        {{-- Body Kartu (Padding Dikurangi) --}}
                        <div class="p-4 flex flex-col flex-1">
                            <div class="mb-3">
                                <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest mb-1 flex items-center gap-1 truncate">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    {{ $cert->batch->batch_name ?? 'Kelas Mandiri' }}
                                </p>
                                <h3 class="text-base font-black text-slate-800 leading-tight line-clamp-2 group-hover:text-indigo-700 transition-colors">
                                    {{ $cert->course->title ?? $cert->batch->course->title ?? 'Course Title Not Found' }}
                                </h3>
                            </div>

                            {{-- Meta Data (Jarak Dirapatkan) --}}
                            <div class="mt-auto space-y-2 pt-3 border-t border-slate-100">
                                <div class="flex justify-between items-center text-[13px]">
                                    <span class="text-slate-500 font-medium">Diterbitkan</span>
                                    <span class="text-slate-800 font-bold">{{ \Carbon\Carbon::parse($cert->issued_at)->translatedFormat('d M Y') }}</span>
                                </div>

                                {{-- Fitur Copy to Clipboard (Diperbaiki Layoutnya agar tidak terpotong) --}}
                                <div class="flex justify-between items-center text-[13px]" x-data="{ copied: false }">
                                    <span class="text-slate-500 font-medium">ID Kredensial</span>
                                    <div class="flex items-center gap-1 bg-slate-50 hover:bg-slate-100 transition-colors pl-2 pr-1 py-0.5 rounded border border-slate-200 w-auto max-w-[150px]">
                                        <span class="text-slate-800 font-mono font-bold text-[11px] truncate">{{ $cert->certificate_code }}</span>
                                        <button @click="
                                                    navigator.clipboard.writeText('{{ $cert->certificate_code }}');
                                                    copied = true;
                                                    setTimeout(() => copied = false, 2000);
                                                    $dispatch('notify', [{ message: 'ID berhasil disalin!', type: 'success' }])
                                                "
                                                class="p-1 rounded text-slate-400 hover:text-indigo-600 focus:outline-none flex-shrink-0"
                                                title="Salin ID">
                                            <svg x-show="!copied" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                            <svg x-show="copied" style="display: none;" class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons: (Tinggi dikurangi, sudut dipertajam) --}}
                        <div class="p-3 bg-slate-50 border-t border-slate-100 grid grid-cols-2 gap-2">
                            <a href="{{ route('certificate.verify', $cert->certificate_code) }}" target="_blank" class="flex items-center justify-center gap-1.5 py-1.5 bg-white border border-slate-200 hover:border-indigo-600 hover:text-indigo-600 text-slate-700 font-bold rounded-lg transition-all shadow-sm text-xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                Detail
                            </a>

                            <button wire:click="downloadPdf('{{ $cert->certificate_code }}')" class="flex items-center justify-center gap-1.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg transition-all shadow-sm text-xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Unduh
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

        @endif
    </div>

    {{-- GLOBAL TOAST NOTIFICATION USING ALPINE --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:notify.window="show = true; message = $event.detail[0].message; type = $event.detail[0].type; setTimeout(() => { show = false }, 3000)"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-10 left-1/2 transform -translate-x-1/2 z-[100] px-5 py-2.5 rounded-lg shadow-xl flex items-center gap-2 font-bold text-white text-sm"
         :class="type === 'success' ? 'bg-emerald-600' : (type === 'error' ? 'bg-rose-600' : 'bg-amber-500')"
         style="display: none;">

        <template x-if="type === 'success'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </template>
        <template x-if="type === 'error' || type === 'warning'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </template>

        <span x-text="message"></span>
    </div>
</div>

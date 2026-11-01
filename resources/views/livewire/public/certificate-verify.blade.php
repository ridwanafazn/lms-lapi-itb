<?php
use function Livewire\Volt\{state, mount, layout};
use App\Models\Certificate;

layout('layouts.blank');

state(['certificate' => null]);

mount(function ($code) {
    // Mencari data sertifikat dengan relasi lengkap
    $this->certificate = Certificate::with(['user.profile', 'course', 'batch'])
        ->where('certificate_code', $code)
        ->firstOrFail();
});
?>

{{-- Wrapper Utama: Latar belakang sedikit lebih gelap (slate-100) untuk kontras --}}
<div class="h-screen w-screen relative bg-slate-100 flex flex-col font-sans text-slate-800 overflow-hidden select-none z-0">

    {{-- Decorative Ambient Glowing Orbs (Disesuaikan agar lebih terlihat di latar baru) --}}
    <div class="absolute top-[-10%] left-[-10%] w-[40vw] h-[40vw] rounded-full bg-indigo-400/20 blur-[120px] pointer-events-none -z-10"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[40vw] h-[40vw] rounded-full bg-emerald-400/20 blur-[120px] pointer-events-none -z-10"></div>
    <div class="absolute bottom-[20%] left-[10%] w-[25vw] h-[25vw] rounded-full bg-blue-300/20 blur-[100px] pointer-events-none -z-10"></div>

    {{-- HEADER --}}
    <header class="w-full max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center shrink-0 z-10">
        {{-- Tombol Home dengan kontras lebih baik --}}
        <a href="/" wire:navigate class="relative inline-block px-3 py-1.5 bg-white/70 backdrop-blur-md border border-white/50 ring-1 ring-slate-200/20 rounded-xl shadow-sm transition-all hover:bg-white/90 duration-300">
            <img src="{{ asset('img/Logo_PT LAPI_PNG.png') }}" alt="Logo LAPI ITB" class="h-6 lg:h-7 object-contain transition-transform hover:scale-105 duration-300">
        </a>
    </header>

    {{-- KONTEN UTAMA --}}
    <main class="flex-1 w-full max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 pb-5 flex flex-col z-10 overflow-hidden">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-full overflow-hidden">

            {{-- ========================================== --}}
            {{-- KOLOM KIRI: POSISI VERTICAL CENTER (justify-center) --}}
            {{-- Agar terlihat natural dan tidak ada ruang kosong --}}
            {{-- ========================================== --}}
            <div class="lg:col-span-4 xl:col-span-3 flex flex-col justify-center gap-5 h-full overflow-y-auto pr-1" style="scrollbar-width: thin;">

                {{-- Glassmorphic Card: Opasitas dinaikkan (white/80) dan diberi ring untuk definisi lebih tegas --}}
                <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-white/50 ring-1 ring-slate-900/5 relative overflow-hidden shrink-0 group">

                    {{-- Dekorasi Aura --}}
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-emerald-400/15 to-transparent rounded-full blur-2xl -mr-12 -mt-12 pointer-events-none transition-transform group-hover:scale-125 duration-700"></div>

                    <div class="relative z-10">
                        {{-- Badge Verifikasi --}}
                        <div class="flex items-center gap-2 mb-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-50/90 backdrop-blur-sm border border-emerald-200/80 text-emerald-700 text-[9px] font-bold tracking-wide uppercase shadow-sm">
                                <svg class="w-3 h-3 drop-shadow-sm" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                Terverifikasi Resmi
                            </span>
                        </div>

                        {{-- Nama --}}
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <h1 class="text-2xl lg:text-[1.65rem] font-bold text-slate-900 leading-tight tracking-tight drop-shadow-sm break-words">
                                {{ $certificate->user->name }}
                            </h1>
                            <div class="bg-indigo-50/80 p-1.5 rounded-full backdrop-blur-sm border border-indigo-100/80 flex-shrink-0 mt-0.5 shadow-sm">
                                <svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            </div>
                        </div>
                        <p class="text-slate-600 font-medium mb-4 text-[11px] leading-relaxed">
                            Telah sukses menyelesaikan standar kompetensi pada:
                        </p>

                        {{-- Kotak Fokus Pelatihan --}}
                        <div class="px-4 py-3 bg-indigo-50/50 backdrop-blur-md border border-indigo-200/50 rounded-xl mb-5 relative overflow-hidden shadow-sm">
                            <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/5 to-transparent pointer-events-none"></div>
                            <p class="font-bold text-indigo-900 text-[13px] leading-snug relative z-10">
                                {{ $certificate->course->title }}
                            </p>
                        </div>

                        {{-- Metadata Section (Kontras teks ditingkatkan) --}}
                        <div class="grid grid-cols-1 gap-3 pt-4 border-t border-slate-200/70">
                            <div class="group/meta flex flex-col gap-1">
                                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Tanggal Diterbitkan
                                </p>
                                <p class="font-bold text-slate-800 text-[13px]">{{ \Carbon\Carbon::parse($certificate->issued_at)->translatedFormat('d F Y') }}</p>
                            </div>

                            <div class="group/meta flex flex-col gap-1" x-data="{ copied: false }">
                                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                    ID Kredensial Publik
                                </p>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <p class="font-mono font-bold text-slate-700 bg-slate-100/80 backdrop-blur-sm px-2.5 py-1 rounded-md border border-slate-200/80 text-[11px] truncate select-all shadow-sm">
                                        {{ $certificate->certificate_code }}
                                    </p>
                                    <button @click="navigator.clipboard.writeText('{{ $certificate->certificate_code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="p-1 rounded-md bg-white/70 border border-slate-200/80 text-slate-500 hover:text-indigo-600 hover:bg-white shadow-sm transition-all">
                                        <svg x-show="!copied" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        <svg x-show="copied" style="display: none;" class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="group/meta flex flex-col gap-1">
                                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Nomor Surat
                                </p>
                                <p class="font-bold text-slate-800 text-[13px]">{{ $certificate->formal_number ?? 'Diproses Sistem' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action Group --}}
                <div class="flex flex-col gap-2.5 shrink-0">
                    <a href="{{ route('certificate.download', $certificate->certificate_code) }}?download=1"
                       class="w-full flex items-center justify-center gap-2 py-2.5 bg-gradient-to-tr from-slate-900 to-slate-800 hover:from-black hover:to-slate-900 text-white font-bold rounded-xl shadow-md transition-all transform hover:-translate-y-[1px] text-xs ring-1 ring-white/20">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Simpan PDF Sertifikat
                    </a>

                    @php
                        $linkedInUrl = "https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&name=" . urlencode($certificate->course->title) . "&organizationName=" . urlencode('PT LAPI ITB') . "&issueYear=" . \Carbon\Carbon::parse($certificate->issued_at)->format('Y') . "&issueMonth=" . \Carbon\Carbon::parse($certificate->issued_at)->format('m') . "&certUrl=" . urlencode(url()->current()) . "&certId=" . $certificate->certificate_code;
                    @endphp
                    <a href="{{ $linkedInUrl }}" target="_blank"
                       class="w-full flex items-center justify-center gap-2 py-2.5 bg-white/80 backdrop-blur-md border border-[#0A66C2]/30 text-[#0A66C2] hover:bg-[#0A66C2] hover:text-white hover:border-[#0A66C2] font-bold rounded-xl shadow-sm transition-all transform hover:-translate-y-[1px] text-xs group">
                        <svg class="w-3.5 h-3.5 fill-current transition-transform group-hover:scale-110" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        Share ke LinkedIn
                    </a>
                </div>
            </div>

            {{-- ========================================== --}}
            {{-- KOLOM KANAN: Penampil PDF --}}
            {{-- ========================================== --}}
            <div class="lg:col-span-8 xl:col-span-9 bg-white/80 backdrop-blur-xl rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-white/50 ring-1 ring-slate-900/5 flex flex-col overflow-hidden h-full relative z-10">

                {{-- Viewer Header (Kontras ditingkatkan) --}}
                <div class="bg-slate-50/80 backdrop-blur-md border-b border-slate-200/80 px-4 py-2.5 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="flex gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full bg-[#ff5f56] shadow-sm"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-[#ffbd2e] shadow-sm"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-[#27c93f] shadow-sm"></div>
                        </div>
                        <div class="h-3 w-px bg-slate-300"></div>
                        <span class="text-[9px] font-bold text-slate-600 tracking-widest uppercase drop-shadow-sm">Pratinjau Resolusi Tinggi</span>
                    </div>
                </div>

                {{-- Iframe Container --}}
                <div class="relative flex-1 bg-[#323639] overflow-hidden" x-data="{ loading: true }">
                    <div x-show="loading"
                         class="absolute inset-0 flex flex-col items-center justify-center bg-slate-100/90 backdrop-blur-md z-20 transition-opacity duration-500"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0">
                        <div class="relative drop-shadow-sm">
                            <div class="w-8 h-8 border-2 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                        </div>
                    </div>

                    <iframe src="{{ route('certificate.download', $certificate->certificate_code) }}#toolbar=0&navpanes=0&scrollbar=0&view=FitH"
                            @load="loading = false"
                            class="absolute inset-0 w-full h-full border-0 bg-transparent"
                            title="Verifikasi Sertifikat Digital">
                    </iframe>
                </div>

                {{-- Footer Keamanan (Kontras ditingkatkan) --}}
                <div class="bg-slate-50/80 backdrop-blur-md py-1.5 border-t border-slate-200/80 shrink-0">
                    <p class="text-[8.5px] text-slate-600 font-bold text-center flex items-center justify-center gap-1.5">
                        <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        ID Kredensial ini bersifat publik.
                    </p>
                </div>
            </div>

        </div>
    </main>
</div>

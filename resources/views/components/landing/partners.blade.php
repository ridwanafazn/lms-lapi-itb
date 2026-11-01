@php
    // Gunakan placeholder logo yang variatif
    $logos = [
        'https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg',
        'https://upload.wikimedia.org/wikipedia/commons/5/51/IBM_logo.svg',
        'https://upload.wikimedia.org/wikipedia/commons/9/93/Amazon_Web_Services_Logo.svg',
        'https://upload.wikimedia.org/wikipedia/commons/4/44/Microsoft_logo.svg',
        'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg',
        'https://upload.wikimedia.org/wikipedia/commons/a/a9/Amazon_logo.svg',
        'https://upload.wikimedia.org/wikipedia/commons/f/fa/Apple_logo_black.svg',
        'https://upload.wikimedia.org/wikipedia/commons/e/e8/Tesla_logo.png',
    ];

    // Duplikasi agar marquee panjang
    $partners = array_merge($logos, $logos, $logos); 
    // Baris kedua (di-reverse biar beda gerakan)
    $partnersReverse = array_reverse($partners);
@endphp

<section class="w-full relative h-auto py-24 md:py-32" x-data="{ activeTab: 'premium' }">
    <div class="container mx-auto px-6 h-full flex flex-col justify-center">

        {{-- 1. HEADER TEXT --}}
        <div class="text-center mb-12 space-y-3">
            {{-- Judul: Merah Cabai, Bold, Italic --}}
            <h2 class="font-body text-3xl md:text-5xl font-bold italic text-[#D93F21] tracking-tight drop-shadow-sm">
                Partner Kolaborasi Kami
            </h2>
            
            {{-- Sub-judul: Merah Salmon, Medium, Italic --}}
            <h3 class="font-body text-base md:text-xl font-medium italic text-[#E88A83] max-w-3xl mx-auto leading-relaxed">
                Kami bekerja sama dengan berbagai perusahaan dan institusi untuk menghadirkan kualitas terbaik
            </h3>
        </div>

        {{-- 2. MARQUEE LOGO WRAPPER --}}
        <div class="flex flex-col gap-6 opacity-90">

            {{-- BARIS 1: Gerak ke KIRI --}}
            <div class="w-full overflow-hidden flex select-none group/partner">
                {{-- Track Asli --}}
                <div class="flex gap-8 sm:gap-12 animate-marquee group-hover/partner:[animation-play-state:paused] min-w-full flex-shrink-0 items-center px-4">
                    @foreach($partners as $logo)
                        {{-- Container Logo --}}
                        <div class="w-32 h-16 sm:w-40 sm:h-20 bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl flex items-center justify-center p-4 hover:bg-white/40 transition-colors duration-300 cursor-pointer">
                            <img src="{{ $logo }}" class="max-w-full max-h-full object-contain grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all duration-300 mix-blend-multiply" alt="Partner Logo">
                        </div>
                    @endforeach
                </div>
                {{-- Track Duplikat --}}
                <div aria-hidden="true" class="flex gap-8 sm:gap-12 animate-marquee group-hover/partner:[animation-play-state:paused] min-w-full flex-shrink-0 items-center px-4">
                    @foreach($partners as $logo)
                        <div class="w-32 h-16 sm:w-40 sm:h-20 bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl flex items-center justify-center p-4 hover:bg-white/40 transition-colors duration-300 cursor-pointer">
                            <img src="{{ $logo }}" class="max-w-full max-h-full object-contain grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all duration-300 mix-blend-multiply" alt="Partner Logo">
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- BARIS 2: Gerak ke KANAN (Reverse) --}}
            <div class="w-full overflow-hidden flex select-none group/partner">
                <div class="flex gap-8 sm:gap-12 animate-marquee-reverse group-hover/partner:[animation-play-state:paused] min-w-full flex-shrink-0 items-center px-4">
                    @foreach($partnersReverse as $logo)
                        <div class="w-32 h-16 sm:w-40 sm:h-20 bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl flex items-center justify-center p-4 hover:bg-white/40 transition-colors duration-300 cursor-pointer">
                            <img src="{{ $logo }}" class="max-w-full max-h-full object-contain grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all duration-300 mix-blend-multiply" alt="Partner Logo">
                        </div>
                    @endforeach
                </div>
                <div aria-hidden="true" class="flex gap-8 sm:gap-12 animate-marquee-reverse group-hover/partner:[animation-play-state:paused] min-w-full flex-shrink-0 items-center px-4">
                    @foreach($partnersReverse as $logo)
                        <div class="w-32 h-16 sm:w-40 sm:h-20 bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl flex items-center justify-center p-4 hover:bg-white/40 transition-colors duration-300 cursor-pointer">
                            <img src="{{ $logo }}" class="max-w-full max-h-full object-contain grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all duration-300 mix-blend-multiply" alt="Partner Logo">
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

    </div>

    {{-- Overlay Fade Kiri & Kanan (Agar logo seolah muncul dari kabut) --}}
    {{-- Warnanya disesuaikan dengan background oranye Anda (misal from-orange-100) --}}
    {{-- Jika background gambar, gunakan from-transparent tapi dengan backdrop-blur atau biarkan kosong --}}
    
</section>
<?php
use function Livewire\Volt\{layout};

// Kita gunakan layout 'landing' karena landing page biasanya tidak butuh Sidebar admin
// Pastikan file resources/views/layouts/landing.blade.php ada
layout('layouts.landing'); 

// ATAU jika layout landing ada di folder components:
// layout('components.layouts.landing');
?>



<div id="parallax-wrapper" class="relative min-h-[300vh]">

    <!-- FOOTER BACKGROUND -->
    <footer
        id="footer"
        class="fixed bottom-0 left-0 w-full h-screen z-0 overflow-hidden flex items-center justify-center">

        <!-- FOOTER BACKGROUND (YANG DIPARALLAX) -->
        <img
            id="footer-bg"
            src="{{ asset('assets/img/Slice9.webp') }}"
            class="absolute inset-0 w-full h-[120%] object-cover will-transform"
        />

        <!-- DARK OVERLAY (YANG AKAN DI-FADE) -->
        <div
            id="footer-overlay"
            class="absolute inset-0 bg-black/90 z-10">
        </div>

        {{-- 1. CTA FOOTER SECTION --}}
        <div id="footer-content" class="relative z-20 w-full px-4 pt-24">
            <x-landing.cta />
        </div>
    </footer>

    <!-- MAIN CONTENT -->
    <main id="content" class="relative z-10 w-full">
        <div class="absolute inset-0 w-full h-full z-0 pointer-events-none overflow-hidden">
            <img 
                src="{{ asset('assets/img/BGAll.webp') }}" 
                alt="Background" 
                class="w-full h-full object-cover object-top"
            />
            {{-- 
               Catatan: 
               - object-cover: Akan memastikan tidak ada ruang kosong (gambar akan di-zoom in).
               - object-top: Memastikan bagian atas (Hero) selalu pas posisinya.
            --}}
        </div>
        <div class="relative z-10">

            {{-- 2. HERO SECTION --}}
            {{-- Memanggil file: resources/views/components/landing/hero.blade.php --}}
            <x-landing.hero />

            {{-- 3. STATISTICS --}}
            {{-- Memanggil file: resources/views/components/landing/stats.blade.php --}}
            <x-landing.stats />

            {{-- 4. RECOMMENDATIONS --}}
            {{-- Memanggil file: resources/views/components/landing/recommend.blade.php --}}
            <x-landing.recommend />

            {{-- 5. FEATURES/WHY US --}}
            {{-- Memanggil file: resources/views/components/landing/why.blade.php --}}
            <x-landing.why />

            {{-- 6. INSTRUCTORS --}}
            {{-- Memanggil file: resources/views/components/landing/instructors.blade.php --}}
            <x-landing.instructors />

            {{-- 7. PARTNERS --}}
            {{-- Memanggil file: resources/views/components/landing/partners.blade.php --}}
            <x-landing.partners />

            {{-- 8. TESTIMONIALS --}}
            {{-- Memanggil file: resources/views/components/landing/testimonials.blade.php --}}
            <x-landing.testimonials />

            

            

            <!-- EXTRA SCROLL BUFFER (WAJIB) -->
            <!-- <div class="h-[120vh]"></div> -->

        </div>
        
    </main>
    <div class="w-full relative bg-cover bg-center bg-no-repeat aspect-[7680/1652]" 
                style="background-image: url('{{ asset('assets/img/Slice8.webp') }}');"></div>
    <!-- TRANSITION ZONE -->
    <div id="footer-trigger" class="h-[65vh]"></div>
</div>

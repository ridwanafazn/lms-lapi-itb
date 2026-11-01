<div class="flex flex-col items-center justify-center text-center space-y-8">
    
    {{-- 1. HEADLINE TEXT --}}
    <div class="space-y-4 max-w-4xl">
        {{-- Judul Utama: Font Italic, Bold, Putih --}}
        <h2 class="font-body text-4xl md:text-6xl lg:text-7xl font-bold italic text-white tracking-tight drop-shadow-md leading-tight">
            Siap Memulai<br>
            Perjalanan Barumu?
        </h2>
        
        {{-- Sub-judul: Font Italic, Medium, Biru Muda/Putih --}}
        <p class="font-body text-base md:text-xl font-medium italic text-cyan-100/90 max-w-2xl mx-auto leading-relaxed">
            Ribuan materi berkualitas dan mentor ahli sudah menunggu.<br class="hidden md:block">
            Waktunya berinvestasi pada dirimu sendiri.
        </p>
    </div>

    {{-- 2. BUTTON GROUP --}}
    <div class="flex flex-col sm:flex-row items-center gap-4 sm:gap-6 pt-4 w-full sm:w-auto">
        
        {{-- Tombol Kiri: Join for Free --}}
        {{-- Mengarah ke route 'register' --}}
        <a 
            href="{{ route('login') }}"
            wire:navigate 
            class="w-full sm:w-auto px-10 py-4 bg-[#22D3EE] hover:bg-[#06B6D4] text-white rounded-2xl font-bold text-lg shadow-[0_0_20px_rgba(34,211,238,0.4)] hover:shadow-[0_0_30px_rgba(34,211,238,0.6)] transition-all duration-300 transform hover:-translate-y-1 text-center inline-block"
        >
            Join for Free
        </a>

        {{-- Tombol Kanan: Become Member --}}
        {{-- Bisa mengarah ke 'register' juga atau 'login' --}}
        <a 
            href="#"
            class="w-full sm:w-auto px-10 py-4 bg-white/10 backdrop-blur-md border border-white/30 text-white rounded-2xl font-bold text-lg hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-1 text-center inline-block"
        >
            Become Member
        </a>

    </div>

</div>
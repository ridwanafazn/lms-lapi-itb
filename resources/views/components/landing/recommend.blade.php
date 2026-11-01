<section class="w-full relative h-auto py-24 md:py-32" x-data="{ activeTab: 'premium' }">
    <div class="container mx-auto px-6 h-full flex flex-col justify-center">

        {{-- 1. HEADER --}}
        <div class="text-center mb-10 space-y-2 relative z-10">
            <h2 class="font-body text-3xl md:text-4xl font-bold italic text-[#2F70AF]">
                Jelajahi Program Pilihan Kami
            </h2>
            <h3 class="font-body text-base md:text-lg font-medium italic text-[#7C98B6] max-w-2xl mx-auto">
                Mulai perjalanan barumu dengan kelas gratis, atau perdalam keahlian dengan materi premium.
            </h3>
        </div>

        {{-- 2. SLIDING TAB SWITCHER --}}
        <div class="flex justify-center mb-12 relative z-10">
            {{-- Container Tab (Glassmorphism) --}}
            <div class="relative flex w-[280px] h-[50px] backdrop-blur-sm border-4 border-white/60 rounded-full p-1 shadow-sm">
                
                {{-- PIL PUTIH BERGERAK (BACKGROUND ANIMATION) --}}
                {{-- Logika: Jika premium, geser ke kiri (x-0). Jika free, geser ke kanan (x-full) --}}
                <div 
                    class="absolute top-1 bottom-1 w-[calc(50%-4px)] bg-white rounded-full shadow-sm transition-transform duration-500 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
                    :class="activeTab === 'premium' ? 'translate-x-0 left-1' : 'translate-x-full left-1'"
                ></div>

                {{-- TOMBOL PREMIUM (Teks doang, z-index di atas pil) --}}
                <button 
                    @click="activeTab = 'premium'"
                    class="w-1/2 h-full rounded-full font-bold text-sm relative z-10 transition-colors duration-300"
                    :class="activeTab === 'premium' ? 'text-[#3CB4E5]' : 'text-[#2F70AF] hover:text-[#3CB4E5]'"
                >
                    Premium
                </button>

                {{-- TOMBOL FREE --}}
                <button 
                    @click="activeTab = 'free'"
                    class="w-1/2 h-full rounded-full font-bold text-sm relative z-10 transition-colors duration-300"
                    :class="activeTab === 'free' ? 'text-[#3CB4E5]' : 'text-[#2F70AF] hover:text-[#3CB4E5]'"
                >
                    Free
                </button>
            </div>
        </div>

        {{-- 3. SLIDING CONTENT AREA --}}
        {{-- Kita gunakan CSS Grid Stack (grid-areas) agar kedua konten menumpuk di tempat yang sama --}}
        <div class="relative px-4 md:px-12 lg:px-20 min-h-[500px] grid grid-cols-1">

            {{-- GRID PREMIUM --}}
            {{-- Animasi: Masuk dari kiri, Keluar ke kiri --}}
            <div 
                x-show="activeTab === 'premium'"
                class="col-start-1 row-start-1 w-full"
                x-transition:enter="transition ease-out duration-500 delay-100"
                x-transition:enter-start="opacity-0 -translate-x-12"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-300 absolute top-0 left-0 w-full"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-12"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    @for ($i = 0; $i < 3; $i++)
                    {{-- Kartu Premium --}}
                    <div class="bg-white rounded-3xl p-5 shadow-[0_8px_30px_rgba(59,130,246,0.08)] border border-white transition-all hover:-translate-y-2 duration-300 hover:shadow-[0_20px_40px_rgba(59,130,246,0.15)]">
                        <div class="relative h-48 bg-gray-300 rounded-2xl mb-5 overflow-hidden group">
                            <span class="absolute top-3 left-3 bg-white/90 backdrop-blur px-3 py-1 rounded-lg text-[10px] font-bold text-slate-500 shadow-sm z-10">
                                Beginner
                            </span>
                            {{-- Placeholder Image --}}
                            <div class="w-full h-full bg-slate-200 group-hover:scale-105 transition-transform duration-500"></div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="bg-[#FFEDE9] text-[#FF6B6B] px-3 py-1 rounded-lg text-[10px] font-bold">Programming</span>
                                <div class="flex items-center gap-1">
                                    <svg class="w-3 h-3 text-yellow-400 fill-current" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                    <span class="text-xs font-bold text-slate-600">4.9</span>
                                </div>
                            </div>
                            <h3 class="font-body font-bold text-lg text-slate-800 leading-snug">
                                Mastering Laravel 11 (Premium)
                            </h3>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gray-300 shrink-0"></div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-slate-400 font-medium">Instruktur</span>
                                    <span class="text-xs font-bold text-slate-600">Budi Santoso</span>
                                </div>
                            </div>
                            <hr class="border-slate-100">
                            <div class="flex flex-col">
                                <span class="text-xs text-slate-400 font-medium relative w-fit">
                                    Rp. 750.000
                                    <div class="absolute top-1/2 left-0 w-full h-[1.5px] bg-[#FF6B6B] -translate-y-1/2"></div>
                                </span>
                                <span class="font-heading text-2xl font-bold text-[#2F70AF]">Rp. 350.000</span>
                            </div>
                            <button class="w-full py-2.5 rounded-xl border border-[#2F70AF] text-[#2F70AF] font-bold text-sm hover:bg-[#2F70AF] hover:text-white transition-all duration-300">
                                Beli Kelas
                            </button>
                        </div>
                    </div>
                    @endfor
                </div>
            </div>

            {{-- GRID FREE --}}
            {{-- Animasi: Masuk dari kanan, Keluar ke kanan --}}
            <div 
                x-show="activeTab === 'free'"
                style="display: none;"
                class="col-start-1 row-start-1 w-full"
                x-transition:enter="transition ease-out duration-500 delay-100"
                x-transition:enter-start="opacity-0 translate-x-12"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-300 absolute top-0 left-0 w-full"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-12"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    @for ($i = 0; $i < 3; $i++)
                    {{-- Kartu Free --}}
                    <div class="bg-white rounded-3xl p-5 shadow-[0_8px_30px_rgba(59,130,246,0.08)] border border-white transition-all hover:-translate-y-2 duration-300 hover:shadow-[0_20px_40px_rgba(59,130,246,0.15)]">
                        <div class="relative h-48 bg-gray-300 rounded-2xl mb-5 overflow-hidden group">
                            <span class="absolute top-3 left-3 bg-white/90 backdrop-blur px-3 py-1 rounded-lg text-[10px] font-bold text-slate-500 shadow-sm z-10">
                                Basic
                            </span>
                            <div class="w-full h-full bg-slate-200 group-hover:scale-105 transition-transform duration-500"></div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="bg-[#E6F7FF] text-[#0099FF] px-3 py-1 rounded-lg text-[10px] font-bold">Intro</span>
                                <div class="flex items-center gap-1">
                                    <svg class="w-3 h-3 text-yellow-400 fill-current" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                    <span class="text-xs font-bold text-slate-600">4.5</span>
                                </div>
                            </div>
                            <h3 class="font-body font-bold text-lg text-slate-800 leading-snug">
                                Dasar Pemrograman Web (Free)
                            </h3>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gray-300 shrink-0"></div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-slate-400 font-medium">Instruktur</span>
                                    <span class="text-xs font-bold text-slate-600">Siti Aminah</span>
                                </div>
                            </div>
                            <hr class="border-slate-100">
                            <div class="flex flex-col">
                                <span class="text-xs text-transparent font-medium relative w-fit select-none">-</span>
                                <span class="font-heading text-2xl font-bold text-[#3CB4E5]">GRATIS</span>
                            </div>
                            <button class="w-full py-2.5 rounded-xl border border-[#3CB4E5] text-[#3CB4E5] font-bold text-sm hover:bg-[#3CB4E5] hover:text-white transition-all duration-300">
                                Akses Sekarang
                            </button>
                        </div>
                    </div>
                    @endfor
                </div>
            </div>

        </div>
    </div>
</section>
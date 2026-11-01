@php
    $testimonials = [
        [
            'name' => 'Rey Bhuda',
            'role' => 'Peserta Product Management Batch 2',
            'image' => 'https://randomuser.me/api/portraits/men/32.jpg',
            'quote' => 'Awalnya saya ragu karena background saya Akuntansi. Tapi kurikulumnya sangat terstruktur untuk pemula.',
            'active' => false, // Kartu biasa
        ],
        [
            'name' => 'Sarah Wijaya',
            'role' => 'Alumni Data Science Batch 5',
            'image' => 'https://randomuser.me/api/portraits/women/44.jpg',
            'quote' => 'Portfolio dari tugas akhir kursus ini yang bikin saya lolos magang di Unicorn. Terima kasih mentor!',
            'active' => true, // Kartu tengah (Active/Besar)
        ],
        [
            'name' => 'Budi Santoso',
            'role' => 'Peserta Web Dev Batch 12',
            'image' => 'https://randomuser.me/api/portraits/men/86.jpg',
            'quote' => 'Materinya mudah dipahami bahkan untuk saya yang baru mulai. Mentornya seru dan sangat suportif!',
            'active' => false, // Kartu biasa
        ],
    ];
@endphp

<section class="w-full relative h-auto py-24 md:py-32" x-data="{ activeTab: 'premium' }">
    <div class="container mx-auto px-6 h-full flex flex-col justify-center">

        {{-- 1. HEADER TEXT --}}
        <div class="text-center mb-12 space-y-3">
            <h2 class="font-body text-3xl md:text-5xl font-bold italic text-[#D93F21] tracking-tight drop-shadow-sm">
                Pengalaman Belajar yang Berdampak
            </h2>
            <h3 class="font-body text-base md:text-xl font-medium italic text-[#E88A83] max-w-3xl mx-auto leading-relaxed">
                Lihat bagaimana platform ini membantu mereka membuka potensi terbaiknya
            </h3>
        </div>

        {{-- 2. CAROUSEL AREA --}}
        <div class="relative max-w-7xl mx-auto flex items-center justify-center">

            {{-- BUTTON PREV (Kiri) --}}
            <button class="hidden md:flex absolute left-0 z-20 w-14 h-14 bg-white/30 backdrop-blur-sm border border-white/50 rounded-full items-center justify-center text-[#D93F21] hover:bg-white/60 hover:scale-110 transition-all shadow-lg group">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 group-hover:-translate-x-1 transition-transform">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </button>

            {{-- CARD WRAPPER --}}
            {{-- Menggunakan Grid 3 kolom untuk Desktop, Flex scroll untuk Mobile --}}
            <div class="flex md:grid md:grid-cols-3 gap-6 w-full overflow-x-auto md:overflow-visible snap-x snap-mandatory px-4 pb-12 pt-4 items-center hide-scrollbar">

                @foreach($testimonials as $testi)
                <div class="snap-center shrink-0 w-[320px] md:w-auto flex justify-center">
                    
                    {{-- CARD --}}
                    {{-- Logic: Jika 'active' (tengah), scale lebih besar (scale-110) --}}
                    <div class="
                        relative flex flex-col justify-between p-8 md:p-10
                        bg-white/35 backdrop-blur-md border border-white/50 
                        aspect-square rounded-[40px] text-center 
                        shadow-[3px_3px_25px_0_rgba(247,205,170,0.9)] overflow-visible
                        transition-all duration-500 ease-out
                        {{ $testi['active'] ? 'md:scale-110 z-10' : 'md:scale-95 opacity-90 hover:opacity-100 hover:scale-100' }}
                    ">
                        
                        {{-- Quote Icon (Merah) --}}
                        <div class="mb-4 text-[#D93F21]">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14.017 21L14.017 18C14.017 16.8954 14.9124 16 16.017 16H19.017C19.5693 16 20.017 15.5523 20.017 15V9C20.017 8.44772 19.5693 8 19.017 8H15.017C14.4647 8 14.017 8.44772 14.017 9V11C14.017 11.5523 13.5693 12 13.017 12H12.017V5H22.017V15C22.017 18.3137 19.3307 21 16.017 21H14.017ZM5.0166 21L5.0166 18C5.0166 16.8954 5.91203 16 7.0166 16H10.0166C10.5689 16 11.0166 15.5523 11.0166 15V9C11.0166 8.44772 10.5689 8 10.0166 8H6.0166C5.46432 8 5.0166 8.44772 5.0166 9V11C5.0166 11.5523 4.56889 12 4.0166 12H3.0166V5H13.0166V15C13.0166 18.3137 10.3303 21 7.0166 21H5.0166Z" />
                            </svg>
                        </div>

                        {{-- Isi Testimoni --}}
                        <p class="font-body text-base md:text-lg font-medium text-slate-800 leading-relaxed mb-6">
                            "{{ $testi['quote'] }}"
                        </p>

                        {{-- User Profile --}}
                        <div class="flex items-center gap-4 text-left">
                            {{-- Foto --}}
                            <div class="relative shrink-0">
                                <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-white shadow-sm">
                                    <img src="{{ $testi['image'] }}" alt="{{ $testi['name'] }}" class="w-full h-full object-cover">
                                </div>
                                {{-- Aksen Dot Merah (Opsional) --}}
                                <div class="absolute bottom-0 right-0 w-4 h-4 bg-[#D93F21] border-2 border-white rounded-full"></div>
                            </div>
                            
                            {{-- Nama & Role --}}
                            <div>
                                <h4 class="font-body font-bold text-slate-900 text-sm md:text-base">
                                    {{ $testi['name'] }}
                                </h4>
                                <p class="font-body text-xs text-slate-600 font-medium leading-tight max-w-[150px]">
                                    {{ $testi['role'] }}
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
                @endforeach

            </div>

            {{-- BUTTON NEXT (Kanan) --}}
            <button class="hidden md:flex absolute right-0 z-20 w-14 h-14 bg-white/30 backdrop-blur-sm border border-white/50 rounded-full items-center justify-center text-[#D93F21] hover:bg-white/60 hover:scale-110 transition-all shadow-lg group">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 group-hover:translate-x-1 transition-transform">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </button>

        </div>

    </div>
</section>
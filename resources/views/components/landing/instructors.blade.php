@php
    // Data Dummy
    $dummyInstructor = [
        ['name' => 'Ir. Dr. Reja Agin', 'role' => 'VP of Engineering at Tokopedia', 'image' => 'https://randomuser.me/api/portraits/men/32.jpg'],
        ['name' => 'Prof. Alikamu M.Sc.', 'role' => 'Pakar AI & Machine Learning', 'image' => 'https://randomuser.me/api/portraits/women/44.jpg'],
        ['name' => 'Budi Santoso', 'role' => 'Ex-Gojek Backend Lead', 'image' => 'https://randomuser.me/api/portraits/men/86.jpg'],
        ['name' => 'Siti Aminah', 'role' => 'Google Developer Expert', 'image' => 'https://randomuser.me/api/portraits/women/65.jpg'],
         ['name' => 'Prof. Alikamu M.Sc.', 'role' => 'Pakar AI & Machine Learning', 'image' => 'https://randomuser.me/api/portraits/women/44.jpg'],
    ];

    // Duplikasi data agar barisnya panjang
    $instructors = array_merge($dummyInstructor, $dummyInstructor); 
    
    // Reverse untuk baris kedua (biar beda urutan)
    $instructorsReverse = array_reverse($instructors);
@endphp

<section class="w-full relative h-auto py-24 md:py-32" x-data="{ activeTab: 'premium' }">
    <div class="container mx-auto px-6 h-full text-center flex flex-col justify-center mb-16 relative z-10">
    
        <h2 class="font-body text-3xl md:text-4xl font-bold italic text-[#2F70AF] mb-2">
            Dibimbing oleh Mentor Ahli di Bidangnya
        </h2>
        <h3 class="font-body text-base md:text-lg font-medium italic text-[#7C98B6] max-w-2xl mx-auto">
            Kombinasi terbaik antara Dosen ahli ITB dan Praktisi top industri untuk pembelajaran yang mendalam dan relevan
        </h3>
    </div>

    {{-- MARQUEE WRAPPER --}}
    {{-- Kita buat 2 Baris. Baris 1 ke Kiri, Baris 2 ke Kanan (Opsional) --}}
    
    <div class="flex flex-col gap-8 relative z-10">

        {{-- === ROW 1: Gerak ke KIRI === --}}
        {{-- group/marquee: untuk trigger pause saat di-hover --}}
        <div class="group/marquee w-full overflow-hidden flex select-none">
            
            {{-- TRACK (Isi Kartu) --}}
            {{-- animate-marquee: Gerak terus --}}
            {{-- hover:pause : Custom class agar berhenti saat hover (lihat penjelasan di bawah) --}}
            <div class="flex gap-8 animate-marquee group-hover/marquee:[animation-play-state:paused] min-w-full flex-shrink-0 px-4">
                {{-- Kita render kartu disini --}}
                @foreach($instructors as $instructor)
                    <x-landing.instructor-card :instructor="$instructor" />
                @endforeach
            </div>

            {{-- DUPLIKAT TRACK (Agar seamless/nyambung) --}}
            {{-- Isi Wajib SAMA PERSIS dengan di atas --}}
            <div aria-hidden="true" class="flex gap-8 animate-marquee group-hover/marquee:[animation-play-state:paused] min-w-full flex-shrink-0 px-4">
                @foreach($instructors as $instructor)
                    <x-landing.instructor-card :instructor="$instructor" />
                @endforeach
            </div>
            
        </div>

        {{-- === ROW 2: Gerak ke KANAN (Reverse) === --}}
        {{-- Memberikan efek dinamis (atas kiri, bawah kanan) --}}
        <div class="group/marquee w-full overflow-hidden flex select-none">
            
            <div class="flex gap-8 animate-marquee-reverse group-hover/marquee:[animation-play-state:paused] min-w-full flex-shrink-0 px-4">
                @foreach($instructorsReverse as $instructor)
                    <x-landing.instructor-card :instructor="$instructor" />
                @endforeach
            </div>

            <div aria-hidden="true" class="flex gap-8 animate-marquee-reverse group-hover/marquee:[animation-play-state:paused] min-w-full flex-shrink-0 px-4">
                @foreach($instructorsReverse as $instructor)
                    <x-landing.instructor-card :instructor="$instructor" />
                @endforeach
            </div>

        </div>

    </div>

    {{-- Overlay Fade Kiri & Kanan (Agar kartu terlihat muncul/hilang halus) --}}
    <div class="absolute inset-y-0 left-0 w-32 bg-gradient-to-r from-white/40 to-transparent z-20 pointer-events-none"></div>
    <div class="absolute inset-y-0 right-0 w-32 bg-gradient-to-l from-white/40 to-transparent z-20 pointer-events-none"></div>

</section>
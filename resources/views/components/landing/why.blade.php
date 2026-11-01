<section class="w-full relative h-auto py-24 md:py-32" x-data="{ activeTab: 'premium' }">
    <div class="container mx-auto px-6 h-full flex flex-col justify-center">

        {{-- 1. HEADER SECTION --}}
        <div class="text-center mb-16 space-y-2">
            <h2 class="font-body text-3xl md:text-4xl font-bold italic text-[#2F70AF]">
                Kualitas Akademik ITB,<br>Fleksibilitas Masa Depan
            </h2>
            <h3 class="font-body text-base md:text-lg font-medium italic text-[#7C98B6]">
                Standar pendidikan teknologi terbaik dalam genggaman Anda
            </h3>
        </div>

        {{-- 2. CARDS CONTAINER (Flexbox untuk Layout 3-2) --}}
        {{-- Kita gunakan Flexbox dengan Wrap dan Justify Center agar otomatis membentuk formasi 3 di atas, 2 di bawah --}}
        <div class="flex flex-wrap justify-center gap-6 lg:gap-8 max-w-6xl mx-auto">

            {{-- KARTU 1: Kurikulum Terupdate --}}
            <div class="w-full md:w-[calc(33.333%-2rem)] flex-grow-0 flex-shrink-0 group">
                <div class="
                    bg-white/35 border border-white/60 
                    aspect-square rounded-[40px] text-center 
                    shadow-[3px_3px_25px_0_rgba(70,162,193,0.43)] overflow-visible
                    flex flex-col items-center justify-center p-8
                    transition-transform duration-300 hover:-translate-y-2
                ">
                    {{-- Icon Container --}}
                    <div class="w-20 h-20 rounded-full bg-[#E0F2FE] flex items-center justify-center mb-6 text-[#0EA5E9] shadow-inner group-hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                        </svg>
                    </div>
                    <h4 class="font-body text-xl font-bold italic text-slate-900 mb-3">
                        Kurikulum Terupdate
                    </h4>
                    <p class="font-body text-sm text-[#587B9C] leading-relaxed">
                        Materi yang terus berevolusi mengikuti tren teknologi dan standar industri global terkini.
                    </p>
                </div>
            </div>

            {{-- KARTU 2: Ekosistem Unggulan ITB (CENTER PIECE) --}}
            <div class="w-full md:w-[calc(33.333%-2rem)] flex-grow-0 flex-shrink-0 group">
                <div class="
                    bg-white/35 border border-white/60 
                    aspect-square rounded-[40px] text-center 
                    shadow-[3px_3px_25px_0_rgba(70,162,193,0.43)] overflow-visible
                    flex flex-col items-center justify-center p-8
                    transition-transform duration-300 hover:-translate-y-2
                ">
                    <div class="w-20 h-20 rounded-full bg-[#E0F2FE] flex items-center justify-center mb-6 text-[#0EA5E9] shadow-inner group-hover:scale-110 transition-transform">
                        {{-- Icon Building/Institution --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/>
                        </svg>
                    </div>
                    <h4 class="font-body text-xl font-bold italic text-slate-900 mb-3">
                        Ekosistem Unggulan ITB
                    </h4>
                    <p class="font-body text-sm text-[#587B9C] leading-relaxed">
                        Akses langsung ke resources terbaik: tenaga pengajar ahli dan fasilitas laboratorium standar ITB.
                    </p>
                </div>
            </div>

            {{-- KARTU 3: Hybrid & Fleksibel --}}
            <div class="w-full md:w-[calc(33.333%-2rem)] flex-grow-0 flex-shrink-0 group">
                <div class="
                    bg-white/35 border border-white/60 
                    aspect-square rounded-[40px] text-center 
                    shadow-[3px_3px_25px_0_rgba(70,162,193,0.43)] overflow-visible
                    flex flex-col items-center justify-center p-8
                    transition-transform duration-300 hover:-translate-y-2
                ">
                    <div class="w-20 h-20 rounded-full bg-[#E0F2FE] flex items-center justify-center mb-6 text-[#0EA5E9] shadow-inner group-hover:scale-110 transition-transform">
                        {{-- Icon Hybrid/Location --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="currentColor">
                           <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                    </div>
                    <h4 class="font-body text-xl font-bold italic text-slate-900 mb-3">
                        Hybrid & Fleksibel
                    </h4>
                    <p class="font-body text-sm text-[#587B9C] leading-relaxed">
                        Terdapat Program Course fleksibel: Pelatihan tatap muka (Offline) atau jarak jauh (Online).
                    </p>
                </div>
            </div>

            {{-- BARIS KEDUA (Otomatis turun dan centered karena flex-wrap + justify-center) --}}

            {{-- KARTU 4: Review oleh Ahli --}}
            <div class="w-full md:w-[calc(33.333%-2rem)] flex-grow-0 flex-shrink-0 group">
                <div class="
                    bg-white/35 border border-white/60 
                    aspect-square rounded-[40px] text-center 
                    shadow-[3px_3px_25px_0_rgba(70,162,193,0.43)] overflow-visible
                    flex flex-col items-center justify-center p-8
                    transition-transform duration-300 hover:-translate-y-2
                ">
                    <div class="w-20 h-20 rounded-full bg-[#E0F2FE] flex items-center justify-center mb-6 text-[#0EA5E9] shadow-inner group-hover:scale-110 transition-transform">
                        {{-- Icon User Check --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="currentColor">
                           <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <h4 class="font-body text-xl font-bold italic text-slate-900 mb-3">
                        Review oleh Ahli
                    </h4>
                    <p class="font-body text-sm text-[#587B9C] leading-relaxed">
                        Tugas diperiksa langsung secara menyeluruh oleh profesional, bukan sekadar penilaian otomatis.
                    </p>
                </div>
            </div>

            {{-- KARTU 5: Solusi Spesifik --}}
            <div class="w-full md:w-[calc(33.333%-2rem)] flex-grow-0 flex-shrink-0 group">
                <div class="
                    bg-white/35 border border-white/60 
                    aspect-square rounded-[40px] text-center 
                    shadow-[3px_3px_25px_0_rgba(70,162,193,0.43)] overflow-visible
                    flex flex-col items-center justify-center p-8
                    transition-transform duration-300 hover:-translate-y-2
                ">
                    <div class="w-20 h-20 rounded-full bg-[#E0F2FE] flex items-center justify-center mb-6 text-[#0EA5E9] shadow-inner group-hover:scale-110 transition-transform">
                        {{-- Icon Puzzle/Specific --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 19h-6V5h6v14zm-4-2h2V7h-2v10zM5 19h6V5H5v14zm2-2h2V7H7v10z"/>
                        </svg>
                    </div>
                    <h4 class="font-body text-xl font-bold italic text-slate-900 mb-3">
                        Solusi Spesifik
                    </h4>
                    <p class="font-body text-sm text-[#587B9C] leading-relaxed">
                        Program pelatihan yang dapat disesuaikan (customized) untuk kebutuhan spesifik individu.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>
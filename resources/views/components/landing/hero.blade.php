<section class="relative w-full min-h-screen flex items-center pt-20 pb-20 md:py-0">

    <div class="mx-auto max-w-6xl px-6 grid grid-cols-1 lg:grid-cols-[1.2fr_0.8fr] gap-10 items-center h-full">

        {{-- LEFT CONTENT --}}
        <div class="space-y-6 text-center lg:text-left relative z-10">

            {{-- TITLE --}}

            <h1 class="font-heading
                       text-4xl md:text-5xl lg:text-6xl
                       font-extrabold italic
                       leading-tight tracking-tight">

                <span class="text-sky-400">
                    Tingkatkan Skill,
                </span><br>

                <span class="text-blue-700">
                    Investasi Terbaik untuk
                </span><br>

                <span class="text-blue-700">
                    Masa Depan Karir Anda
                </span>
            </h1>

            <p class="font-body
                      text-gray-700
                      max-w-xl
                      text-base md:text-lg
                      leading-relaxed
                      mx-auto lg:mx-0">
                Akses materi praktis dengan kurikulum terbaru.
                Tingkatkan kompetensi profesional Anda bersama
                para praktisi terbaik.
            </p>

            {{-- CTA --}}
            <div class="flex flex-wrap justify-center lg:justify-start gap-4 pt-4">

                <a href="#"
                   class="font-body px-8 py-4 rounded-full
                          bg-blue-600 text-white font-bold
                          hover:bg-blue-700 transition">
                    Become Member
                </a>

                <a href="{{ route('login') }}" wire:navigate
                   class="font-body px-8 py-4 rounded-full
                          border-2 border-blue-600
                          text-blue-600 font-bold
                          hover:bg-blue-50 transition">
                    Join for Free
                </a>
            </div>

        </div>

        {{-- RIGHT VISUAL --}}
        <div class="relative flex justify-center">
            <img
                src="{{ asset('assets/img/aset1.webp') }}"
                alt="Hero"
                class="relative z-10 w-[320px] md:w-[420px] lg:w-[480px]"
            />
        </div>

    </div>
</section>

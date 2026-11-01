<nav
    id="floating-navbar"
    class="fixed top-6 left-1/2 -translate-x-1/2 z-50">

    <div
        class="flex items-center justify-between gap-6
               px-8 py-4
               rounded-full
               bg-white/70 backdrop-blur-md supports-[backdrop-filter]:bg-white/60
               border border-white/40
               shadow-[0_8px_30px_rgba(0,0,0,0.08)]
               min-w-[90vw] max-w-6xl">

        <!-- LEFT: LOGO -->
        <div class="flex items-center gap-3">
            <img
                src="{{ asset('img/Logo_PT LAPI_PNG.png') }}"
                alt="Logo"
                class="h-8 w-auto"
            />
        </div>

        <!-- RIGHT: ACTIONS -->
        <div class="flex items-center gap-4">
            <!-- LANGUAGE -->
            <button
                class="flex items-center gap-2 px-4 py-2 rounded-full
                       border border-blue-500/40
                       text-blue-600 font-medium
                       hover:bg-blue-50 transition">
                🌐 ID
            </button>

            <!-- REGISTER -->
            <a
                href="{{ route('register') }}" wire:navigate
                class="px-6 py-2 rounded-full
                       border border-blue-600
                       text-blue-600 font-semibold
                       hover:bg-blue-50 transition">
                Daftar
            </a>

            <!-- LOGIN -->
            <a
                href="{{ route('login') }}" wire:navigate
                class="px-6 py-2 rounded-full
                       bg-blue-600 text-white font-semibold
                       hover:bg-blue-700 transition">
                Login
            </a>
        </div>
    </div>
</nav>

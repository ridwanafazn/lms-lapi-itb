<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Course Workspace') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-[#F8F9FA] text-slate-900 overflow-hidden">

    @php
        // Ambil ID Course dari URL secara otomatis untuk merender sidebar
        $routeCourse = request()->route('course') ?? request()->route('courseId');
        $workspaceCourse = is_object($routeCourse) ? $routeCourse : \App\Models\Course::find($routeCourse);
    @endphp

    {{-- WRAPPER ALPINEJS UNTUK RESPONSIVE SIDEBAR MOBILE & DESKTOP SHRINK --}}
    <div x-data="{ sidebarOpen: window.innerWidth >= 1024 }" class="flex h-screen w-full relative overflow-hidden">

        {{-- MOBILE OVERLAY (Latar hitam saat sidebar dibuka di HP) --}}
        <div x-show="sidebarOpen"
             x-cloak
             @click="sidebarOpen = false"
             x-transition.opacity
             class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden backdrop-blur-sm">
        </div>

        {{-- LEFT SIDEBAR DENGAN EFEK SLIDE DI MOBILE DAN PUSH DI DESKTOP --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:-ml-72'"
               class="fixed lg:static inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 flex flex-col flex-shrink-0 shadow-2xl lg:shadow-none transition-all duration-300 ease-in-out">

            {{-- Header Sidebar & Tombol Close (Mobile) --}}
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 shrink-0">
                <a wire:navigate href="{{ route('instructor.programs') }}" class="flex items-center text-sm font-bold text-slate-500 hover:text-indigo-600 transition-colors group">
                    <svg class="w-4 h-4 mr-2 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Teaching Programs
                </a>

                {{-- Tombol Tutup Sidebar khusus Mobile --}}
                <button @click="sidebarOpen = false" class="lg:hidden p-2 text-slate-400 hover:text-slate-600 bg-slate-50 rounded-lg">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if($workspaceCourse)
            {{-- Status Course & Feedback Admin --}}
            <div class="p-6 border-b border-slate-100 space-y-4 shrink-0">
                <div class="flex items-center justify-center">
                    <span class="px-4 py-1.5 text-xs font-black uppercase tracking-wider rounded-lg border
                        {{ $workspaceCourse->status->value === 'published' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' :
                           ($workspaceCourse->status->value === 'rejected' ? 'bg-red-50 text-red-600 border-red-200' :
                           ($workspaceCourse->status->value === 'pending' ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-slate-50 text-slate-500 border-slate-200')) }}">
                        Status: {{ $workspaceCourse->status->label() }}
                    </span>
                </div>

                @if($workspaceCourse->status->value === 'rejected')
                    <button class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg flex items-center justify-center transition-colors shadow-sm shadow-blue-200">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        Feedback Admin
                    </button>
                @endif
            </div>

            {{-- Navigasi Tab Workspace --}}
            <nav class="flex-1 overflow-y-auto p-4 space-y-1.5">

                {{-- Tab Curriculum --}}
                <a wire:navigate href="{{ route('instructor.course.curriculum', $workspaceCourse->id) }}" class="flex items-center px-4 py-3 text-sm font-bold rounded-xl transition-colors {{ request()->routeIs('instructor.course.curriculum') || request()->routeIs('instructor.course.builder') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' }}">
                    <div class="w-6 flex justify-center mr-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                    </div>
                    Curriculum
                </a>

                <div class="my-4 border-t border-slate-100"></div>

                {{-- Tab Course Identity --}}
                <a wire:navigate href="{{ route('instructor.course.identity', $workspaceCourse->id) }}" class="flex items-center px-4 py-3 text-sm font-bold rounded-xl transition-colors {{ request()->routeIs('instructor.course.identity') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' }}">
                    <div class="w-6 flex justify-center mr-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    </div>
                    Course Identity
                </a>

                {{-- Tab Goals & Requirement --}}
                <a wire:navigate href="{{ route('instructor.course.goals', $workspaceCourse->id) }}" class="flex items-center px-4 py-3 text-sm font-bold rounded-xl transition-colors {{ request()->routeIs('instructor.course.goals') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' }}">
                    <div class="w-6 flex justify-center mr-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                    </div>
                    Goals & Requirement
                </a>

                {{-- Tab Certificate --}}
                <a wire:navigate href="{{ route('instructor.course.certificate', $workspaceCourse->id) }}" class="flex items-center px-4 py-3 text-sm font-bold rounded-xl transition-colors {{ request()->routeIs('instructor.course.certificate') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' }}">
                    <div class="w-6 flex justify-center mr-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    </div>
                    Certificate
                </a>

                {{-- Tab Course Settings --}}
                <a wire:navigate href="{{ route('instructor.course.settings', $workspaceCourse->id) }}" class="flex items-center px-4 py-3 text-sm font-bold rounded-xl transition-colors {{ request()->routeIs('instructor.course.settings') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' }}">
                    <div class="w-6 flex justify-center mr-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>
                    </div>
                    Course Settings
                </a>

            </nav>
            @endif
        </aside>

        {{-- MAIN AREA (Sebelah Kanan) --}}
        <div class="flex-1 flex flex-col h-screen overflow-hidden bg-[#F8F9FA] relative w-full">

            @if($workspaceCourse)
            {{-- Top Navbar (Global Workspace Header) --}}
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 shrink-0 z-10 w-full">

                <div class="flex items-center gap-3 w-full">
                    {{-- Tombol Hamburger (Sekarang Selalu Muncul & Mem-toggle sidebarOpen) --}}
                    <button @click="sidebarOpen = !sidebarOpen" class="p-2 text-slate-500 hover:text-indigo-600 bg-slate-50 hover:bg-indigo-50 rounded-lg transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>

                    {{-- Icon Buku TELAH DIHAPUS --}}

                    {{-- Judul Course --}}
                    <h1 class="text-base sm:text-lg font-bold text-slate-800 truncate flex-1 ml-2">{{ $workspaceCourse->title }}</h1>
                </div>

                <div class="flex items-center gap-3 pl-4 shrink-0">
                    {{-- Slot dinamis aksi (Save / Submit) --}}
                    @if(isset($actions))
                        {{ $actions }}
                    @endif
                </div>
            </header>
            @endif

            {{-- Dinamic Content Slot --}}
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>

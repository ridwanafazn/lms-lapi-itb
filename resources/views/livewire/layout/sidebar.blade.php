<?php
use Livewire\Volt\Component;
new class extends Component {};
?>

@php
    // Logika Server-Side untuk mendeteksi Workspace yang sedang aktif
    $currentWorkspace = 'learning'; // Default
    
    // Variabel untuk mengecek apakah kita TEPAT berada di halaman dashboard, 
    // bukan di sub-menunya.
    $isExactAdminDashboard = request()->routeIs('admin.dashboard');
    $isExactInstructorDashboard = request()->routeIs('instructor.dashboard');
    $isExactLearningDashboard = request()->routeIs('dashboard'); // Asumsi route name untuk dashboard learning

    if (request()->is('admin*')) {
        $currentWorkspace = 'admin';
    } elseif (request()->is('instructor*')) {
        $currentWorkspace = 'instructor';
    }
@endphp

<aside
    x-data="{
        activeGroup: '{{ $currentWorkspace }}'
    }"
    :class="sidebarOpen ? 'translate-x-0 lg:w-64' : '-translate-x-full lg:translate-x-0 lg:w-0'"
    class="fixed lg:static top-0 left-0 z-50 h-screen bg-white border-r border-gray-200
           rounded-r-2xl transition-all duration-300 ease-in-out overflow-hidden
           flex flex-col shadow-xl lg:shadow-none"
>
    <div class="w-64 h-full flex flex-col flex-shrink-0">

        {{-- Logo --}}
        <div class="h-20 flex items-center justify-center px-6 mt-2 flex-shrink-0">
            <a href="{{ url('/') }}" wire:navigate class="transition-opacity hover:opacity-80">
                <img src="{{ asset('img/Logo_PT LAPI_PNG.png') }}" alt="Logo" class="h-10 w-auto">
            </a>
        </div>

        {{-- [PERUBAHAN] Garis pemisah tipis dengan margin vertikal yang lega --}}
        <div class="px-6 my-4">
            <div class="border-t border-gray-200"></div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-8 custom-scrollbar">

            {{-- ==========================================
                 1. ADMIN WORKSPACE
                 ========================================== --}}
            @role('admin')
            <div>
                {{-- [PERUBAHAN LOGIKA HIGHLIGHT] --}}
                <a href="{{ route('admin.dashboard') }}" wire:navigate
                    class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-md transition-colors 
                    {{ $isExactAdminDashboard ? 'bg-blue-50 text-blue-600 font-bold' : ($currentWorkspace === 'admin' ? 'text-gray-900 font-bold hover:bg-gray-50' : 'text-gray-600 font-medium hover:bg-gray-50 hover:text-gray-900') }}">
                    <div class="flex items-center gap-3">
                        
                        {{-- [PERUBAHAN SVG DISINI] --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 {{ $isExactAdminDashboard ? 'text-blue-600' : 'text-gray-500' }}" fill="currentColor" viewBox="0 0 256 256">
                            <path d="M198.13,194.85A8,8,0,0,1,192,208H24a8,8,0,0,1-6.12-13.15c14.94-17.78,33.52-30.41,54.17-37.17a68,68,0,1,1,71.9,0C164.6,164.44,183.18,177.07,198.13,194.85ZM255.18,154a8,8,0,0,1-6.94,4,7.92,7.92,0,0,1-4-1.07l-4.67-2.7a23.92,23.92,0,0,1-7.58,4.39V164a8,8,0,0,1-16,0v-5.38a23.92,23.92,0,0,1-7.58-4.39l-4.67,2.7a7.92,7.92,0,0,1-4,1.07,8,8,0,0,1-4-14.93l4.66-2.69a23.6,23.6,0,0,1,0-8.76l-4.66-2.69a8,8,0,1,1,8-13.86l4.67,2.7a23.92,23.92,0,0,1,7.58-4.39V108a8,8,0,0,1,16,0v5.38a23.92,23.92,0,0,1,7.58,4.39l4.67-2.7a8,8,0,1,1,8,13.86l-4.66,2.69a23.6,23.6,0,0,1,0,8.76l4.66,2.69A8,8,0,0,1,255.18,154ZM224,144a8,8,0,1,0-8-8A8,8,0,0,0,224,144Z"></path>
                        </svg>

                        <span class="whitespace-nowrap">Admin Dashboard</span>
                    </div>
                    
                    {{-- Ikon panah (chevron) biarkan saja, ini efek putar untuk buka-tutup grup --}}
                    <svg class="w-3 h-3 transition-transform duration-300 flex-shrink-0 text-gray-400"
                        :class="activeGroup === 'admin' ? 'rotate-0' : '-rotate-90'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </a>

                <div x-show="activeGroup === 'admin'" x-collapse x-cloak class="mt-1 space-y-1 pl-1">
                    <x-sidebar-link :href="route('admin.reports')" :active="request()->routeIs('admin.reports')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 2v-6m-8-2h11a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h2m2 4h6" /></svg>
                        <span class="whitespace-nowrap">Laporan</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('admin.users')" :active="request()->routeIs('admin.users')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        <span class="whitespace-nowrap">Pengguna</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('admin.programs')" :active="request()->routeIs('admin.programs')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        <span class="whitespace-nowrap">Program</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('admin.courses')" :active="request()->routeIs('admin.courses')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>
                        <span class="whitespace-nowrap">Pelatihan</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('admin.batches')" :active="request()->routeIs('admin.batches')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span class="whitespace-nowrap">Angkatan</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('admin.approvals')" :active="request()->routeIs('admin.approvals')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                        <span class="whitespace-nowrap">Persetujuan</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('admin.transactions')" :active="request()->routeIs('admin.transactions')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <span class="whitespace-nowrap">Transaksi</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('admin.certificates')" :active="request()->routeIs('admin.certificates')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z" /></svg>
                        <span class="whitespace-nowrap">Sertifikat</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('admin.feedback')" :active="request()->routeIs('admin.feedback')" color="blue">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                        <span class="whitespace-nowrap">Umpan Balik</span>
                    </x-sidebar-link>
                </div>
            </div>
            @endrole

            {{-- ==========================================
                 2. INSTRUCTOR WORKSPACE
                 ========================================== --}}
            @role('instructor')
            <div>
                <a href="{{ route('instructor.dashboard') }}" wire:navigate
                    class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-md transition-colors 
                    {{ $isExactInstructorDashboard ? 'bg-green-50 text-green-600 font-bold' : ($currentWorkspace === 'instructor' ? 'text-gray-900 font-bold hover:bg-gray-50' : 'text-gray-600 font-medium hover:bg-gray-50 hover:text-gray-900') }}">
                    <div class="flex items-center gap-3">
                        
                        {{-- [PERUBAHAN SVG DISINI] --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 {{ $isExactInstructorDashboard ? 'text-green-600' : 'text-gray-500' }}" fill="currentColor" viewBox="0 0 256 256">
                            <path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H53.39a8,8,0,0,0,7.23-4.57,48,48,0,0,1,86.76,0,8,8,0,0,0,7.23,4.57H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40ZM104,168a32,32,0,1,1,32-32A32,32,0,0,1,104,168Zm112,32H159.43a63.93,63.93,0,0,0-13.16-16H192a8,8,0,0,0,8-8V80a8,8,0,0,0-8-8H64a8,8,0,0,0-8,8v96a8,8,0,0,0,6,7.75A63.72,63.72,0,0,0,48.57,200H40V56H216Z"></path>
                        </svg>

                        <span class="whitespace-nowrap">Instructor Dashboard</span>
                    </div>
                    <svg class="w-3 h-3 transition-transform duration-300 flex-shrink-0 text-gray-400"
                        :class="activeGroup === 'instructor' ? 'rotate-0' : '-rotate-90'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </a>

                <div x-show="activeGroup === 'instructor'" x-collapse x-cloak class="mt-1 space-y-1 pl-1">
                    <x-sidebar-link :href="route('instructor.programs')" :active="request()->routeIs('instructor.programs')" color="green">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" /></svg>
                        <span class="whitespace-nowrap">Program Ajar</span>
                    </x-sidebar-link>
                </div>
            </div>
            @endrole

            {{-- ==========================================
                 3. LEARNING WORKSPACE (All Roles)
                 ========================================== --}}
            
            {{-- [PERUBAHAN] Garis pemisah tipis dengan margin vertikal yang lega --}}
            <div class="px-6 my-4">
                <div class="border-t border-gray-200"></div>
            </div>    
        
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate
                    class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-md transition-colors 
                    {{ $isExactLearningDashboard ? 'bg-indigo-50 text-indigo-600 font-bold' : ($currentWorkspace === 'learning' ? 'text-gray-900 font-bold hover:bg-gray-50' : 'text-gray-600 font-medium hover:bg-gray-50 hover:text-gray-900') }}">
                    <div class="flex items-center gap-3">
                        
                        {{-- [PERUBAHAN SVG DISINI] --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 {{ $isExactLearningDashboard ? 'text-indigo-600' : 'text-gray-500' }}" fill="currentColor" viewBox="0 0 256 256">
                            <path d="M176,207.24a119,119,0,0,0,16-7.73V240a8,8,0,0,1-16,0Zm11.76-88.43-56-29.87a8,8,0,0,0-7.52,14.12L171,128l17-9.06Zm64-29.87-120-64a8,8,0,0,0-7.52,0l-120,64a8,8,0,0,0,0,14.12L32,117.87v48.42a15.91,15.91,0,0,0,4.06,10.65C49.16,191.53,78.51,216,128,216a130,130,0,0,0,48-8.76V130.67L171,128l-43,22.93L43.83,106l0,0L25,96,128,41.07,231,96l-18.78,10-.06,0L188,118.94a8,8,0,0,1,4,6.93v73.64a115.63,115.63,0,0,0,27.94-22.57A15.91,15.91,0,0,0,224,166.29V117.87l27.76-14.81a8,8,0,0,0,0-14.12Z"></path>
                        </svg>

                        <span class="whitespace-nowrap">Learning Dashboard</span>
                    </div>
                    <svg class="w-3 h-3 transition-transform duration-300 flex-shrink-0 text-gray-400"
                        :class="activeGroup === 'learning' ? 'rotate-0' : '-rotate-90'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </a>

                <div x-show="activeGroup === 'learning'" x-collapse x-cloak class="mt-1 space-y-1 pl-1">
                    <x-sidebar-link :href="route('student.browse')" :active="request()->routeIs('student.browse')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        <span class="whitespace-nowrap">Katalog Pelatihan</span>
                    </x-sidebar-link>
                    
                    <x-sidebar-link :href="route('student.my-courses')" :active="request()->routeIs('student.my-courses')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="whitespace-nowrap">Program Saya</span>
                    </x-sidebar-link>
                    
                    <x-sidebar-link :href="route('student.announcement')" :active="request()->routeIs('student.announcement')" color="indigo">
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
                                <span class="whitespace-nowrap">Pengumuman</span>
                            </div>
                            <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full flex-shrink-0">1</span>
                        </div>
                    </x-sidebar-link>
                    
                    <x-sidebar-link :href="route('student.forum')" :active="request()->routeIs('student.forum')" color="indigo">
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" /></svg>
                                <span class="whitespace-nowrap">Forum</span>
                            </div>
                            <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full flex-shrink-0">3</span>
                        </div>
                    </x-sidebar-link>
                    
                    <x-sidebar-link :href="route('student.certificate')" :active="request()->routeIs('student.certificate')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span class="whitespace-nowrap">Sertifikat</span>
                    </x-sidebar-link>
                </div>
            </div>

            {{-- Workspace Links (Phase 2) — Peran Spesifik, Gaya Sama Sidebar, Bisa Shrink --}}
            <div x-data="{ workspaceOpen: true }">
                <button @click="workspaceOpen = !workspaceOpen"
                    class="w-full flex items-center justify-between px-3 py-2 text-xs rounded-md font-bold uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                    <span>Ruang Kerja</span>
                    <svg class="w-3 h-3 transition-transform duration-300 flex-shrink-0 text-gray-400"
                         :class="workspaceOpen ? 'rotate-0' : '-rotate-90'"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="workspaceOpen" x-collapse x-cloak class="mt-1 space-y-1 pl-1">

                @role('admin')
                    <x-sidebar-link :href="route('billing.index')" :active="request()->routeIs('billing.index')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                        <span class="whitespace-nowrap">Tagihan & Perpanjangan</span>
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('corporate.dashboard')" :active="request()->routeIs('corporate.dashboard')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H5v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <span class="whitespace-nowrap">Pembelian Korporat / HR</span>
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('admin.coupons')" :active="request()->routeIs('admin.coupons')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5v14h14V5H5z" /></svg>
                        <span class="whitespace-nowrap">Kupon</span>
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('admin.refunds')" :active="request()->routeIs('admin.refunds')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 3v5h-.582m-15.356-2A8.001 8.001 0 0021.418 9m0 0H15" /></svg>
                        <span class="whitespace-nowrap">Refund</span>
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('admin.payouts')" :active="request()->routeIs('admin.payouts')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="whitespace-nowrap">Honor Instruktur</span>
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('admin.batch-operations')" :active="request()->routeIs('admin.batch-operations')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span class="whitespace-nowrap">Operasi Batch</span>
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('admin.attendance')" :active="request()->routeIs('admin.attendance')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        <span class="whitespace-nowrap">Absensi Live</span>
                    </x-sidebar-link>
                @endrole
                @role('instructor')
                    <x-sidebar-link :href="route('instructor.payouts')" :active="request()->routeIs('instructor.payouts')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="whitespace-nowrap">Honor Saya</span>
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('instructor.attendance')" :active="request()->routeIs('instructor.attendance')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        <span class="whitespace-nowrap">Absensi Live</span>
                    </x-sidebar-link>
                @endrole
                @if(auth()->user() && auth()->user()->hasAnyRole(['admin', 'instructor']))
                    <x-sidebar-link :href="route('grading.essays')" :active="request()->routeIs('grading.essays')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036 2.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.464z" /></svg>
                        <span class="whitespace-nowrap">Penilaian Esai</span>
                    </x-sidebar-link>
                @endif
                @if(auth()->user() && auth()->user()->hasRole('student'))
                    <x-sidebar-link :href="route('student.waitlist')" :active="request()->routeIs('student.waitlist')" color="indigo">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 7l-5.714 2.143L13 21l2.286-5.857L10 12.857 15.286 7z" /></svg>
                        <span class="whitespace-nowrap">Antrean Batch</span>
                    </x-sidebar-link>
                @endif
            </div>
        </div>
    </div>
</aside>
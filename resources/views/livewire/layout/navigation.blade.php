<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        // Menggunakan navigate: true agar logout terasa instan tanpa flash putih
        $this->redirect('/', navigate: true);
    }

    // Listener agar navbar otomatis re-render jika ada perubahan profil atau membership
    // di komponen Livewire lain (misalnya setelah transaksi atau update profil)
    #[On('profile-updated')]
    #[On('membership-updated')]
    public function with(): array
    {
        $user = Auth::user();

        // Memaksa load data terbaru dari database (mengatasi stale cache di memori Auth)
        $user->refresh();

        // 1. Tentukan Role Berdasarkan Hirarki Otoritas (Mencegah konflik jika punya multi-role)
        if ($user->hasRole('super-admin')) {
            $roleName = 'super-admin';
        } elseif ($user->hasRole('admin')) {
            $roleName = 'admin';
        } elseif ($user->hasRole('instructor')) {
            $roleName = 'instructor';
        } else {
            $roleName = 'student'; // Default terendah
        }

        // 2. Tentukan Tampilan Nama Role
        $displayRole = match($roleName) {
            'admin' => 'Admin',
            'instructor' => 'Instruktur',
            'student' => 'Peserta',
            'super-admin' => 'Super Admin',
            default => ucfirst($roleName),
        };

        // 3. Tentukan Tujuan Route Dashboard Berdasarkan Role (Hanya untuk Dropdown)
        $dashboardRoute = match($roleName) {
            'admin', 'super-admin' => 'admin.dashboard',
            'instructor' => 'instructor.dashboard',
            default => 'dashboard', // Default untuk student
        };

        $names = explode(' ', trim($user->name));
        $firstName = $names[0];

        return [
            'user' => $user,
            'displayRole' => $displayRole,
            'dashboardRoute' => $dashboardRoute,
            'firstName' => $firstName,
            'hasMembership' => $user->hasActiveMembership(),
            'membershipLevel' => $user->membershipPlan?->level,
        ];
    }
}; ?>

<nav class="bg-white border border-gray-100 sticky top-0 z-40 rounded-b-2xl mx-4 sm:mx-6 shadow-sm">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20">
            {{-- SISI KIRI --}}
            <div class="flex items-center">
                <button @click="sidebarOpen = !sidebarOpen" 
                        class="p-2.5 rounded-full focus:outline-none transition-all duration-360"
                        :class="sidebarOpen ? 'bg-secondary1 text-primary' : 'bg-gray-50 text-gray-500 hover:text-gray-700 hover:bg-gray-200'">
                    <svg class="h-6 w-6 transition-transform duration-360" :class="sidebarOpen ? 'rotate-180' : 'rotate-0'" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            {{-- SISI KANAN --}}
            <div class="flex items-center gap-4 sm:gap-5">

                {{-- 1. Tombol Admin Dashboard --}}
                @role('admin')

                <a href="{{ route('admin.dashboard') }}" wire:navigate.hover class="group flex items-center gap-1 font-body text-sm font-semibold hover:bg-secondary2 rounded-lg py-2 px-4 transition duration-150 hidden sm:flex">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary group-hover:text-text-pastel transition-colors duration-150" fill="currentColor" viewBox="0 0 256 256">
                        <path d="M198.13,194.85A8,8,0,0,1,192,208H24a8,8,0,0,1-6.12-13.15c14.94-17.78,33.52-30.41,54.17-37.17a68,68,0,1,1,71.9,0C164.6,164.44,183.18,177.07,198.13,194.85ZM255.18,154a8,8,0,0,1-6.94,4,7.92,7.92,0,0,1-4-1.07l-4.67-2.7a23.92,23.92,0,0,1-7.58,4.39V164a8,8,0,0,1-16,0v-5.38a23.92,23.92,0,0,1-7.58-4.39l-4.67,2.7a7.92,7.92,0,0,1-4,1.07,8,8,0,0,1-4-14.93l4.66-2.69a23.6,23.6,0,0,1,0-8.76l-4.66-2.69a8,8,0,1,1,8-13.86l4.67,2.7a23.92,23.92,0,0,1,7.58-4.39V108a8,8,0,0,1,16,0v5.38a23.92,23.92,0,0,1,7.58,4.39l4.67-2.7a8,8,0,1,1,8,13.86l-4.66,2.69a23.6,23.6,0,0,1,0,8.76l4.66,2.69A8,8,0,0,1,255.18,154ZM224,144a8,8,0,1,0-8-8A8,8,0,0,0,224,144Z"></path>
                    </svg>
                    <span class="hidden md:inline text-text-blue group-hover:text-text-pastel transition-colors duration-150">Admin Dashboard</span>
                </a>
                @endrole

                {{-- 2. Tombol Instructor Dashboard --}}
                @role('instructor')
                <a href="{{ route('instructor.dashboard') }}" wire:navigate.hover class="group flex items-center gap-1 font-body text-sm font-semibold hover:bg-secondary2 rounded-lg py-2 px-4 transition duration-150 hidden sm:flex">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary group-hover:text-text-pastel transition-colors duration-150" fill="currentColor" viewBox="0 0 256 256">
                        <path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H53.39a8,8,0,0,0,7.23-4.57,48,48,0,0,1,86.76,0,8,8,0,0,0,7.23,4.57H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40ZM104,168a32,32,0,1,1,32-32A32,32,0,0,1,104,168Zm112,32H159.43a63.93,63.93,0,0,0-13.16-16H192a8,8,0,0,0,8-8V80a8,8,0,0,0-8-8H64a8,8,0,0,0-8,8v96a8,8,0,0,0,6,7.75A63.72,63.72,0,0,0,48.57,200H40V56H216Z"></path>
                    </svg>
                    <span class="hidden md:inline text-text-blue group-hover:text-text-pastel transition-colors duration-150">Instructor Dashboard</span>
                </a>
                @endrole

                {{-- 3. Link Dashboard Pembelajaran (Akses Semua Role) --}}
                
                <a href="{{ route('dashboard') }}" wire:navigate.hover class="group flex items-center gap-1 font-body text-sm font-semibold hover:bg-secondary2 rounded-lg py-2 px-4 transition duration-150 hidden sm:flex">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary group-hover:text-text-pastel transition-colors duration-150" fill="currentColor" viewBox="0 0 256 256">
                        <path d="M176,207.24a119,119,0,0,0,16-7.73V240a8,8,0,0,1-16,0Zm11.76-88.43-56-29.87a8,8,0,0,0-7.52,14.12L171,128l17-9.06Zm64-29.87-120-64a8,8,0,0,0-7.52,0l-120,64a8,8,0,0,0,0,14.12L32,117.87v48.42a15.91,15.91,0,0,0,4.06,10.65C49.16,191.53,78.51,216,128,216a130,130,0,0,0,48-8.76V130.67L171,128l-43,22.93L43.83,106l0,0L25,96,128,41.07,231,96l-18.78,10-.06,0L188,118.94a8,8,0,0,1,4,6.93v73.64a115.63,115.63,0,0,0,27.94-22.57A15.91,15.91,0,0,0,224,166.29V117.87l27.76-14.81a8,8,0,0,0,0-14.12Z"></path>
                    </svg>
                    <span class="hidden md:inline text-text-blue group-hover:text-text-pastel transition-colors duration-150">Learning Dashboard</span>
                </a>

                

                {{-- Link Membership --}}
                @php
                    $membershipClass = 'px-8 py-1.5 rounded-md text-xs font-semibold font-body uppercase tracking-wider hidden sm:block transition-all duration-300';

                    if (!$hasMembership) {
                        $membershipClass .= ' bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-600';
                        $label = 'Langganan';
                    } elseif ($membershipLevel === 'standard') {
                        $membershipClass .= ' bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700';
                        $label = 'Standard';
                    } elseif ($membershipLevel === 'pro') {
                        $membershipClass .= ' bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg shadow-purple-500/30 hover:scale-105';
                        $label = 'Pro Member';
                    }
                @endphp

                <a href="{{ route('membership') }}" wire:navigate.hover class="{{ $membershipClass }}">
                    {{ $label }}
                </a>

                {{-- Dropdown Profil User --}}
                <x-dropdown align="right" width="64">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 pl-1 pr-3 py-1 border-2 border-gray3 rounded-3xl text-[#3730A3] font-bold bg-white hover:bg-indigo-50 focus:outline-none transition duration-150 shadow-sm ml-1">
                            <div class="w-10 h-10 flex-shrink-0 rounded-full bg-text-pastel text-white flex items-center justify-center overflow-hidden">
                                @if($user->hasMedia('avatar'))
                                    <img src="{{ $user->getFirstMediaUrl('avatar') }}" class="w-full h-full object-cover">
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </div>
                            <div class="text-sm text-text-blue font-body font-normal hidden sm:block capitalize">{{ $firstName }}</div>
                            <svg class="w-4 h-4 fill-current opacity-70" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        {{-- Identitas User --}}
                        <div class="px-5 py-4 bg-gray-50/80 border-b border-gray-200 rounded-t-md">
                            <div class="flex items-center gap-3">
                                @if($user->hasMedia('avatar'))
                                    <img src="{{ $user->getFirstMediaUrl('avatar') }}" class="w-10 h-10 flex-shrink-0 rounded-full object-cover ring-2 ring-white shadow-sm">
                                @else
                                    <div class="w-10 h-10 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-[#3730A3] font-bold text-lg ring-2 ring-white shadow-sm">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-bold text-gray-900 truncate">{{ $user->name }}</div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 uppercase tracking-wide">
                                            {{ $displayRole }}
                                        </span>

                                        @if($hasMembership)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 uppercase tracking-wide">
                                                {{ ucfirst($membershipLevel) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1 truncate font-medium">{{ $user->email }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Account --}}
                        <div class="border-t border-gray-100 py-2">
                            <div class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Akun</div>

                            <x-dropdown-link :href="route('profile')" wire:navigate.hover class="flex items-center gap-3 group">
                                <svg class="w-5 h-5 text-gray-600 group-hover:text-gray-900 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <span class="font-medium text-gray-700 group-hover:text-gray-900">Edit Profil & Pengaturan</span>
                            </x-dropdown-link>

                            <x-dropdown-link :href="route('membership')" wire:navigate.hover class="flex items-center gap-3 group">
                                <svg class="w-5 h-5 text-gray-600 group-hover:text-gray-900 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                <span class="font-medium text-gray-700 group-hover:text-gray-900">Status Berlangganan</span>
                            </x-dropdown-link>
                        </div>

                        {{-- Logout --}}
                        <div class="border-t border-gray-100 pt-1 pb-1">
                            <button wire:click="logout" class="w-full text-left group">
                                <div class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                    <span>Keluar</span>
                                </div>
                            </button>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>
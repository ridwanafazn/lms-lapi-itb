<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profil & Pengaturan') }}
        </h2>
    </x-slot>

    <div class="py-8 bg-gray-50/50 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Dual Column Layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                {{-- Kolom Kiri: Sticky Sidebar (Identitas & Membership) --}}
                <div class="lg:col-span-4">
                    <div class="sticky top-8 space-y-6">
                        <livewire:profile.membership-card />

                        {{-- Quick Info / Bantuan (Opsional, pemanis UI psikologis) --}}
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                            <h4 class="text-sm font-semibold text-gray-900 mb-2">Butuh Bantuan?</h4>
                            <p class="text-xs text-gray-500 mb-4 leading-relaxed">Jika Anda mengalami kendala terkait tagihan atau akses kelas, tim support kami siap membantu.</p>
                            <a href="#" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                Hubungi Support
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Kolom Kanan: Main Content dengan Sectional Tabs (Alpine.js) --}}
                <div class="lg:col-span-8" x-data="{ activeTab: 'biodata' }">

                    {{-- Navigasi Tabs --}}
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
                        <nav class="flex flex-col sm:flex-row divide-y sm:divide-y-0 sm:divide-x divide-gray-200" aria-label="Tabs">
                            <button @click="activeTab = 'biodata'"
                                :class="activeTab === 'biodata' ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                                class="group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium focus:z-10 transition-colors">
                                <span>Biodata Diri</span>
                                <span aria-hidden="true" :class="activeTab === 'biodata' ? 'bg-indigo-600' : 'bg-transparent'" class="absolute inset-x-0 bottom-0 h-0.5 transition-colors"></span>
                            </button>

                            <button @click="activeTab = 'transaksi'"
                                :class="activeTab === 'transaksi' ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                                class="group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium focus:z-10 transition-colors">
                                <span>Riwayat Transaksi</span>
                                <span aria-hidden="true" :class="activeTab === 'transaksi' ? 'bg-indigo-600' : 'bg-transparent'" class="absolute inset-x-0 bottom-0 h-0.5 transition-colors"></span>
                            </button>

                            <button @click="activeTab = 'keamanan'"
                                :class="activeTab === 'keamanan' ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                                class="group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium focus:z-10 transition-colors">
                                <span>Keamanan Akses</span>
                                <span aria-hidden="true" :class="activeTab === 'keamanan' ? 'bg-indigo-600' : 'bg-transparent'" class="absolute inset-x-0 bottom-0 h-0.5 transition-colors"></span>
                            </button>
                        </nav>
                    </div>

                    {{-- Area Konten Tabs --}}
                    <div class="relative">

                        {{-- Tab 1: Biodata --}}
                        <div x-show="activeTab === 'biodata'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                            <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-200 shadow-sm">
                                <div class="mb-6 border-b border-gray-100 pb-4">
                                    <h3 class="text-lg font-bold text-gray-900">Informasi Biodata</h3>
                                    <p class="text-sm text-gray-500 mt-1">Kelola identitas publik dan data administratif Anda di platform ini.</p>
                                </div>
                                <livewire:profile.update-profile-information-form />
                            </div>
                        </div>

                        {{-- Tab 2: Transaksi --}}
                        <div x-show="activeTab === 'transaksi'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" x-cloak>
                            <livewire:profile.transaction-history />
                        </div>

                        {{-- Tab 3: Keamanan & Hapus Akun --}}
                        <div x-show="activeTab === 'keamanan'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" x-cloak>
                            <div class="space-y-6">
                                <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-200 shadow-sm">
                                    <div class="mb-6 border-b border-gray-100 pb-4">
                                        <h3 class="text-lg font-bold text-gray-900">Ubah Kata Sandi</h3>
                                        <p class="text-sm text-gray-500 mt-1">Pastikan akun Anda menggunakan kata sandi yang panjang dan acak untuk tetap aman.</p>
                                    </div>
                                    <livewire:profile.update-password-form />
                                </div>

                                <div class="bg-white p-6 sm:p-8 rounded-2xl border border-red-100 shadow-sm">
                                    <div class="mb-6 border-b border-red-50 pb-4">
                                        <h3 class="text-lg font-bold text-red-600">Zona Berbahaya</h3>
                                        <p class="text-sm text-gray-500 mt-1">Tindakan di bawah ini bersifat permanen dan tidak dapat dibatalkan.</p>
                                    </div>
                                    <livewire:profile.delete-user-form />
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

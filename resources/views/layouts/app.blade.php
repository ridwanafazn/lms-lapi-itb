<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-[#F5F6F8]">

    <div
        x-data="{
            sidebarOpen: localStorage.getItem('sidebarOpen') === null
                ? window.innerWidth >= 1024
                : localStorage.getItem('sidebarOpen') === 'true',
            init() {
                this.$watch('sidebarOpen', value => localStorage.setItem('sidebarOpen', value))
            }
        }"
        class="flex h-screen overflow-hidden relative"
    >
        <div
            x-show="sidebarOpen"
            x-cloak
            @click="sidebarOpen = false"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden backdrop-blur-sm"
        ></div>

        <livewire:layout.sidebar />

        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">

            <livewire:layout.navigation />

            <main class="p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @if(config('services.midtrans.client_key'))
        <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    @endif

    @stack('scripts')
</body>
</html>

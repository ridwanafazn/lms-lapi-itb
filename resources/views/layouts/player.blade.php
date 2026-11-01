<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $courseTitle ?? 'LMS Player' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased h-full bg-slate-50"
      x-data="{ sidebarOpen: JSON.parse(localStorage.getItem('playerSidebarOpen') || 'true') }"
      x-init="$watch('sidebarOpen', val => localStorage.setItem('playerSidebarOpen', val))">

    {{--
        HANYA RENDER SLOT.
        Sidebar & Header dipindah ke dalam komponen Livewire (Player.blade.php)
        agar bisa berinteraksi dengan logic backend.
    --}}
    {{ $slot }}

    @livewireScripts
</body>
</html>

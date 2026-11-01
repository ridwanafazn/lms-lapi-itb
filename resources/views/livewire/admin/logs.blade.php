<?php

use App\Models\ActivityLog;
use function Livewire\Volt\{state, computed, usesPagination, layout};

layout('layouts.app');
usesPagination();

state([
    'search' => '',
    'filterAction' => '', // State baru untuk filter kategori
]);

// Reset pagination saat searching atau filtering berubah
$updatingSearch = fn() => $this->resetPage();
$updatingFilterAction = fn() => $this->resetPage();

$logs = computed(function () {
    return ActivityLog::query()
        // Filter Search (Deskripsi / Nama Actor)
        ->when($this->search, function ($query) {
            $query->where(function ($q) {
                $q->where('description', 'like', '%' . $this->search . '%')
                  ->orWhere('actor_name', 'like', '%' . $this->search . '%')
                  ->orWhere('ip_address', 'like', '%' . $this->search . '%');
            });
        })
        // Filter Dropdown Kategori Action
        ->when($this->filterAction, function ($query) {
            $query->where('action', $this->filterAction);
        })
        ->latest()
        ->paginate(8); // Pagination diubah jadi 8
});

?>

<div class="min-h-screen bg-gray-50/50">
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight tracking-tight">{{ __('Activity Logs') }}</h2>
        <p class="text-sm text-gray-500 mt-1">Jejak audit aktivitas pengguna dalam sistem.</p>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Search & Filter Bar --}}
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-6 flex flex-col md:flex-row gap-4 justify-between items-center">

                {{-- Kiri: Search & Filter --}}
                <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto flex-1">
                    {{-- Input Search --}}
                    <div class="relative w-full md:w-72">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input wire:model.live.debounce.300ms="search" type="text" class="pl-10 block w-full rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Cari aktivitas, user, atau IP...">
                    </div>

                    {{-- Dropdown Filter Kategori --}}
                    <div class="relative w-full md:w-48">
                        <select wire:model.live="filterAction" class="block w-full rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Semua Kategori</option>
                            <option value="CREATE_USER">Create User</option>
                            <option value="UPDATE_USER">Update User</option>
                            <option value="DELETE_USER">Delete User</option>
                            {{-- Tambahkan opsi lain di sini jika ada fitur lain nanti --}}
                        </select>
                    </div>
                </div>

                {{-- Kanan: Info Total --}}
                <div class="text-sm text-gray-500 font-medium whitespace-nowrap">
                    Total Log: <span class="text-indigo-600 font-bold">{{ $this->logs->total() }}</span>
                </div>
            </div>

            {{-- Table --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Waktu</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Aktor (Admin)</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Deskripsi Aktivitas</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($this->logs as $log)
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    {{-- Waktu --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="font-medium text-gray-900">{{ $log->created_at->format('d M Y') }}</div>
                                        <div class="text-xs text-gray-400">{{ $log->created_at->format('H:i:s') }}</div>
                                    </td>

                                    {{-- Aktor --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-8 w-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-xs mr-3 border border-indigo-100">
                                                {{ substr($log->actor_name ?? '?', 0, 1) }}
                                            </div>
                                            <div class="text-sm font-medium text-gray-900">{{ $log->actor_name ?? 'System' }}</div>
                                        </div>
                                    </td>

                                    {{-- Aksi (Badge Warna-warni) --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @php
                                            $colorClass = match(true) {
                                                str_contains($log->action, 'CREATE') => 'bg-green-100 text-green-700 border-green-200',
                                                str_contains($log->action, 'UPDATE') => 'bg-blue-100 text-blue-700 border-blue-200',
                                                str_contains($log->action, 'DELETE') => 'bg-red-100 text-red-700 border-red-200',
                                                default => 'bg-gray-100 text-gray-700 border-gray-200'
                                            };
                                        @endphp
                                        <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-md border {{ $colorClass }}">
                                            {{ $log->action }}
                                        </span>
                                    </td>

                                    {{-- Deskripsi --}}
                                    <td class="px-6 py-4 text-sm text-gray-600 max-w-md truncate" title="{{ $log->description }}">
                                        {{ $log->description }}
                                    </td>

                                    {{-- IP Address --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-mono text-gray-400">
                                        {{ $log->ip_address }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 italic">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            <p>Tidak ada log aktivitas ditemukan.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $this->logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

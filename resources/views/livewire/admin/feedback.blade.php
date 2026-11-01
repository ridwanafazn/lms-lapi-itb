<?php

use App\Models\Feedback;
use function Livewire\Volt\{state, computed, usesPagination, layout, with};

layout('layouts.app');
usesPagination();

state([
    'selected_feedback' => null,
    'isModalOpen' => false,
]);

$feedbacks = computed(fn () => Feedback::with('user')->latest()->paginate(10));

$viewDetails = function ($id) {
    $this->selected_feedback = Feedback::with('user')->find($id);
    $this->isModalOpen = true;
};

$markAsResolved = function () {
    if ($this->selected_feedback) {
        $this->selected_feedback->update(['status' => 'resolved']);
        $this->isModalOpen = false;
        $this->selected_feedback = null;
        session()->flash('message', 'Feedback berhasil ditandai selesai.');
    }
};

$delete = function ($id) {
    Feedback::find($id)->delete();
    session()->flash('message', 'Feedback berhasil dihapus.');
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('User Feedback & Reports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Message --}}
            @if (session()->has('message'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                {{-- Tabel Data --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject / Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preview</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($this->feedbacks as $item)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $item->created_at->format('d M Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $item->user->name ?? 'Guest' }}</div>
                                    <div class="text-sm text-gray-500">{{ $item->user->email ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $item->category ?? 'General' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ Str::limit($item->message, 40) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($item->status === 'resolved')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Resolved
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <button wire:click="viewDetails({{ $item->id }})" class="text-blue-600 hover:text-blue-900">View</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="Yakin ingin menghapus feedback ini?" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    Belum ada feedback masuk.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $this->feedbacks->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Detail Feedback --}}
    @if($isModalOpen && $selected_feedback)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">

            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full {{ $selected_feedback->status === 'resolved' ? 'bg-green-100' : 'bg-yellow-100' }} sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 {{ $selected_feedback->status === 'resolved' ? 'text-green-600' : 'text-yellow-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Detail Feedback
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-1">From: <span class="font-bold">{{ $selected_feedback->user->name ?? 'Guest' }}</span></p>
                                <p class="text-xs text-gray-400 mb-4">{{ $selected_feedback->created_at->format('d F Y, H:i') }}</p>

                                <div class="bg-gray-50 p-3 rounded text-sm text-gray-700 whitespace-pre-wrap">
                                    {{ $selected_feedback->message }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    @if($selected_feedback->status !== 'resolved')
                        <button type="button" wire:click="markAsResolved" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Tandai Selesai
                        </button>
                    @endif
                    <button type="button" wire:click="$set('isModalOpen', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

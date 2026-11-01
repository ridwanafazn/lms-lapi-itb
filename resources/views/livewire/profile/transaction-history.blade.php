<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            // Memuat transaksi dengan pagination, 5 item per halaman
            'transactions' => Auth::user()->transactions()->paginate(5),
        ];
    }
}; ?>

<div class="space-y-4">
    <header class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('Riwayat Transaksi') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
            {{ __('Catatan pembelian program dan status tagihan Anda.') }}
        </p>
    </header>

    @if($transactions->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 px-4 text-center bg-gray-50 border border-dashed border-gray-200 rounded-xl">
            <div class="h-12 w-12 text-gray-300 mb-3">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <h3 class="text-sm font-medium text-gray-900">Belum ada transaksi</h3>
            <p class="mt-1 text-xs text-gray-500 max-w-sm">Anda belum melakukan pembelian program atau keanggotaan apapun di platform ini.</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <ul role="list" class="divide-y divide-gray-100">
                @foreach($transactions as $tx)
                    <li class="p-4 sm:p-5 hover:bg-gray-50 transition duration-150">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center min-w-0 gap-x-4">
                                {{-- Icon Transaksi --}}
                                <div class="hidden sm:flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-indigo-50 border border-indigo-100">
                                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </div>
                                <div class="min-w-0 flex-auto">
                                    <p class="text-sm font-semibold leading-6 text-gray-900 truncate">
                                        {{ $tx->purchased_item_name }}
                                    </p>
                                    <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-gray-500">
                                        <p class="truncate font-mono">{{ $tx->invoice_code }}</p>
                                        <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 fill-current"><circle cx="1" cy="1" r="1" /></svg>
                                        <p>{{ $tx->transaction_date->format('d M Y, H:i') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end shrink-0 gap-y-1">
                                <p class="text-sm font-bold text-gray-900">Rp {{ number_format($tx->amount, 0, ',', '.') }}</p>

                                {{-- Status Pill --}}
                                @if($tx->refunded_at)
                                    <span class="text-xs font-semibold text-red-700">Direfund</span>
                                @elseif($tx->isPaid())
                                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Berhasil</span>
                                @elseif($tx->payment_status === 'pending')
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Menunggu</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Gagal / Expired</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Pagination Controls --}}
        <div class="mt-4">
            {{ $transactions->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>

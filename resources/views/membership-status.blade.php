<?php

use App\Models\Transaction;
use function Livewire\Volt\{state, mount, layout};

layout('layouts.app');

state([
    'orderId' => null,
    'status' => 'loading',
    'message' => '',
]);

mount(function () {

    $this->orderId = request()->query('order_id');

    if (!$this->orderId) {
        $this->status = 'failed';
        $this->message = 'Order ID tidak ditemukan.';
        return;
    }

    $transaction = Transaction::where('invoice_code', $this->orderId)->where('user_id', auth()->id())->first();

    if (!$transaction) {
        $this->status = 'failed';
        $this->message = 'Transaksi tidak valid.';
        return;
    }

    // View hanya membaca status — tidak mengubah apapun
    if ($transaction->refunded_at) {
            $this->status = 'failed';
            $this->message = 'Pembayaran ini sudah direfund. Periksa status membership dan riwayat tagihan Anda.';
        } elseif ($transaction->isPaid()) {

        $this->status = 'success';
        $this->message = 'Pembayaran berhasil. Membership Anda sudah aktif.';

    } elseif ($transaction->payment_status === 'pending') {

        $this->status = 'pending';
        $this->message = 'Pembayaran masih tertunda. Jika Anda menggunakan Virtual Account, silakan selesaikan pembayaran dan tekan Refresh.';

    } else {

        $this->status = 'failed';
        $this->message = 'Pembayaran gagal, kadaluarsa, atau dibatalkan.';
    }

});

?>

<div>
    <div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50">
        <div class="max-w-md w-full bg-white rounded-3xl shadow-xl p-8 text-center border border-slate-100 relative overflow-hidden">

            {{-- Loading --}}
            @if($status === 'loading')
            <div class="animate-pulse flex flex-col items-center">
                <div class="w-20 h-20 rounded-full bg-indigo-100 flex items-center justify-center mb-6">
                    <svg class="animate-spin w-10 h-10 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Memverifikasi Status...</h3>
            </div>
            @endif


            {{-- Success --}}
            @if($status === 'success')
            <div class="flex flex-col items-center">
                <div class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center mb-6 text-emerald-600 shadow-inner">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-black text-slate-900 mb-2">Pembayaran Sukses!</h3>
                <p class="text-slate-600 mb-6 leading-relaxed">{{ $message }}</p>

                <a href="{{ route('dashboard') }}"
                   class="w-full inline-flex justify-center items-center py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition">
                    Mulai Belajar Sekarang
                </a>
            </div>
            @endif


            {{-- Pending --}}
            @if($status === 'pending')
            <div class="flex flex-col items-center">
                <div class="w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center mb-6 text-amber-600 shadow-inner">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>

                <h3 class="text-2xl font-black text-slate-900 mb-2">Menunggu Pembayaran</h3>

                <p class="text-slate-600 mb-6 text-sm leading-relaxed">
                    {{ $message }}
                    <br><br>
                    Nomor Tagihan:<br>
                    <strong>{{ $orderId }}</strong>
                </p>

                <button wire:click="$refresh"
                        class="w-full inline-flex justify-center items-center py-3.5 px-4 rounded-xl text-sm font-bold text-slate-900 bg-amber-400 hover:bg-amber-500 transition mb-3">
                    Refresh Status
                </button>

                <a href="{{ route('dashboard') }}"
                   class="text-sm font-bold text-slate-500 hover:text-slate-900 transition">
                    Kembali ke Dashboard
                </a>
            </div>
            @endif


            {{-- Failed --}}
            @if($status === 'failed')
            <div class="flex flex-col items-center">
                <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mb-6 text-red-600 shadow-inner">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>

                <h3 class="text-2xl font-black text-slate-900 mb-2">Pembayaran Gagal</h3>

                <p class="text-slate-600 mb-6 leading-relaxed">
                    {{ $message }}
                </p>

                <a href="{{ route('membership') }}"
                   class="w-full inline-flex justify-center items-center py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-slate-900 hover:bg-slate-800 transition">
                    Coba Ulangi Pembayaran
                </a>
            </div>
            @endif

        </div>
    </div>
</div>

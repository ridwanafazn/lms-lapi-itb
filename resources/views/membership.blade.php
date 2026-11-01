<?php

use App\Models\MembershipPlan;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\{layout, with, state};

layout('layouts.app');

state(['couponCode' => '']);

with(function () {
    $user = Auth::user();

    return [
        'currentUser' => $user,
        'currentPlan' => $user?->membershipPlan,
        'isActiveMembership' => $user?->hasActiveMembership(),
    ];
});

$checkout = function ($level, $billingPeriod) {

    $user = Auth::user();

    $plan = MembershipPlan::active()
        ->where('level', $level)
        ->where('billing_period', $billingPeriod)
        ->first();

    if (!$plan) {
        $this->dispatch('notify', message: 'Paket tidak ditemukan atau belum tersedia.', type: 'error');
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | GUARD: Membership Rule
    |--------------------------------------------------------------------------
    | - Jika user punya membership aktif
    | - Hanya boleh upgrade
    | - Tidak boleh beli plan sama / downgrade
    */

    if ($user->hasActiveMembership()) {

        $currentPlan = $user->membershipPlan;

        if ($currentPlan) {

            // Asumsi level punya urutan logis: starter < standard < pro
            $levelOrder = [
                'starter' => 1,
                'standard' => 2,
                'pro' => 3,
            ];

            $currentLevelValue = $levelOrder[$currentPlan->level] ?? 0;
            $selectedLevelValue = $levelOrder[$plan->level] ?? 0;

            // Jika bukan upgrade → blok
            if ($selectedLevelValue <= $currentLevelValue) {

                $this->dispatch(
                    'notify',
                    message: 'Membership Anda masih aktif. Anda hanya dapat melakukan upgrade ke paket yang lebih tinggi.',
                    type: 'warning'
                );

                return;
            }
        }
    }

    $this->validate(['couponCode' => 'nullable|string|max:64']);
    try {
        $transaction = app(CheckoutService::class)->create($user, $plan, $this->couponCode);
        if ($transaction->isPaid()) {
            return redirect()->route('membership.status', ['order_id' => $transaction->invoice_code]);
        }
        $this->dispatch('snap-token-received', token: $transaction->snap_token);
    } catch (ValidationException $e) {
        throw $e;
    } catch (\Throwable $e) {
        report($e);
        $this->addError('checkout', 'Gagal memproses pembayaran. Silakan coba lagi.');
    }
};

?>

<div>
    <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Membership & Pricing') }}
        </h2>
    </x-slot>

    <div class="pt-6 pb-20 min-h-screen font-sans text-slate-900 bg-[#F8F9FE] relative overflow-hidden"
         x-data="{
             billing: 'yearly',
             prices: {
                 standard: { monthly: '19.000', quarterly: '49.000', semiannual: '100.000', yearly: '189.000' },
                 pro: { monthly: '49.000', quarterly: '139.000', semiannual: '259.000', yearly: '499.000' }
             },
             periods: {
                 monthly: 'bulan', quarterly: '3 bulan', semiannual: '6 bulan', yearly: 'tahun'
             }
         }"
         x-init="
             $wire.on('snap-token-received', (data) => {
                 let token = Array.isArray(data) ? data[0].token : data.token;

                 if (typeof window.snap === 'undefined') {
                     alert('Script Midtrans gagal dimuat. Pastikan koneksi internet lancar dan Client Key benar.');
                     return;
                 }

                 window.snap.pay(token, {
                     onSuccess: function(result){
                         window.location.href = '/membership/status?order_id=' + result.order_id;
                     },
                     onPending: function(result){
                         window.location.href = '/membership/status?order_id=' + result.order_id;
                     },
                     onError: function(result){
                         alert('Transaksi Gagal atau Dibatalkan.');
                     },
                     onClose: function(){
                         alert('Kamu menutup popup sebelum menyelesaikan pembayaran.');
                     }
                 });
             })
         ">

        <div class="absolute inset-0" style="background-image: radial-gradient(#E2E8F0 1px, transparent 1px); background-size: 32px 32px; opacity: 0.5;"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[500px] bg-purple-200/40 blur-[120px] -z-10 rounded-full"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-xl mx-auto mb-6">
                <label for="membership-coupon" class="block font-semibold">Kode kupon (opsional, satu kupon per transaksi)</label>
                <input id="membership-coupon" wire:model="couponCode" maxlength="64" class="w-full rounded border-gray-300" placeholder="Masukkan kode sebelum memilih paket">
                @error('couponCode') <p class="text-red-700" role="alert">{{ $message }}</p> @enderror
                @error('checkout') <p class="text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="text-center max-w-3xl mx-auto mb-10">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-600 text-xs font-bold tracking-wide uppercase mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    Investasi Karir Terbaik
                </div>
                <h3 class="text-4xl md:text-5xl font-black text-slate-900 tracking-tight mb-4 leading-tight">
                    Pilih Cara Belajarmu,<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">Raih Masa Depan.</span>
                </h3>
                <p class="text-slate-500 text-lg leading-relaxed max-w-xl mx-auto">
                    Bergabung dengan ribuan talenta digital yang telah sukses bertransformasi. Mulai perjalananmu hari ini dengan harga terjangkau.
                </p>
            </div>

            <div class="flex justify-center mb-12">
                <div class="bg-white p-1.5 rounded-full border border-slate-200 shadow-sm inline-flex relative">
                    <button @click="billing = 'monthly'"
                        :class="billing === 'monthly' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900'"
                        class="px-5 py-2.5 rounded-full text-xs font-bold transition-all duration-300">
                        1 Bulan
                    </button>
                    <button @click="billing = 'quarterly'"
                        :class="billing === 'quarterly' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900'"
                        class="px-5 py-2.5 rounded-full text-xs font-bold transition-all duration-300">
                        3 Bulan
                    </button>
                    <button @click="billing = 'semiannual'"
                        :class="billing === 'semiannual' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900'"
                        class="px-5 py-2.5 rounded-full text-xs font-bold transition-all duration-300">
                        6 Bulan
                    </button>
                    <button @click="billing = 'yearly'"
                        :class="billing === 'yearly' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900'"
                        class="px-5 py-2.5 rounded-full text-xs font-bold transition-all duration-300 flex items-center gap-2">
                        1 Tahun
                        <span class="bg-emerald-400 text-slate-900 text-[9px] px-1.5 py-0.5 rounded font-black uppercase">Hemat 20%</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-center max-w-6xl mx-auto mb-20">
                <div class="bg-white rounded-[2rem] p-8 border border-slate-200 hover:shadow-xl hover:shadow-slate-200/50 transition-all duration-300 relative group h-fit">
                    <div class="mb-6">
                        <h4 class="text-lg font-bold text-slate-900 mb-2">Starter</h4>
                        <p class="text-xs text-slate-500 mb-4">Untuk pemula yang ingin mencoba.</p>
                        <div class="flex items-baseline gap-1">
                            <span class="text-sm font-bold text-slate-500 align-top">Rp</span>
                            <span class="text-4xl font-black text-slate-900">0</span>
                        </div>
                        <p class="text-xs text-slate-400 font-medium mt-1">Selamanya</p>
                    </div>
                    <button class="w-full py-3 rounded-xl border border-slate-200 text-slate-700 font-bold text-sm hover:border-slate-400 hover:bg-slate-50 transition mb-8">
                        Mulai Gratis
                    </button>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3 text-sm text-slate-600">
                            <div class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center shrink-0 text-emerald-600 mt-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Akses ke 20+ video materi dasar</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-slate-600">
                            <div class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center shrink-0 text-emerald-600 mt-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Quiz harian terbatas</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-slate-600">
                            <div class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center shrink-0 text-emerald-600 mt-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Akses forum komunitas umum</span>
                        </li>
                    </ul>
                </div>

                <div class="bg-white rounded-[2rem] p-8 border-2 border-indigo-500 shadow-2xl shadow-indigo-200/50 relative transform md:-translate-y-4 z-10">
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2">
                        <span class="bg-indigo-600 text-white text-[10px] font-bold px-4 py-1.5 rounded-full uppercase tracking-wider shadow-lg shadow-indigo-600/30">
                            Paling Populer
                        </span>
                    </div>
                    <div class="mb-6 mt-2">
                        <h4 class="text-xl font-bold text-slate-900 mb-2">Standard</h4>
                        <p class="text-xs text-slate-500 mb-4">Mulai serius belajar skill baru.</p>
                        <div class="flex items-baseline gap-1">
                            <span class="text-lg font-bold text-slate-500 align-top">Rp</span>
                            <span class="text-5xl font-black text-slate-900 tracking-tight" x-text="prices.standard[billing]"></span>
                        </div>
                        <p class="text-xs text-slate-400 font-medium mt-1">Per <span x-text="periods[billing]"></span></p>
                    </div>
                    <button
                        @click="$wire.checkout('standard', billing)"
                        @if($isActiveMembership && $currentPlan?->level === 'standard')
                            Sedang Aktif
                        @else
                            Pilih Standard
                        @endif
                            wire:loading.attr="disabled"
                            class="w-full py-3.5 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-600/30 transition mb-8 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="checkout">Pilih Standard</span>
                        <span wire:loading wire:target="checkout">Memproses...</span>
                    </button>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3 text-sm text-slate-700 font-medium">
                            <div class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-indigo-600 mt-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Semua fitur Starter</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-slate-700 font-medium">
                            <div class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-indigo-600 mt-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Akses <strong>500+ materi & studi kasus</strong></span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-slate-700 font-medium">
                            <div class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-indigo-600 mt-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Sertifikat kompetensi digital</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-slate-700 font-medium">
                            <div class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-indigo-600 mt-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Akses Group Mentoring mingguan</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-slate-700 font-medium">
                            <div class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-indigo-600 mt-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Proyek Riil Portfolio</span>
                        </li>
                    </ul>
                </div>

                <div class="bg-[#1e1b4b] rounded-[2rem] p-8 border border-indigo-900 hover:shadow-2xl hover:shadow-indigo-900/50 transition-all duration-300 relative group overflow-hidden h-fit">
                    <div class="absolute top-0 right-0 w-48 h-48 bg-purple-600/30 blur-[60px] rounded-full pointer-events-none"></div>
                    <div class="mb-6 relative z-10">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-xl font-bold text-white">Pro</h4>
                            <span class="bg-purple-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg uppercase tracking-wide">Best Value</span>
                        </div>
                        <p class="text-xs text-indigo-200 mb-4">Akselerasi karir maksimal.</p>
                        <div class="flex items-baseline gap-1 text-white">
                            <span class="text-lg font-bold text-slate-400 align-top">Rp</span>
                            <span class="text-5xl font-black" x-text="prices.pro[billing]"></span>
                        </div>
                        <p class="text-xs text-slate-400 font-medium mt-1">Per <span x-text="periods[billing]"></span></p>
                    </div>
                    <button
                        @click="$wire.checkout('pro', billing)"
                        @if($isActiveMembership && $currentPlan?->level === 'pro')
                                Sedang Aktif
                            @else
                                Dapatkan Akses Premium
                            @endif
                            wire:loading.attr="disabled"
                            class="w-full py-3.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold text-sm hover:shadow-lg hover:shadow-purple-600/40 hover:scale-[1.02] transition-all mb-8 relative z-10 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="checkout">Dapatkan Akses Premium</span>
                        <span wire:loading wire:target="checkout">Memproses...</span>
                    </button>
                    <ul class="space-y-4 relative z-10">
                        <li class="flex items-start gap-3 text-sm text-indigo-50 font-medium">
                            <div class="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-400 mt-0.5 border border-emerald-500/30">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Semua fitur Standard</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-indigo-50 font-medium">
                            <div class="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-400 mt-0.5 border border-emerald-500/30">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span><strong>1-on-1 Career Mentoring</strong></span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-indigo-50 font-medium">
                            <div class="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-400 mt-0.5 border border-emerald-500/30">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Code Review dari Expert</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-indigo-50 font-medium">
                            <div class="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-400 mt-0.5 border border-emerald-500/30">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Prioritas Job Connect</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-indigo-50 font-medium">
                            <div class="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-400 mt-0.5 border border-emerald-500/30">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Akses Offline Mode App</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-indigo-50 font-medium">
                            <div class="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-400 mt-0.5 border border-emerald-500/30">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span>Unlimited Mock Interview</span>
                        </li>
                    </ul>
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
                </div>

            </div>

            <div class="max-w-6xl mx-auto mb-24">
                @if($isActiveMembership && $currentPlan)
                <div class="mb-8 bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center">
                    <h4 class="text-lg font-bold text-emerald-700">
                        Membership Aktif: {{ ucfirst($currentPlan->level) }}
                    </h4>
                    <p class="text-sm text-emerald-600 mt-1">
                        Berlaku hingga {{ optional($currentUser->membership_expires_at)->format('d M Y') }}
                    </p>
                </div>
            @endif
                <div class="text-center mb-10">
                    <h4 class="text-2xl font-bold text-slate-900">Opsi Belajar Alternatif</h4>
                    <p class="text-slate-500 mt-2">Tidak ingin berlangganan? Beli satuan atau gabung kelas live.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-200 hover:border-purple-300 transition-all flex items-center gap-6 group hover:shadow-lg">
                        <div class="h-16 w-16 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        </div>
                        <div>
                            <h5 class="text-lg font-bold text-slate-900 mb-1">Beli Program Satuan</h5>
                            <p class="text-sm text-slate-500 mb-3 leading-relaxed">Bayar sekali untuk akses selamanya (Lifetime). Cocok jika butuh materi spesifik saja.</p>
                            <a href="{{ route('student.browse') }}" class="text-purple-600 text-sm font-bold hover:underline flex items-center gap-1 group-hover:gap-2 transition-all">
                                Lihat Katalog <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                            </a>
                        </div>
                    </div>
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-200 hover:border-orange-300 transition-all flex items-center gap-6 group hover:shadow-lg">
                        <div class="h-16 w-16 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h5 class="text-lg font-bold text-slate-900">Live Cohort</h5>
                                <span class="bg-orange-100 text-orange-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase">Limited</span>
                            </div>
                            <p class="text-sm text-slate-500 mb-3 leading-relaxed">Belajar intensif dengan mentor live. Wajib daftar per-batch. Tidak termasuk di membership.</p>
                            <a href="{{ route('student.browse') }}?tab=cohort" class="text-orange-600 text-sm font-bold hover:underline flex items-center gap-1 group-hover:gap-2 transition-all">
                                Cek Jadwal <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="max-w-3xl mx-auto" x-data="{ active: null }">
                <div class="text-center mb-10">
                    <h4 class="text-2xl font-bold text-slate-900">Pertanyaan yang Sering Diajukan</h4>
                </div>
                <div class="space-y-4">
                    @php
                        $faqs = [
                            'Apakah saya bisa upgrade paket di tengah jalan?' => 'Ya, sistem kami akan otomatis menghitung selisih harga (prorata) jika Anda ingin upgrade dari Standard ke Pro di tengah masa langganan aktif.',
                            'Bagaimana jika saya tidak puas dengan materinya?' => 'Kami menawarkan garansi uang kembali 7 hari untuk pembelian pertama membership jika Anda merasa materi tidak sesuai ekspektasi.',
                            'Apakah sertifikatnya diakui oleh industri?' => 'Sertifikat kami dilengkapi dengan ID verifikasi unik dan kurikulum disusun berdasarkan standar industri terkini yang divalidasi oleh partner hiring kami.',
                            'Apa bedanya beli satuan dengan membership?' => 'Beli satuan memberikan akses selamanya (Lifetime) untuk 1 materi spesifik. Membership memberikan akses ke banyak materi sekaligus selama masa langganan aktif.',
                        ];
                    @endphp
                    @foreach($faqs as $q => $a)
                    <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300">
                        <button @click="active === {{ $loop->index }} ? active = null : active = {{ $loop->index }}"
                                class="w-full flex justify-between items-center p-5 text-left bg-white">
                            <span class="text-sm font-bold text-slate-800">{{ $q }}</span>
                            <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 transition-transform duration-300" :class="active === {{ $loop->index }} ? 'rotate-180 bg-indigo-50 text-indigo-600' : ''">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </button>
                        <div x-show="active === {{ $loop->index }}" x-collapse>
                            <div class="px-5 pb-5 pt-0 text-sm text-slate-600 leading-relaxed">
                                <div class="h-px bg-slate-100 mb-4 w-full"></div>
                                {{ $a }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</div>

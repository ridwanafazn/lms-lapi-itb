<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public $hasMembership = false;
    public $planName = '';
    public $daysLeft = 0;
    public $expiresAt = null;
    public $userName = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->userName = $user->name;
        $this->hasMembership = $user->hasActiveMembership();

        if ($this->hasMembership) {
            $this->planName = $user->membershipPlan ? ucfirst($user->membershipPlan->level) : 'Premium';
            $this->daysLeft = $user->membership_days_left;
            $this->expiresAt = $user->membership_expires_at ? $user->membership_expires_at->format('d M Y') : 'Selamanya';
        }
    }
}; ?>

<div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800 p-6 text-white shadow-xl shadow-indigo-200/50">
    {{-- Decorative Background Elements --}}
    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
    <div class="absolute -bottom-8 -left-8 h-40 w-40 rounded-full bg-indigo-400/20 blur-2xl"></div>

    <div class="relative z-10 flex flex-col h-full justify-between gap-6">
        {{-- Header Card --}}
        <div class="flex items-start justify-between">
            <div>
                <p class="text-indigo-100 text-xs font-medium uppercase tracking-wider mb-1">Status Identitas</p>
                <h3 class="font-bold text-xl truncate max-w-[180px]" title="{{ $userName }}">{{ $userName }}</h3>
            </div>

            @if($hasMembership)
                <div class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur-md border border-white/30 shadow-sm">
                    <svg class="w-3.5 h-3.5 text-yellow-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    {{ $planName }}
                </div>
            @else
                <div class="inline-flex items-center rounded-full bg-gray-900/40 px-3 py-1 text-xs font-medium backdrop-blur-md border border-white/10 text-gray-200">
                    Standard User
                </div>
            @endif
        </div>

        {{-- Footer Card / Progress --}}
        <div>
            @if($hasMembership)
                <div class="flex justify-between items-end mb-2">
                    <p class="text-sm font-medium text-indigo-50">Sisa Akses</p>
                    <p class="text-2xl font-bold">{{ $daysLeft }} <span class="text-xs font-normal text-indigo-200">hari</span></p>
                </div>
                {{-- Progress Bar Visual (Dekoratif, tidak persentase asli agar selalu terlihat menarik) --}}
                <div class="h-1.5 w-full bg-black/20 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-yellow-300 to-yellow-500 rounded-full" style="width: {{ min(100, max(10, ($daysLeft / 365) * 100)) }}%"></div>
                </div>
                <p class="text-[10px] text-indigo-200 mt-2 text-right">Berlaku s.d. {{ $expiresAt }}</p>
            @else
                <p class="text-sm text-indigo-100 mb-3 leading-relaxed">Tingkatkan keanggotaan Anda untuk mengakses materi eksklusif dan fitur premium.</p>
                <a href="{{ route('membership') }}" class="inline-flex w-full items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-indigo-600 shadow-sm">
                    Lihat Paket Premium
                </a>
            @endif
        </div>
    </div>
</div>

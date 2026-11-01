<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    // Dummy Data Leaderboard
    public $leaders = [
        ['name' => 'Peserta (You)', 'points' => 1351, 'is_me' => true],
        ['name' => 'Sarah J.', 'points' => 1350, 'is_me' => false],
        ['name' => 'Michael B.', 'points' => 1254, 'is_me' => false],
        ['name' => 'Jessica T.', 'points' => 940, 'is_me' => false],
        ['name' => 'David R.', 'points' => 850, 'is_me' => false],
    ];
};
?>

<div class="space-y-4">
    @foreach($leaders as $index => $user)
    <div class="flex items-center justify-between {{ $user['is_me'] ? 'bg-indigo-50 p-2 -mx-2 rounded-xl ring-1 ring-indigo-100' : '' }}">
        <div class="flex items-center gap-3">
            {{-- Rank Number --}}
            <span class="text-sm font-bold w-4 text-center {{ $index < 3 ? 'text-indigo-600' : 'text-gray-400' }}">
                {{ $index + 1 }}
            </span>

            {{-- Avatar --}}
            <div class="w-8 h-8 rounded-full bg-gray-200 border-2 border-white shadow-sm flex items-center justify-center text-[10px] font-bold text-gray-500">
                {{ substr($user['name'], 0, 1) }}
            </div>

            {{-- Name --}}
            <span class="text-xs font-bold text-gray-700 {{ $user['is_me'] ? 'text-indigo-900' : '' }}">
                {{ $user['name'] }}
            </span>
        </div>

        {{-- Points --}}
        <div class="flex items-center gap-1 bg-white px-2 py-1 rounded-lg border border-gray-100 shadow-sm">
            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
            <span class="text-[10px] font-bold text-gray-600">{{ $user['points'] }}</span>
        </div>
    </div>
    @endforeach
</div>

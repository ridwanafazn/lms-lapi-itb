<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Enrollment;

new class extends Component {
    public $recentEnrollment;

    public function mount()
    {
        // Ambil 1 pendaftaran terakhir yang aktif
        $this->recentEnrollment = Enrollment::where('user_id', Auth::id())
            ->where('status', 'active')
            ->latest('updated_at')
            ->with('enrollable') // Pastikan model Enrollment support polymorphic
            ->first();
    }
}; ?>

<div class="space-y-4">
    @if($recentEnrollment && $recentEnrollment->enrollable)
    <div class="bg-white p-5 rounded-3xl shadow-sm border border-gray-100 flex flex-col md:flex-row items-center gap-5 hover:shadow-md transition duration-300">
        {{-- Thumbnail --}}
        <div class="w-full md:w-32 h-32 md:h-24 bg-gray-200 rounded-2xl flex-shrink-0 overflow-hidden">
             @if($recentEnrollment->thumbnail)
                <img src="{{ $recentEnrollment->thumbnail }}" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center bg-indigo-50 text-indigo-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            @endif
        </div>

        {{-- Content --}}
        <div class="flex-1 w-full">
            <div class="flex justify-between items-start">
                <div>
                     <span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wide">On-Going</span>
                     <h4 class="font-bold text-gray-900 mt-2 text-lg leading-tight">{{ $recentEnrollment->title }}</h4>
                     <p class="text-xs text-gray-500 mt-1">Lanjutkan progresmu</p>
                </div>
                <a href="{{ route('student.learning.player', $recentEnrollment->enrollable_id) }}"
                    wire:navigate
                    class="bg-[#3754DB] hover:bg-blue-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition shadow-sm shadow-blue-200">
                        Resume Learning &rarr;
                </a>
            </div>

            {{-- Progress --}}
            <div class="mt-4">
                <div class="flex justify-between text-[11px] font-bold text-gray-500 mb-1.5">
                    <span>{{ number_format($recentEnrollment->progress_percentage, 0) }}% Completed</span>
                    <span>Last Activity: {{ $recentEnrollment->updated_at->diffForHumans() }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-[#3754DB] h-2 rounded-full transition-all duration-1000" style="width: {{ $recentEnrollment->progress_percentage }}%"></div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="bg-gray-50 border border-dashed border-gray-300 rounded-3xl p-6 text-center text-gray-500 text-sm">
        Belum ada kursus yang dipelajari baru-baru ini.
    </div>
    @endif
</div>


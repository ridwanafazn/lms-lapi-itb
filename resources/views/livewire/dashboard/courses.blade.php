<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Enrollment;

new class extends Component {
    public function with(): array
    {
        return [
            'enrollments' => Auth::user()->enrollments()
                ->where('status', 'active')
                ->with('enrollable')
                ->latest('updated_at')
                ->take(3)
                ->get(),
        ];
    }
}; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @foreach($enrollments as $enrollment)
        @if(!$enrollment->enrollable) @continue @endif

        <div class="bg-white p-4 rounded-3xl border border-gray-100 shadow-sm hover:shadow-lg transition duration-300 group flex flex-col h-full">
            {{-- Thumbnail --}}
            <div class="w-full aspect-[4/3] bg-gray-200 rounded-2xl overflow-hidden mb-4 relative">
                @if($enrollment->thumbnail)
                    <img src="{{ $enrollment->thumbnail }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                @else
                     <div class="w-full h-full flex items-center justify-center bg-indigo-50 text-indigo-300">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                @endif

                {{-- Badge --}}
                <div class="absolute top-3 left-3">
                    <span class="bg-white/90 backdrop-blur text-gray-800 text-[10px] font-bold px-2 py-1 rounded-md shadow-sm border border-gray-100">
                        INTERMEDIATE
                    </span>
                </div>
            </div>

            {{-- Info --}}
            <div class="flex-1 flex flex-col">
                <h4 class="font-bold text-gray-900 text-sm line-clamp-2 leading-snug mb-2 group-hover:text-indigo-600 transition">
                    {{ $enrollment->title }}
                </h4>

                <div class="mt-auto pt-3 border-t border-gray-50 flex justify-between items-center text-[11px] text-gray-500 font-medium">
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        22 Lessons
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        48 Hours
                    </span>
                </div>
            </div>
        </div>
    @endforeach
</div>

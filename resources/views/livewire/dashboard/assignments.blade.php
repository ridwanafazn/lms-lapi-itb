<?php use Livewire\Volt\Component; new class extends Component {}; ?>

<div class="space-y-3">
    {{-- Item 1 --}}
    <div class="bg-[#F4F7FB] p-4 rounded-2xl hover:bg-indigo-50 transition cursor-pointer group">
        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Mastering Embeded System</p>
        <h4 class="font-bold text-gray-800 text-sm group-hover:text-indigo-600 transition">CPP Quiz</h4>
        <div class="flex justify-between items-end mt-3">
            <span class="text-[10px] text-gray-400">Deadline: Tomorrow</span>
            <span class="text-[10px] text-indigo-600 font-bold flex items-center gap-1">
                See Details <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
        </div>
    </div>

     {{-- Item 2 --}}
     <div class="bg-[#F4F7FB] p-4 rounded-2xl hover:bg-indigo-50 transition cursor-pointer group">
        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Digital Marketing</p>
        <h4 class="font-bold text-gray-800 text-sm group-hover:text-indigo-600 transition">Draft Essay</h4>
         <div class="flex justify-between items-end mt-3">
            <span class="text-[10px] text-gray-400">Deadline: 2 Days</span>
            <span class="text-[10px] text-indigo-600 font-bold flex items-center gap-1">
                See Details <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
        </div>
    </div>
</div>

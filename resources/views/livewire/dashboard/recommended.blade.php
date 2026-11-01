<?php

use Livewire\Volt\Component;

new class extends Component {
    // Definisi variabel harus menggunakan 'public' dan struktur array yang valid
    public array $recommendations = [
        [
            'title' => 'Mastering Embedded System',
            'price' => 'Rp 2.000.000',
            'rating' => 4.8
        ],
        [
            'title' => 'Fullstack Laravel Mastery',
            'price' => 'Rp 1.500.000',
            'rating' => 4.9
        ],
        [
            'title' => 'UI/UX Design for Beginners',
            'price' => 'Rp 800.000',
            'rating' => 4.7
        ],
    ];
}; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @foreach($recommendations as $item)
    <div class="bg-white p-4 rounded-3xl border border-gray-100 shadow-sm hover:shadow-lg transition duration-300 group cursor-pointer flex flex-col h-full">
        {{-- Thumbnail --}}
        <div class="w-full aspect-[4/3] bg-gray-200 rounded-2xl mb-4 relative overflow-hidden flex-shrink-0">
             @if(isset($item['thumbnail']))
                <img src="{{ $item['thumbnail'] }}" class="w-full h-full object-cover">
             @else
                <div class="w-full h-full bg-gradient-to-tr from-gray-200 to-gray-300 flex items-center justify-center">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
             @endif
             {{-- Gradient Overlay --}}
             <div class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent"></div>
        </div>

        {{-- Title --}}
        <div class="mb-2 flex-grow">
            <h4 class="font-bold text-gray-900 text-sm line-clamp-2 leading-snug group-hover:text-indigo-600 transition">
                {{ $item['title'] }}
            </h4>
        </div>

        {{-- Rating & Info --}}
        <div class="flex items-center gap-1 mb-3">
            <svg class="w-3.5 h-3.5 text-yellow-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            <span class="text-xs font-bold text-gray-700">{{ $item['rating'] }}</span>
            <span class="text-[10px] text-gray-400 ml-1">(Intermediate)</span>
        </div>

        {{-- Price --}}
        <div class="pt-3 border-t border-gray-50 mt-auto">
            <p class="text-sm font-extrabold text-[#3730A3]">{{ $item['price'] }}</p>
        </div>
    </div>
    @endforeach
</div>

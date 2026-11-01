<?php
use Livewire\Volt\Component;
new class extends Component {};
?>

<div
    x-data="{
        activeSlide: 0,
        slides: [
            'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=1000&q=80'
        ],
        init() { setInterval(() => { this.next() }, 5000); },
        next() { this.activeSlide = (this.activeSlide === this.slides.length - 1) ? 0 : this.activeSlide + 1; },
        prev() { this.activeSlide = (this.activeSlide === 0) ? this.slides.length - 1 : this.activeSlide - 1; }
    }"
    class="relative w-full h-48 md:h-64 bg-gray-200 rounded-3xl overflow-hidden shadow-sm group"
>
    <div class="flex h-full transition-transform duration-700 ease-in-out" :style="`transform: translateX(-${activeSlide * 100}%)`">
        <template x-for="(slide, index) in slides" :key="index">
            <div class="min-w-full h-full relative">
                <img :src="slide" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-r from-indigo-900/40 to-transparent"></div>
            </div>
        </template>
    </div>

    <button @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/40 backdrop-blur-md text-white p-2 rounded-full opacity-0 group-hover:opacity-100 transition duration-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
    </button>
    <button @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/40 backdrop-blur-md text-white p-2 rounded-full opacity-0 group-hover:opacity-100 transition duration-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
    </button>

    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
        <template x-for="(slide, index) in slides" :key="index">
            <div class="w-2 h-2 rounded-full transition-all duration-300" :class="activeSlide === index ? 'bg-white w-6' : 'bg-white/50'"></div>
        </template>
    </div>
</div>

@props(['instructor'])

{{-- Card Container --}}
<div class="
    w-[280px] h-[350px] flex-shrink-0 relative
    bg-gradient-to-b from-white/60 to-white/20 backdrop-blur-md 
    border border-white/60 rounded-[3rem] 
    flex flex-col items-center justify-end pb-8
    shadow-[0_10px_40px_-10px_rgba(70,162,193,0.3)]
    transition-transform duration-300 hover:-translate-y-4 hover:shadow-[0_20px_50px_-10px_rgba(70,162,193,0.5)]
    cursor-pointer group
">
    
    {{-- Foto Instruktur --}}
    {{-- Posisi absolute agar bisa keluar dari layout jika mau, atau diatur presisi --}}
    <div class="absolute bottom-24 w-full flex justify-center overflow-visible">
        {{-- Circle Background (Opsional, pemanis di belakang kepala) --}}
        <div class="absolute bottom-0 w-48 h-48 bg-blue-200/30 rounded-full blur-xl group-hover:bg-blue-300/40 transition-colors"></div>
        
        {{-- Foto --}}
        <img 
            src="{{ $instructor['image'] }}" 
            alt="{{ $instructor['name'] }}" 
            class="w-48 h-48 object-cover object-top rounded-b-3xl mask-image-gradient"
            style="mask-image: linear-gradient(to bottom, black 80%, transparent 100%); -webkit-mask-image: linear-gradient(to bottom, black 70%, transparent 100%);"
        >
    </div>

    {{-- Info Text --}}
    <div class="text-center relative z-10 px-4">
        <h4 class="font-body font-bold text-xl text-[#2F70AF] mb-1 leading-tight">
            {{ $instructor['name'] }}
        </h4>
        <p class="font-body text-xs font-bold text-[#7C98B6] uppercase tracking-wide">
            {{ $instructor['role'] }}
        </p>
    </div>

</div>
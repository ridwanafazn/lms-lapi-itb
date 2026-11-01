<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Course;

new #[Layout('layouts.course-workspace')] class extends Component {
    public Course $course;

    public $title;
    public $description;

    public function mount(Course $course)
    {
        // Otorisasi Manual
        $this->authorize('update', $course);

        $this->course = $course;
        $this->title = $course->title;
        $this->description = $course->description;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|min:3|max:255',
            'description' => 'required|min:10', // Mewajibkan deskripsi diisi
        ], [
            'description.required' => 'Deskripsi course tidak boleh kosong agar bisa diajukan untuk review.'
        ]);

        $this->course->update([
            'title' => $this->title,
            'description' => $this->description,
        ]);

        session()->flash('message', 'Course Identity berhasil disimpan!');
    }
}; ?>

<div>
    <x-slot:actions>
        <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center px-5 py-2.5 text-sm font-bold text-white transition-all bg-indigo-600 rounded-xl shadow-md hover:bg-indigo-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
            <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
            <span wire:loading wire:target="save" class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Menyimpan...
            </span>
        </button>
    </x-slot:actions>

    <div class="max-w-4xl mx-auto space-y-6 pb-20">

        @if (session()->has('message'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-xl shadow-sm flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div class="font-medium">{{ session('message') }}</div>
            </div>
        @endif

        <div class="bg-white border border-slate-200 shadow-sm rounded-2xl overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
                <h2 class="text-lg font-bold text-slate-800">Course Identity</h2>
                <p class="text-sm text-slate-500">Berikan identitas yang jelas untuk kelas Anda agar menarik minat siswa.</p>
            </div>

            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Judul Course <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="title" class="block w-full rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 px-4 py-3 font-medium transition-colors" placeholder="Contoh: Pengantar Internet of Things">
                    @error('title') <span class="text-xs text-red-500 mt-1 font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Deskripsi Lengkap <span class="text-red-500">*</span></label>
                    <textarea wire:model="description" rows="6" class="block w-full rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 px-4 py-3 font-medium transition-colors" placeholder="Jelaskan apa yang akan dipelajari siswa di kelas ini..."></textarea>
                    @error('description') <span class="text-xs text-red-500 mt-1 font-medium">{{ $message }}</span> @enderror
                </div>

                <div class="border-t border-slate-100 pt-6 mt-6">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Course Thumbnail (Opsional saat draf)</label>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-48 border-2 border-slate-300 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-10 h-10 text-slate-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <p class="mb-2 text-sm text-slate-500"><span class="font-semibold text-indigo-600">Klik untuk upload</span> atau drag and drop</p>
                                <p class="text-xs text-slate-400">PNG, JPG or WEBP (Rekomendasi: 1280x720px)</p>
                            </div>
                            <input type="file" class="hidden" disabled />
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

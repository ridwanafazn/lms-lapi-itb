<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Assignment;

new #[Layout('layouts.app')] class extends Component {
    public Assignment $assignment;

    // Form Inputs
    public $title;
    public $description;
    public $deadline_days;

    public function mount(Assignment $assignment)
    {
        $this->assignment = $assignment;
        $this->title = $assignment->title;
        $this->description = $assignment->description;
        $this->deadline_days = $assignment->deadline_days;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|min:3|max:255',
            'description' => 'nullable|string',
            'deadline_days' => 'nullable|integer|min:1|max:365',
        ], [
            'title.required' => 'Judul tugas wajib diisi.',
            'deadline_days.integer' => 'Durasi harus berupa angka bulat.',
        ]);

        $this->assignment->update([
            'title' => $this->title,
            'description' => $this->description,
            'deadline_days' => $this->deadline_days,
        ]);

        session()->flash('message', 'Tugas berhasil diperbarui!');
    }
}; ?>

<div class="min-h-screen bg-gray-50/50 pb-20 font-sans text-gray-900">

    {{-- STICKY HEADER --}}
    <div class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- Breadcrumbs --}}
                <div class="flex items-center gap-3">
                    <a href="{{ $assignment->course_id ? route('instructor.course.builder', $assignment->course_id) : route('instructor.programs') }}"
                       class="group flex items-center justify-center w-8 h-8 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm">
                        <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Editor Tugas</span>
                        <h1 class="text-sm font-bold text-gray-900 truncate max-w-xs md:max-w-md">{{ $assignment->title }}</h1>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-3">
                    <div x-data="{ show: false }"
                         x-show="show"
                         x-transition.opacity.duration.500ms
                         x-init="@this.on('message', () => { show = true; setTimeout(() => show = false, 3000) })"
                         class="hidden md:flex items-center text-xs font-medium text-green-600 bg-green-50 px-3 py-1.5 rounded-full border border-green-100">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Tersimpan
                    </div>

                    <button wire:click="save"
                            wire:loading.attr="disabled"
                            class="relative inline-flex items-center justify-center px-6 py-2 text-sm font-bold text-white transition-all bg-indigo-600 border border-transparent rounded-lg shadow-md hover:bg-indigo-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 disabled:opacity-70 disabled:cursor-not-allowed">
                        <span wire:loading.remove>Simpan Tugas</span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menyimpan...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            {{-- LEFT: Main Editor --}}
            <div class="lg:col-span-8 space-y-6">

                {{-- Judul Input --}}
                <div class="bg-white border-b-2 border-gray-100 focus-within:border-indigo-500 transition-colors">
                    <input type="text"
                           wire:model="title"
                           class="block w-full text-3xl font-extrabold text-gray-900 border-0 focus:ring-0 px-0 py-4 placeholder-gray-300 bg-transparent"
                           placeholder="Judul Tugas...">
                    @error('title') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- DESCRIPTION EDITOR (CKEDITOR) --}}
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden min-h-[500px] relative">
                    <div class="p-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="text-sm font-semibold text-gray-700">Instruksi Pengerjaan</h3>
                    </div>

                    {{-- Alpine Wrapper for CKEditor --}}
                    <div wire:ignore
                         x-data="{
                             value: @entangle('description'),
                             init() {
                                 ClassicEditor.create(this.$refs.editor, {
                                     toolbar: {
                                         items: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo'],
                                         shouldNotGroupWhenFull: true
                                     },
                                     placeholder: 'Jelaskan apa yang harus dikerjakan siswa...'
                                 })
                                 .then(editor => {
                                     editor.model.document.on('change:data', () => {
                                         this.value = editor.getData();
                                     });

                                     this.$watch('value', (newValue) => {
                                         if (newValue !== editor.getData()) {
                                             editor.setData(newValue);
                                         }
                                     });
                                 })
                                 .catch(error => console.error(error));
                             }
                         }"
                         class="p-4">
                        <div x-ref="editor" class="prose max-w-none"></div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Settings --}}
            <div class="lg:col-span-4 space-y-6">

                {{-- Deadline Card --}}
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Pengaturan Batas Waktu</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Durasi Pengerjaan (Hari)</label>
                            <div class="relative rounded-md shadow-sm">
                                <input type="number" wire:model="deadline_days" class="block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Contoh: 3">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-xs">hari</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Dihitung sejak siswa mulai mengakses materi ini.</p>
                            @error('deadline_days') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Submission Info Card --}}
                <div class="bg-gradient-to-br from-indigo-50 to-white border border-indigo-100 shadow-sm rounded-xl p-6">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-indigo-100 rounded-lg text-indigo-600 shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-indigo-900 mb-1">Info Submission</h3>
                            <p class="text-xs text-indigo-800/80 leading-relaxed">
                                Siswa akan diminta mengupload file (PDF/Zip) sebagai bukti pengerjaan tugas. Anda dapat memberikan nilai dan feedback setelah mereka mengumpulkan.
                            </p>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

@assets
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
<style>
    .ck-editor__editable_inline {
        min-height: 300px;
    }
</style>
@endassets

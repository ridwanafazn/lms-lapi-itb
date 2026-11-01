<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Course;

new #[Layout('layouts.course-workspace')] class extends Component {
    public Course $course;

    public function mount(Course $course)
    {
        // Otorisasi Manual
        $this->authorize('update', $course);

        $this->course = $course;
    }
}; ?>

<div>
    <x-slot:actions>
        <button disabled class="px-5 py-2.5 text-sm font-bold text-slate-400 bg-slate-100 border border-slate-200 rounded-xl cursor-not-allowed">Simpan Perubahan</button>
    </x-slot:actions>

    <div class="max-w-4xl mx-auto pb-20">
        <div class="bg-white border border-slate-200 shadow-sm rounded-2xl overflow-hidden text-center py-16 px-6">
            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-2">Course Settings</h2>
            <p class="text-slate-500 max-w-md mx-auto">Pengaturan lanjutan seperti batasan akses peserta, visibilitas global, dan opsi penghapusan kelas akan ditempatkan pada halaman ini.</p>
        </div>
    </div>
</div>

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
            <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-2">Pengaturan Sertifikat</h2>
            <p class="text-slate-500 max-w-md mx-auto">Pengaturan desain sertifikat kelulusan, tanda tangan digital, dan syarat kelulusan kelas akan segera tersedia di sini.</p>
        </div>
    </div>
</div>

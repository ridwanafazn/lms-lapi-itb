<?php

use App\Models\Batch;
use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use function Livewire\Volt\{state, computed, usesPagination, layout, updated};

layout('layouts.app');
usesPagination();

state([
    'name' => '',
    'batch_type' => 'program',
    'selected_entity_id' => '',
    'start_date' => '',
    'end_date' => '',
    'status' => 'open',
    'price' => 0,
    'min_membership_level' => 'none',
    'batch_id' => null,
    'isModalOpen' => false,
    'search' => '',
    'filterStatus' => '',
    'filterType' => '',
    'perPage' => 5,
]);

updated(['search', 'filterStatus', 'filterType', 'perPage'], function () {
    $this->resetPage();
});

// FILTER LOGIC:
// Hanya Program tipe 'Cohort' yang boleh punya Batch.
// Hanya Course tipe 'Cohort' yang boleh punya Batch.
$programs = computed(fn () => Program::where('is_active', true)->where('type', 'cohort')->select('id', 'title')->get());
$courses = computed(fn () => Course::where('delivery_mode', 'cohort_based')->select('id', 'title')->get());

$batches = computed(function () {
    return Batch::query()
        ->with(['program', 'course']) // Hapus 'instructors'
        ->where(function ($query) {
            $query->whereNotNull('program_id')
                  ->orWhereHas('course', function ($q) {
                      $q->where('delivery_mode', 'cohort_based');
                  });
        })
        ->when($this->filterType, function ($query) {
            if ($this->filterType === 'program') {
                $query->whereNotNull('program_id');
            } elseif ($this->filterType === 'course') {
                $query->whereNotNull('course_id');
            }
        })
        ->when($this->search, function ($query) {
            $query->where(function ($q) {
                $q->where('batch_name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('program', fn($sub) => $sub->where('title', 'like', '%' . $this->search . '%'))
                  ->orWhereHas('course', fn($sub) => $sub->where('title', 'like', '%' . $this->search . '%'));
            });
        })
        ->when($this->filterStatus, function ($query) {
            $query->where('status', $this->filterStatus);
        })
        ->latest()
        ->paginate($this->perPage);
});

$save = function () {
    $rules = [
        'batch_type' => 'required|in:program,course',
        'selected_entity_id' => 'required',
        'name' => 'required|string|max:255',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'status' => 'required',
        'price' => 'required|numeric|min:0',
        'min_membership_level' => 'required|in:none,standard,pro',
    ];

    $this->validate($rules);

    $data = [
        'batch_name' => $this->name,
        'start_date' => $this->start_date,
        'end_date' => $this->end_date,
        'status' => $this->status,
        'price' => $this->price,
        'min_membership_level' => $this->min_membership_level ?? 'none',
        'program_id' => $this->batch_type === 'program' ? $this->selected_entity_id : null,
        'course_id' => $this->batch_type === 'course' ? $this->selected_entity_id : null,
    ];

    if ($this->batch_id) {
        $batch = Batch::findOrFail($this->batch_id);
        $batch->update($data);
    } else {
        $batch = Batch::create($data);
    }

    session()->flash('message', 'Jadwal Batch berhasil disimpan.');
    $this->isModalOpen = false;
    $this->reset(['name', 'batch_type', 'selected_entity_id', 'start_date', 'end_date', 'batch_id', 'status']);
};

$edit = function ($id) {
    $batch = Batch::findOrFail($id);
    $this->batch_id = $id;
    $this->name = $batch->batch_name;
    
    // [PERBAIKAN] Cek apakah array punya key 'date', lalu parse dengan Carbon
    $this->start_date = isset($batch->start_date['date']) ? \Carbon\Carbon::parse($batch->start_date['date'])->format('Y-m-d') : null;
    $this->end_date = isset($batch->end_date['date']) ? \Carbon\Carbon::parse($batch->end_date['date'])->format('Y-m-d') : null;
    
    $this->status = $batch->status;
    $this->price = $batch->price ?? 0;
    $this->min_membership_level = $batch->min_membership_level ?? 'none';

    if ($batch->program_id) {
        $this->batch_type = 'program';
        $this->selected_entity_id = $batch->program_id;
    } else {
        $this->batch_type = 'course';
        $this->selected_entity_id = $batch->course_id;
    }

    $this->isModalOpen = true;
};

$delete = function ($id) {
    $batch = Batch::findOrFail($id);
    $batch->delete();
    session()->flash('message', 'Batch berhasil dihapus.');
};

$closeModal = function () {
    $this->isModalOpen = false;
    $this->reset(['name', 'batch_type', 'selected_entity_id', 'start_date', 'end_date', 'batch_id', 'status', 'price', 'min_membership_level']);
};

$create = function () {
    $this->reset(['name', 'batch_type', 'selected_entity_id', 'start_date', 'end_date', 'batch_id', 'price', 'min_membership_level']);
    $this->status = 'open';
    $this->batch_type = 'program';
    $this->price = 0;
    $this->min_membership_level = 'none';
    $this->isModalOpen = true;
};

?>

<div class="min-h-screen bg-slate-50/50 font-sans text-slate-900">
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-800 leading-tight tracking-tight">{{ __('Batch Management') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                     class="fixed top-5 right-5 z-50 bg-emerald-500 text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-2 transition-all duration-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="font-medium">{{ session('message') }}</span>
                </div>
            @endif

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4 md:space-y-0 md:flex md:items-center md:justify-between">

                <div class="md:w-1/4">
                    <h1 class="text-lg font-bold text-slate-900">Angkatan Belajar</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Kelola jadwal program angkatan.</p>
                </div>

                <div class="flex-1 flex flex-col md:flex-row gap-3 md:justify-center">
                    <div class="relative w-full md:w-60">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Batch..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-sm focus:ring-indigo-500 focus:border-indigo-500 placeholder-slate-400 transition-all">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <select wire:model.live="filterStatus" class="border-slate-200 bg-slate-50 rounded-lg text-sm focus:ring-indigo-500 py-2">
                        <option value="">Status</option>
                        <option value="open">Open</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 md:w-1/4">
                    <button wire:click="create" class="flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow transition-all whitespace-nowrap">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Batch Baru
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4">
                @forelse($this->batches as $batch)
                    <div wire:key="batch-{{ $batch->id }}" class="group bg-white rounded-xl p-5 border border-slate-200 hover:border-indigo-300 hover:shadow-md transition-all duration-200 flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">

                        <div class="flex items-start gap-4 flex-1">
                            <div class="flex-shrink-0">
                                <div class="h-12 w-12 rounded-xl flex items-center justify-center border shadow-sm transition-colors
                                    {{ $batch->program_id ? 'bg-purple-50 border-purple-100 text-purple-600' : 'bg-blue-50 border-blue-100 text-blue-600' }}">
                                    @if($batch->program_id)
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    @else
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-base font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">{{ $batch->batch_name }}</h4>
                                    @if($batch->program_id)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 border border-purple-200 uppercase">Program</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200 uppercase">Course</span>
                                    @endif
                                </div>
                                <p class="text-sm text-slate-500 font-medium mb-1">
                                    {{ $batch->program_id ? ($batch->program->title ?? '-') : ($batch->course->title ?? '-') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-row md:flex-col items-center md:items-end justify-between w-full md:w-auto gap-4">
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold capitalize border
                                    {{ $batch->status == 'open' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                      ($batch->status == 'ongoing' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-100 text-slate-600 border-slate-200') }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $batch->status == 'open' ? 'bg-emerald-500' : ($batch->status == 'ongoing' ? 'bg-amber-500' : 'bg-slate-500') }}"></span>
                                    {{ $batch->status }}
                                </span>
                                <div class="flex items-center gap-1 mt-2 text-xs text-slate-500 justify-end">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    {{-- [PERBAIKAN] Mengecek key 'date' dan mem-parsingnya saat dirender --}}
                                    @if(isset($batch->start_date['date']) && isset($batch->end_date['date']))
                                        {{ \Carbon\Carbon::parse($batch->start_date['date'])->format('d M') }} - {{ \Carbon\Carbon::parse($batch->end_date['date'])->format('d M Y') }}
                                    @else
                                        <span class="italic text-slate-400">Tanggal belum diset</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="edit('{{ $batch->id }}')" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <button wire:click="delete('{{ $batch->id }}')" wire:confirm="Hapus batch ini?" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center bg-white rounded-xl border border-dashed border-slate-300">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-4">
                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900">Tidak ada batch ditemukan</h3>
                        <p class="text-sm text-slate-500 mt-1">Coba sesuaikan filter pencarian atau buat jadwal baru.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $this->batches->links() }}
            </div>
        </div>
    </div>

    @if($isModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" aria-hidden="true" wire:click="closeModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100">

                    <form wire:submit="save">
                        <div class="bg-white px-6 py-5 border-b border-slate-100 flex items-center justify-between sticky top-0 z-10">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">{{ $batch_id ? 'Edit Jadwal' : 'Buat Jadwal Baru' }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Atur detail angkatan dan tanggal pelaksanaan.</p>
                            </div>
                            <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <div class="px-6 py-6 space-y-5 max-h-[70vh] overflow-y-auto">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    {{ $batch_type == 'program' ? 'Pilih Program' : 'Pilih Course' }}
                                </label>
                                <select wire:model="selected_entity_id" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5">
                                    <option value="">-- Pilih --</option>
                                    @if($batch_type == 'program')
                                        @foreach($this->programs as $p)
                                            <option value="{{ $p->id }}">{{ Str::limit($p->title, 60) }}</option>
                                        @endforeach
                                    @else
                                        @foreach($this->courses as $c)
                                            <option value="{{ $c->id }}">{{ Str::limit($c->title, 60) }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('selected_entity_id') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Batch</label>
                                <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5 placeholder-slate-400" placeholder="Contoh: Angkatan Q1 2026">
                                @error('name') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Mulai</label>
                                    <input type="date" wire:model="start_date" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @error('start_date') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Selesai</label>
                                    <input type="date" wire:model="end_date" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @error('end_date') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Harga Override (IDR)</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <span class="text-slate-500 sm:text-sm">Rp</span>
                                        </div>
                                        <input type="number" wire:model="price" class="block w-full rounded-lg border-slate-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5" placeholder="0 (ikuti program)">
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-1">0 = gunakan harga program. Kosongkan untuk gratis.</p>
                                    @error('price') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Membership Level Diperlukan</label>
                                    <select wire:model="min_membership_level" class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5">
                                        <option value="none">None (tidak termasuk membership)</option>
                                        <option value="standard">Standard</option>
                                        <option value="pro">Pro</option>
                                    </select>
                                    <p class="text-[10px] text-slate-400 mt-1">Kosong = ikuti program. Menentukan apakah user membership bisa akses batch ini.</p>
                                    @error('min_membership_level') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Status</label>
                                <select wire:model="status" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2.5">
                                    <option value="open">Open</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                        </div>

                        <div class="bg-slate-50 px-6 py-4 flex flex-row-reverse gap-3 border-t border-slate-100 rounded-b-2xl">
                            <button type="submit" class="inline-flex justify-center items-center rounded-lg shadow-sm px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all w-full sm:w-auto">
                                Simpan Jadwal
                            </button>
                            <button type="button" wire:click="closeModal" class="inline-flex justify-center rounded-lg px-5 py-2.5 bg-white border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all w-full sm:w-auto">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

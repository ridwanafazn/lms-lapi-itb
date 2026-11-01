<?php

namespace App\Livewire\Operations;

use App\Models\Batch;
use App\Models\LiveAttendance;
use App\Models\Material;
use App\Models\User;
use App\Services\LiveAttendanceService;
use App\Services\OperationsAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AttendancePage extends Component
{
    use WithPagination;

    #[Url]
    public string $batchId = '';

    public string $materialId = '';

    public string $studentId = '';

    public string $status = 'present';

    public string $notes = '';

    public function mount(): void
    {
        app(OperationsAccess::class)->staff(auth()->user());
    }

    public function updatedBatchId(): void
    {
        $this->reset('materialId', 'studentId', 'notes');
        $this->resetPage();
        $this->resetValidation();
    }

    public function updatedMaterialId(): void
    {
        $this->resetPage();
        $this->reset('studentId', 'notes');
    }

    public function selectStudent(string $id): void
    {
        $batch = Batch::findOrFail($this->batchId);
        $service = app(LiveAttendanceService::class);
        $service->roster(auth()->user(), $batch)->findOrFail($id);
        $service->sessions(auth()->user(), $batch)->findOrFail($this->materialId);
        $record = LiveAttendance::where('batch_id', $batch->id)->where('material_id', $this->materialId)->where('user_id', $id)->first();
        $this->studentId = $id;
        $this->status = $record?->status ?? 'present';
        $this->notes = $record?->notes ?? '';
    }

    public function save(): void
    {
        app(OperationsAccess::class)->staff(auth()->user());
        $this->validate(['batchId' => 'required|uuid', 'materialId' => 'required|uuid', 'studentId' => 'required|uuid']);
        app(LiveAttendanceService::class)->record(auth()->user(), Batch::findOrFail($this->batchId), Material::findOrFail($this->materialId), User::findOrFail($this->studentId), $this->status, $this->notes);
        session()->flash('success', 'Absensi manual disimpan. Absensi bukan syarat sertifikat.');
    }

    public function render()
    {
        $actor = auth()->user();
        $service = app(LiveAttendanceService::class);
        $batches = app(OperationsAccess::class)->batches($actor)->orderBy('batch_name')->get();
        $batch = $this->batchId !== '' ? Batch::findOrFail($this->batchId) : null;
        $sessions = $batch ? $service->sessions($actor, $batch)->orderBy('start_time')->get() : collect();
        if ($this->materialId !== '') {
            abort_unless($sessions->contains('id', $this->materialId), 403);
        }
        $roster = $batch && $this->materialId !== '' ? $service->roster($actor, $batch)->orderBy('name')->paginate(20) : null;
        $records = $roster ? LiveAttendance::with('recorder')->where('batch_id', $batch->id)->where('material_id', $this->materialId)
            ->whereIn('user_id', $roster->pluck('id'))->get()->keyBy('user_id') : collect();

        return view('livewire.operations.attendance-page', compact('batches', 'batch', 'sessions', 'roster', 'records'));
    }
}

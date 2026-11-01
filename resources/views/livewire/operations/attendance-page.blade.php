<div class="space-y-6">
    <h1 class="text-2xl font-bold">Absensi live session</h1>
    @include('livewire.operations.navigation')
    <p class="text-sm text-gray-600">Dicatat manual oleh admin atau instruktur terkait. Absensi tidak mengubah progres belajar dan bukan syarat sertifikat.</p>
    <div class="grid gap-4 rounded-xl bg-white p-6 md:grid-cols-2">
        <label>Batch
            <select wire:model.live="batchId" class="mt-1 w-full rounded border-gray-300">
                <option value="">Pilih batch</option>
                @foreach($batches as $option)<option value="{{ $option->id }}">{{ $option->batch_name }}</option>@endforeach
            </select>
        </label>
        <label>Sesi live
            <select wire:model.live="materialId" class="mt-1 w-full rounded border-gray-300">
                <option value="">Pilih sesi live</option>
                @foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->title }} {{ $session->start_time?->format('d/m/Y H:i') }}</option>@endforeach
            </select>
        </label>
        @if($batch && $sessions->isEmpty())<p class="text-gray-500 md:col-span-2">Tidak ada sesi live yang dapat Anda kelola pada batch ini.</p>@endif
    </div>
    @if($roster)
        @if($studentId)
            <form wire:submit="save" class="space-y-3 rounded-xl bg-white p-6">
                <h2 class="font-semibold">Catat / koreksi absensi peserta terpilih</h2>
                <label class="block">Status<select wire:model="status" class="mt-1 w-full rounded border-gray-300"><option value="present">Hadir</option><option value="absent">Tidak hadir</option><option value="excused">Izin</option></select></label>
                <label class="block">Catatan<textarea wire:model="notes" maxlength="2000" class="mt-1 w-full rounded border-gray-300"></textarea></label>
                <button wire:loading.attr="disabled" class="rounded bg-indigo-600 px-4 py-2 text-white">Simpan absensi</button>
            </form>
        @endif
        <div class="overflow-x-auto rounded-xl bg-white">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b"><th class="p-4">Peserta</th><th class="p-4">Status & catatan</th><th class="p-4">Dicatat oleh</th><th class="p-4">Tindakan</th></tr></thead>
                <tbody>
                    @forelse($roster as $student)
                        @php($record = $records->get($student->id))
                        <tr wire:key="attendance-{{ $student->id }}" class="border-b">
                            <td class="p-4">{{ $student->name }}<br>{{ $student->email }}</td>
                            <td class="p-4">{{ $record ? (['present' => 'Hadir', 'absent' => 'Tidak hadir', 'excused' => 'Izin'][$record->status] ?? $record->status) : 'Belum dicatat' }}<p>{{ $record?->notes }}</p></td>
                            <td class="p-4">{{ $record?->recorder?->name }}<br>{{ $record?->recorded_at?->format('d/m/Y H:i') }}</td>
                            <td class="p-4"><button wire:click="selectStudent('{{ $student->id }}')" class="text-indigo-700">{{ $record ? 'Koreksi' : 'Catat' }}</button></td>
                        </tr>
                    @empty<tr><td colspan="4" class="p-6 text-gray-500">Belum ada peserta aktif/selesai pada batch ini.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $roster->links() }}
    @endif
</div>

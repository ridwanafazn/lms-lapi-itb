<div class="space-y-6">
    <h1 class="text-2xl font-bold">Antrean & salin batch</h1>
    @include('livewire.operations.navigation')
    <label class="block rounded-xl bg-white p-6">Batch
        <select wire:model.live="batchId" class="mt-2 w-full rounded border-gray-300">
            <option value="">Pilih batch</option>
            @foreach($batches as $option)<option value="{{ $option->id }}">{{ $option->batch_name }}</option>@endforeach
        </select>
    </label>
    @if($batch)
        <section class="space-y-3 rounded-xl bg-white p-6">
            <h2 class="font-semibold">{{ $batch->batch_name }} · {{ $batch->status }}</h2>
            <p>Kursi terisi / direservasi: {{ $occupied }} / {{ $batch->quota }}. Kuota 0 belum mengizinkan promosi.</p>
            <p class="text-sm text-gray-600">Promosi hanya untuk peserta paling awal saat kursi tersedia. Reservasi tidak memberi akses belajar atau membebaskan pembayaran. Batalkan reservasi secara eksplisit jika tidak dilanjutkan; tidak ada kedaluwarsa otomatis.</p>
            <div class="flex flex-wrap gap-4 text-indigo-700">
                <a href="{{ route('admin.attendance', ['batchId' => $batch->id]) }}" wire:navigate>Absensi batch</a>
                <a href="{{ route('admin.payouts') }}" wire:navigate>Kelola honor</a>
                <a href="{{ route('admin.batches') }}" wire:navigate>Ubah konfigurasi / jadwal batch</a>
            </div>
        </section>
        <form wire:submit="cloneBatch" class="space-y-3 rounded-xl bg-white p-6">
            <h2 class="font-semibold">Salin konfigurasi batch</h2>
            <p class="text-sm text-gray-600">Menyalin konfigurasi termasuk jadwal/status, relasi kelas/program, dan penugasan instruktur. Tidak menyalin peserta, transaksi, progres, waitlist, honor, atau absensi. Kelas/program tetap menggunakan data yang sama.</p>
            <label class="block">Nama batch baru<input wire:model="cloneName" required maxlength="255" class="mt-1 w-full rounded border-gray-300"></label>
            <button wire:loading.attr="disabled" class="rounded bg-indigo-600 px-4 py-2 text-white">Salin batch</button>
        </form>
        <div class="overflow-x-auto rounded-xl bg-white">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b"><th class="p-4">Urutan</th><th class="p-4">Peserta</th><th class="p-4">Status</th><th class="p-4">Tindakan</th></tr></thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr wire:key="waitlist-{{ $entry->id }}" class="border-b">
                            <td class="p-4">#{{ $entry->queue_number }}<br>{{ $entry->joined_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4">{{ $entry->user?->name ?? 'Peserta dihapus' }}<br>{{ $entry->user?->email }}</td>
                            <td class="p-4">{{ ['waiting' => 'Menunggu', 'promoted' => 'Direservasi — lanjut pembayaran', 'cancelled' => 'Dibatalkan'][$entry->status] ?? $entry->status }}</td>
                            <td class="space-x-3 p-4">
                                @if($entry->status === 'waiting')<button wire:click="promote('{{ $entry->id }}')" wire:loading.attr="disabled" class="text-indigo-700">Promosikan FIFO</button>@endif
                                @if(in_array($entry->status, ['waiting', 'promoted']))<button wire:click="cancel('{{ $entry->id }}')" wire:confirm="Batalkan antrean/reservasi ini? Enrollment yang sudah ada tidak diubah." class="text-red-700">Batalkan</button>@endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="4" class="p-6 text-gray-500">Belum ada antrean.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $entries->links() }}
    @endif
</div>

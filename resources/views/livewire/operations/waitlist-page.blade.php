<div class="space-y-6">
    <h1 class="text-2xl font-bold">Antrean batch saya</h1>
    @include('livewire.operations.navigation')
    <p class="text-sm text-gray-600">Admin mempromosikan peserta sesuai urutan FIFO bila kursi tersedia. Promosi bukan enrollment dan tidak membebaskan pembayaran. Setelah dipromosikan, lanjutkan pendaftaran serta pembayaran normal dari halaman program.</p>
    <section class="space-y-4 rounded-xl bg-white p-6">
        <h2 class="font-semibold">Status antrean saya</h2>
        @forelse($entries as $entry)
            <div wire:key="mine-{{ $entry->id }}" class="flex flex-wrap items-center justify-between gap-4 border-b pb-3">
                <div>
                    <p class="font-medium">{{ $entry->batch?->batch_name ?? 'Batch tidak tersedia' }} · #{{ $entry->queue_number }}</p>
                    <p>{{ ['waiting' => 'Menunggu promosi admin', 'promoted' => 'Kursi direservasi — lanjut pendaftaran & pembayaran', 'cancelled' => 'Dibatalkan'][$entry->status] ?? $entry->status }}</p>
                    @if($entry->status === 'promoted' && $entry->batch?->program_id)
                        <a href="{{ route('student.program.detail', $entry->batch->program_id) }}" wire:navigate class="text-indigo-700">Lanjut ke program</a>
                    @elseif($entry->status === 'promoted')
                        <p class="text-sm text-gray-500">Hubungi admin untuk melanjutkan pembayaran batch kelas ini.</p>
                    @endif
                </div>
                @if($entry->batch && in_array($entry->status, ['waiting', 'promoted']))<button wire:click="cancel('{{ $entry->id }}')" wire:confirm="Batalkan antrean atau reservasi Anda?" class="text-red-700">Batalkan</button>@endif
            </div>
        @empty<p class="text-gray-500">Anda belum masuk antrean.</p>@endforelse
    </section>
    <section class="space-y-4 rounded-xl bg-white p-6">
        <h2 class="font-semibold">Batch terbuka</h2>
        @forelse($batches as $batch)
            <div wire:key="batch-{{ $batch->id }}" class="flex items-center justify-between gap-4 border-b pb-3">
                <div><p>{{ $batch->batch_name }}</p><p class="text-sm text-gray-500">Kuota: {{ $batch->quota }}</p></div>
                <button wire:click="join('{{ $batch->id }}')" wire:loading.attr="disabled" class="rounded bg-indigo-600 px-4 py-2 text-white">Masuk antrean</button>
            </div>
        @empty<p class="text-gray-500">Tidak ada batch terbuka.</p>@endforelse
        {{ $batches->links() }}
    </section>
</div>

<div class="space-y-6">
    <h1 class="text-2xl font-bold">{{ $isAdmin ? 'Honor instruktur' : 'Honor saya' }}</h1>
    @include('livewire.operations.navigation')
    <p class="text-sm text-gray-600">Honor tetap per instruktur dan batch, bukan persentase pendapatan. Pembayaran dilakukan manual di luar aplikasi, lalu bukti transfer dicatat di sini.</p>

    @if($isAdmin)
        <form wire:submit="setHonor" class="grid gap-4 rounded-xl bg-white p-6 md:grid-cols-2">
            <h2 class="font-semibold md:col-span-2">Tetapkan / ubah honor belum dibayar</h2>
            <label>Batch
                <select wire:model="batchId" required class="mt-1 w-full rounded border-gray-300">
                    <option value="">Pilih batch</option>
                    @foreach($batches as $batch)<option value="{{ $batch->id }}">{{ $batch->batch_name }}</option>@endforeach
                </select>
            </label>
            <label>Instruktur terkait
                <select wire:model="instructorId" required class="mt-1 w-full rounded border-gray-300">
                    <option value="">Pilih instruktur</option>
                    @foreach($instructors as $instructor)<option value="{{ $instructor->id }}">{{ $instructor->name }}</option>@endforeach
                </select>
            </label>
            <label>Honor tetap (Rp)<input wire:model="amount" type="number" min="0.01" step="0.01" required class="mt-1 w-full rounded border-gray-300"></label>
            <label>Catatan<input wire:model="honorNotes" maxlength="2000" class="mt-1 w-full rounded border-gray-300"></label>
            <button wire:loading.attr="disabled" class="rounded bg-indigo-600 px-4 py-2 text-white">Simpan honor</button>
        </form>
        @if($paymentPayout)
            <form method="POST" action="{{ route('admin.payouts.payment', $paymentPayout) }}" enctype="multipart/form-data" x-data x-on:submit="if (! confirm('Sudah mentransfer seluruh honor dan memeriksa buktinya?')) $event.preventDefault()" class="space-y-4 rounded-xl border border-indigo-200 bg-white p-6">
                @csrf
                <input type="hidden" name="payment_payout_id" value="{{ $paymentPayout->id }}">
                <h2 class="font-semibold">Catat transfer penuh · {{ $paymentPayout->instructorUser?->name }} · {{ $paymentPayout->batch?->batch_name }}</h2>
                <p>Rp {{ number_format((float) $paymentPayout->amount, 2, ',', '.') }}</p>
                <p class="text-sm">Pastikan jumlah transfer sama dengan honor di atas. Pencatatan bersifat final dan tidak dapat dibayar dua kali.</p>
                <label class="block">Referensi transfer<input name="transfer_reference" value="{{ old('transfer_reference') }}" required maxlength="255" class="mt-1 w-full rounded border-gray-300"></label>
                <label class="block">Tanggal pembayaran<input name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" type="date" max="{{ now()->toDateString() }}" required class="mt-1 w-full rounded border-gray-300"></label>
                <label class="block">Bukti transfer privat (PDF/JPG/PNG, maks. 10 MB)<input name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="mt-1 block"></label>
                <label class="block">Catatan pembayaran<textarea name="notes" maxlength="2000" class="mt-1 w-full rounded border-gray-300">{{ old('notes') }}</textarea></label>
                <button class="rounded bg-indigo-600 px-4 py-2 text-white">Konfirmasi pembayaran manual</button>
            </form>
        @endif
    @endif

    <div class="overflow-x-auto rounded-xl bg-white">
        <table class="w-full text-left text-sm">
            <thead><tr class="border-b"><th class="p-4">Batch / instruktur</th><th class="p-4">Honor</th><th class="p-4">Pembayaran</th><th class="p-4">Bukti & audit</th></tr></thead>
            <tbody>
                @forelse($payouts as $payout)
                    <tr wire:key="payout-{{ $payout->id }}" class="border-b align-top">
                        <td class="p-4">{{ $payout->batch?->batch_name ?? 'Batch dihapus' }}<br>{{ $payout->instructorUser?->name }}<p class="text-gray-500">{{ $payout->notes }}</p></td>
                        <td class="p-4">Rp {{ number_format((float) $payout->amount, 2, ',', '.') }}</td>
                        <td class="space-y-2 p-4">
                            <p>{{ $payout->status === 'paid' ? 'Dibayar' : 'Belum dibayar' }}</p>
                            @if($payout->paid_at)<p>{{ $payout->paid_at->format('d/m/Y') }} · {{ $payout->transfer_reference }}</p><p>Dicatat oleh {{ $payout->paidBy?->name }}</p>@endif
                            @if($isAdmin && $payout->status === 'pending')
                                <button wire:click="editHonor('{{ $payout->id }}')" class="text-indigo-700">Ubah honor</button>
                                <button wire:click="selectPayment('{{ $payout->id }}')" class="block text-indigo-700">Catat transfer</button>
                            @endif
                        </td>
                        <td class="p-4">
                            @if($payout->evidence_path)<a href="{{ route('payouts.evidence', $payout) }}" class="text-indigo-700">Unduh bukti transfer</a>@endif
                            <details class="mt-2"><summary class="cursor-pointer">Riwayat audit ({{ $payout->audits->count() }})</summary>
                                @foreach($payout->audits as $audit)
                                    <div class="mt-2 border-t pt-2">
                                        <p>{{ $audit->created_at->format('d/m/Y H:i') }} · {{ $audit->actor?->name }} · {{ ['honor_set' => 'Honor ditetapkan', 'honor_updated' => 'Honor diubah', 'payment_recorded' => 'Transfer dicatat'][$audit->action] ?? $audit->action }}</p>
                                        <pre class="max-w-sm whitespace-pre-wrap break-all text-xs">{{ json_encode($audit->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endforeach
                            </details>
                        </td>
                    </tr>
                @empty<tr><td colspan="4" class="p-6 text-gray-500">Belum ada honor.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    {{ $payouts->links() }}
</div>

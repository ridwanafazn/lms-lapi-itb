<div class="mx-auto max-w-5xl space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Tagihan & Perpanjangan</h1>
        <p class="mt-2 text-gray-600">Perpanjangan menggunakan invoice yang Anda bayar sendiri. Tidak ada auto debit. Akses baru diperpanjang setelah pembayaran diverifikasi admin.</p>
    </div>

    @if (session('billingStatus'))
        <div role="status" class="rounded bg-green-50 p-4 text-green-800">{{ session('billingStatus') }}</div>
    @endif
    @error('billing') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror

    @if ($subscription)
        <section class="space-y-3 rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">{{ $subscription->membershipPlan->display_name }}</h2>
            <p>Status perpanjangan: <strong>{{ $subscription->status === 'active' ? 'Aktif' : 'Dibatalkan' }}</strong></p>
            <p>Masa aktif membership: {{ auth()->user()->membership_expires_at?->format('d M Y H:i') ?? 'Lifetime' }}</p>
            @if ($subscription->next_billing_at)
                <p>Jadwal invoice: {{ $subscription->next_billing_at->format('d M Y H:i') }}. Invoice yang belum dibayar tidak memberikan akses.</p>
                @if ($subscription->status === 'active')
                    <button type="button" wire:click="cancelSubscription('{{ $subscription->id }}')" wire:confirm="Batalkan perpanjangan? Invoice renewal pending akan dibatalkan, tetapi akses yang sudah dibayar tetap berlaku." wire:loading.attr="disabled" class="rounded border border-red-600 px-4 py-2 text-red-700">Batalkan perpanjangan</button>
                @else
                    <button type="button" wire:click="reactivateSubscription('{{ $subscription->id }}')" wire:loading.attr="disabled" class="rounded bg-indigo-600 px-4 py-2 text-white">Aktifkan kembali perpanjangan</button>
                @endif
            @else
                <p>Membership lifetime tidak memiliki invoice perpanjangan.</p>
            @endif
            <p class="text-sm text-gray-600">Setelah pembatalan, jangan membayar invoice renewal lama. Reaktivasi tidak menghidupkan invoice lama dan tidak langsung mengaktifkan akses. Jika sudah terlanjur transfer, hubungi admin.</p>
        </section>
    @endif

    @if ($invoice)
        <section class="space-y-4 rounded-lg bg-white p-6 shadow">
            <a href="{{ route('billing.index') }}" wire:navigate class="text-indigo-700">Kembali ke daftar invoice</a>
            <h2 class="text-lg font-semibold">{{ $invoice->invoice_code }}</h2>
            <p>{{ $invoice->purchased_item_name }} — Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }}</p>
            <p>Status: <strong>{{ $invoice->refunded_at ? 'Direfund' : $invoice->payment_status }}</strong></p>
            @if (! empty($bank['bank_name']) && ! empty($bank['account_number']) && ! empty($bank['account_name']))
                <div class="space-y-1 rounded bg-gray-50 p-4">
                    <h3 class="font-semibold">Instruksi transfer manual</h3>
                    <p>Bank: {{ $bank['bank_name'] }}</p>
                    <p>Nomor rekening: {{ $bank['account_number'] }}</p>
                    <p>Atas nama: {{ $bank['account_name'] }}</p>
                    <p class="whitespace-pre-line">{{ $bank['instructions'] ?? '' }}</p>
                    <p>Cantumkan kode invoice {{ $invoice->invoice_code }}. Bayar hanya invoice berstatus pending yang masih berlaku.</p>
                </div>
            @else
                <p class="rounded bg-amber-50 p-4 text-amber-900">Instruksi transfer belum dikonfigurasi. Hubungi admin sebelum melakukan pembayaran; jangan menggunakan rekening dari sumber lain.</p>
            @endif
            @if ($invoice->payment_status === 'pending' && $invoice->payment_method === 'manual_transfer' && ! $invoice->refunded_at)
                <form method="POST" action="{{ route('billing.invoices.proof.store', $invoice) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <label for="billing-proof" class="block font-medium">Bukti transfer privat (PDF, JPG, PNG; maksimal 5 MB)</label>
                    <input id="billing-proof" type="file" name="proof" required accept="application/pdf,image/jpeg,image/png">
                    @error('proof') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
                    <button type="submit" class="block rounded bg-indigo-600 px-4 py-2 text-white">Kirim bukti untuk verifikasi</button>
                </form>
            @endif
            @if ($invoice->proof_file_url && str_starts_with($invoice->proof_file_url, 'billing/proofs/'.auth()->id().'/'))
                <a href="{{ route('billing.invoices.proof', $invoice) }}" class="inline-block text-indigo-700">Unduh bukti transfer saya</a>
            @endif
        </section>
    @endif

    <section class="overflow-x-auto rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold">Invoice saya</h2>
        <table class="w-full text-left">
            <thead><tr class="border-b"><th class="p-2">Invoice</th><th class="p-2">Pembelian</th><th class="p-2">Total</th><th class="p-2">Status</th></tr></thead>
            <tbody>
                @forelse ($invoices as $item)
                    <tr wire:key="invoice-{{ $item->id }}" class="border-b">
                        <td class="p-2"><a href="{{ route('billing.invoices.show', $item) }}" wire:navigate class="text-indigo-700">{{ $item->invoice_code }}</a></td>
                        <td class="p-2">{{ $item->purchased_item_name }}</td>
                        <td class="p-2">Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</td>
                        <td class="p-2">{{ $item->refunded_at ? 'Direfund' : $item->payment_status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-gray-600">Belum ada invoice.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </section>
</div>

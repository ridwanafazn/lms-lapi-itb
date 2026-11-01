<div class="max-w-6xl mx-auto p-6 space-y-6">
    <h1 class="text-2xl font-bold">Pembelian Korporat / HR</h1>
    <p>Satu invoice untuk seluruh kursi. Pilih akun siswa yang sudah ada setelah pembayaran lunas. Peserta yang telah dialokasikan tidak dapat diganti.</p>
    @if (session('commerceStatus')) <p role="status" class="p-3 bg-green-50 text-green-800">{{ session('commerceStatus') }}</p> @endif
    @if ($errors->any()) <ul role="alert" class="text-red-700">@foreach ($errors->all() as $message) <li>{{ $message }}</li> @endforeach</ul> @endif
    <form wire:submit="createPurchase" class="bg-white rounded-xl p-5 space-y-4 shadow">
        <label class="block">Nama perusahaan<input wire:model="companyName" required maxlength="255" class="block w-full rounded border-gray-300"></label>
        <label class="block">Program / batch
            <select wire:model="product" required class="block w-full rounded border-gray-300">
                <option value="">Pilih produk</option>
                @foreach ($programs as $program) <option value="program:{{ $program->id }}">{{ $program->title }} — Rp {{ number_format($program->price, 0, ',', '.') }} / kursi</option> @endforeach
                @foreach ($batches as $batch) <option value="batch:{{ $batch->id }}">{{ $batch->program?->title }} / {{ $batch->batch_name }} — Rp {{ number_format($batch->effective_price, 0, ',', '.') }} / kursi</option> @endforeach
            </select>
        </label>
        <label class="block">Jumlah kursi<input wire:model="seatCount" type="number" min="1" max="10000" required class="block rounded border-gray-300"></label>
        <label class="block">Pembayaran<select wire:model="paymentMethod" class="block rounded border-gray-300"><option value="manual_transfer">Transfer manual — diverifikasi admin</option><option value="gateway_va">Midtrans</option></select></label>
        <button wire:loading.attr="disabled" class="bg-blue-600 text-white rounded px-4 py-2">Buat invoice seluruh kursi</button>
        <p class="text-sm text-gray-600">Pembelian tidak otomatis mendaftarkan pembeli. Kuota seluruh kursi batch diperiksa saat pembayaran dan ditahan setelah invoice lunas.</p>
    </form>
    <form wire:submit="allocate" class="bg-white rounded-xl p-5 space-y-4 shadow">
        <h2 class="text-lg font-semibold">Alokasi peserta</h2>
        <label class="block">Invoice lunas<select wire:model="purchaseId" required class="block w-full rounded border-gray-300"><option value="">Pilih invoice</option>
            @foreach ($purchases as $purchase)
                @if ($purchase->transaction?->isPaid()) <option value="{{ $purchase->id }}">{{ $purchase->transaction->invoice_code }} — {{ $purchase->seats->count() }}/{{ $purchase->seat_count }} kursi</option> @endif
            @endforeach
        </select></label>
        <label class="block">Email akun siswa<input wire:model="participantEmail" type="email" required class="block w-full rounded border-gray-300"></label>
        <button wire:loading.attr="disabled" class="bg-blue-600 text-white rounded px-4 py-2">Alokasikan permanen</button>
    </form>
    @foreach ($purchases as $purchase)
        <section wire:key="purchase-{{ $purchase->id }}" class="bg-white rounded-xl p-5 shadow space-y-2">
            <h2 class="font-bold">{{ $purchase->company_name }} — {{ $purchase->program?->title ?? $purchase->batch?->batch_name }}</h2>
            <p>{{ $purchase->transaction?->invoice_code }} · {{ $purchase->transaction?->payment_status }} · {{ $purchase->seat_count }} kursi · Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}</p>
            <p>Dialokasikan: {{ $purchase->seats->count() }}/{{ $purchase->seat_count }} · Status: {{ $purchase->status }}</p>
            @if ($purchase->transaction?->refunded_at)
                <p class="text-sm text-red-700">Invoice telah direfund. Alokasi peserta tidak tersedia.</p>
            @endif
            @if ($purchase->transaction?->payment_method === 'manual_transfer' && $purchase->transaction?->payment_status === 'pending' && ! $purchase->transaction?->refunded_at)
                <a href="{{ route('billing.invoices.show', $purchase->transaction) }}" wire:navigate class="text-blue-700 underline">Lihat instruksi transfer & unggah bukti pembayaran</a>
            @endif
            @if ($purchase->transaction?->snap_token && $purchase->transaction->payment_status === 'pending')
                <button type="button" x-on:click="$dispatch('commerce-payment', { token: @js($purchase->transaction->snap_token) })" class="text-blue-700 underline">Lanjutkan pembayaran Midtrans</button>
            @endif
            <ul class="text-sm">@foreach ($purchase->seats as $seat) <li>{{ $seat->user?->name ?? 'Akun dinonaktifkan' }} — {{ $seat->user?->email ?? '-' }} · {{ $seat->allocated_at->format('d/m/Y H:i') }}</li> @endforeach</ul>
        </section>
    @endforeach
    {{ $purchases->links() }}
    <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    <div x-data x-on:commerce-payment.window="if (window.snap) { window.snap.pay($event.detail.token, { onSuccess: () => window.location.reload(), onPending: () => window.location.reload() }); } else { alert('Gateway belum tersedia. Muat ulang halaman.'); }"></div>
</div>

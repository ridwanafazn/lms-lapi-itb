<div class="space-y-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold">Refund & Pembatalan</h1>
    <nav class="flex flex-wrap gap-4 text-blue-700"><a href="{{ route('admin.transactions') }}">Transaksi</a><a href="{{ route('admin.payouts') }}">Honor instruktur</a></nav>
    <p class="rounded bg-amber-50 p-4">Refund penuh dilakukan manual oleh admin. Pencatatan di sini bukan transfer uang otomatis. Pastikan dana sudah dikembalikan dan unggah bukti. Akses dari pembelian lain tetap dipertahankan. Membership hanya dapat direfund dari pembelian terakhir; transaksi legacy memerlukan rekonsiliasi.</p>
    @if(session('success')) <p class="p-3 bg-green-50">{{ session('success') }}</p> @endif
    @if($errors->any()) <ul class="p-3 bg-red-50 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul> @endif
    <input wire:model.live.debounce.300ms="search" placeholder="Cari kode invoice" class="border rounded w-full" aria-label="Cari kode invoice">
    @foreach($invoices as $invoice)
        <section class="bg-white rounded-lg p-4 space-y-3" wire:key="refund-invoice-{{ $invoice->id }}">
            <h2 class="font-semibold">{{ $invoice->invoice_code }} — {{ $invoice->user?->name }}</h2>
            <p>{{ $invoice->purchased_item_name }} · Rp {{ number_format($invoice->amount, 0, ',', '.') }}</p>
            <form action="{{ route('admin.refunds.store', $invoice) }}" method="POST" enctype="multipart/form-data" class="space-y-3" onsubmit="return confirm('Dana sudah dikembalikan penuh? Pencatatan ini akan membatalkan akses dari transaksi tersebut.');">
                @csrf
                <label class="block">Alasan pengembalian<textarea name="reason" required maxlength="2000" class="block border rounded w-full"></textarea></label>
                <label class="block">Bukti pengembalian (PDF/JPG/PNG, maks. 10 MB)<input name="proof" type="file" required accept=".pdf,.jpg,.jpeg,.png" class="block"></label>
                <button type="submit" class="bg-red-700 text-white rounded px-4 py-2">Catat refund penuh</button>
            </form>
        </section>
    @endforeach
    {{ $invoices->links() }}
    <h2 class="text-xl font-semibold">Riwayat pengembalian terakhir</h2>
    @forelse($refunds as $refund)
        <article class="bg-white p-4 rounded"><p>{{ $refund->transaction->invoice_code }} · Rp {{ number_format($refund->amount, 0, ',', '.') }} · {{ $refund->processor?->name }} · {{ $refund->processed_at }}</p><p>{{ $refund->reason }}</p><a class="text-blue-700" href="{{ route('admin.refunds.proof', $refund) }}">Unduh bukti privat</a></article>
    @empty <p>Belum ada refund.</p> @endforelse
</div>

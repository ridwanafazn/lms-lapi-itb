<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex justify-between"><h1 class="text-2xl font-bold">Kelola Kupon</h1><a href="{{ route('admin.transactions') }}" class="text-blue-700 underline">Transaksi & persetujuan korporat</a></div>
    @if (session('commerceStatus')) <p role="status" class="p-3 bg-green-50 text-green-800">{{ session('commerceStatus') }}</p> @endif
    @if ($errors->any()) <ul role="alert" class="text-red-700">@foreach ($errors->all() as $message) <li>{{ $message }}</li> @endforeach</ul> @endif
    <form wire:submit="save" class="bg-white rounded-xl shadow p-5 grid gap-4 md:grid-cols-2">
        <label>Kode<input wire:model="code" required maxlength="64" class="block w-full rounded border-gray-300"></label>
        <label>Jenis diskon<select wire:model.live="discountType" class="block w-full rounded border-gray-300"><option value="percentage">Persentase</option><option value="fixed_amount">Nominal (Rp)</option></select></label>
        <label>Nilai diskon<input wire:model="discountValue" type="number" step="0.01" min="0.01" required class="block w-full rounded border-gray-300"></label>
        <label>Batas penggunaan (kosong = tanpa batas)<input wire:model="maxUses" type="number" min="1" class="block w-full rounded border-gray-300"></label>
        <label>Berlaku sampai (kosong = tanpa batas)<input wire:model="validUntil" type="datetime-local" class="block w-full rounded border-gray-300"></label>
        <label class="flex items-center gap-2"><input wire:model="isActive" type="checkbox">Aktif</label>
        <div class="flex gap-3"><button wire:loading.attr="disabled" class="bg-blue-600 text-white rounded px-4 py-2">{{ $couponId ? 'Simpan perubahan' : 'Buat kupon' }}</button><button type="button" wire:click="clearForm" class="border rounded px-4 py-2">Reset</button></div>
    </form>
    <p class="text-sm text-gray-600">Satu kupon per transaksi. Invoice tertunda menahan kuota hingga dibayar atau dinyatakan gagal. Kupon dengan riwayat hanya dinonaktifkan saat dihapus.</p>
    <div class="overflow-x-auto bg-white rounded-xl shadow"><table class="w-full text-left"><thead><tr><th class="p-3">Kode</th><th>Diskon</th><th>Terpakai / reservasi / batas</th><th>Berlaku sampai</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @foreach ($coupons as $coupon)
            <tr wire:key="coupon-{{ $coupon->id }}" class="border-t"><td class="p-3 font-mono">{{ $coupon->code }}</td><td>{{ $coupon->discount_value }} {{ $coupon->discount_type === 'percentage' ? '%' : 'Rp' }}</td><td>{{ $coupon->used_count }} / {{ $coupon->reserved_count }} / {{ $coupon->max_uses ?? '∞' }}</td><td>{{ $coupon->valid_until?->format('d/m/Y H:i') ?? 'Tanpa batas' }}</td><td>{{ $coupon->is_active ? 'Aktif' : 'Nonaktif' }}</td><td class="space-x-2"><button wire:click="edit('{{ $coupon->id }}')" class="text-blue-700">Edit</button><button wire:click="delete('{{ $coupon->id }}')" wire:confirm="Hapus / nonaktifkan kupon ini?" class="text-red-700">Hapus</button></td></tr>
        @endforeach
    </tbody></table></div>
    {{ $coupons->links() }}
</div>

<?php

use App\Models\Transaction;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    // State untuk Filter & Pencarian
    public string $search = '';
    public string $statusFilter = '';
    public string $methodFilter = '';

    // Reset paginasi saat filter berubah
    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingMethodFilter() { $this->resetPage(); }

    public function with(): array
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $query = Transaction::with(['user', 'membershipPlan', 'batch.program', 'program'])
            ->when($this->search, function (Builder $q) {
                $q->where('invoice_code', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $this->search . '%')->orWhere('email', 'like', '%' . $this->search . '%'));
            })
            ->when($this->statusFilter, fn($q) => $q->where('payment_status', $this->statusFilter))
            ->when($this->methodFilter, fn($q) => $q->where('payment_method', $this->methodFilter))
            ->latest('created_at');

        return [
            'transactions' => $query->paginate(10),

            // Data KPI (Key Performance Indicators)
            'kpiPending' => Transaction::where('payment_status', 'pending')->count(),
            'kpiManualNeedAction' => Transaction::where('payment_status', 'pending')->where('payment_method', 'manual_transfer')->count(),
            'kpiRevenueToday' => Transaction::whereIn('payment_status', ['paid', 'verified_by_admin'])
                                            ->whereNull('refunded_at')
                                            ->whereDate('paid_at', today())
                                            ->sum('amount'),
        ];
    }

    // Aksi Verifikasi
    public function approve(Transaction $transaction)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $transaction->approveTransaction();
        $this->dispatch('transaction-updated');
    }

    public function reject(Transaction $transaction)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $transaction->rejectTransaction();
        $this->dispatch('transaction-updated');
    }
}; ?>

<div class="py-8 bg-gray-50/50 min-h-screen"
     x-data="{
        isDrawerOpen: false,
        selectedTx: null,
        openDrawer(tx) {
            this.selectedTx = tx;
            this.isDrawerOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeDrawer() {
            this.isDrawerOpen = false;
            setTimeout(() => { this.selectedTx = null; }, 300);
            document.body.style.overflow = '';
        }
     }"
     @transaction-updated.window="closeDrawer()">

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- BLOK 1: KPI Header --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Menunggu Verifikasi</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $kpiPending }} <span class="text-sm font-normal text-red-500 ml-1">({{ $kpiManualNeedAction }} Manual)</span></p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Pendapatan Hari Ini</p>
                    <p class="text-2xl font-bold text-gray-900">Rp {{ number_format($kpiRevenueToday, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-indigo-600 p-6 rounded-2xl shadow-sm shadow-indigo-200 flex flex-col justify-center relative overflow-hidden">
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-white/10 blur-xl"></div>
                <h3 class="text-indigo-50 font-medium text-sm">Pusat Kendali Transaksi</h3>
                <p class="text-white text-xs mt-1 leading-relaxed opacity-80">Gunakan filter untuk menemukan transaksi. Klik baris data untuk melihat detail struk atau payload gateway secara instan.</p>
            </div>
        </div>

        {{-- BLOK 2: Filter & Data Table --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- Toolbar Filters --}}
            <div class="p-4 border-b border-gray-200 bg-gray-50/50 flex flex-col sm:flex-row gap-4 justify-between items-center">
                <div class="w-full sm:max-w-xs relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition duration-150 ease-in-out" placeholder="Cari invoice, nama, atau email...">
                </div>

                <div class="flex gap-2 w-full sm:w-auto">
                    <select wire:model.live="statusFilter" class="block w-full sm:w-auto pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-lg">
                        <option value="">Semua Status</option>
                        <option value="pending">Menunggu (Pending)</option>
                        <option value="paid">Sukses Gateway (Paid)</option>
                        <option value="verified_by_admin">Disetujui Manual</option>
                        <option value="failed">Gagal / Kedaluwarsa</option>
                    </select>

                    <select wire:model.live="methodFilter" class="block w-full sm:w-auto pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-lg">
                        <option value="">Semua Metode</option>
                        <option value="manual_transfer">Transfer Manual</option>
                        <option value="gateway_va">Virtual Account (Gateway)</option>
                        <option value="gateway_ewallet">E-Wallet (Gateway)</option>
                        <option value="free_access">Akses Gratis</option>
                    </select>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice / Tanggal</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pengguna</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item Pembelian</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nominal & Metode</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($transactions as $tx)
                            <tr class="hover:bg-indigo-50/30 transition-colors duration-150 group cursor-pointer"
                                @click="openDrawer({{ collect([
                                    'id' => $tx->id,
                                    'invoice' => $tx->invoice_code,
                                    'user_name' => $tx->user->name ?? 'User Dihapus',
                                    'user_email' => $tx->user->email ?? '-',
                                    'item' => $tx->purchased_item_name,
                                    'amount' => 'Rp ' . number_format($tx->amount, 0, ',', '.'),
                                    'method_raw' => $tx->payment_method,
                                    'method_label' => str_replace('_', ' ', Str::title($tx->payment_method)),
                                    'status' => $tx->refunded_at ? 'refunded' : $tx->payment_status,
                                    'date' => $tx->transaction_date->format('d M Y, H:i'),
                                    'proof_url' => $tx->proof_file_url ? (str_starts_with($tx->proof_file_url, 'billing/proofs/') ? route('billing.invoices.proof', $tx) : asset('storage/' . $tx->proof_file_url)) : null,
                                    'payload' => $tx->gateway_payload ? json_encode($tx->gateway_payload, JSON_PRETTY_PRINT) : null
                                ])->toJson() }})">

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-900">{{ $tx->invoice_code }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $tx->transaction_date->format('d/m/Y H:i') }}</div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $tx->user->name ?? 'User Terhapus' }}</div>
                                    <div class="text-xs text-gray-500">{{ $tx->user->email ?? '-' }}</div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate" title="{{ $tx->purchased_item_name }}">
                                        {{ $tx->purchased_item_name }}
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900">Rp {{ number_format($tx->amount, 0, ',', '.') }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ str_replace('_', ' ', Str::title($tx->payment_method)) }}</div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($tx->refunded_at)
                                        <span class="text-xs font-semibold text-red-700">Direfund</span>
                                    @elseif($tx->payment_status === 'paid')
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Paid (Gateway)</span>
                                    @elseif($tx->payment_status === 'verified_by_admin')
                                        <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">Verified Manual</span>
                                    @elseif($tx->payment_status === 'pending')
                                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Pending</span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Failed/Expired</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <span class="text-indigo-600 group-hover:text-indigo-900 flex justify-end items-center gap-1">
                                        Detail
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-semibold text-gray-900">Tidak ada transaksi ditemukan</h3>
                                    <p class="mt-1 text-sm text-gray-500">Ubah filter pencarian untuk menemukan data.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-200">
                {{ $transactions->links() }}
            </div>
        </div>

    </div>

    {{-- BLOK 3: Alpine.js Slide-over Drawer --}}
    <div x-show="isDrawerOpen" class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true" x-cloak>

        {{-- Backdrop Overlay --}}
        <div x-show="isDrawerOpen"
             x-transition:enter="ease-in-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity"
             @click="closeDrawer()"></div>

        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">

                    {{-- Drawer Panel --}}
                    <div x-show="isDrawerOpen"
                         x-transition:enter="transform transition ease-in-out duration-300 sm:duration-400"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-300 sm:duration-400"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full"
                         class="pointer-events-auto w-screen max-w-md">

                        <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-2xl">
                            <div class="px-4 py-6 sm:px-6 bg-gray-50 border-b border-gray-200">
                                <div class="flex items-start justify-between">
                                    <h2 class="text-base font-semibold leading-6 text-gray-900" id="slide-over-title">Detail Transaksi</h2>
                                    <div class="ml-3 flex h-7 items-center">
                                        <button type="button" @click="closeDrawer()" class="relative rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                            <span class="absolute -inset-2.5"></span>
                                            <span class="sr-only">Close panel</span>
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Content Drawer --}}
                            <div class="relative flex-1 px-4 py-6 sm:px-6" x-show="selectedTx">

                                {{-- Summary Data --}}
                                <div class="space-y-6">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Nomor Invoice</p>
                                        <p class="mt-1 text-lg font-mono font-bold text-gray-900" x-text="selectedTx?.invoice"></p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500">Waktu Transaksi</p>
                                            <p class="mt-1 text-sm text-gray-900" x-text="selectedTx?.date"></p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-500">Status</p>
                                            <p class="mt-1 text-sm font-bold uppercase"
                                               :class="{
                                                   'text-emerald-600': selectedTx?.status === 'paid',
                                                   'text-blue-600': selectedTx?.status === 'verified_by_admin',
                                                   'text-amber-500': selectedTx?.status === 'pending',
                                                   'text-red-600': selectedTx?.status === 'failed'
                                               }"
                                               x-text="selectedTx?.status.replace('_', ' ')"></p>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-100 pt-4">
                                        <p class="text-sm font-medium text-gray-500">Informasi Pelanggan</p>
                                        <p class="mt-1 text-sm font-medium text-gray-900" x-text="selectedTx?.user_name"></p>
                                        <p class="text-xs text-gray-500" x-text="selectedTx?.user_email"></p>
                                    </div>

                                    <div class="border-t border-gray-100 pt-4 bg-gray-50 -mx-4 sm:-mx-6 px-4 sm:px-6 py-4">
                                        <p class="text-sm font-medium text-gray-500">Item Pembelian</p>
                                        <p class="mt-1 text-sm font-bold text-gray-900 leading-snug" x-text="selectedTx?.item"></p>
                                        <div class="mt-3 flex justify-between items-center">
                                            <p class="text-xs text-gray-500 bg-white px-2 py-1 rounded border border-gray-200" x-text="selectedTx?.method_label"></p>
                                            <p class="text-xl font-bold text-indigo-600" x-text="selectedTx?.amount"></p>
                                        </div>
                                    </div>

                                    {{-- Khusus Manual Transfer: Bukti Pembayaran --}}
                                    <template x-if="selectedTx?.method_raw === 'manual_transfer'">
                                        <div class="border-t border-gray-100 pt-4">
                                            <p class="text-sm font-medium text-gray-500 mb-2">Bukti Pembayaran (Manual)</p>
                                            <template x-if="selectedTx?.proof_url">
                                                <a :href="selectedTx.proof_url" target="_blank" class="block rounded-lg overflow-hidden border border-gray-200 hover:opacity-90 transition">
                                                    <span class="block p-4 text-indigo-700">Unduh / buka bukti transfer</span>
                                                </a>
                                            </template>
                                            <template x-if="!selectedTx?.proof_url">
                                                <div class="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-6 text-center">
                                                    <p class="text-sm text-gray-500">Pengguna belum mengunggah bukti transfer.</p>
                                                </div>
                                            </template>

                                            {{-- Action Buttons untuk Manual Transfer --}}
                                            <template x-if="selectedTx?.status === 'pending'">
                                                <div class="mt-6 flex gap-3">
                                                    <button @click="$wire.reject(selectedTx.id)" type="button" class="flex-1 rounded-lg bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Tolak & Batalkan</button>
                                                    <button @click="$wire.approve(selectedTx.id)" type="button" class="flex-1 rounded-lg bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Verifikasi (Approve)</button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Khusus Gateway: Payload JSON untuk Debugging --}}
                                    <template x-if="selectedTx?.method_raw !== 'manual_transfer' && selectedTx?.payload">
                                        <div class="border-t border-gray-100 pt-4">
                                            <p class="text-sm font-medium text-gray-500 mb-2">Gateway Payload Data (Debug)</p>
                                            <div class="bg-gray-900 rounded-lg p-3 overflow-x-auto">
                                                <pre class="text-[10px] font-mono text-green-400 leading-relaxed" x-text="selectedTx.payload"></pre>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

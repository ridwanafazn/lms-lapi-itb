<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Batch;
use App\Models\Program;
use App\Models\Course;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new
#[Layout('layouts.app')]
class extends Component {
    public $type;
    public $modelId;
    public $modelData;
    public $courses = [];
    public $instructors = [];
    public $hasAccess = false;
    public $snapToken = null;
    public $displayPrice = 0;

    public function mount($type, $id)
    {
        $this->type = $type;
        $this->modelId = $id;
        $user = Auth::user();

        // Load Data Berdasarkan Tipe
        if ($type === 'program') {
            $this->modelData = Program::with('courses.instructors')->findOrFail($id);
            $this->courses = $this->modelData->courses;
            $this->hasAccess = $user->canAccessProgram($this->modelData);

            $this->displayPrice = $this->modelData->price ?? 0;

        } elseif ($type === 'batch') {
            $this->modelData = Batch::with(['program.courses.instructors', 'course.instructors'])->findOrFail($id);
            $this->courses = $this->modelData->program ? $this->modelData->program->courses : collect([$this->modelData->course]);
            $this->hasAccess = $user->canAccessBatch($this->modelData);

            $this->displayPrice = $this->modelData->effective_price;

        } elseif ($type === 'course') {
            $this->modelData = Course::with('instructors')->findOrFail($id);
            $this->courses = collect([$this->modelData]);
            $this->hasAccess = $user->hasAccessToCourse($id);
            $this->displayPrice = 0;
        } else {
            abort(404);
        }

        $this->instructors = $this->courses->pluck('instructors')->flatten()->unique('id');
    }

    public function checkout()
    {
        // 1. Validasi
        if ($this->hasAccess) {
            $this->dispatch('notify', message: 'Anda sudah memiliki akses ke materi ini.', type: 'error');
            return;
        }

        if ($this->type === 'course') {
            $this->dispatch('notify', message: 'Course satuan tidak bisa dibeli langsung. Silakan beli Program Bundling-nya.', type: 'warning');
            return;
        }

        // 2. Persiapan Data Transaksi
        $user = Auth::user();
        $amount = $this->displayPrice;
        $invoiceCode = 'INV-' . strtoupper(Str::random(6)) . '-' . time();

        // 3. Jika Gratis (Price = 0) -> Langsung Buka Akses tanpa Midtrans
        if ($amount <= 0) {
            $transaction = Transaction::create([
                'id' => Str::uuid(),
                'invoice_code' => $invoiceCode,
                'user_id' => $user->id,
                'program_id' => $this->type === 'program' ? $this->modelId : null,
                'batch_id' => $this->type === 'batch' ? $this->modelId : null,
                'amount' => 0,
                'payment_status' => 'paid',
                'payment_method' => 'free_access'
            ]);

            // Memicu fulfillment logic (Enrollment) di model Transaction
            $transaction->markAsPaid(['transaction_id' => 'FREE-ACC-' . time()]);

            session()->flash('message', 'Akses gratis berhasil diklaim!');

            // PERBAIKAN: Redirect ke rute yang benar sesuai dengan nama rute di file web.php kamu
            // Biasanya route dashboard belajar siswa bernama 'student.program.show' dengan parameter yg sesuai
            return $this->redirect(route('student.my-courses'), navigate: true);
        }

        // 4. Jika Berbayar -> Integrasi Midtrans
        \Midtrans\Config::$serverKey = config('services.midtrans.server_key');
        \Midtrans\Config::$isProduction = config('services.midtrans.is_production', false);
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $transaction = Transaction::create([
            'id' => Str::uuid(),
            'invoice_code' => $invoiceCode,
            'user_id' => $user->id,
            'program_id' => $this->type === 'program' ? $this->modelId : null,
            'batch_id' => $this->type === 'batch' ? $this->modelId : null,
            'amount' => $amount,
            'payment_status' => 'pending',
            'payment_method' => 'gateway_va',
            'gateway_name'   => 'midtrans',
        ]);

        $params = [
            'transaction_details' => [
                'order_id' => $invoiceCode,
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => $this->modelId,
                    'price' => (int) $amount,
                    'quantity' => 1,
                    'name' => substr($this->modelData->title ?? $this->modelData->batch_name, 0, 50),
                ]
            ]
        ];

        try {
            $this->snapToken = \Midtrans\Snap::getSnapToken($params);
            $transaction->update(['snap_token' => $this->snapToken]);
            $this->dispatch('trigger-midtrans', token: $this->snapToken);
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Gagal menghubungi payment gateway: ' . $e->getMessage(), type: 'error');
        }
    }
}; ?>

<div class="min-h-screen bg-slate-50 font-lexend pb-20">
    @push('head')
        <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;900&display=swap" rel="stylesheet"/>
        <style>.font-lexend { font-family: 'Lexend', sans-serif; }</style>
        {{-- Script Midtrans Snap --}}
        <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    @endpush

    {{-- Hero Section --}}
    <div class="bg-white border-b border-slate-200 pt-10 pb-16 px-6">
        <div class="max-w-5xl mx-auto flex flex-col md:flex-row gap-10 items-center">

            {{-- Image/Thumbnail Placeholder --}}
            <div class="w-full md:w-1/3 aspect-square bg-gradient-to-br from-blue-50 to-indigo-100 rounded-3xl border border-blue-100 flex items-center justify-center shadow-inner relative overflow-hidden">
                <div class="absolute inset-0 opacity-20 pattern-grid-lg text-blue-900"></div>
                <span class="text-6xl font-black text-blue-200 relative z-10">
                    {{ substr($modelData->title ?? $modelData->batch_name ?? 'P', 0, 1) }}
                </span>
            </div>

            {{-- Info Content --}}
            <div class="w-full md:w-2/3 flex flex-col items-start text-left">
                <span class="px-3 py-1 bg-blue-100 text-blue-700 font-bold text-xs rounded-full uppercase tracking-widest mb-4">
                    {{ $type === 'batch' ? 'Live Cohort' : ($type === 'program' ? 'Program Bundling' : 'Course Mandiri') }}
                </span>

                <h1 class="text-3xl md:text-5xl font-extrabold text-slate-900 mb-4 leading-tight">
                    {{ $modelData->title ?? $modelData->batch_name }}
                </h1>

                <p class="text-slate-500 text-lg leading-relaxed mb-8">
                    {{ $modelData->description ?? 'Tingkatkan keahlian Anda melalui materi komprehensif yang dirancang khusus untuk kebutuhan industri saat ini.' }}
                </p>

                {{-- Price & Action Area --}}
                <div class="bg-slate-50 w-full p-6 rounded-2xl border border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-6">
                    <div>
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">Investasi Belajar</p>
                        @if($type === 'course')
                            <span class="text-slate-400 font-medium italic text-sm">Tidak dijual eceran</span>
                        @else
                            <h2 class="text-3xl font-black text-slate-900">
                                {{ $displayPrice > 0 ? 'Rp ' . number_format($displayPrice, 0, ',', '.') : 'Gratis' }}
                            </h2>
                        @endif
                    </div>

                    <div class="w-full sm:w-auto flex flex-col gap-2">
                        @if($hasAccess)
                            {{-- Jika Sudah Punya Akses --}}
                            <a href="{{ route('student.program.show', ['batch' => $modelId]) }}" wire:navigate class="px-8 py-3.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/30 transition-all text-center">
                                Lanjutkan Belajar
                            </a>
                        @elseif($type === 'course')
                            {{-- Jika Tipe Course (Hanya Boleh Beli Bundling Program) --}}
                            <button disabled class="px-8 py-3.5 bg-slate-200 text-slate-500 font-bold rounded-xl cursor-not-allowed text-center">
                                Beli via Program
                            </button>
                        @elseif($displayPrice <= 0)
                            {{-- Jika Harga Gratis / Rp 0 --}}
                            <button wire:click="checkout" wire:loading.attr="disabled" class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
                                <span wire:loading.remove target="checkout">Klaim Akses Gratis</span>
                                <span wire:loading target="checkout">Memproses...</span>
                            </button>
                        @else
                            {{-- Jika Berbayar --}}
                            <button wire:click="checkout" wire:loading.attr="disabled" class="px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-600/30 transition-all flex items-center justify-center gap-2">
                                <span wire:loading.remove target="checkout">Beli Program Sekarang</span>
                                <span wire:loading target="checkout">Memproses...</span>
                            </button>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Detail Kurikulum --}}
    <div class="max-w-5xl mx-auto mt-10 px-6">
        <h3 class="text-2xl font-bold text-slate-900 mb-6">Materi yang akan dipelajari ({{ $courses->count() }} Modul)</h3>
        <div class="space-y-4">
            @forelse($courses as $index => $course)
                <div class="bg-white border border-slate-200 rounded-xl p-5 flex items-center gap-5">
                    <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 font-black text-xl flex items-center justify-center flex-shrink-0">
                        {{ $index + 1 }}
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-slate-800">{{ $course->title }}</h4>
                        <p class="text-sm text-slate-500 mt-1 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            {{ $course->instructors->pluck('name')->join(', ') ?: 'Tim Instruktur' }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-slate-500 italic">Detail materi belum tersedia.</p>
            @endforelse
        </div>
    </div>

    {{-- Listener JS untuk Midtrans --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('trigger-midtrans', (event) => {
                let token = event.token;
                snap.pay(token, {
                    onSuccess: function(result){
                        window.location.href = "{{ route('student.my-courses') }}";
                    },
                    onPending: function(result){
                        alert("Menunggu pembayaran Anda!");
                        window.location.reload();
                    },
                    onError: function(result){
                        alert("Pembayaran gagal!");
                    },
                    onClose: function(){
                        alert('Anda menutup popup tanpa menyelesaikan pembayaran');
                    }
                })
            });
        });
    </script>
</div>

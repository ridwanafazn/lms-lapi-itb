<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Program;
use App\Models\Enrollment;
use App\Models\Batch;
use App\Models\Course;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        // 1. Data Ringkasan (Top Cards)
        $totalRevenue = Transaction::whereIn('payment_status', ['paid', 'verified_by_admin'])->whereNull('refunded_at')->sum('amount');
        $totalStudents = User::role('student')->count();

        // Breakdown Program Aktif
        $activePrograms = Program::where('is_active', true)->count();
        $draftPrograms = Program::where('is_active', false)->count();

        // Transaksi Pending
        $pendingCount = Transaction::where('payment_status', 'pending')->count();
        $pendingAmount = Transaction::where('payment_status', 'pending')->sum('amount');

        // 2. Data Monitoring Batch (Cohort)
        $batchMonitoring = Batch::with(['course', 'program'])
            ->where('status', '!=', 'closed')
            ->withCount('enrollments')
            ->latest()
            ->take(3)
            ->get()
            ->map(function($batch) {
                // Simulasi target 50 siswa per batch untuk progress bar UI
                $batch->target = 50;
                $batch->percentage = min(($batch->enrollments_count / $batch->target) * 100, 100);
                
                // [PERBAIKAN] Ambil key 'date' dari array JSON start_date
                $batch->formatted_start_date = isset($batch->start_date['date']) 
                    ? Carbon::parse($batch->start_date['date'])->diffForHumans() 
                    : 'Soon';
                    
                return $batch;
            });

        // 3. Transaksi Terbaru
        $recentTransactions = Transaction::with(['user', 'program', 'batch.course'])
            ->latest()
            ->take(6)
            ->get();

        // 4. Statistik Bulanan untuk Chart (Simulasi)
        $chartData = [
            'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'bundleSales' => [30, 45, 35, 50, 70, 60, 85, 40, 50, 90, 80, 110],
            'singleSales' => [20, 30, 40, 30, 45, 50, 55, 35, 45, 60, 65, 75],
        ];

        return [
            'totalRevenue' => $totalRevenue,
            'totalStudents' => $totalStudents,
            'activePrograms' => $activePrograms,
            'draftPrograms' => $draftPrograms,
            'pendingCount' => $pendingCount,
            'pendingAmount' => $pendingAmount,
            'batchMonitoring' => $batchMonitoring,
            'recentTransactions' => $recentTransactions,
            'chartData' => $chartData,
        ];
    }
};
?>

<div class="min-h-screen bg-[#f8fafc] p-4 md:p-8 font-sans text-slate-900"
     x-data="{
        initChart() {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @js($chartData['months']),
                    datasets: [
                        {
                            label: 'Bundle Sales',
                            data: @js($chartData['bundleSales']),
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 0,
                        },
                        {
                            label: 'Single Course',
                            data: @js($chartData['singleSales']),
                            borderColor: '#94a3b8',
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { display: false },
                        x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } }
                    }
                }
            });
        }
     }" x-init="initChart()">

    {{-- Scripts for Chart & Icons --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Admin Overview</h1>
            <p class="text-sm text-slate-500 font-medium italic">Monitor performa kurikulum dan pendaftaran hari ini.</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition flex items-center gap-2">
                <i data-lucide="download" class="w-4 h-4"></i> Export PDF
            </button>
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-indigo-200 shadow-lg hover:bg-indigo-700 transition flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Buat Program
            </button>
        </div>
    </div>

    {{-- Top Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Card Revenue --}}
        <div class="bg-white p-6 rounded-[24px] shadow-sm border border-slate-100 flex justify-between items-start group">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Revenue</p>
                <h3 class="text-2xl font-black text-slate-800">Rp{{ number_format($totalRevenue/1000000, 1) }}M</h3>
                <span class="text-[10px] font-bold text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded-full flex items-center w-fit mt-2">
                    <i data-lucide="trending-up" class="w-3 h-3 mr-1"></i> 12% vs last month
                </span>
            </div>
            <div class="p-3 bg-slate-50 rounded-2xl group-hover:bg-indigo-50 transition-colors">
                <i data-lucide="dollar-sign" class="w-6 h-6 text-slate-400 group-hover:text-indigo-600"></i>
            </div>
        </div>

        {{-- Card Students --}}
        <div class="bg-white p-6 rounded-[24px] shadow-sm border border-slate-100 flex justify-between items-start group">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Active Students</p>
                <h3 class="text-2xl font-black text-slate-800">{{ number_format($totalStudents) }}</h3>
                <span class="text-[10px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full flex items-center w-fit mt-2">
                    <i data-lucide="user-plus" class="w-3 h-3 mr-1"></i> 5% new enrollments
                </span>
            </div>
            <div class="p-3 bg-slate-50 rounded-2xl group-hover:bg-blue-50 transition-colors">
                <i data-lucide="graduation-cap" class="w-6 h-6 text-slate-400 group-hover:text-blue-600"></i>
            </div>
        </div>

        {{-- Card Programs --}}
        <div class="bg-white p-6 rounded-[24px] shadow-sm border border-slate-100 flex justify-between items-start group">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Programs Breakdown</p>
                <h3 class="text-2xl font-black text-slate-800">{{ $activePrograms }} <span class="text-sm font-medium text-slate-400">Active</span></h3>
                <p class="text-[10px] font-bold text-slate-400 mt-2">{{ $draftPrograms }} Drafts Pending</p>
            </div>
            <div class="p-3 bg-slate-50 rounded-2xl group-hover:bg-purple-50 transition-colors">
                <i data-lucide="layers" class="w-6 h-6 text-slate-400 group-hover:text-purple-600"></i>
            </div>
        </div>

        {{-- Card Pending --}}
        <div class="bg-white p-6 rounded-[24px] shadow-sm border border-slate-100 flex justify-between items-start group">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Pending Transactions</p>
                <h3 class="text-2xl font-black text-slate-800">Rp{{ number_format($pendingAmount/1000, 0) }}k</h3>
                <span class="text-[10px] font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full flex items-center w-fit mt-2">
                    Action Needed
                </span>
            </div>
            <div class="p-3 bg-slate-50 rounded-2xl group-hover:bg-orange-50 transition-colors">
                <i data-lucide="clock" class="w-6 h-6 text-slate-400 group-hover:text-orange-600"></i>
            </div>
        </div>
    </div>

    {{-- Main Analytics Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        {{-- Chart Section --}}
        <div class="lg:col-span-2 bg-white p-8 rounded-[32px] shadow-sm border border-slate-100">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Revenue Trends</h2>
                    <p class="text-xs text-slate-400 font-medium">Bundle vs. Single Course Sales</p>
                </div>
                <button class="text-xs font-bold bg-slate-50 border-none rounded-lg px-3 py-1 text-slate-500 focus:ring-0">
                    <option>This Year</option>
                </button>
            </div>
            <div class="h-[300px] relative">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        {{-- Batch Monitoring Section --}}
        <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-lg font-bold text-slate-800">Batch Monitoring</h2>
                <a href="{{ route('admin.batches') }}" class="text-[10px] font-black text-indigo-600 uppercase tracking-tighter hover:underline">View All</a>
            </div>
            <div class="space-y-8">
                @forelse($batchMonitoring as $batch)
                <div class="relative">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center border border-slate-100">
                                <i data-lucide="{{ $batch->program_id ? 'package' : 'book-open' }}" class="w-5 h-5 text-indigo-500"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-800 leading-none">{{ Str::limit($batch->batch_name, 18) }}</h4>
                                {{-- [PERBAIKAN] Memanggil variabel string mentah yang sudah diformat di PHP --}}
                                <p class="text-[10px] text-slate-400 font-medium mt-1">Started {{ $batch->formatted_start_date }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-black text-slate-800">{{ round($batch->percentage) }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-500 rounded-full transition-all duration-1000" style="width: {{ $batch->percentage }}%"></div>
                    </div>
                    <div class="flex justify-between mt-1.5">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">{{ $batch->enrollments_count }}/{{ $batch->target }} Students</span>
                    </div>
                </div>
                @empty
                <p class="text-center text-slate-400 text-xs py-10">No active batches found.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Transactions Table Section --}}
    <div class="bg-white rounded-[32px] shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <h2 class="text-lg font-bold text-slate-800">Recent Transactions</h2>
            <div class="flex gap-2">
                <button class="px-4 py-2 bg-slate-50 text-slate-500 text-xs font-bold rounded-xl hover:bg-slate-100 transition">Export CSV</button>
                <button class="px-4 py-2 bg-slate-50 text-slate-500 text-xs font-bold rounded-xl hover:bg-slate-100 transition">Filters</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                        <th class="px-8 py-5">Transaction ID</th>
                        <th class="px-8 py-5">Student</th>
                        <th class="px-8 py-5">Product</th>
                        <th class="px-8 py-5">Date</th>
                        <th class="px-8 py-5">Amount</th>
                        <th class="px-8 py-5">Status</th>
                        <th class="px-8 py-5 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($recentTransactions as $tx)
                    <tr class="hover:bg-slate-50/50 transition group">
                        <td class="px-8 py-5 text-xs font-bold text-slate-500">#{{ strtoupper(substr($tx->id, 0, 8)) }}</td>
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500 border border-white shadow-sm">
                                    {{ substr($tx->user->name, 0, 1) }}
                                </div>
                                <span class="text-sm font-bold text-slate-700">{{ $tx->user->name }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="text-xs font-medium text-slate-600">
                                {{ $tx->program->title ?? $tx->batch->course->title ?? 'Standalone Course' }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-xs text-slate-400 font-medium">
                            {{-- Aman dari error ini karena $recentTransactions tidak diproses oleh ->map() --}}
                            {{ $tx->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-8 py-5 text-sm font-black text-slate-800">Rp{{ number_format($tx->amount, 0, ',', '.') }}</td>
                        <td class="px-8 py-5">
                            @if($tx->refunded_at)
                                <span class="text-xs font-semibold text-red-700">Direfund</span>
                            @elseif($tx->isPaid())
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">
                                    <span class="w-1 h-1 bg-emerald-600 rounded-full mr-1.5"></span> Paid
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-600 border border-amber-100">
                                    <span class="w-1 h-1 bg-amber-600 rounded-full mr-1.5"></span> Pending
                                </span>
                            @endif
                        </td>
                        <td class="px-8 py-5 text-center">
                            <button class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</div>
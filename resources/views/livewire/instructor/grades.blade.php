<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')]
class extends Component {

    public $viewState = 'list'; // 'list' atau 'detail'

    // Data Detail (Model Eloquent Asli, karena cuma 1 jenis)
    public $contextModel;
    public $contextType;  // 'batch' atau 'course'
    public $students;

    // Data List (Kita ubah jadi Collection of Objects biar aman)
    public $myClasses;

    public function mount(Request $request)
    {
        $batchId = $request->query('batch_id');
        $courseId = $request->query('course_id');

        if ($batchId) {
            $this->loadDetail('batch', $batchId);
        } elseif ($courseId) {
            $this->loadDetail('course', $courseId);
        } else {
            $this->loadList();
        }
    }

    public function loadList()
    {
        $this->viewState = 'list';
        $instructorId = Auth::id();

        // 1. AMBIL BATCH (ANGKATAN)
        $batches = Batch::query()
            ->with(['course', 'program'])
            // [FIX] Mengganti 'users.id' dengan 'user_id' karena tabel instructors punya kolom user_id
            ->where(function (Builder $query) use ($instructorId) {
                $query->whereHas('instructors', fn($q) => $q->where('user_id', $instructorId))
                      ->orWhereHas('course.instructors', fn($q) => $q->where('user_id', $instructorId))
                      ->orWhereHas('program.courses.instructors', fn($q) => $q->where('user_id', $instructorId));
            })
            ->latest()
            ->get()
            ->map(function($item) {
                // Konversi ke Object Standar (stdClass)
                return (object) [
                    'id' => $item->id,
                    'type_label' => 'Batch / Angkatan',
                    'badge_color' => 'bg-blue-100 text-blue-800',
                    'display_name' => $item->batch_name,
                    'code' => $item->id, // Fallback karena tidak ada kolom code
                    'date_label' => $item->start_date ? $item->start_date->format('d M Y') : '-',
                    'link_params' => ['batch_id' => $item->id],
                ];
            });

        // 2. AMBIL COURSE MANDIRI (SELF PACED)
        $courses = Course::query()
            ->where('delivery_mode', 'self_paced')
            // [FIX] Mengganti 'users.id' dengan 'user_id'
            ->whereHas('instructors', fn($q) => $q->where('user_id', $instructorId))
            ->latest()
            ->get()
            ->map(function($item) {
                // Konversi ke Object Standar (stdClass)
                return (object) [
                    'id' => $item->id,
                    'type_label' => 'Program Mandiri',
                    'badge_color' => 'bg-purple-100 text-purple-800',
                    'display_name' => $item->title,
                    'code' => null,
                    'date_label' => 'Fleksibel',
                    'link_params' => ['course_id' => $item->id],
                ];
            });

        // Gabungkan dan konversi ke Base Collection (bukan Eloquent Collection)
        $this->myClasses = $batches->toBase()->merge($courses->toBase());
    }

    public function loadDetail($type, $id)
    {
        $this->viewState = 'detail';
        $this->contextType = $type;
        $userId = Auth::id();

        if ($type === 'batch') {
            $this->contextModel = Batch::with('course')->findOrFail($id);

            // [FIX] Security Check Batch: Gunakan 'user_id'
            $hasAccess = $this->contextModel->instructors()->where('user_id', $userId)->exists() ||
                         ($this->contextModel->course && $this->contextModel->course->instructors()->where('user_id', $userId)->exists());

            $enrollmentQuery = Enrollment::where('enrollable_type', Batch::class)
                                         ->where('enrollable_id', $id);

        } else {
            $this->contextModel = Course::findOrFail($id);

            // [FIX] Security Check Course: Gunakan 'user_id'
            $hasAccess = $this->contextModel->instructors()->where('user_id', $userId)->exists();

            $enrollmentQuery = Enrollment::where('enrollable_type', Course::class)
                                         ->where('enrollable_id', $id);
        }

        if (!$hasAccess) {
             abort(403, 'Anda tidak memiliki akses penilaian di kelas ini.');
        }

        $this->students = $enrollmentQuery->with(['user', 'user.profile'])->get();
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <a href="{{ url('/grading/essays') }}" wire:navigate class="inline-block mb-6 px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Penilaian Esai</a>

        {{-- TAMPILAN 1: LIST KELAS GABUNGAN --}}
        @if($viewState === 'list')
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Penilaian Mahasiswa</h2>
                <p class="text-sm text-gray-500">Pilih Angkatan atau Program Mandiri untuk mengelola nilai.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($myClasses as $item)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow flex flex-col justify-between h-full">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item->badge_color }}">
                                    {{ $item->type_label }}
                                </span>
                                <span class="text-xs text-gray-500">{{ $item->date_label }}</span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-1 line-clamp-2">{{ $item->display_name }}</h3>
                            @if($item->code)
                                <p class="text-sm text-gray-500 mb-4 font-mono text-xs">ID: {{ substr($item->code, 0, 8) }}...</p>
                            @else
                                <p class="text-sm text-gray-500 mb-4 font-mono text-xs">-</p>
                            @endif
                        </div>

                        <a href="{{ route('instructor.grades', $item->link_params) }}"
                           wire:navigate
                           class="mt-4 block w-full text-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
                            Buka Gradebook
                        </a>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <p class="text-gray-500">Belum ada kelas atau program yang memerlukan penilaian.</p>
                    </div>
                @endforelse
            </div>

        {{-- TAMPILAN 2: DETAIL GRADEBOOK --}}
        @elseif($viewState === 'detail')

            {{-- Header Section --}}
            <div class="mb-6">
                <a href="{{ route('instructor.grades') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center mb-2 font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Kembali ke Daftar Kelas
                </a>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl p-6 border border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Gradebook (Buku Nilai)</h2>
                        <div class="mt-1 flex flex-col sm:flex-row gap-2 sm:gap-4 text-sm text-gray-500">
                            <span class="flex items-center">
                                <span class="font-semibold text-gray-700 mr-1">Kelas:</span>
                                {{ $contextType === 'batch' ? $contextModel->batch_name : $contextModel->title }}
                            </span>
                            <span class="hidden sm:inline text-gray-300">|</span>
                            <span class="flex items-center">
                                <span class="font-semibold text-gray-700 mr-1">Tipe:</span>
                                {{ $contextType === 'batch' ? 'Angkatan (Cohort)' : 'Mandiri (Self-Paced)' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-50 shadow-sm transition">
                            Export Excel
                        </button>
                    </div>
                </div>
            </div>

            {{-- Table Siswa --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Mahasiswa</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Nilai Akhir</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($students as $enrollment)
                                <tr class="hover:bg-indigo-50/30 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                @if($enrollment->user->profile && $enrollment->user->profile->profile_photo_url)
                                                    <img class="h-10 w-10 rounded-full object-cover" src="{{ $enrollment->user->profile->profile_photo_url }}" alt="">
                                                @else
                                                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                                                        {{ substr($enrollment->user->name, 0, 1) }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-gray-900">{{ $enrollment->user->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $enrollment->user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-bold text-gray-700">{{ $enrollment->progress_percentage }}%</span>
                                        <div class="w-20 bg-gray-200 rounded-full h-1.5 mt-1 mx-auto">
                                            <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $enrollment->progress_percentage }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-medium text-gray-500">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if($enrollment->progress_percentage >= 100)
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Lulus</span>
                                        @else
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Proses</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <button class="text-indigo-600 hover:text-indigo-900 font-semibold">Detail</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                        <p>Belum ada siswa yang mendaftar.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</div>

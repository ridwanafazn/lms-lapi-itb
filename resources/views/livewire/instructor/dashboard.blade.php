<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Batch;
use App\Models\Submission;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        $instructorId = Auth::id();

        // [FIX] Gunakan column 'user_id' di tabel instructors (Polymorphic)
        $myCourseIds = Course::whereHas('instructors', fn($q) => $q->where('user_id', $instructorId))
            ->pluck('id')
            ->toArray();

        // 2. Metrik Utama
        // Total Siswa Unik yang diajar
        $totalStudents = Enrollment::where('status', 'active')
            ->where(function($query) use ($myCourseIds) {
                $query->where(fn($q) => $q->where('enrollable_type', Course::class)->whereIn('enrollable_id', $myCourseIds))
                      ->orWhere(fn($q) => $q->where('enrollable_type', \App\Models\Program::class)->whereHasMorph('enrollable', [\App\Models\Program::class], fn($sub) => $sub->whereHas('courses', fn($c) => $c->whereIn('id', $myCourseIds))))
                      ->orWhere(fn($q) => $q->where('enrollable_type', Batch::class)->whereHasMorph('enrollable', [Batch::class], fn($sub) => $sub->whereIn('course_id', $myCourseIds)));
            })
            ->distinct('user_id')
            ->count();

        // Rata-rata progress siswa di course saya
        $avgProgress = Enrollment::whereIn('enrollable_id', $myCourseIds)
            ->where('enrollable_type', Course::class)
            ->avg('progress_percentage') ?? 0;

        // Antrean Penilaian
        $pendingGradingCount = Submission::whereHas('assignment.lesson.section', fn($q) => $q->whereIn('course_id', $myCourseIds))
            ->whereNull('grade')
            ->count();

        // 3. Data List untuk UI
        $gradingQueue = Submission::with(['user', 'assignment.lesson.section.course'])
        ->whereHas('assignment.lesson.section', fn($q) => $q->whereIn('course_id', $myCourseIds))
            ->whereNull('grade')
            ->latest()
            ->take(3)
            ->get();

        $activeBatches = Batch::with(['course', 'program'])
            ->where('status', 'ongoing')
            ->where(function($query) use ($myCourseIds) {
                $query->whereIn('course_id', $myCourseIds)
                      ->orWhereHas('program.courses', fn($q) => $q->whereIn('id', $myCourseIds));
            })
            ->withCount('enrollments')
            ->take(2)
            ->get();

        return [
            'totalStudents' => $totalStudents,
            'myCoursesCount' => count($myCourseIds),
            'avgProgress' => round($avgProgress),
            'pendingGradingCount' => $pendingGradingCount,
            'gradingQueue' => $gradingQueue,
            'activeBatches' => $activeBatches,
        ];
    }
};
?>

<div class="min-h-screen bg-[#f8fafc] p-4 md:p-8 font-sans text-slate-900">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Dashboard Instruktur</h1>
            <p class="text-sm text-slate-500 font-medium italic">Pantau interaksi siswa dan selesaikan antrean penilaian.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.courses') }}" class="bg-indigo-600 text-white px-5 py-2.5 rounded-2xl text-sm font-bold shadow-indigo-100 shadow-lg hover:bg-indigo-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Kelola Materi
            </a>
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-[28px] shadow-sm border border-slate-100 group">
            <div class="flex justify-between items-start mb-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Siswa</p>
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
            </div>
            <h3 class="text-2xl font-black text-slate-800">{{ number_format($totalStudents) }}</h3>
            <p class="text-[10px] font-bold text-emerald-500 mt-1">↑ 5% bulan ini</p>
        </div>

        <div class="bg-white p-6 rounded-[28px] shadow-sm border border-slate-100 group">
            <div class="flex justify-between items-start mb-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Materi Saya</p>
                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg></div>
            </div>
            <h3 class="text-2xl font-black text-slate-800">{{ $myCoursesCount }}</h3>
            <p class="text-[10px] font-bold text-slate-400 mt-1">Semester Aktif</p>
        </div>

        <div class="bg-white p-6 rounded-[28px] shadow-sm border border-slate-100 group">
            <div class="flex justify-between items-start mb-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Rata-rata Progress</p>
                <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
            </div>
            <h3 class="text-2xl font-black text-slate-800">{{ $avgProgress }}%</h3>
            <p class="text-[10px] font-bold text-emerald-500 mt-1">↑ 2% vs minggu lalu</p>
        </div>

        <div class="bg-white p-6 rounded-[28px] shadow-sm border border-emerald-500/20 group relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-emerald-500/5 rounded-bl-[50px]"></div>
            <div class="flex justify-between items-start mb-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Butuh Penilaian</p>
                <div class="p-2 bg-emerald-100 rounded-lg text-emerald-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg></div>
            </div>
            <h3 class="text-2xl font-black text-emerald-600">{{ $pendingGradingCount }}</h3>
            <p class="text-[10px] font-bold text-emerald-600 mt-1 italic">Butuh ditinjau segera</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left: Grading Queue --}}
        <div class="lg:col-span-2 bg-white rounded-[32px] shadow-sm border border-slate-100 overflow-hidden flex flex-col">
            <div class="p-8 border-b border-slate-50 flex justify-between items-center bg-gray-50/30">
                <h2 class="text-lg font-black text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    Grading Queue
                </h2>
                <a href="#" class="text-[10px] font-bold text-indigo-600 uppercase tracking-tighter hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                            <th class="px-8 py-5">Siswa</th>
                            <th class="px-8 py-5">Tugas</th>
                            <th class="px-8 py-5">Waktu</th>
                            <th class="px-8 py-5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($gradingQueue as $sub)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500">
                                        {{ substr($sub->user->name, 0, 1) }}
                                    </div>
                                    <span class="text-sm font-bold text-slate-700">{{ $sub->user->name }}</span>
                                </div>
                            </td>
                            <td class="px-8 py-5 text-xs font-medium text-slate-600">{{ Str::limit($sub->assignment->title, 25) }}</td>
                            <td class="px-8 py-5 text-[10px] text-slate-400 font-bold uppercase">{{ $sub->created_at->diffForHumans() }}</td>
                            <td class="px-8 py-5 text-center">
                                <button class="text-xs font-bold text-emerald-600 hover:text-emerald-700">Grade Now</button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="py-12 text-center text-slate-400 text-xs italic">Antrean kosong.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Right: Progress Overview & Questions --}}
        <div class="space-y-8">
            {{-- Progress Summary --}}
            <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 text-center">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-6">Progress Overview</h2>
                <div class="relative w-32 h-32 mx-auto mb-4">
                    <svg class="w-full h-full rotate-[-90deg]">
                        <circle cx="64" cy="64" r="58" stroke="currentColor" stroke-width="12" fill="transparent" class="text-slate-100" />
                        <circle cx="64" cy="64" r="58" stroke="currentColor" stroke-width="12" fill="transparent" class="text-emerald-500" stroke-dasharray="{{ ($avgProgress/100)*364 }} 364" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-black text-slate-800">{{ $avgProgress }}%</span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed px-4">Mayoritas siswa berada di rentang penyelesaian materi 75-90%.</p>
            </div>

            {{-- Recent Questions --}}
            <div class="bg-white p-6 rounded-[32px] shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest">Pertanyaan</h2>
                    <span class="bg-red-50 text-red-600 text-[9px] font-black px-2 py-0.5 rounded-full">3 BARU</span>
                </div>
                <div class="space-y-4">
                    {{-- Dummy Question 1 --}}
                    <div class="bg-slate-50 p-4 rounded-2xl group cursor-pointer hover:bg-slate-100 transition">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-6 h-6 rounded-full bg-slate-200"></div>
                            <span class="text-[10px] font-bold text-slate-700">Sarah M.</span>
                        </div>
                        <p class="text-[11px] text-slate-600 line-clamp-2 leading-relaxed">Izin bertanya pak, untuk pengumpulan tugas modul 4 apakah boleh menggunakan format PDF?</p>
                        <span class="text-[9px] font-bold text-indigo-600 mt-2 block">REPLY NOW</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom: Active Cohort Batches --}}
    <div class="mt-8">
        <h2 class="text-lg font-black text-slate-800 mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Active Cohort Batches
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($activeBatches as $batch)
            <div class="bg-white p-6 rounded-[28px] shadow-sm border border-slate-100 hover:border-indigo-200 transition">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="font-black text-slate-800">{{ $batch->batch_name }}</h4>
                        <p class="text-xs text-slate-500 font-medium">{{ $batch->course->title ?? $batch->program->title }}</p>
                    </div>
                    <span class="bg-emerald-50 text-emerald-600 text-[10px] font-black px-2 py-1 rounded-lg uppercase">Active</span>
                </div>
                <div class="w-full bg-slate-50 h-2 rounded-full overflow-hidden mb-3">
                    <div class="h-full bg-indigo-500 rounded-full" style="width: 45%"></div>
                </div>
                <div class="flex justify-between items-center">
                    <div class="flex -space-x-2">
                        <div class="w-6 h-6 rounded-full border-2 border-white bg-slate-100 flex items-center justify-center text-[8px] font-bold">+{{ $batch->enrollments_count }}</div>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">45% Complete</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<?php

use function Livewire\Volt\{state, mount, layout};
use App\Models\Program;
use App\Models\Enrollment;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\DB;

layout('layouts.app');

state([
    'couponCode' => '',
    'paymentMethod' => 'gateway_va',
    'checkoutInvoice' => '',
    'program' => null,
    'activeBatch' => null,
    'learningOutcomes' => [],
    'toolsUsed' => [],
    'targetAudiences' => [],
    'prerequisites' => [],
    'instructorsList' => [],
    'languages' => [],
    'programLevel' => 'General',
    'initialCourseState' => [],
    'initialSectionState' => [],
    'isEnrolled' => false, 
    'courseStatuses' => [], // State baru: Menyimpan status tiap course (unlocked/completed)
    'resumeCourseId' => null, // State baru: Menyimpan ID Course terakhir untuk tombol utama
]);

mount(function (Program $program) {
    // 1. Eager Load semua relasi yang dibutuhkan
    $program->load([
        'courses.sections.lessons.material',
        'courses.sections.lessons.quiz',
        'courses.sections.lessons.assignment',
        'courses.instructors.user',
        'batches' => fn($q) => $q->where('status', 'open')->orderBy('start_date->date', 'asc')
    ])->loadCount('enrollments');

    $this->program = $program;
    $this->activeBatch = $program->batches->first(); 

    // 2. Agregasi Data
    $this->learningOutcomes = $program->courses->pluck('learning_outcomes')->flatten()->filter()->unique()->toArray();
    $this->toolsUsed = $program->courses->pluck('tools_used')->flatten()->filter()->unique()->toArray();
    $this->targetAudiences = $program->courses->pluck('target_audience')->flatten()->filter()->unique()->toArray();
    $this->prerequisites = $program->courses->pluck('prerequisites')->flatten()->filter()->unique()->toArray();
    $this->languages = $program->courses->pluck('language')->filter()->unique()->toArray();

    // 3. Logika Penentuan Level
    $levels = $program->courses->pluck('level')->unique()->toArray();
    if (count($levels) === 1) {
        $this->programLevel = ucfirst($levels[0]);
    } elseif (count($levels) > 1) {
        $levelOrder = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
        usort($levels, fn($a, $b) => ($levelOrder[$a] ?? 0) <=> ($levelOrder[$b] ?? 0));
        $this->programLevel = ucfirst(reset($levels)) . ' - ' . ucfirst(end($levels));
    }

    // 4. Agregasi Instruktur
    $instructors = collect();
    foreach ($program->courses as $course) {
        foreach ($course->instructors as $instructorPivot) {
            $user = $instructorPivot->user;
            if ($user && !$instructors->contains('id', $user->id)) {
                $user->course_context = $course->title; 
                $user->instructor_role = $instructorPivot->role_label ?? 'Instructor'; 
                $instructors->push($user);
            }
        }
    }
    $this->instructorsList = $instructors;

    // 5. State Setup untuk Alpine.js Accordion
    $this->initialCourseState = $program->courses->mapWithKeys(fn($c, $idx) => [$c->id => $idx === 0])->toArray();
    $this->initialSectionState = $program->courses->flatMap->sections->mapWithKeys(fn($s) => [$s->id => false])->toArray();

    // 6. Cek Status Enroll
    if (auth()->check()) {
        $target = $this->activeBatch ?? $program;
        $this->isEnrolled = Enrollment::where('user_id', auth()->id())
            ->where('enrollable_id', $target->id)
            ->where('enrollable_type', get_class($target))
            ->whereIn('status', ['active', 'completed'])
            ->where(fn ($query) => $query->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()))
            ->exists();
    }

    // 7. Logika Sekuensial Course (Hanya jika terdaftar)
    if ($this->isEnrolled) {
        // Ambil semua ID lesson yang sudah diselesaikan oleh user ini
        $completedLessonIds = DB::table('lesson_completions')
            ->where('user_id', auth()->id())
            ->pluck('lesson_id')
            ->toArray();

        $previousCourseCompleted = true; // Course pertama selalu terbuka otomatis

        foreach ($program->courses as $course) {
            // Ambil semua lesson yang ada di dalam course ini
            $courseLessons = $course->sections->flatMap->lessons;
            $totalLessons = $courseLessons->count();
            
            // Hitung berapa lesson di course ini yang ID-nya ada di daftar selesai
            $completedCount = $courseLessons->filter(fn($l) => in_array($l->id, $completedLessonIds))->count();
            
            // Penentuan Status
            $isUnlocked = $previousCourseCompleted;
            $isCompleted = false;

            if ($totalLessons > 0) {
                // Jika ada isinya, cek apakah yang selesai sama dengan totalnya
                $isCompleted = ($completedCount === $totalLessons);
            } else {
                // PERBAIKAN: Jika kosong (0 lesson), dia otomatis selesai HANYA JIKA dia sudah terbuka.
                // Jika dia masih terkunci, jangan paksa selesai.
                $isCompleted = $isUnlocked ? true : false;
            }

            $this->courseStatuses[$course->id] = [
                'unlocked' => $isUnlocked,
                'completed' => $isCompleted,
            ];

            // Set Tombol Utama ke course PERTAMA yang terbuka tapi BELUM selesai
            if ($isUnlocked && !$isCompleted && !$this->resumeCourseId) {
                $this->resumeCourseId = $course->id;
            }

            // Status 'selesai' course ini menjadi syarat buka course berikutnya
            $previousCourseCompleted = $isCompleted; 
        }

        // Fallback: Jika semua course sudah tamat, arahkan kembali ke course terakhir
        if (!$this->resumeCourseId && $program->courses->isNotEmpty()) {
            $this->resumeCourseId = $program->courses->last()->id;
        }
    }
});

// Fungsi Action untuk Enroll
$enroll = function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    $this->validate([
        'couponCode' => 'nullable|string|max:64',
        'paymentMethod' => 'required|in:gateway_va,manual_transfer',
    ]);
    $program = Program::findOrFail($this->program->id);
    $batch = $program->batches()->where('status', 'open')->orderBy('start_date->date')->first();
    $target = $batch ?? $program;
    $transaction = app(CheckoutService::class)->create(auth()->user(), $target, $this->couponCode, $this->paymentMethod);
    $this->checkoutInvoice = $transaction->invoice_code;
    if ($transaction->isPaid()) {
        return redirect()->route('student.program.detail', $program);
    }
    if ($transaction->snap_token) {
        $this->dispatch('commerce-payment', token: $transaction->snap_token);
    }
};

?>

<div class="min-h-screen text-slate-800 pb-12">
    <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    <div x-data x-on:commerce-payment.window="if (window.snap) { window.snap.pay($event.detail.token, { onSuccess: () => window.location.reload(), onPending: () => window.location.reload() }); } else { alert('Gateway belum tersedia. Muat ulang halaman.'); }"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
            
            <div>
                <h1 class="text-5xl font-bold font-heading text-text-blue mb-4">{{ $program->title }}</h1>
                <p class="text-text-gray font-body mb-4 text-md">
                    {{ $program->short_description ?? \Illuminate\Support\Str::limit($program->description, 150) }}
                </p>
                
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="text-text-gray text-xs ml-2">Topic:</span>
                    @foreach($program->topics as $topic)
                        <span class="font-caption text-xs bg-secondary2 text-text-blue px-2.5 py-1 rounded border border-primary font-medium">{{ $topic }}</span>
                    @endforeach
                    <div>
                        <span class="text-text-gray text-xs ml-2">Level:</span>
                        <span class="font-caption text-xs bg-secondary2 text-text-blue px-2.5 py-1 rounded border border-primary font-medium">{{ $programLevel }}</span>
                        
                        <span class="text-text-gray text-xs ml-2">Type:</span>
                        <span class="font-caption text-xs bg-secondary2 text-text-blue px-2.5 py-1 rounded border border-primary font-medium">{{ $program->type === 'cohort' ? 'Program Angkatan' : 'Program Mandiri' }}</span>
                        
                        <span class="text-text-gray text-xs ml-2">Contain:</span>
                        <span class="font-caption text-xs bg-secondary2 text-text-blue px-2.5 py-1 rounded border border-primary font-medium">{{ $program->courses->count() }} Courses</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-sm font-caption text-text-gray border-b border-slate-200 pb-6 font-extralight">
                    <div class="flex items-center gap-1 font-caption">
                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        {{ number_format($program->rating_avg, 1) }}
                    </div>
                    <div class="flex items-center gap-1 mx-2">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg> 
                        {{ number_format($program->enrollments_count, 0, ',', '.') }} Learner Enrolled
                    </div>
                    <div class="flex items-center gap-1 mx-2">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> 
                        Last updated {{ $program->updated_at->format('m/y') }}
                    </div>
                    <div class="flex items-center gap-1 mx-2">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 
                        {{ count($languages) > 0 ? implode(', ', $languages) : 'Indonesia' }}
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h2 class="font-heading text-3xl font-bold text-text-blue mb-8">About this program</h2>
                <div class="bg-white rounded-xl shadow-sm border border-gray-stroke border-2 p-8 md:p-16">
                    
                    <div class="text-base font-body text-text-gray leading-normal mb-8 prose max-w-none">
                        {!! nl2br(e($program->description)) !!}
                    </div>

                    @if($program->type === 'cohort' && $activeBatch)
                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-8">
                            <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> 
                            Date and Time Information
                        </h3>

                        <div class="relative max-w-4xl mb-12">
                            <div class="absolute top-6 bottom-6 w-0.5 bg-primary left-[111px] md:left-[183px] z-0"></div>
                            
                            <div class="space-y-6">
                                
                                @if($activeBatch->registration_deadline && isset($activeBatch->registration_deadline['date']))
                                <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                    <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                        {{ \Carbon\Carbon::parse($activeBatch->registration_deadline['date'])->translatedFormat('d F Y') }}
                                    </div>
                                    <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </div>
                                    <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                        <div class="font-semibold font-body text-text-blue mb-1">Registration Deadline</div>
                                        <div class="mt-2 text-sm text-text-gray font-body">
                                            {{ $activeBatch->registration_deadline['description'] ?? 'Batas akhir masa pendaftaran peserta untuk program ini.' }}
                                        </div>
                                    </div>
                                </div>
                                @endif

                                @if($activeBatch->start_date && isset($activeBatch->start_date['date']))
                                <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                    <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                        {{ \Carbon\Carbon::parse($activeBatch->start_date['date'])->translatedFormat('d F Y') }}
                                    </div>
                                    <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                                    </div>
                                    <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                        <div class="font-semibold font-body text-text-blue mb-1">Program Dimulai</div>
                                        <div class="mt-2 text-sm text-text-gray font-body">
                                            {{ $activeBatch->start_date['description'] ?? 'Hari pertama dimulainya rangkaian program pembelajaran dan akses course.' }}
                                        </div>
                                    </div>
                                </div>
                                @endif

                                @if($activeBatch->timeline_detail)
                                    @foreach($activeBatch->timeline_detail as $timeline)
                                    <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                        <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                            {!! nl2br(e($timeline['date'])) !!}
                                        </div>
                                        <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M5 5l1.5 1.5"/><path d="M17.5 17.5L19 19"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M5 19l1.5-1.5"/><path d="M17.5 6.5L19 5"/>
                                            </svg>
                                        </div>
                                        <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                            <div class="font-semibold font-body text-text-blue mb-1">{{ $timeline['event'] }}</div>
                                            @if(isset($timeline['description']) && !empty($timeline['description']))
                                                <div class="mt-2 text-sm text-text-gray font-body">{{ $timeline['description'] }}</div>
                                            @else
                                                <div class="mt-2 text-sm text-text-gray font-body italic text-slate-400">Detail deskripsi acara tidak tersedia.</div>
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                @endif

                                @if($activeBatch->end_date && isset($activeBatch->end_date['date']))
                                <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                    <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                        {{ \Carbon\Carbon::parse($activeBatch->end_date['date'])->translatedFormat('d F Y') }}
                                    </div>
                                    <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                                    </div>
                                    <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                        <div class="font-semibold font-body text-text-blue mb-1">Program Berakhir</div>
                                        <div class="mt-2 text-sm text-text-gray font-body">
                                            {{ $activeBatch->end_date['description'] ?? 'Penutupan program dan batas akhir masa pembelajaran.' }}
                                        </div>
                                    </div>
                                </div>
                                @endif

                            </div>
                        </div>

                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-4">
                            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> 
                            Location Information
                        </h3>

                        <div class="flex flex-col md:flex-row gap-4 items-start mb-12 text-sm">
                            <div class="flex-1 md:ml-12">
                                <p class="text-slate-600 mt-1">{{ $activeBatch->location ?? 'Online / TBA' }}</p>
                                <div class="mt-4 flex items-center gap-2 text-slate-700 bg-blue-50 p-2 rounded w-fit border border-blue-100">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <div class="font-semibold">Quota: {{ $activeBatch->quota > 0 ? $activeBatch->quota . ' Seats' : 'Unlimited' }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if(!empty($learningOutcomes))
                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">What you'll learn</h3>
                        <p class="text-xs text-slate-500 mb-6">Knowledge and skills that you will have after finishing program</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-10">
                            @foreach($learningOutcomes as $outcome)
                            <div class="flex items-start gap-2 text-sm text-slate-600">
                                <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <div class="text-sm font-body text-text-gray leading-normal">{{ $outcome }}</div>
                            </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($toolsUsed))
                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">Tools/Software Used</h3>
                        <div class="flex flex-wrap gap-2 mb-10 mt-4">
                            @foreach($toolsUsed as $tool)
                            <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-md text-sm border border-slate-200">{{ $tool }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($targetAudiences))
                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">Target Audience</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-10 mt-4">
                            @foreach($targetAudiences as $audience)
                            <div class="flex items-start gap-2 text-sm text-slate-600">
                                <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <div class="text-sm font-body text-text-gray leading-normal">{{ $audience }}</div>
                            </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($prerequisites))
                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">Pre-requisites</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-10 mt-4">
                            @foreach($prerequisites as $prereq)
                            <div class="flex items-start gap-2 text-sm text-slate-600">
                                <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <div class="text-sm font-body text-text-gray leading-normal">{{ $prereq }}</div>
                            </div>
                            @endforeach
                        </div>
                    @endif

                    @if($program->pdf_brochure_url)
                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">PDF Brochure</h3>
                        <p class="text-xs text-slate-500 mb-6">Download our official brochure for complete details</p>
                        <a href="{{ $program->pdf_brochure_url }}" target="_blank" class="flex w-fit items-center gap-4 bg-secondary2 border border-gray2 rounded-lg p-3 pr-16 hover:bg-slate-50 transition">
                            <div class="bg-white border border-gray2 rounded p-2 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-800">Download Brochure</p>
                                <p class="text-xs text-slate-500">PDF Document</p>
                            </div>
                        </a>
                    @endif
                </div>
            </div>

            <div x-data="{ 
                coursesState: @entangle('initialCourseState'),
                sectionsState: @entangle('initialSectionState'),
                
                isAllExpanded() {
                    return Object.values(this.coursesState).every(val => val === true) && 
                           Object.values(this.sectionsState).every(val => val === true);
                },
                
                toggleAll() {
                    let targetState = !this.isAllExpanded();
                    for (let key in this.coursesState) { this.coursesState[key] = targetState; }
                    for (let key in this.sectionsState) { this.sectionsState[key] = targetState; }
                }
            }">
                <h2 class="font-heading text-3xl font-bold text-text-blue mb-2">Your learning path</h2>
                <p class="font-caption text-xs text-text-blue mb-8">
                    <span>This program contains</span>
                    <span class="font-caption font-bold text-text-blue"> {{ $program->courses->count() }} Courses</span>
                </p>
                
                <div class="flex justify-between items-end mb-4">
                    <p class="text-xs text-slate-500">
                        <span>{{ $program->courses->count() }}</span> Courses • <span>{{ $program->lessons_count }}</span> Total Activities
                    </p>
                    <button @click="toggleAll()" class="text-blue-600 text-sm font-semibold hover:underline" x-text="isAllExpanded() ? 'Collapse all courses' : 'Expand all courses'"></button>
                </div>

                @foreach($program->courses as $course)
                <div class="bg-white border border-slate-200 rounded-xl mb-3 shadow-sm overflow-hidden">
                    <div class="w-full flex justify-between items-center p-4 bg-white hover:bg-slate-50 transition">
                        
                        <button @click="coursesState['{{ $course->id }}'] = !coursesState['{{ $course->id }}']" class="flex-1 flex items-center gap-3 text-left focus:outline-none">
                            <svg class="w-5 h-5 text-slate-400 transition-transform" :class="{'rotate-90': coursesState['{{ $course->id }}']}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span class="font-semibold text-slate-800">Course {{ $loop->iteration }}: {{ $course->title }}</span>
                        </button>
                        
                        <div class="flex items-center gap-4 shrink-0 pl-4">
                            <span class="text-xs text-slate-500">{{ $course->sections->count() }} Sections</span>
                            
                            @if($isEnrolled)
                                @if(isset($courseStatuses[$course->id]) && $courseStatuses[$course->id]['unlocked'])
                                <a href="{{ route('student.learning.player', $activeBatch ? ['course' => $course->id, 'batch_id' => $activeBatch->id] : ['course' => $course->id, 'program_id' => $program->id]) }}" class="inline-flex items-center justify-center px-4 py-1.5 text-xs font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-300 transition-all shadow-sm">    
                                        {{ $courseStatuses[$course->id]['completed'] ? 'Selesai' : 'Mulai Belajar' }}
                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                    </a>
                                @else
                                    <span class="inline-flex items-center justify-center px-4 py-1.5 text-xs font-bold text-slate-400 bg-slate-100 rounded-lg border border-slate-200 cursor-not-allowed" title="Selesaikan course sebelumnya terlebih dahulu">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        Terkunci
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>

                    <div x-show="coursesState['{{ $course->id }}']" x-collapse class="p-4 border-t border-slate-100 bg-slate-50">
                        <div class="px-4 mb-4">
                            <p class="text-slate-600 text-sm">{{ $course->description }}</p>
                        </div>

                        @forelse($course->sections as $section)
                        <div class="mb-3 bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                            <button @click="sectionsState['{{ $section->id }}'] = !sectionsState['{{ $section->id }}']" class="w-full flex justify-between items-center p-3 hover:bg-slate-50 transition">
                                <div class="flex items-center gap-2 text-left">
                                    <svg class="w-4 h-4 text-blue-500 transition-transform shrink-0" :class="{'rotate-90': sectionsState['{{ $section->id }}']}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    <span class="font-medium text-slate-700 text-sm">Section {{ $loop->iteration }}: {{ $section->title }}</span>
                                </div>
                            </button>
                            
                            <div x-show="sectionsState['{{ $section->id }}']" x-collapse class="p-3 border-t border-slate-100 text-sm text-slate-600 pl-9">
                                <ul class="space-y-3">
                                    @forelse($section->lessons as $lesson)
                                    <li class="flex gap-3 items-start">
                                        @if($lesson->item_type === 'material')
                                            <svg class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg> 
                                        @elseif($lesson->item_type === 'quiz')
                                            <svg class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @else
                                            <svg class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        @endif
                                        <div class="flex-1">
                                            <span class="font-medium capitalize text-slate-700">{{ $lesson->item_type }}:</span> 
                                            {{ $lesson->content->title ?? 'Untitled Lesson' }}
                                        </div>
                                    </li>
                                    @empty
                                    <li class="text-slate-400 italic">No lessons in this section yet.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                        @empty
                        <div class="px-4 pb-4 text-slate-400 italic text-sm">No sections created for this course.</div>
                        @endforelse
                    </div>
                </div>
                @endforeach
            </div>
            
            @if($instructorsList->isNotEmpty())
            <div class="mt-12">
                <h2 class="font-heading text-3xl font-bold text-text-blue mb-8">Meet the Instructors</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($instructorsList as $instructorUser)
                    <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 bg-slate-200 rounded-full flex shrink-0 items-center justify-center text-slate-400 overflow-hidden">
                            @if($instructorUser->profile_photo_url)
                                <img src="{{ $instructorUser->profile_photo_url }}" class="w-full h-full object-cover">
                            @else
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                            @endif
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-sm">{{ $instructorUser->name }}</h4>
                            <p class="text-xs text-slate-500 mb-1">{{ $instructorUser->instructor_role }}</p>
                            <p class="text-[10px] text-text-blue font-medium">Teaching: <span class="text-primary">{{ $instructorUser->course_context }}</span></p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($program->faq))
            <div x-data="{ activeFaq: null }" class="mt-12 mb-4">
                <h2 class="font-heading text-3xl font-bold text-text-blue mb-8">Frequently Asked Questions</h2>
                
                @foreach($program->faq as $index => $item)
                <div class="space-y-3 text-sm mb-3">
                    <button @click="activeFaq = activeFaq === {{ $index }} ? null : {{ $index }}" 
                        class="relative z-10 w-full flex justify-between items-center px-5 py-4 bg-white rounded-lg transition-colors duration-200 focus:outline-none border text-left"
                        :class="activeFaq === {{ $index }} ? 'border-[#3b82f6]' : 'border-[#60a5fa] hover:border-[#3b82f6]'">
                        <span class="font-semibold text-[#2d3359] text-[15px] pr-4">{{ $item['question'] }}</span>
                        
                        <svg class="w-3.5 h-3.5 text-[#2d3359] shrink-0 transition-transform duration-300" 
                            :class="activeFaq === {{ $index }} ? 'rotate-0' : 'rotate-180'" 
                            viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 7l7 10H5l7-10z"/>
                        </svg>
                    </button>

                    <div x-show="activeFaq === {{ $index }}" x-collapse class="relative z-0">
                        <div class="bg-white border-b border-l border-r border-[#e2e8f0] rounded-b-lg -mt-2 pt-6 pb-4 px-5">
                            <p class="text-[#2d3359] font-medium text-[14px]">
                                {!! nl2br(e($item['answer'])) !!}
                            </p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

        </div>

        <div class="lg:col-span-1">
            <div class=" top-24 space-y-6">
                
                <div class="bg-white rounded-xl shadow-md border border-slate-100 p-6">
                    <h2 class="text-3xl font-extrabold text-blue-600 mb-6">
                        {{ ($activeBatch ? $activeBatch->effective_price : $program->price) == 0 ? 'Gratis' : 'Rp ' . number_format($activeBatch ? $activeBatch->effective_price : $program->price, 0, ',', '.') }}
                    </h2>
                    
                    @if($isEnrolled)
                        {{-- Tombol Utama Mengarah ke Course Terakhir yang Belum Selesai --}}
                        <a href="{{ route('student.learning.player', $activeBatch ? ['course' => $resumeCourseId, 'batch_id' => $activeBatch->id] : ['course' => $resumeCourseId, 'program_id' => $program->id]) }}" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-4 rounded-lg transition shadow-sm shadow-green-200 mb-6 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Lanjutkan Belajar
                        </a>
                        
                    @else
                        <label class="block text-sm mb-2">Kupon (opsional)<input wire:model="couponCode" maxlength="64" class="block w-full rounded border-gray-300" placeholder="Satu kupon per transaksi"></label>
                        <label class="block text-sm mb-3">Metode pembayaran<select wire:model="paymentMethod" class="block w-full rounded border-gray-300"><option value="gateway_va">Midtrans</option><option value="manual_transfer">Transfer manual (verifikasi admin)</option></select></label>
                        @foreach (['couponCode', 'checkout', 'paymentMethod', 'waitlist'] as $field)
                            @error($field) <p role="alert" class="text-red-700 text-sm mb-2">{{ $message }}</p> @enderror
                        @endforeach
                        @if ($checkoutInvoice)
                            <p role="status" class="text-sm mb-3">Invoice: {{ $checkoutInvoice }}. Akses diberikan setelah lunas. Untuk transfer manual, hubungi admin dengan nomor invoice ini.</p>
                        @endif
                        <button wire:click="enroll" wire:loading.attr="disabled" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition shadow-sm shadow-blue-200 mb-3">
                            Daftar / Bayar
                        </button>
                        <a href="{{ url('/corporate') }}" class="block text-center text-blue-700 text-sm mb-6">Beli beberapa kursi untuk perusahaan</a>
                    @endif

                    @if(!empty($program->what_is_included))
                        <h3 class="font-semibold text-slate-800 text-sm mb-3">This Program bundle includes:</h3>
                        <ul class="space-y-3 text-sm text-slate-600 mb-6">
                            @foreach($program->what_is_included as $included)
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> 
                                {{ $included }}
                            </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($program->enrollment_code)
                    <div class="border-t border-slate-100 pt-4 mt-4">
                        <label class="text-xs text-slate-500 block mb-2">Have an enrollment code? Use it here</label>
                        <div class="flex gap-2">
                            <input type="text" class="flex-1 border-slate-200 rounded-md text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm" placeholder="Code...">
                            <button class="bg-blue-100 text-blue-700 px-4 py-2 rounded-md text-sm font-semibold hover:bg-blue-200 transition">Apply</button>
                        </div>
                    </div>
                    @endif
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 text-center">
                    <h3 class="font-bold text-slate-800 mb-2">Got questions?</h3>
                    <p class="text-xs text-slate-500 mb-6">Ask, discuss, and connect. Join the forum discussion!</p>
                    <button class="w-full bg-blue-50 border border-blue-200 hover:bg-blue-100 text-blue-700 font-semibold py-2.5 px-4 rounded-lg transition shadow-sm">
                        Go to Forum
                    </button>
                </div>

            </div>
        </div>

    </div>
</div>
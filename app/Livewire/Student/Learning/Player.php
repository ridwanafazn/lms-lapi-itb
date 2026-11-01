<?php

namespace App\Livewire\Student\Learning;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseTestimonial;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
// TAMBAHKAN IMPORT MODEL DI SINI AGAR TIDAK ERROR CLASS NOT FOUND
use Livewire\Component;
use Livewire\WithFileUploads;

class Player extends Component
{
    use WithFileUploads;

    // --- MAIN LEARNING PROPERTIES ---
    public $batch;

    public $program;

    #[Locked]
    public $course;

    #[Locked]
    public $currentLesson;

    #[Locked]
    public $currentContent;

    public $contentType;

    public $assignmentFile;

    public $assignmentNotes;

    public $existingSubmission;

    // View States
    public $isForumActive = false;

    public $isTestimonialActive = false;

    // --- NAVIGATION & UI PROPERTIES ---
    public $prevLessonId = null;

    public $nextLessonId = null;

    public $completedLessonIds = [];

    public $totalLessons = 0;

    public $overallProgress = 0;

    public $isLocked = false;

    public $activeSectionId = null;

    // --- QUIZ ENGINE PROPERTIES ---
    public $isQuizActive = false;

    #[Locked]
    public $quizAttempt;

    public $quizQuestions;

    public $currentQuestionIndex = 0;

    public $userAnswers = [];

    public $flaggedQuestions = [];

    public $timeRemaining = 0;

    // --- TESTIMONIAL PROPERTIES ---
    public $ratingMaterial = 5;

    public $ratingInstructor = 5;

    public $ratingPlatform = 5;

    public $publicTestimonial = '';

    public $privateFeedback = '';

    public $hasTestimonial = false;

    public function mount(Course $course, Request $request)
    {
        $this->course = $course;

        // 1. Ambil ID Program/Batch dari Query String (misal: ?program_id=1)
        $programId = $request->query('program_id');
        $batchId = $request->query('batch_id');

        $target = null;

        if ($batchId) {
            $this->batch = Batch::find($batchId);
            $target = $this->batch;
        } elseif ($programId) {
            $this->program = Program::find($programId);
            $target = $this->program;
        }

        // --- MULAI PERBAIKAN KEAMANAN ENROLLMENT ---
        if ($target && auth()->check()) {
            $user = auth()->user();
            $hasAccess = false;

            if ($target instanceof \App\Models\Program) {
                $hasAccess = $user->canAccessProgram($target);
            } elseif ($target instanceof \App\Models\Batch) {
                $hasAccess = $user->canAccessBatch($target);
            }

            if (! $hasAccess) {
                // Redirect id yang tepat jika targetnya adalah batch
                $redirectId = $target instanceof \App\Models\Batch ? $target->program_id : $target->id;

                return redirect()->route('student.program.detail', $redirectId)
                    ->with('error', 'Akses ditolak. Anda belum terdaftar di program ini.');
            }
        } else {
            // Logika fallback menggunakan method hasAccessToCourse bawaan User model
            if (auth()->check() && ! auth()->user()->hasAccessToCourse($course->id)) {
                abort(403, 'Akses tidak sah. Anda belum memiliki akses ke kelas ini.');
            }
        }
        // --- AKHIR PERBAIKAN KEAMANAN ---

        // Langsung oper ID course yang didapatkan dari Route Model Binding
        $this->loadCourseData($this->course->id);
        $this->loadUserProgress();
        $this->checkExistingTestimonial();

        $viewParam = $request->query('view');
        $lessonId = $request->query('lesson_id');

        if ($viewParam === 'forum') {
            $this->openForum(false);
        } elseif ($viewParam === 'testimonial' && $this->overallProgress >= 100) {
            $this->openTestimonial(false);
        } elseif ($lessonId) {
            $this->loadLesson($lessonId);
        } else {
            $this->resumeLastPosition();
        }
    }

    /**
     * Livewire rehydrates models with a bare find() on every request, which drops
     * eager loads and re-triggers N+1 queries (sections->lessons, material, quiz).
     * Reload the relations once per request instead of lazily per model.
     */
    public function hydrate(): void
    {
        if ($this->course && ! $this->course->relationLoaded('sections')) {
            $this->course->load(self::COURSE_RELATIONS);
        }

        if ($this->currentLesson && ! $this->currentLesson->relationLoaded('material')) {
            $relations = ['material', 'assignment'];

            if ($this->currentLesson->quiz_id) {
                $relations[] = 'quiz.questions.options';
            }

            $this->currentLesson->load($relations);
        }
    }

    /**
     * Relations needed to render the player shell and sidebar without N+1.
     * Quiz questions are intentionally excluded: they are only needed for the
     * active lesson, which loadLesson() loads on demand.
     */
    private const COURSE_RELATIONS = [
        'sections.lessons.material',
        'sections.lessons.quiz',
        'sections.lessons.assignment',
    ];

    private function loadCourseData($courseId)
    {
        if ($courseId) {
            $this->course = Course::with(self::COURSE_RELATIONS)->where('id', $courseId)->firstOrFail();
        } else {
            // Ambil courses dari relasi Program (baik via batch maupun langsung program)
            $targetProgram = $this->batch ? $this->batch->program : $this->program;
            $this->course = $targetProgram->courses()->first();
            if (! $this->course) {
                abort(404, 'Materi tidak ditemukan dalam batch/program ini.');
            }
            $this->course->load(self::COURSE_RELATIONS);
        }
    }

    private function resumeLastPosition()
    {
        if ($this->course && $this->course->sections->isNotEmpty()) {
            $firstSection = $this->course->sections->first();
            $firstLesson = $firstSection->lessons->first();

            if ($firstLesson) {
                $this->loadLesson($firstLesson->id);
            }
        }
    }

    private function checkExistingTestimonial()
    {
        $this->hasTestimonial = CourseTestimonial::where('user_id', auth()->id())
            ->where('course_id', $this->course->id)
            ->exists();
    }

    // --- LOAD CONTENT LOGIC ---

    public function loadLesson($lessonId)
    {
        // Clicking the lesson that is already open is a no-op: re-fetching it
        // costs queries without changing anything (Phase 3.2).
        if ($this->currentLesson
            && $this->currentLesson->id === $lessonId
            && $this->currentContent
            && ! $this->isQuizActive) {
            return;
        }

        $this->calculateNavigation($lessonId);

        if (! $this->checkSequentialAccess($lessonId)) {
            if ($this->prevLessonId) {
                $this->dispatch('notify', ['message' => 'Selesaikan materi sebelumnya terlebih dahulu!', 'type' => 'error']);
                $this->loadLesson($this->prevLessonId);

                return;
            }
        }

        $this->isForumActive = false;
        $this->isTestimonialActive = false;
        $this->isQuizActive = false;
        $this->quizAttempt = null;
        $this->isLocked = false;

        $this->currentLesson = Lesson::with(['material', 'quiz.questions.options', 'assignment'])->find($lessonId);

        if (! $this->currentLesson) {
            return;
        }
        // Uses the already-loaded section collection to avoid an extra query.
        abort_unless(
            $this->course->sections->contains(fn ($section) => $section->id === $this->currentLesson->section_id),
            404
        );

        $this->activeSectionId = $this->currentLesson->section_id;

        if ($this->currentLesson->quiz_id) {
            $this->contentType = 'quiz';
            $this->currentContent = $this->currentLesson->quiz;
            $this->checkQuizResume();
        } elseif ($this->currentLesson->assignment_id) {
            $this->contentType = 'assignment';
            $this->currentContent = $this->currentLesson->assignment;

            $this->existingSubmission = $this->currentContent && auth()->check()
                ? app(\App\Services\AssignmentSubmissionService::class)->find(
                    auth()->user(),
                    $this->currentContent,
                    $this->batch?->id
                )
                : null;
        } else {
            // Mapping material type, live_session akan masuk ke sini
            $type = $this->currentLesson->material?->type ?? 'text';
            if ($type === 'article') {
                $this->contentType = 'text';
            } else {
                $this->contentType = $type; // Output: 'live_session', 'video', etc.
            }
            $this->currentContent = $this->currentLesson->material;
        }

        $this->js("window.history.pushState({}, '', '?course_id={$this->course->id}&lesson_id={$lessonId}')");
    }

    private function checkSequentialAccess($targetLessonId)
    {
        if (! $this->course->is_sequential) {
            return true;
        }
        if (! $this->prevLessonId) {
            return true;
        }
        if (in_array($this->prevLessonId, $this->completedLessonIds)) {
            return true;
        }

        return false;
    }

    public function openForum($pushState = true)
    {
        $this->isForumActive = true;
        $this->isTestimonialActive = false;
        $this->currentLesson = null;
        $this->activeSectionId = null;

        if ($pushState) {
            $this->js("window.history.pushState({}, '', '?course_id={$this->course->id}&view=forum')");
        }
    }

    public function openTestimonial($pushState = true)
    {
        if ($this->overallProgress < 100) {
            $this->dispatch('notify', ['message' => 'Selesaikan seluruh materi terlebih dahulu!', 'type' => 'error']);

            return;
        }

        $this->isTestimonialActive = true;
        $this->isForumActive = false;
        $this->currentLesson = null;
        $this->activeSectionId = null;

        $this->contentType = 'testimonial';

        if ($pushState) {
            $this->js("window.history.pushState({}, '', '?course_id={$this->course->id}&view=testimonial')");
        }
    }

    // --- COMPLETION LOGIC ---

    public function markAsComplete()
    {
        if (! auth()->check() || ! $this->currentLesson) {
            return;
        }
        if ($this->contentType === 'quiz') {
            return;
        }

        $this->executeCompletion();
    }

    public function autoCompleteContent()
    {
        if (! auth()->check() || ! $this->currentLesson) {
            return;
        }

        // Memastikan tipe live_session termasuk auto-complete jika user membuka halamannya
        $allowedAutoTypes = ['text', 'live_session', 'google_drive_video', 'google_drive_file'];

        if (in_array($this->contentType, $allowedAutoTypes)) {
            $this->executeCompletion();
        }
    }

    public function completeByScroll()
    {
        $this->autoCompleteContent();
    }

    private function executeCompletion()
    {
        $lesson = Lesson::with('quiz')->whereKey($this->currentLesson?->id)
            ->whereHas('section', fn ($query) => $query->where('course_id', $this->course->id))->firstOrFail();
        if ($lesson->quiz_id && (! $lesson->quiz || ! $this->quizService()->hasPassed(auth()->user(), $lesson->quiz, $this->batch?->id))) {
            return;
        }
        try {
            if (! in_array($this->currentLesson->id, $this->completedLessonIds)) {
                DB::table('lesson_completions')->updateOrInsert(
                    ['user_id' => auth()->id(), 'lesson_id' => $this->currentLesson->id],
                    ['completed_at' => now()]
                );

                $this->loadUserProgress();
                $this->dispatch('notify', ['message' => 'Materi Selesai!', 'type' => 'success']);

                if ($this->overallProgress >= 100 && ! $this->hasTestimonial) {
                    $this->openTestimonial();
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::channel('domain')->error('Player execution completion failed', [
                'user_id' => auth()->id(),
                'lesson_id' => $this->currentLesson?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // --- QUIZ ENGINE LOGIC ---

    private function quizService(): \App\Services\QuizService
    {
        return app(\App\Services\QuizService::class);
    }

    private function checkQuizResume()
    {
        $ongoingAttempt = $this->quizService()->findInProgress(
            auth()->user(),
            $this->currentContent,
            $this->batch?->id
        );

        if ($ongoingAttempt) {
            $this->resumeQuiz($ongoingAttempt);
        } else {
            $this->quizAttempt = null;
            $this->isQuizActive = false;
        }
    }

    public function startQuiz()
    {
        if ($this->contentType !== 'quiz' || ! auth()->check()) {
            return;
        }

        try {
            $this->authorize('submit', $this->currentLesson->quiz);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'message' => 'Akses kuis ditolak: Anda belum memiliki akses ke materi ini.',
                'type' => 'error',
            ]);

            return;
        }

        $attempt = $this->quizService()->startOrResume(
            auth()->user(),
            $this->currentContent,
            $this->batch?->id
        );

        if (! $attempt) {
            $this->dispatch('notify', [
                'message' => 'Batas percobaan kuis sudah habis.',
                'type' => 'error',
            ]);

            return;
        }

        $this->quizAttempt = $attempt;
        $this->initializeQuizState();
    }

    public function resumeQuiz(QuizAttempt $attempt)
    {
        if (! auth()->check() || $attempt->user_id !== auth()->id() || ($attempt->quiz_id !== ($this->currentLesson?->quiz_id ?? null))) {
            $this->dispatch('notify', ['message' => 'Akses kuis ditolak.', 'type' => 'error']);

            return;
        }
        if ($attempt->isSubmitted()) {
            $this->dispatch('notify', ['message' => 'Kuis sudah selesai.', 'type' => 'warning']);

            return;
        }
        try {
            $this->authorize('submit', $attempt->quiz);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['message' => 'Akses ditolak.', 'type' => 'error']);

            return;
        }
        $this->quizService()->authorizeBatchContext(auth()->user(), $attempt->quiz, $attempt->batch_id);
        $this->quizAttempt = $attempt;
        $this->initializeQuizState();
    }

    private function initializeQuizState()
    {
        // Urutan soal ditentukan server (acak per attempt bila diaktifkan).
        $this->quizQuestions = $this->quizService()->questionsFor($this->quizAttempt);

        $this->userAnswers = [];
        $savedAnswers = QuizAnswer::where('quiz_attempt_id', $this->quizAttempt->id)->get();
        foreach ($savedAnswers as $answer) {
            $this->userAnswers[$answer->question_id] = $answer->question_option_id ?? $answer->essay_answer;
        }

        $this->timeRemaining = $this->quizService()->secondsRemaining($this->quizAttempt);
        $this->currentQuestionIndex = $this->quizAttempt->current_question_index ?? 0;
        $this->isQuizActive = true;

        // Attempt yang sudah melewati batas waktu langsung dinilai (timeout).
        if ($this->quizService()->isExpired($this->quizAttempt)) {
            $this->finishQuiz();
        }
    }

    private function ownedQuizAttempt(): ?QuizAttempt
    {
        try {
            $attempt = QuizAttempt::whereKey($this->quizAttempt?->id)->where('user_id', auth()->id())
                ->where('quiz_id', $this->currentLesson?->quiz_id)->firstOrFail();
            $this->authorize('submit', $attempt->quiz);
            $this->quizService()->authorizeBatchContext(auth()->user(), $attempt->quiz, $attempt->batch_id);

            return $attempt;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function saveAnswer($questionId, $optionId)
    {
        if (! $this->quizAttempt) {
            return;
        }
        $ownedAttempt = $this->ownedQuizAttempt();

        if (! $ownedAttempt) {
            $this->dispatch('notify', ['message' => 'Kuis tidak dapat dilanjutkan.', 'type' => 'error']);
            $this->isQuizActive = false;

            return;
        }

        $this->quizService()->saveChoice(auth()->user(), $ownedAttempt, $questionId, $optionId);
        $this->userAnswers[$questionId] = $optionId;
    }

    public function saveEssayAnswer($questionId, $text)
    {
        if (! $this->quizAttempt) {
            return;
        }
        $ownedAttempt = $this->ownedQuizAttempt();

        if (! $ownedAttempt) {
            $this->dispatch('notify', ['message' => 'Jawaban tidak dapat disimpan.', 'type' => 'error']);
            $this->isQuizActive = false;

            return;
        }

        $this->quizService()->saveEssay(auth()->user(), $ownedAttempt, $questionId, (string) $text);
        $this->userAnswers[$questionId] = $text;
    }

    public function toggleFlag($questionId)
    {
        if (isset($this->flaggedQuestions[$questionId])) {
            unset($this->flaggedQuestions[$questionId]);
        } else {
            $this->flaggedQuestions[$questionId] = true;
        }
    }

    public function goToQuestion($index)
    {
        if ($index >= 0 && $index < count($this->quizQuestions)) {
            $this->currentQuestionIndex = $index;
            $this->quizAttempt?->update(['current_question_index' => $index]);
        }
    }

    public function nextQuestion()
    {
        $this->goToQuestion($this->currentQuestionIndex + 1);
    }

    public function prevQuestion()
    {
        $this->goToQuestion($this->currentQuestionIndex - 1);
    }

    public function finishQuiz()
    {
        if (! $this->quizAttempt || ! auth()->check()) {
            return;
        }

        $ownedAttempt = $this->ownedQuizAttempt();
        $timedOut = $this->quizService()->isExpired($ownedAttempt);

        $attempt = app(\App\Services\QuizGrader::class)->grade($ownedAttempt, $timedOut);

        $this->quizAttempt = $attempt;
        $this->isQuizActive = false;

        if ($attempt->isPendingGrading()) {
            $this->dispatch('notify', ['message' => 'Kuis dikumpulkan. Menunggu penilaian seluruh esai.', 'type' => 'info']);

            return;
        }

        if ($timedOut) {
            $this->dispatch('notify', [
                'message' => 'Waktu habis! Kuis ditutup secara otomatis.',
                'type' => 'error',
            ]);

            return;
        }

        $finalScore = (float) $attempt->score;

        if ($attempt->hasPassed()) {
            $this->executeCompletion();

            if ($this->nextLessonId) {
                $this->goToNext();
            }
        } else {
            $this->dispatch('notify', [
                'message' => 'Maaf, belum lulus. Skor: '.number_format($finalScore, 1),
                'type' => 'error',
            ]);
        }
    }

    // --- UTILITIES ---

    private function calculateNavigation($lessonId)
    {
        $allLessons = $this->course->sections->flatMap->lessons;
        $currentIndex = $allLessons->search(fn ($item) => $item->id === $lessonId);

        $this->prevLessonId = ($currentIndex !== false && $currentIndex > 0)
            ? $allLessons[$currentIndex - 1]->id : null;

        $this->nextLessonId = ($currentIndex !== false && $currentIndex < $allLessons->count() - 1)
            ? $allLessons[$currentIndex + 1]->id : null;
    }

    public function loadUserProgress()
    {
        if (! auth()->check() || ! $this->course) {
            return;
        }

        $calc = \App\Livewire\Student\Learning\PlayerProgressCalculator::progressForUserAndCourse(
            auth()->user(), $this->course
        );
        $this->totalLessons = $calc['total_lessons'];
        $this->completedLessonIds = $calc['completed_ids'];
        $this->overallProgress = $calc['overall_progress'];

        foreach ($this->course->sections as $section) {
            $section->progress_percent = $calc['section_progress'][$section->id] ?? 0;
        }
    }

    public function changeLesson($id)
    {
        $this->loadLesson($id);
    }

    public function goToNext()
    {
        if ($this->course->is_sequential && $this->currentLesson) {
            if (! in_array($this->currentLesson->id, $this->completedLessonIds)) {
                $this->dispatch('notify', ['message' => 'Anda harus menyelesaikan materi ini sebelum melanjutkan.', 'type' => 'warning']);

                return;
            }
        }

        if ($this->nextLessonId) {
            $this->loadLesson($this->nextLessonId);
        } else {
            if ($this->overallProgress >= 100) {
                $this->openTestimonial();
            }
        }
    }

    public function goToPrev()
    {
        if ($this->prevLessonId) {
            $this->loadLesson($this->prevLessonId);
        }
    }

    public function submitAssignment()
    {
        if ($this->contentType !== 'assignment' || ! $this->currentContent || ! auth()->check()) {
            return;
        }

        $this->validate([
            'assignmentFile' => 'required|file|max:'.\App\Services\AssignmentSubmissionService::MAX_KILOBYTES
                .'|mimes:'.implode(',', \App\Services\AssignmentSubmissionService::ALLOWED_EXTENSIONS),
            'assignmentNotes' => 'nullable|string|max:1000',
        ], [
            'assignmentFile.required' => 'Pilih berkas tugas terlebih dahulu.',
            'assignmentFile.max' => 'Ukuran berkas maksimal 20MB.',
            'assignmentFile.mimes' => 'Format berkas tidak diizinkan.',
        ]);

        $this->existingSubmission = app(\App\Services\AssignmentSubmissionService::class)->submit(
            auth()->user(),
            $this->currentContent,
            $this->assignmentFile,
            $this->batch?->id,
            $this->assignmentNotes ?: null
        );

        $this->assignmentFile = null;
        $this->assignmentNotes = null;

        $this->executeCompletion();

        $this->dispatch('notify', ['message' => 'Tugas berhasil dikirim!', 'type' => 'success']);

        if ($this->nextLessonId) {
            $this->goToNext();
        }
    }

    public function deleteSubmission()
    {
        if (! $this->existingSubmission) {
            return;
        }

        $deleted = app(\App\Services\AssignmentSubmissionService::class)->delete($this->existingSubmission);

        $this->dispatch('notify', $deleted
            ? ['message' => 'Pengumpulan dibatalkan.', 'type' => 'info']
            : ['message' => 'Tugas sudah dinilai sehingga tidak bisa dibatalkan.', 'type' => 'error']);

        if ($deleted) {
            $this->existingSubmission = null;
        }
    }

    // --- SUBMIT TESTIMONIAL & ISSUE CERTIFICATE ---
    public function submitTestimonial()
    {
        app(\App\Services\CertificateService::class)->assertEligible(auth()->user(), $this->course, $this->batch?->id);
        $this->validate([
            'ratingMaterial' => 'required|integer|min:1|max:5',
            'ratingInstructor' => 'required|integer|min:1|max:5',
            'ratingPlatform' => 'required|integer|min:1|max:5',
            'publicTestimonial' => 'required|string|min:10',
            'privateFeedback' => 'nullable|string',
        ]);

        \App\Models\CourseTestimonial::create([
            'id' => Str::uuid(),
            'user_id' => auth()->id(),
            'course_id' => $this->course->id,
            'rating_material' => $this->ratingMaterial,
            'rating_instructor' => $this->ratingInstructor,
            'rating_platform' => $this->ratingPlatform,
            'public_testimonial' => $this->publicTestimonial,
            'private_feedback' => $this->privateFeedback,
            'status' => 'published',
        ]);

        $this->hasTestimonial = true;
        session()->flash('back_url', url()->previous() ?? route('student.program.detail', $this->program?->id ?? $this->batch?->program_id ?? $this->course?->program_id));

        $romans = [
            '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
            '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
            '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
        ];
        $romanMonth = $romans[date('m')];
        $year = date('Y');

        $prefix = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $formalNumber = "{$prefix}/PT LAPI ITB/Sert/{$romanMonth}/{$year}";

        Certificate::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'course_id' => $this->course->id,
            ],
            [
                'id' => Str::uuid(),
                'batch_id' => $this->batch ? $this->batch->id : null,
                'certificate_code' => 'CERT-'.strtoupper(Str::random(8)),
                'formal_number' => $formalNumber,
                'issued_at' => now(),
                'file_url' => '-',
            ]
        );

        $this->dispatch('notify', ['message' => 'Terima kasih atas ulasan Anda! Sertifikat diterbitkan.', 'type' => 'success']);

        return redirect()->route('student.certificate');
    }

    #[Layout('layouts.player')]
    public function render()
    {
        // Fallback default jika terjadi anomali
        $backUrl = route('dashboard');

        if ($this->batch && $this->batch->program_id) {
            // Kembali ke halaman program menggunakan relasi program_id dari batch
            $backUrl = route('student.program.detail', $this->batch->program_id);
        } elseif ($this->program) {
            $backUrl = route('student.program.detail', $this->program->id);
        } elseif ($this->course && request()->query('program_id')) {
            $backUrl = route('student.program.detail', request()->query('program_id'));
        }

        // Tambahkan array berisi $backUrl ke dalam fungsi view()
        return view('livewire.student.learning.player', [
            'backUrl' => $backUrl,
            'quizResult' => $this->currentLesson?->quiz_id
                ? $this->quizService()->latestSubmitted(auth()->user(), $this->currentLesson->quiz, $this->batch?->id)
                : null,
        ])
            ->layoutData([
                'courseTitle' => $this->course->title ?? 'Learning Player',
                'backUrl' => $backUrl, // Tetap dipertahankan jika layout utama membutuhkannya
            ]);
    }
}

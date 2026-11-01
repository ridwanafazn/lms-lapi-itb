<?php

use App\Http\Controllers\MidtransWebhookController;
use App\Livewire\Instructor\CourseBuilder;
use App\Livewire\Student\Learning\Player;
use App\Livewire\Student\Learning\QuizPlayer;
use App\Services\ZoomService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Volt::route('/', 'welcome.teshome')->name('welcome');
Volt::route('/certificates/{code}', 'public.certificate-verify')->name('certificate.verify')
    ->middleware('throttle:certificate');
Route::get('/certificates/{code}/download', function ($code) {
    $certificate = \App\Models\Certificate::with(['user.profile', 'course', 'batch'])
        ->where('certificate_code', $code)
        ->firstOrFail();

    $verifyUrl = route('certificate.verify', $certificate->certificate_code);
    $qrCodeSvg = QrCode::size(100)->margin(0)->generate($verifyUrl);
    $qrCodeBase64 = base64_encode($qrCodeSvg);
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.certificate-layout', [
        'certificate' => $certificate,
        'qrCodeBase64' => $qrCodeBase64,
    ])->setPaper('a4', 'landscape');

    $filename = 'Sertifikat-'.Str::slug($certificate->user->name).'-'.$certificate->course->id.'.pdf';

    if (request()->has('download')) {
        return $pdf->download($filename);
    }

    return $pdf->stream($filename);

})->name('certificate.download');

Route::post('/webhook/midtrans', [MidtransWebhookController::class, 'handle'])
    ->name('webhook.midtrans')
    ->middleware('throttle:webhook');

Route::middleware(['auth', 'verified'])->group(function () {

    volt::route('/tespage2', 'pages.tespage2')->name('tespage2');
    volt::route('/tespage3', 'pages.tespage3')->name('tespage3');

    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/profile', 'profile')->name('profile');

    Route::get('/api/zoom/signature/{meetingId}', function ($meetingId, ZoomService $zoomService) {
        try {
            $signature = $zoomService->generateSignature($meetingId, 0);

            return response()->json([
                'success' => true,
                'signature' => $signature,
                'sdkKey' => config('services.zoom.client_id'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    })->name('api.zoom.signature');

    Volt::route('/membership', 'membership')->name('membership');
    Volt::route('/membership/status', 'membership-status')->name('membership.status');

    Volt::route('/browse-program', 'student.browse')->name('student.browse');
    Volt::route('/my-courses', 'student.my-courses')->name('student.my-courses');

    // Ganti 'pages.program-detail' sesuai dengan nama dan lokasi file blade volt kamu
    Volt::route('/programs/{program}', 'student.program-detail')->name('student.program.detail');

    Volt::route('/announcement', 'student.announcement')->name('student.announcement');
    Volt::route('/forum', 'student.forum')->name('student.forum');
    Volt::route('/certificate', 'student.certificate')->name('student.certificate');
    // Sebelumnya: Route::get('/learning/{id}', Player::class)->...
    Route::get('/learning/course/{course}', Player::class)->name('student.learning.player');

    Route::get('/learning/{course}/quiz/{quiz}/{batch?}', QuizPlayer::class)
        ->name('student.learning.quiz')
        ->middleware('throttle:quiz_submit');

    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Volt::route('/dashboard', 'admin.dashboard')->name('dashboard');
        Volt::route('/reports', 'admin.reports')->name('reports');
        Volt::route('/users', 'admin.users')->name('users');
        Volt::route('/approvals', 'admin.approvals')->name('approvals');
        Volt::route('/transactions', 'admin.transactions')->name('transactions');
        Volt::route('/certificates', 'admin.certificates')->name('certificates');
        Volt::route('/evaluations', 'admin.evaluations')->name('evaluations');
        Volt::route('/batches', 'admin.batches')->name('batches');
        Volt::route('/courses', 'admin.courses')->name('courses');
        Volt::route('/programs', 'admin.programs')->name('programs');
        Volt::route('/course/{courseId}/curriculum', 'pages.course-curriculum')->name('course.curriculum');
        Volt::route('/feedback', 'admin.feedback')->name('feedback');
        Volt::route('/logs', 'admin.logs')->name('logs');
    });

    Route::middleware(['role:instructor'])->prefix('instructor')->name('instructor.')->group(function () {
        Volt::route('/dashboard', 'instructor.dashboard')->name('dashboard');
        Volt::route('/programs', 'instructor.programs')->name('programs');
        Volt::route('/program/{program}/courses', 'instructor.program-courses')->name('program.courses');

        // --- NEW COURSE WORKSPACE ROUTES ---
        // Menghapus middleware 'can:update,course' dari grup agar Volt tidak error 403
        Route::prefix('course/{course}')->name('course.')->group(function () {

            Route::get('/', function ($course) {
                return redirect()->route('instructor.course.curriculum', $course);
            })->name('manage');
            Route::get('/curriculum', CourseBuilder::class)->name('curriculum');
            Volt::route('/identity', 'instructor.course.identity')->name('identity');
            Volt::route('/goals', 'instructor.course.goals')->name('goals');
            Volt::route('/certificate', 'instructor.course.certificate')->name('certificate');
            Volt::route('/settings', 'instructor.course.settings')->name('settings');
        });
        // -----------------------------------

        Volt::route('/material/{material}/edit', 'instructor.edit-material')->name('material.edit');
        Volt::route('/assignment/{assignment}/edit', 'instructor.edit-assignment')->name('assignment.edit');
        Volt::route('/quiz/{quiz}/edit', 'instructor.edit-quiz')->name('quiz.edit');
        Volt::route('/grades', 'instructor.grades')->name('grades');
        Volt::route('/certificates', 'instructor.certificates')->name('certificates');
    });
});

require __DIR__.'/phase2-billing.php';
require __DIR__.'/phase2-commerce.php';
require __DIR__.'/phase2-operations.php';
require __DIR__.'/phase2-essays.php';
require __DIR__.'/phase2-refunds.php';
require __DIR__.'/auth.php';

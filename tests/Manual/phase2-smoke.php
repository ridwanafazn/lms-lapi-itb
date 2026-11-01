<?php

/**
 * Run from the repository root: php tests/Manual/phase2-smoke.php
 * Requires the existing, migrated sbu_test schema; never runs migrations.
 * Run only after other database tests finish (they may reset the same database).
 * All business writes roll back, including on failure. Only testing disk files
 * and application logs may remain. Render checks do not fetch browser assets.
 */

declare(strict_types=1);

use App\Models\Batch;
use App\Models\BatchWaitlist;
use App\Models\CorporatePurchase;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\EnrollmentPaymentSource;
use App\Models\Lesson;
use App\Models\LiveAttendance;
use App\Models\Material;
use App\Models\MembershipGrant;
use App\Models\MembershipPlan;
use App\Models\Payout;
use App\Models\Program;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BatchCloneService;
use App\Services\BatchWaitlistService;
use App\Services\CertificateService;
use App\Services\CheckoutService;
use App\Services\CorporatePurchaseService;
use App\Services\EssayGradingService;
use App\Services\LiveAttendanceService;
use App\Services\PayoutService;
use App\Services\QuizGrader;
use App\Services\QuizService;
use App\Services\RefundService;
use App\Services\SubscriptionService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$stage = 'bootstrap and safety guards';
$assertionFailure = null;
$passed = 0;
$exitCode = 0;
$check = static function (bool $condition, string $message) use (&$assertionFailure): void {
    if (! $condition) {
        $assertionFailure = $message;
        throw new RuntimeException($message);
    }
};
$pass = static function (string $message) use (&$passed): void {
    echo sprintf("%02d PASS %s\n", ++$passed, $message);
};
$reject = static function (callable $action, string $message) use ($check): void {
    try {
        $action();
    } catch (ValidationException) {
        return;
    }
    $check(false, $message);
};

try {
    // Ignore cached development config/routes without deleting or rebuilding them.
    $configCache = __DIR__.'/.phase2-smoke-disabled-config.php';
    $routeCache = __DIR__.'/.phase2-smoke-disabled-routes.php';
    $check(! is_file($configCache) && ! is_file($routeCache), 'Smoke cache bypass paths must not exist.');
    foreach ([
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
        'APP_CONFIG_CACHE' => $configCache, 'APP_ROUTES_CACHE' => $routeCache,
        'VIEW_COMPILED_PATH' => dirname(__DIR__, 2).'/storage/framework/testing/disks/local/compiled-views',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
        'DB_DATABASE' => 'sbu_test', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => 'root',
        'DB_URL' => '', 'DB_SOCKET' => '',
        'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        'MAIL_MAILER' => 'array', 'FILESYSTEM_DISK' => 'local', 'MEDIA_DISK' => 'local',
    ] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(ConsoleKernel::class)->bootstrap();

    $check($app->environment('testing') && config('app.env') === 'testing', 'Laravel must boot in testing.');
    $check(config('database.default') === 'mysql', 'Default connection must be mysql.');
    $check(config('database.connections.mysql.database') === 'sbu_test', 'Configured database must be sbu_test.');
    $check(config('cache.default') === 'array' && config('session.driver') === 'array'
        && config('queue.default') === 'sync', 'Cache, session and queue must use isolated drivers.');
    $check(DB::connection()->getDatabaseName() === 'sbu_test', 'Resolved connection must target sbu_test.');
    $check(DB::selectOne('SELECT DATABASE() AS database_name')->database_name === 'sbu_test', 'Actual database must be sbu_test.');
    $check(DB::transactionLevel() === 0, 'Smoke requires its own outer transaction.');
    $nonTransactional = DB::selectOne(
        "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND engine <> 'InnoDB'"
    );
    $check((int) $nonTransactional->total === 0, 'All test tables must support transactional rollback.');
    $pass('Testing environment and effective sbu_test connection verified');

    Queue::fake();
    Mail::fake();
    Notification::fake();
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'media-library.disk_name' => 'local']);
    $compiled = Storage::disk('local')->path('compiled-views');
    File::ensureDirectoryExists($compiled);
    config(['view.compiled' => $compiled]);
    // Emit development asset tags only; no Vite server or build is required.
    Storage::disk('local')->put('vite.hot', 'http://localhost:5173');
    $app->make(Vite::class)->useHotFile(Storage::disk('local')->path('vite.hot'));
    $kernel = $app->make(HttpKernel::class);

    $get = static function (User $actor, string $url, int $status = 200, ?string $text = null) use ($app, $kernel, $check): void {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $session = $app->make('session')->driver();
        $session->flush();
        $session->start();
        Auth::guard('web')->login($actor);
        $request = Request::create($url, 'GET');
        $request->setLaravelSession($session);
        $response = $kernel->handle($request);
        try {
            $check($response->getStatusCode() === $status, 'Unexpected HTTP status for '.$request->getPathInfo().'.');
            $check(Auth::id() === $actor->id, 'HTTP request must retain the intended authenticated actor.');
            if ($status === 200) {
                $html = (string) $response->getContent();
                $check(str_contains(strtolower($html), '<html') && str_contains($html, 'wire:snapshot'), 'Expected a rendered Livewire page.');
                if ($text !== null) {
                    $check(str_contains($html, e($text)), 'Expected fixture content in rendered page.');
                }
            }
        } finally {
            $kernel->terminate($request, $response);
        }
    };
    $proof = static fn (): UploadedFile => UploadedFile::fake()->createWithContent(
        'manual-transfer.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"
    );
    $user = static function (string $role): User {
        $user = new User([
            'name' => 'Smoke '.ucfirst($role), 'email' => 'smoke-'.Str::uuid().'@example.test',
            'password' => Str::random(40), 'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role);

        return $user;
    };

    DB::beginTransaction();
    try {
        Date::setTestNow(now()->startOfSecond());
        foreach (['admin', 'instructor', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $admin = $user('admin');
        $instructor = $user('instructor');
        $student = $user('student');
        $buyer = $user('student');
        $participant = $user('student');
        $outsider = $user('student');
        $checkout = app(CheckoutService::class);
        $refunds = app(RefundService::class);
        $waitlist = app(BatchWaitlistService::class);

        $stage = 'membership invoice, renewal and snapshot refund';
        // Reuse the schema's unique level/period pair; any update also rolls back.
        $plan = MembershipPlan::firstOrCreate(['level' => 'pro', 'billing_period' => 'monthly'], ['price' => 100000, 'is_active' => true]);
        $plan->update(['price' => 100000, 'is_active' => true]);
        $initial = $checkout->create($student, $plan, method: 'manual_transfer');
        $check(! $student->fresh()->hasActiveMembership(), 'Pending invoice must not grant membership.');
        $initial->approveTransaction();
        $subscription = Subscription::where('user_id', $student->id)->sole();
        $subscriptions = app(SubscriptionService::class);
        $previousExpiry = $student->fresh()->membership_expires_at->copy();
        $check($subscriptions->invoiceRenewal($subscription) === null, 'Renewal must not be invoiced early.');
        Date::setTestNow($previousExpiry->copy()->addSecond());
        $renewal = $subscriptions->invoiceRenewal($subscription);
        $check($renewal !== null && $renewal->payment_status === 'pending'
            && $renewal->gateway_name === 'manual', 'Due renewal must create a manual pending invoice.');
        $check($subscriptions->invoiceRenewal($subscription)->id === $renewal->id, 'Due invoice must be repeatable.');
        $check(! $student->fresh()->hasActiveMembership(), 'Due invoice alone must not extend access.');
        $get($student, route('billing.invoices.show', $renewal), text: $renewal->invoice_code);
        $renewal->approveTransaction();
        $renewal->approveTransaction();
        $grant = MembershipGrant::where('transaction_id', $renewal->id)->sole();
        $check($grant->grant_order === 2 && $grant->previous_plan_id === $plan->id
            && $grant->previous_expires_at->equalTo($previousExpiry), 'Renewal must record the previous membership snapshot.');
        $check($student->fresh()->hasActiveMembership()
            && $subscription->fresh()->next_billing_at->equalTo($student->fresh()->membership_expires_at), 'Approval must extend and reschedule membership.');
        $reject(fn () => $refunds->refund($admin, $initial, 'Smoke older grant', $proof()), 'Older membership grant must not be refunded.');
        $refund = $refunds->refund($admin, $renewal, 'Smoke latest renewal', $proof());
        $check($student->fresh()->membership_plan_id === $plan->id
            && $student->fresh()->membership_expires_at->equalTo($previousExpiry)
            && $grant->fresh()->reversed_at !== null, 'Latest refund must restore the exact snapshot.');
        $check($subscription->fresh()->status === 'cancelled' && $subscription->fresh()->pending_transaction_id === null, 'Membership refund must stop renewal.');
        $check(Storage::disk('local')->exists($refund->proof_path), 'Refund evidence must exist on the fake private disk.');
        $pass('Membership due invoice -> approval -> latest snapshot refund');

        $stage = 'coupon reservation, redemption and refund';
        $program = Program::create([
            'title' => 'Phase 2 smoke program', 'type' => 'self_paced', 'price' => 100000,
            'is_active' => true, 'min_membership_level' => 'none',
        ]);
        $coupon = Coupon::create([
            'code' => 'SMOKE-'.Str::upper(Str::random(12)), 'discount_type' => 'percentage',
            'discount_value' => 25, 'max_uses' => 1, 'is_active' => true, 'valid_until' => now()->addDay(),
        ]);
        $discounted = $checkout->create($student->fresh(), $program, $coupon->code, 'manual_transfer');
        $redemption = CouponRedemption::where('transaction_id', $discounted->id)->sole();
        $check($discounted->original_amount === '100000.00' && $discounted->discount_amount === '25000.00'
            && $discounted->amount === '75000.00' && $redemption->status === 'reserved', 'Coupon must snapshot the discounted price and reserve quota.');
        $reject(fn () => $checkout->create($outsider, $program, $coupon->code, 'manual_transfer'), 'Reserved last-use coupon must reject another checkout.');
        $discounted->approveTransaction();
        $discounted->approveTransaction();
        $check($redemption->fresh()->status === 'redeemed' && $coupon->fresh()->used_count === 1
            && $student->fresh()->canAccessProgram($program), 'Payment must redeem once and enroll.');
        $couponRefund = $refunds->refund($admin, $discounted, 'Smoke discounted refund', $proof());
        $check(! $student->fresh()->canAccessProgram($program) && $coupon->fresh()->used_count === 1, 'Refund must revoke its access without restoring coupon quota.');
        $check($refunds->refund($admin, $discounted, 'Smoke repeat refund', $proof())->id === $couponRefund->id, 'Refund must be idempotent.');
        $pass('Coupon reservation -> single redemption -> refund retains used quota');

        $stage = 'corporate seats, payment-source ledger and refund';
        $corporateBatch = Batch::create([
            'program_id' => $program->id, 'batch_name' => 'Smoke corporate batch',
            'quota' => 2, 'price' => 100000, 'status' => 'open', 'min_membership_level' => 'none',
        ]);
        $corporate = app(CorporatePurchaseService::class);
        $purchase = $corporate->create($buyer, $corporateBatch, 'Smoke Company', 2);
        $purchase->transaction->approveTransaction();
        $check($waitlist->occupiedSeats($corporateBatch) === 2 && ! $buyer->fresh()->canAccessBatch($corporateBatch), 'Paid seats must reserve capacity without enrolling the buyer.');
        $check($corporate->allocate($buyer, $purchase, [$participant->id]) === 1, 'Owner must allocate one paid seat.');
        $seat = $purchase->seats()->sole();
        $source = EnrollmentPaymentSource::where('transaction_id', $purchase->transaction->id)
            ->where('enrollment_id', $seat->enrollment_id)->sole();
        $check($source->reversed_at === null && $participant->fresh()->canAccessBatch($corporateBatch)
            && $waitlist->occupiedSeats($corporateBatch) === 2, 'Allocation must create a source ledger and convert reserved capacity.');
        // A separate paid source for the same participant must survive corporate reversal.
        $independent = $checkout->create($participant, $corporateBatch, method: 'manual_transfer');
        $independent->approveTransaction();
        $refunds->refund($admin, $purchase->transaction, 'Smoke corporate refund', $proof());
        $check($purchase->fresh()->status === 'refunded' && $source->fresh()->reversed_at !== null
            && $participant->fresh()->canAccessBatch($corporateBatch)
            && $waitlist->occupiedSeats($corporateBatch) === 1, 'Corporate refund must release unused seats but preserve another paid source.');
        $reject(fn () => $corporate->allocate($buyer, $purchase, [$outsider->id]), 'Refunded purchase must reject further allocations.');
        $refunds->refund($admin, $independent, 'Smoke final source refund', $proof());
        $check(! $participant->fresh()->canAccessBatch($corporateBatch)
            && $waitlist->occupiedSeats($corporateBatch) === 0, 'Final source refund must close access and release capacity.');
        $pass('Corporate seats -> source ledger -> refund preserves only other paid access');

        $stage = 'waitlist promotion, paid enrollment and capacity';
        $course = Course::create([
            'title' => 'Smoke live and essay course', 'instructor_id' => $instructor->id,
            'delivery_mode' => 'self_paced', 'status' => 'published', 'published_at' => now(),
        ]);
        $program->courses()->attach($course->id, ['order_index' => 1]);
        $batch = Batch::create([
            'course_id' => $course->id, 'program_id' => $program->id, 'batch_name' => 'Smoke operations batch',
            'quota' => 1, 'price' => 100000, 'status' => 'open', 'min_membership_level' => 'none',
            'start_date' => ['date' => now()->addDay()->toDateString()],
            'end_date' => ['date' => now()->addDays(30)->toDateString()],
        ]);
        $batch->instructors()->create(['user_id' => $instructor->id, 'is_primary' => true]);
        $entry = $waitlist->join($student, $batch);
        $secondEntry = $waitlist->join($outsider, $batch);
        $reject(fn () => $waitlist->promote($admin, $secondEntry), 'Promotion must enforce FIFO.');
        $waitlist->promote($admin, $entry);
        $check(! $student->fresh()->canAccessBatch($batch) && $batch->enrollments()->count() === 0
            && $waitlist->occupiedSeats($batch) === 1, 'Promotion must reserve a seat, not enroll.');
        $blocked = $checkout->create($outsider, $batch, method: 'manual_transfer');
        $reject(fn () => $blocked->approveTransaction(), 'Unreserved payment must not exceed capacity.');
        $check($blocked->fresh()->payment_status === 'pending', 'Rejected fulfillment must roll back payment status.');
        $paid = $checkout->create($student->fresh(), $batch, method: 'manual_transfer');
        $paid->approveTransaction();
        $check($entry->fresh()->status === 'enrolled' && $student->fresh()->canAccessBatch($batch)
            && $batch->enrollments()->count() === 1 && $waitlist->occupiedSeats($batch) === 1, 'Paid reserved student must enroll without double-counting capacity.');
        $pass('Waitlist FIFO -> promotion -> paid enrollment enforces capacity');

        $stage = 'fixed instructor honor and private payout evidence';
        $payouts = app(PayoutService::class);
        $payout = $payouts->setHonor($admin, $batch, $instructor, '750000.00', 'Smoke fixed honor');
        $payout = $payouts->recordPayment($admin, $payout, [
            'transfer_reference' => 'SMOKE-MANUAL-TRANSFER', 'paid_at' => now()->toDateString(),
        ], $proof());
        $check($payout->status === 'paid' && $payout->amount === '750000.00' && $payout->audits()->count() === 2, 'Payout must record payment and both audits.');
        $check(Storage::disk('local')->exists($payout->evidence_path)
            && hash('sha256', Storage::disk('local')->get($payout->evidence_path)) === $payout->evidence_sha256, 'Private payout evidence hash must match.');
        $reject(fn () => $payouts->setHonor($admin, $batch, $instructor, '800000.00'), 'Paid honor must be immutable.');
        $pass('Fixed honor -> manual payout with private evidence and audit');

        $stage = 'course live attendance';
        $live = Material::create([
            'course_id' => $course->id, 'title' => 'Smoke live session', 'type' => 'live_session',
            'start_time' => now(), 'duration' => 60, 'status' => 'published',
        ]);
        $attendanceService = app(LiveAttendanceService::class);
        $attendance = $attendanceService->record($instructor, $batch, $live, $student, 'present', 'Smoke attendance');
        $attendanceService->record($instructor, $batch, $live, $student, 'excused', 'Smoke correction');
        $check($attendance->fresh()->status === 'excused' && $attendance->fresh()->recorded_by === $instructor->id
            && LiveAttendance::where('batch_id', $batch->id)->count() === 1, 'Related instructor must record and update attendance without duplicates.');
        $pass('Course live attendance recorded and corrected by its instructor');

        $stage = 'batch configuration clone';
        $clone = app(BatchCloneService::class)->clone($admin, $batch, 'Smoke cloned batch');
        $check($clone->id !== $batch->id && $clone->course_id === $course->id
            && $clone->program_id === $program->id && $clone->quota === $batch->quota
            && $clone->price === $batch->price && $clone->start_date === $batch->start_date
            && $clone->instructors()->where('user_id', $instructor->id)->count() === 1, 'Clone must copy configuration and instructor assignment.');
        $check($clone->enrollments()->count() === 0, 'Clone must not copy enrollments.');
        foreach ([Transaction::class, CorporatePurchase::class, BatchWaitlist::class, Payout::class, LiveAttendance::class] as $model) {
            $check($model::where('batch_id', $clone->id)->count() === 0, 'Clone must not copy operational records.');
        }
        $pass('Batch clone copies configuration, not operational data');

        $stage = 'essay submission, grading and lesson completion';
        $section = Section::create(['course_id' => $course->id, 'title' => 'Smoke section', 'order_index' => 1]);
        $quiz = Quiz::create([
            'course_id' => $course->id, 'title' => 'Smoke essay quiz', 'status' => 'active',
            'duration_minutes' => 10, 'passing_grade' => 70, 'max_attempts' => 1,
        ]);
        $lesson = Lesson::create([
            'section_id' => $section->id, 'quiz_id' => $quiz->id,
            'item_type' => 'quiz', 'sort_order' => 1, 'is_required' => true,
        ]);
        $questions = [];
        for ($index = 1; $index <= 2; $index++) {
            $questions[] = Question::create([
                'quiz_id' => $quiz->id, 'question_text' => 'Explain smoke scenario '.$index,
                'question_type' => 'essay', 'score_weight' => 4,
            ]);
        }
        $quizzes = app(QuizService::class);
        $attempt = $quizzes->startOrResume($student->fresh(), $quiz, $batch->id);
        $check($attempt !== null, 'Enrolled student must be able to start the quiz.');
        foreach ($questions as $question) {
            $quizzes->saveEssay($student, $attempt, $question->id, 'A standalone smoke answer.');
        }
        $attempt = app(QuizGrader::class)->grade($attempt);
        $completion = static fn (): bool => DB::table('lesson_completions')
            ->where('user_id', $student->id)->where('lesson_id', $lesson->id)->exists();
        $check($attempt->isPendingGrading() && $attempt->score === null && ! $completion(), 'Submitted essays must remain pending without completion.');
        $reject(fn () => app(CertificateService::class)->assertEligible($student, $course, $batch->id), 'Pending essays must block certificate eligibility.');
        $get($instructor, route('grading.essays', $attempt));
        $grading = app(EssayGradingService::class);
        $answers = $attempt->answers()->orderBy('id')->get();
        $attempt = $grading->grade($instructor, $answers[0], 4, 'Complete explanation.');
        $check($attempt->score === null && ! $completion(), 'Partial grading must not finalize or complete the quiz.');
        $attempt = $grading->grade($instructor, $answers[1], 2, 'Add more detail.');
        $check($attempt->score === 75.0 && $attempt->hasPassed() && $completion()
            && $quizzes->hasPassed($student, $quiz, $batch->id), 'Final manual grades must produce a passing score and lesson completion.');
        // Excused attendance is intentionally not a certificate gate.
        app(CertificateService::class)->assertEligible($student, $course, $batch->id);
        $pass('Essay submit -> pending -> all grades final -> lesson completion');

        $stage = 'authenticated in-process page rendering';
        foreach ([
            [$student, 'billing.index'], [$buyer, 'corporate.dashboard'],
            [$student, 'student.waitlist'], [$admin, 'admin.coupons'],
            [$admin, 'admin.refunds'], [$admin, 'admin.payouts'],
            [$admin, 'admin.batch-operations'], [$admin, 'admin.attendance'],
            [$instructor, 'instructor.payouts'], [$instructor, 'instructor.attendance'],
        ] as [$actor, $route]) {
            $get($actor, route($route));
        }
        $get($student, route('admin.refunds'), 403);
        $pass('Role-appropriate pages render; student refund page is forbidden');
        $check(DB::transactionLevel() === 1, 'Services must leave the outer rollback transaction intact.');
    } finally {
        // Also unwind nested savepoints if an unexpected exception interrupted a service.
        try {
            DB::rollBack(0);
        } finally {
            Date::setTestNow();
        }
    }

    $stage = 'rollback verification';
    $check(DB::transactionLevel() === 0, 'Outer transaction must be closed.');
    $check(! User::withTrashed()->whereKey($admin->id)->exists()
        && ! Program::withTrashed()->whereKey($program->id)->exists()
        && ! Transaction::whereKey($initial->id)->exists(), 'Smoke business fixtures must not persist.');
    $pass('All business data rolled back; only fake disk files/logs may remain');
} catch (Throwable $exception) {
    // Never print exception messages, SQL, config, response bodies, or credentials.
    fwrite(STDERR, 'FAIL '.$stage.': '.($assertionFailure ?? get_class($exception))
        .' ('.basename($exception->getFile()).':'.$exception->getLine().").\n");
    $exitCode = 1;
}

exit($exitCode);

<?php

namespace Tests\Feature;

use App\Livewire\Operations\AttendancePage;
use App\Livewire\Operations\BatchOperationsPage;
use App\Livewire\Operations\PayoutsPage;
use App\Livewire\Operations\WaitlistPage;
use App\Models\Batch;
use App\Models\BatchWaitlist;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LiveAttendance;
use App\Models\Material;
use App\Models\Payout;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BatchCloneService;
use App\Services\BatchWaitlistService;
use App\Services\LiveAttendanceService;
use App\Services\PayoutService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase2OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'instructor', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Also allow this isolated suite before the parent integrates the route file.
        if (! Route::has('admin.payouts')) {
            Route::middleware('web')->group(base_path('routes/phase2-operations.php'));
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function user(string $role = 'student'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function batch(array $attributes = []): Batch
    {
        return Batch::create(array_merge([
            'batch_name' => 'Operations batch', 'status' => 'open', 'quota' => 1,
            'start_date' => ['date' => '2026-12-01'], 'end_date' => ['date' => '2026-12-31'],
            'price' => 200000, 'min_membership_level' => 'none',
        ], $attributes));
    }

    private function assign(User $user, $entity): void
    {
        $entity->instructors()->create(['user_id' => $user->id, 'is_primary' => true]);
    }

    private function enroll(User $user, Batch $batch, string $status = 'active'): Enrollment
    {
        return Enrollment::create([
            'user_id' => $user->id, 'enrollable_type' => $batch->getMorphClass(),
            'enrollable_id' => $batch->id, 'status' => $status, 'enrolled_at' => now(),
        ]);
    }

    public function test_admin_sets_fixed_honor_and_records_private_transfer_evidence_and_audit(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin');
        $instructor = $this->user('instructor');
        $batch = $this->batch();
        $this->assign($instructor, $batch);
        $service = app(PayoutService::class);
        $payout = $service->setHonor($admin, $batch, $instructor, '750000.00', 'Honor tetap');
        $payout = $service->recordPayment($admin, $payout, [
            'transfer_reference' => 'BANK-123', 'paid_at' => now()->toDateString(), 'notes' => 'Transfer manual',
        ], UploadedFile::fake()->create('transfer.pdf', 10, 'application/pdf'));

        $this->assertSame('750000.00', $payout->amount);
        $this->assertSame('paid', $payout->status);
        $this->assertSame($admin->id, $payout->paid_by);
        Storage::disk('local')->assertExists($payout->evidence_path);
        $this->assertCount(2, $payout->audits);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_paid_honor_cannot_be_changed_or_paid_twice(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $batch = $this->batch();
        $this->assign($teacher, $batch);
        $service = app(PayoutService::class);
        $payout = $service->setHonor($admin, $batch, $teacher, '100000');
        $service->recordPayment($admin, $payout, ['transfer_reference' => 'REF', 'paid_at' => now()->toDateString()], UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
        try {
            $service->setHonor($admin, $batch, $teacher, '200000');
            $this->fail('Paid honor must be immutable.');
        } catch (ValidationException) {
            $this->assertSame('100000.00', $payout->fresh()->amount);
        }
        $this->expectException(ValidationException::class);
        $service->recordPayment($admin, $payout, ['transfer_reference' => 'REF2', 'paid_at' => now()->toDateString()], UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
    }

    public function test_payout_rejects_unrelated_instructor_and_invalid_evidence(): void
    {
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $batch = $this->batch();
        $service = app(PayoutService::class);
        try {
            $service->setHonor($admin, $batch, $teacher, '100000');
            $this->fail('Instructor must be related.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('payouts', 0);
        }
        $this->assign($teacher, $batch);
        $payout = $service->setHonor($admin, $batch, $teacher, '100000');
        $this->expectException(ValidationException::class);
        $service->recordPayment($admin, $payout, ['transfer_reference' => 'REF', 'paid_at' => now()->toDateString()], UploadedFile::fake()->create('proof.exe', 10, 'application/octet-stream'));
    }

    public function test_service_does_not_allow_instructor_to_set_honor(): void
    {
        $teacher = $this->user('instructor');
        $batch = $this->batch();
        $this->assign($teacher, $batch);
        $this->expectException(AuthorizationException::class);
        app(PayoutService::class)->setHonor($teacher, $batch, $teacher, '100');
    }

    public function test_instructor_only_sees_own_payout_and_cannot_download_other_evidence(): void
    {
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $other = $this->user('instructor');
        $batch = $this->batch();
        $this->assign($teacher, $batch);
        $this->assign($other, $batch);
        $service = app(PayoutService::class);
        $mine = $service->setHonor($admin, $batch, $teacher, '12345');
        $theirs = $service->setHonor($admin, $batch, $other, '98765');
        $this->assertSame([$mine->id], $service->visibleTo($teacher)->pluck('id')->all());
        $this->actingAs($teacher);
        Livewire::test(PayoutsPage::class)->assertSee('12.345')->assertDontSee('98.765');
        $this->expectException(AuthorizationException::class);
        $service->downloadEvidence($teacher, $theirs);
    }

    public function test_waitlist_is_unique_and_fifo_even_when_joined_at_matches(): void
    {
        $admin = $this->user('admin');
        $batch = $this->batch(['quota' => 2]);
        $service = app(BatchWaitlistService::class);
        $firstUser = $this->user();
        $first = $service->join($firstUser, $batch);
        $second = $service->join($this->user(), $batch);
        $this->assertSame($first->id, $service->join($firstUser, $batch)->id);
        $this->assertLessThan($second->queue_number, $first->queue_number);
        try {
            $service->promote($admin, $second);
            $this->fail('Cannot skip FIFO.');
        } catch (ValidationException) {
            $this->assertSame('waiting', $second->fresh()->status);
        }
        $service->promote($admin, $first);
        $this->assertSame('promoted', $first->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertFalse($firstUser->canAccessBatch($batch));
    }

    public function test_full_batch_rejects_promotion_and_promoted_reservations_consume_capacity(): void
    {
        $admin = $this->user('admin');
        $batch = $this->batch();
        $service = app(BatchWaitlistService::class);
        $enrollment = $this->enroll($this->user(), $batch);
        $entry = $service->join($this->user(), $batch);
        try {
            $service->promote($admin, $entry);
            $this->fail('Full batch cannot promote.');
        } catch (ValidationException) {
            $this->assertSame('waiting', $entry->fresh()->status);
        }
        $enrollment->update(['status' => 'dropped']);
        $service->promote($admin, $entry);
        $second = $service->join($this->user(), $batch);
        $this->expectException(ValidationException::class);
        $service->promote($admin, $second);
    }

    public function test_student_cannot_promote_and_rejoining_goes_to_back_of_queue(): void
    {
        $student = $this->user();
        $batch = $this->batch();
        $service = app(BatchWaitlistService::class);
        $first = $service->join($student, $batch);
        $second = $service->join($this->user(), $batch);
        $service->cancel($student, $first);
        $rejoined = $service->join($student, $batch);
        $this->assertGreaterThan($second->queue_number, $rejoined->queue_number);
        $this->assertDatabaseCount('batch_waitlists', 2);
        $this->expectException(AuthorizationException::class);
        $service->promote($student, $rejoined);
    }

    public function test_enrolled_users_and_closed_batches_cannot_join_waitlist(): void
    {
        $user = $this->user();
        $batch = $this->batch();
        $this->enroll($user, $batch);
        $service = app(BatchWaitlistService::class);
        try {
            $service->join($user, $batch);
            $this->fail('Enrolled user cannot join.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('batch_waitlists', 0);
        }
        $batch->update(['status' => 'closed']);
        $this->expectException(ValidationException::class);
        $service->join($this->user(), $batch);
    }

    public function test_capacity_guard_does_not_double_count_reserved_students_after_enrollment(): void
    {
        $admin = $this->user('admin');
        $user = $this->user();
        $batch = $this->batch();
        $service = app(BatchWaitlistService::class);
        $entry = $service->join($user, $batch);
        $service->promote($admin, $entry);
        $service->withSeat($batch, $user, fn () => $this->enroll($user, $batch));
        $this->assertSame(1, $service->occupiedSeats($batch));
        $this->expectException(ValidationException::class);
        $service->withSeat($batch, $this->user(), fn () => $this->fail('Full batch callback must not run.'));
    }

    public function test_clone_copies_configuration_and_instructor_relationships_but_not_operational_data(): void
    {
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $course = Course::create(['title' => 'Original course', 'instructor_id' => $teacher->id]);
        $program = Program::create(['title' => 'Original program', 'type' => 'cohort', 'price' => 100]);
        $program->courses()->attach($course->id, ['order_index' => 3]);
        $batch = $this->batch(['program_id' => $program->id, 'course_id' => $course->id, 'location' => 'Bandung']);
        $this->assign($teacher, $batch);
        $student = $this->user();
        $enrollment = $this->enroll($student, $batch);
        $enrollment->update(['progress_percentage' => 75]);
        Transaction::create([
            'invoice_code' => 'INV-CLONE-SOURCE', 'user_id' => $student->id, 'batch_id' => $batch->id,
            'amount' => 200000, 'payment_method' => 'manual_transfer', 'payment_status' => 'pending',
        ]);
        $material = Material::create(['course_id' => $course->id, 'title' => 'Original live session', 'type' => 'live_session']);
        app(LiveAttendanceService::class)->record($admin, $batch, $material, $student, 'present');
        app(PayoutService::class)->setHonor($admin, $batch, $teacher, '100000');
        app(BatchWaitlistService::class)->join($this->user(), $batch);
        $clone = app(BatchCloneService::class)->clone($admin, $batch, 'New cohort');
        $this->assertNotSame($batch->id, $clone->id);
        $this->assertSame($course->id, $clone->course_id);
        $this->assertSame($program->id, $clone->program_id);
        $this->assertSame('Bandung', $clone->location);
        $this->assertEquals($batch->start_date, $clone->start_date);
        $this->assertSame($teacher->id, $clone->instructors()->first()->user_id);
        $this->assertSame(0, $clone->enrollments()->count());
        $this->assertSame(0, Payout::where('batch_id', $clone->id)->count());
        $this->assertSame(0, BatchWaitlist::where('batch_id', $clone->id)->count());
        $this->assertSame(0, LiveAttendance::where('batch_id', $clone->id)->count());
        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseMissing('transactions', ['batch_id' => $clone->id]);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertEquals(75, $enrollment->fresh()->progress_percentage);
        $this->assertDatabaseCount('live_attendances', 1);
    }

    public function test_non_admin_cannot_clone_batch(): void
    {
        $this->expectException(AuthorizationException::class);
        app(BatchCloneService::class)->clone($this->user('instructor'), $this->batch(), 'Forbidden');
    }

    public function test_related_instructor_can_record_and_correct_attendance_without_certificate_effects(): void
    {
        $teacher = $this->user('instructor');
        $course = Course::create(['title' => 'Live course', 'instructor_id' => $teacher->id]);
        $batch = $this->batch(['course_id' => $course->id]);
        $student = $this->user();
        $this->enroll($student, $batch);
        $material = Material::create(['course_id' => $course->id, 'title' => 'Session', 'type' => 'live_session']);
        $service = app(LiveAttendanceService::class);
        $record = $service->record($teacher, $batch, $material, $student, 'present', 'Manual');
        $service->record($teacher, $batch, $material, $student, 'absent', 'Correction');
        $this->assertSame('absent', $record->fresh()->status);
        $this->assertSame($teacher->id, $record->fresh()->recorded_by);
        $this->assertDatabaseCount('live_attendances', 1);
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('lesson_completions', 0);
    }

    public function test_attendance_rejects_unrelated_instructor_material_and_non_enrolled_student(): void
    {
        $admin = $this->user('admin');
        $course = Course::create(['title' => 'Course']);
        $batch = $this->batch(['course_id' => $course->id]);
        $material = Material::create(['course_id' => $course->id, 'title' => 'Session', 'type' => 'live_session']);
        $student = $this->user();
        $this->enroll($student, $batch);
        $service = app(LiveAttendanceService::class);
        try {
            $service->record($this->user('instructor'), $batch, $material, $student, 'present');
            $this->fail('Unrelated instructor cannot record attendance.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('live_attendances', 0);
        }
        try {
            $service->record($admin, $batch, $material, $this->user(), 'present');
            $this->fail('Student must be enrolled.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('live_attendances', 0);
        }
        $material->update(['type' => 'article']);
        $this->expectException(ValidationException::class);
        $service->record($admin, $batch, $material, $student, 'present');
    }

    public function test_program_morph_assignment_authorizes_instructor_without_legacy_columns(): void
    {
        $teacher = $this->user('instructor');
        $admin = $this->user('admin');
        $program = Program::create(['title' => 'Cohort program', 'type' => 'cohort', 'price' => 100]);
        $course = Course::create(['title' => 'Program course']);
        $program->courses()->attach($course->id, ['order_index' => 0]);
        $batch = $this->batch(['program_id' => $program->id]);
        Instructor::create([
            'user_id' => $teacher->id, 'instructor_type' => $program->getMorphClass(),
            'instructor_id' => $program->id, 'is_primary' => true,
        ]);
        $payout = app(PayoutService::class)->setHonor($admin, $batch, $teacher, '100000');
        $this->assertSame($teacher->id, $payout->instructor_user_id);
        $student = $this->user();
        $this->enroll($student, $batch);
        $material = Material::create(['course_id' => $course->id, 'title' => 'Program session', 'type' => 'live_session']);
        $record = app(LiveAttendanceService::class)->record($teacher, $batch, $material, $student, 'present');
        $this->assertSame('present', $record->status);
        $this->actingAs($teacher);
        Livewire::test(AttendancePage::class)->set('batchId', $batch->id)->assertSee('Program session');
    }

    public function test_course_instructor_cannot_record_another_course_session_in_same_program(): void
    {
        $teacher = $this->user('instructor');
        $program = Program::create(['title' => 'Multi-course program', 'type' => 'cohort']);
        $mine = Course::create(['title' => 'My course', 'instructor_id' => $teacher->id]);
        $other = Course::create(['title' => 'Other course']);
        $program->courses()->attach([$mine->id, $other->id]);
        $batch = $this->batch(['program_id' => $program->id]);
        $student = $this->user();
        $this->enroll($student, $batch);
        $material = Material::create(['course_id' => $other->id, 'title' => 'Private other session', 'type' => 'live_session']);
        $this->assertSame(0, app(LiveAttendanceService::class)->sessions($teacher, $batch)->count());
        $this->expectException(AuthorizationException::class);
        app(LiveAttendanceService::class)->record($teacher, $batch, $material, $student, 'present');
    }

    public function test_payout_validation_rejects_negative_amount_future_date_and_oversized_evidence(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $batch = $this->batch();
        $this->assign($teacher, $batch);
        $service = app(PayoutService::class);
        foreach (['-1', '0', '1.001', '10000000000000'] as $amount) {
            try {
                $service->setHonor($admin, $batch, $teacher, $amount);
                $this->fail('Invalid amount must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('payouts', 0);
            }
        }
        $payout = $service->setHonor($admin, $batch, $teacher, '100');
        try {
            $service->recordPayment($admin, $payout, ['transfer_reference' => 'REF', 'paid_at' => now()->addDay()->toDateString()], UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
            $this->fail('Future transfer date must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('pending', $payout->fresh()->status);
        }
        $this->expectException(ValidationException::class);
        $service->recordPayment($admin, $payout, ['transfer_reference' => 'REF', 'paid_at' => now()->toDateString()], UploadedFile::fake()->create('proof.pdf', 10241, 'application/pdf'));
    }

    public function test_admin_can_update_pending_honor_with_audit_and_without_duplicate_payout(): void
    {
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $batch = $this->batch();
        $this->assign($teacher, $batch);
        $service = app(PayoutService::class);
        $payout = $service->setHonor($admin, $batch, $teacher, '100');
        $updated = $service->setHonor($admin, $batch, $teacher, '200');
        $this->assertSame($payout->id, $updated->id);
        $this->assertSame('200.00', $updated->amount);
        $this->assertDatabaseCount('payouts', 1);
        $this->assertDatabaseHas('payout_audits', ['payout_id' => $payout->id, 'action' => 'honor_updated']);
    }

    public function test_only_owner_or_admin_can_cancel_a_reservation_and_release_capacity(): void
    {
        $admin = $this->user('admin');
        $student = $this->user();
        $other = $this->user();
        $batch = $this->batch();
        $service = app(BatchWaitlistService::class);
        $entry = $service->join($student, $batch);
        $next = $service->join($other, $batch);
        $service->promote($admin, $entry);
        try {
            $service->cancel($other, $entry);
            $this->fail('Cannot cancel another student reservation.');
        } catch (AuthorizationException) {
            $this->assertSame('promoted', $entry->fresh()->status);
        }
        $service->cancel($student, $entry);
        $service->promote($admin, $next);
        $this->assertSame('promoted', $next->fresh()->status);
    }

    public function test_zero_quota_is_not_assumed_to_mean_unlimited_capacity(): void
    {
        $batch = $this->batch(['quota' => 0]);
        $entry = app(BatchWaitlistService::class)->join($this->user(), $batch);
        $this->expectException(ValidationException::class);
        app(BatchWaitlistService::class)->promote($this->user('admin'), $entry);
    }

    public function test_routes_require_verified_roles_and_private_evidence_ownership(): void
    {
        Storage::fake('local');
        $this->get(route('admin.payouts'))->assertRedirect(route('login'));
        $student = $this->user();
        $this->actingAs($student)->get(route('admin.batch-operations'))->assertForbidden();
        $this->get(route('instructor.attendance'))->assertForbidden();
        $student->forceFill(['email_verified_at' => null])->save();
        $this->get(route('student.waitlist'))->assertRedirect(route('verification.notice'));

        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $batch = $this->batch();
        $this->assign($teacher, $batch);
        $service = app(PayoutService::class);
        $payout = $service->setHonor($admin, $batch, $teacher, '100');
        $service->recordPayment($admin, $payout, ['transfer_reference' => 'REF', 'paid_at' => now()->toDateString()], UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
        $this->actingAs($teacher)->get(route('payouts.evidence', $payout))->assertOk()->assertDownload();
        $this->post(route('admin.payouts.payment', $payout))->assertForbidden();
        $this->actingAs($this->user('instructor'))->get(route('payouts.evidence', $payout))->assertForbidden();
        $this->actingAs($admin)->get(route('payouts.evidence', $payout))->assertOk();
    }

    public function test_multipart_payment_stays_on_private_disk_and_requires_valid_evidence(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['filesystems.default' => 'google']);
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $batch = $this->batch();
        $this->assign($teacher, $batch);
        $payout = app(PayoutService::class)->setHonor($admin, $batch, $teacher, '100000');
        $this->actingAs($admin);
        $data = ['transfer_reference' => 'PRIVATE-REF', 'paid_at' => now()->toDateString()];
        $this->post(route('admin.payouts.payment', $payout), $data)->assertSessionHasErrors('evidence');
        $this->post(route('admin.payouts.payment', $payout), array_merge($data, [
            'evidence' => UploadedFile::fake()->create('spoofed.pdf', 10, 'text/plain'),
        ]))->assertSessionHasErrors('evidence');
        $this->assertSame('pending', $payout->fresh()->status);
        $this->post(route('admin.payouts.payment', $payout), array_merge($data, [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ]))->assertRedirect(route('admin.payouts'))->assertSessionHasNoErrors();
        $payout->refresh();
        Storage::disk('local')->assertExists($payout->evidence_path);
        Storage::disk('public')->assertMissing($payout->evidence_path);
    }

    public function test_livewire_actions_use_services_for_honor_waitlist_clone_and_attendance(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        $course = Course::create(['title' => 'Operational course', 'instructor_id' => $teacher->id]);
        $batch = $this->batch(['course_id' => $course->id]);
        $student = $this->user();
        $this->actingAs($student);
        Livewire::test(WaitlistPage::class)->call('join', $batch->id)->assertHasNoErrors();
        $entry = BatchWaitlist::where('batch_id', $batch->id)->firstOrFail();
        $this->actingAs($admin);
        Livewire::test(BatchOperationsPage::class)->set('batchId', $batch->id)->call('promote', $entry->id)->assertHasNoErrors();
        Livewire::test(PayoutsPage::class)->set('batchId', $batch->id)->set('instructorId', $teacher->id)->set('amount', '500000')->call('setHonor')->assertHasNoErrors();
        $payout = Payout::firstOrFail();
        Livewire::test(PayoutsPage::class)->call('selectPayment', $payout->id)
            ->assertSee('multipart/form-data', false)->assertSee('500.000');
        $this->post(route('admin.payouts.payment', $payout), [
            'transfer_reference' => 'UI-REF', 'paid_at' => now()->toDateString(),
            'evidence' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('admin.payouts'))->assertSessionHasNoErrors();
        $this->assertSame('paid', $payout->fresh()->status);
        Livewire::test(BatchOperationsPage::class)->set('batchId', $batch->id)->set('cloneName', 'UI clone')->call('cloneBatch')->assertHasNoErrors();
        $this->assertDatabaseHas('batches', ['batch_name' => 'UI clone']);
        $this->enroll($student, $batch);
        $material = Material::create(['course_id' => $course->id, 'title' => 'UI live', 'type' => 'live_session']);
        $this->actingAs($teacher);
        Livewire::test(AttendancePage::class)->set('batchId', $batch->id)->set('materialId', $material->id)
            ->call('selectStudent', $student->id)->set('status', 'present')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('live_attendances', ['user_id' => $student->id, 'status' => 'present']);
    }

    public function test_operational_livewire_pages_render_and_enforce_roles(): void
    {
        $admin = $this->user('admin');
        $this->batch();
        $this->actingAs($admin);
        Livewire::test(PayoutsPage::class)->assertOk();
        Livewire::test(BatchOperationsPage::class)->assertOk()->assertSee('Operations batch');
        Livewire::test(AttendancePage::class)->assertOk();
        $this->actingAs($this->user());
        Livewire::test(WaitlistPage::class)->assertOk()->assertSee('Operations batch');
        Livewire::test(BatchOperationsPage::class)->assertForbidden();
        Livewire::test(PayoutsPage::class)->assertForbidden();
        Livewire::test(AttendancePage::class)->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\MembershipPlan;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase2RefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        foreach (['admin', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function user(string $role = 'student'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function invoice(User $user, array $data): Transaction
    {
        return Transaction::create(array_merge([
            'invoice_code' => 'REF-'.Str::uuid(), 'user_id' => $user->id,
            'amount' => 100000, 'payment_method' => 'manual_transfer', 'payment_status' => 'pending',
        ], $data));
    }

    private function refund(Transaction $invoice, ?User $actor = null): mixed
    {
        return app(RefundService::class)->refund($actor ?? $this->user('admin'), $invoice,
            'Pengembalian penuh disetujui', UploadedFile::fake()->image('transfer.png'));
    }

    public function test_full_refund_revokes_only_its_enrollment_and_records_private_evidence(): void
    {
        $user = $this->user();
        $program = Program::create(['title' => 'Refund Program', 'price' => 100000]);
        $invoice = $this->invoice($user, ['program_id' => $program->id]);
        $invoice->markAsPaid();
        $refund = $this->refund($invoice);
        $this->assertFalse($invoice->fresh()->isPaid());
        $this->assertFalse($user->canAccessProgram($program));
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'status' => 'dropped']);
        Storage::disk('local')->assertExists($refund->proof_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'transaction_refunded']);
        $invoice->markAsPaid();
        $this->assertFalse($user->canAccessProgram($program));
    }

    public function test_refund_is_idempotent_and_does_not_revoke_another_paid_source(): void
    {
        $user = $this->user();
        $program = Program::create(['title' => 'Two purchases', 'price' => 100000]);
        $first = $this->invoice($user, ['program_id' => $program->id]);
        $second = $this->invoice($user, ['program_id' => $program->id]);
        $first->markAsPaid();
        $second->markAsPaid();
        $refund = $this->refund($first);
        $this->assertTrue($user->canAccessProgram($program));
        $this->assertSame($refund->id, $this->refund($first)->id);
        $this->assertDatabaseCount('refunds', 1);
        $this->refund($second);
        $this->assertFalse($user->canAccessProgram($program));
    }

    public function test_refund_preserves_preexisting_untracked_enrollment(): void
    {
        $user = $this->user();
        $program = Program::create(['title' => 'Legacy access', 'price' => 100000]);
        Enrollment::create(['user_id' => $user->id, 'enrollable_type' => Program::class,
            'enrollable_id' => $program->id, 'status' => 'active', 'enrolled_at' => now()]);
        $invoice = $this->invoice($user, ['program_id' => $program->id]);
        $invoice->markAsPaid();
        $this->refund($invoice);
        $this->assertTrue($user->canAccessProgram($program));
    }

    public function test_membership_refund_restores_previous_snapshot_not_other_renewals(): void
    {
        $user = $this->user();
        $plan = MembershipPlan::create(['level' => 'pro', 'billing_period' => 'monthly', 'price' => 100000]);
        $first = $this->invoice($user, ['membership_plan_id' => $plan->id]);
        $first->markAsPaid();
        $originalExpiry = $user->fresh()->membership_expires_at;
        $second = $this->invoice($user, ['membership_plan_id' => $plan->id]);
        $second->markAsPaid();
        try {
            $this->refund($first);
            $this->fail('An older membership grant must not erase a later renewal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->refund($second);
        $this->assertTrue($user->fresh()->membership_expires_at->equalTo($originalExpiry));
        $this->refund($first);
        $this->assertNull($user->fresh()->membership_plan_id);
    }

    public function test_students_cannot_refund_and_unpaid_invoices_cannot_be_refunded(): void
    {
        $user = $this->user();
        $invoice = $this->invoice($user, []);
        try {
            $this->refund($invoice, $user);
            $this->fail('Student must not refund.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('refunds', 0);
        }
        $this->expectException(ValidationException::class);
        $this->refund($invoice);
    }

    public function test_expired_legacy_access_is_not_preserved_forever_after_repurchase_and_refund(): void
    {
        $user = $this->user();
        $program = Program::create(['title' => 'Expiring legacy', 'price' => 100000]);
        Enrollment::create(['user_id' => $user->id, 'enrollable_type' => Program::class,
            'enrollable_id' => $program->id, 'status' => 'active', 'enrolled_at' => now(),
            'access_expires_at' => now()->addDay()]);
        $first = $this->invoice($user, ['program_id' => $program->id]);
        $first->markAsPaid();
        $this->refund($first);
        $this->travel(2)->days();
        $second = $this->invoice($user, ['program_id' => $program->id]);
        $second->markAsPaid();
        $this->assertTrue($user->canAccessProgram($program));
        $this->refund($second);
        $this->assertFalse($user->canAccessProgram($program));
    }

    public function test_replayed_payment_event_after_refund_cannot_restore_access(): void
    {
        $user = $this->user();
        $program = Program::create(['title' => 'Replay refund', 'price' => 100000]);
        $invoice = $this->invoice($user, ['program_id' => $program->id]);
        $invoice->markAsPaid();
        $event = new \App\Events\TransactionPaid(\App\Data\TransactionPaidData::fromTransaction($invoice));
        $this->refund($invoice);
        app(\App\Listeners\FulfillEnrollment::class)->handle($event);
        $this->assertFalse($user->canAccessProgram($program));
    }

    public function test_full_corporate_refund_revokes_allocated_seats_and_releases_unused_capacity(): void
    {
        $buyer = $this->user();
        $student = $this->user();
        $program = Program::create(['title' => 'Corporate refund', 'price' => 100000, 'is_active' => true]);
        $batch = \App\Models\Batch::create(['program_id' => $program->id, 'batch_name' => 'Refund batch', 'quota' => 2, 'status' => 'open']);
        $purchase = app(\App\Services\CorporatePurchaseService::class)->create($buyer, $batch, 'PT Refund', 2);
        $purchase->transaction->approveTransaction();
        app(\App\Services\CorporatePurchaseService::class)->allocate($buyer, $purchase, [$student->id]);
        $this->assertTrue($student->canAccessBatch($batch));
        $this->refund($purchase->transaction);
        $this->assertFalse($student->canAccessBatch($batch));
        $this->assertSame('refunded', $purchase->fresh()->status);
        $this->assertSame(0, app(\App\Services\BatchWaitlistService::class)->occupiedSeats($batch));
    }

    public function test_refunded_invoices_are_excluded_from_revenue_and_labeled_in_history(): void
    {
        $student = $this->user();
        $admin = $this->user('admin');
        Role::findOrCreate('instructor', 'web');
        $program = Program::create(['title' => 'Refund reporting', 'price' => 100000]);
        $invoice = $this->invoice($student, ['program_id' => $program->id]);
        $invoice->markAsPaid();
        $this->refund($invoice, $admin);
        \Livewire\Volt\Volt::actingAs($admin)->test('admin.dashboard')->assertViewHas('totalRevenue', fn ($amount) => (float) $amount === 0.0)->assertSee('Direfund');
        \Livewire\Volt\Volt::actingAs($admin)->test('admin.transactions')->assertViewHas('kpiRevenueToday', fn ($amount) => (float) $amount === 0.0)->assertSee('Direfund');
        \Livewire\Volt\Volt::actingAs($student)->test('profile.transaction-history')->assertSee('Direfund');
    }

    public function test_refund_page_and_evidence_are_admin_only(): void
    {
        $student = $this->user();
        $this->actingAs($student)->get(route('admin.refunds'))->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('admin.refunds'))->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Events\TransactionPaid;
use App\Mail\PaymentReceipt;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\MembershipPlan;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Budi Test',
            'email' => 'budi@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
    }

    private function makeProgram(): Program
    {
        return Program::create([
            'title' => 'Bootcamp Data Science',
            'price' => 2_500_000,
        ]);
    }

    private function makeBatch(Program $program): Batch
    {
        return Batch::create([
            'program_id' => $program->id,
            'batch_name' => 'Batch 12',
            'quota' => 30,
        ]);
    }

    private function makeTransaction(User $user, array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'invoice_code' => 'INV-'.uniqid(),
            'user_id' => $user->id,
            'amount' => 2_500_000,
            'payment_method' => 'gateway_va',
            'gateway_name' => 'midtrans',
            'payment_status' => 'pending',
        ], $attributes));
    }

    public function test_it_dispatches_transaction_paid_event_once(): void
    {
        Event::fake([TransactionPaid::class]);

        $user = $this->makeUser();
        $program = $this->makeProgram();
        $transaction = $this->makeTransaction($user, ['program_id' => $program->id]);

        $transaction->markAsPaid(['transaction_id' => 'MID-123']);

        Event::assertDispatchedTimes(TransactionPaid::class, 1);
        $this->assertSame('paid', $transaction->fresh()->payment_status);
    }

    public function test_paying_a_program_creates_an_active_enrollment(): void
    {
        $user = $this->makeUser();
        $program = $this->makeProgram();
        $transaction = $this->makeTransaction($user, ['program_id' => $program->id]);

        $transaction->markAsPaid(['transaction_id' => 'MID-123']);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $user->id,
            'enrollable_type' => Program::class,
            'enrollable_id' => $program->id,
            'status' => 'active',
        ]);
    }

    public function test_paying_a_batch_creates_a_batch_enrollment(): void
    {
        $user = $this->makeUser();
        $program = $this->makeProgram();
        $batch = $this->makeBatch($program);
        $transaction = $this->makeTransaction($user, ['batch_id' => $batch->id]);

        $transaction->markAsPaid();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $user->id,
            'enrollable_type' => Batch::class,
            'enrollable_id' => $batch->id,
            'status' => 'active',
        ]);
    }

    public function test_a_dropped_enrollment_is_reactivated_when_the_student_buys_the_program_again(): void
    {
        $user = $this->makeUser();
        $program = $this->makeProgram();

        $dropped = Enrollment::create([
            'user_id' => $user->id,
            'enrollable_type' => Program::class,
            'enrollable_id' => $program->id,
            'status' => 'dropped',
            'enrolled_at' => now()->subYear(),
        ]);

        $transaction = $this->makeTransaction($user, ['program_id' => $program->id]);

        $transaction->markAsPaid();

        $this->assertSame(1, Enrollment::where('user_id', $user->id)->count());
        $this->assertSame('active', $dropped->fresh()->status);
    }

    public function test_paying_a_membership_plan_activates_it_on_the_user(): void
    {
        $user = $this->makeUser();
        $plan = MembershipPlan::create([
            'level' => 'pro',
            'billing_period' => 'monthly',
            'price' => 49_000,
        ]);

        $transaction = $this->makeTransaction($user, [
            'membership_plan_id' => $plan->id,
            'amount' => 49_000,
        ]);

        $transaction->markAsPaid();

        $user->refresh();
        $this->assertSame($plan->id, $user->membership_plan_id);
        $this->assertTrue($user->hasActiveMembership());
    }

    public function test_membership_renewal_extends_from_the_current_expiry_date(): void
    {
        $user = $this->makeUser();
        $plan = MembershipPlan::create([
            'level' => 'standard',
            'billing_period' => 'monthly',
            'price' => 19_000,
        ]);

        $currentExpiry = now()->addDays(10);
        $user->update([
            'membership_plan_id' => $plan->id,
            'membership_expires_at' => $currentExpiry,
        ]);

        $transaction = $this->makeTransaction($user, [
            'membership_plan_id' => $plan->id,
            'amount' => 19_000,
        ]);

        $transaction->markAsPaid();

        $expected = $currentExpiry->copy()->addMonth();
        $this->assertTrue(
            $user->fresh()->membership_expires_at->isSameDay($expected),
            'Renewal must stack on top of the remaining days, not reset them.'
        );
    }

    public function test_lifetime_plan_has_no_expiry_date(): void
    {
        $user = $this->makeUser();
        $plan = MembershipPlan::create([
            'level' => 'starter',
            'billing_period' => 'lifetime',
            'price' => 0,
        ]);

        $this->makeTransaction($user, ['membership_plan_id' => $plan->id, 'amount' => 0])
            ->markAsPaid();

        $user->refresh();
        $this->assertSame($plan->id, $user->membership_plan_id);
        $this->assertNull($user->membership_expires_at);
        $this->assertTrue($user->hasActiveMembership());
    }

    public function test_paying_a_program_sends_a_receipt_and_writes_an_activity_log(): void
    {
        Mail::fake();

        $user = $this->makeUser();
        $program = $this->makeProgram();
        $transaction = $this->makeTransaction($user, ['program_id' => $program->id]);

        $transaction->markAsPaid();

        Mail::assertSent(PaymentReceipt::class, function (PaymentReceipt $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'payment_paid',
        ]);
    }

    public function test_admin_approval_uses_the_same_fulfillment_path(): void
    {
        $user = $this->makeUser();
        $program = $this->makeProgram();
        $transaction = $this->makeTransaction($user, [
            'program_id' => $program->id,
            'payment_method' => 'manual_transfer',
            'gateway_name' => null,
        ]);

        $transaction->approveTransaction();

        $this->assertSame('verified_by_admin', $transaction->fresh()->payment_status);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $user->id,
            'enrollable_id' => $program->id,
            'status' => 'active',
        ]);
    }

    public function test_marking_a_paid_transaction_twice_does_not_duplicate_enrollments_or_events(): void
    {
        Mail::fake();

        $user = $this->makeUser();
        $program = $this->makeProgram();
        $transaction = $this->makeTransaction($user, ['program_id' => $program->id]);

        $transaction->markAsPaid();
        $transaction->markAsPaid();

        $this->assertSame(1, Enrollment::where('user_id', $user->id)->count());
        $this->assertSame(1, ActivityLog::where('action', 'payment_paid')->count());
        Mail::assertSent(PaymentReceipt::class, 1);
    }
}

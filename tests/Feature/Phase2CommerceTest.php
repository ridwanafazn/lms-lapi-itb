<?php

namespace Tests\Feature;

use App\Data\TransactionPaidData;
use App\Events\TransactionPaid;
use App\Listeners\FulfillEnrollment;
use App\Livewire\Commerce\CorporateDashboard;
use App\Livewire\Commerce\CouponManager;
use App\Models\Batch;
use App\Models\CorporatePurchase;
use App\Models\CorporateSeat;
use App\Models\Coupon;
use App\Models\MembershipPlan;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CorporatePurchaseService;
use App\Services\CouponService;
use App\Services\EnrollmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase2CommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        foreach (['student', 'admin', 'instructor'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function user(?string $role = 'student'): User
    {
        $user = User::create([
            'name' => 'Commerce User', 'email' => Str::uuid().'@example.test',
            'password' => 'password', 'email_verified_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function program(array $attributes = []): Program
    {
        return Program::create(array_merge([
            'title' => 'Commerce Program', 'price' => 100000, 'type' => 'self_paced', 'is_active' => true,
        ], $attributes));
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE-'.Str::upper(Str::random(8)), 'discount_type' => 'percentage',
            'discount_value' => 25, 'max_uses' => 5, 'is_active' => true,
        ], $attributes));
    }

    private function invoice(User $user, Program|Batch|MembershipPlan $item, ?Coupon $coupon = null): Transaction
    {
        return app(CheckoutService::class)->create($user, $item, $coupon?->code, 'manual_transfer');
    }

    private function purchase(User $buyer, int $seats = 2, Program|Batch|null $item = null): CorporatePurchase
    {
        return app(CorporatePurchaseService::class)->create($buyer, $item ?? $this->program(), 'PT Commerce', $seats);
    }

    private function batch(int $quota = 5): Batch
    {
        return Batch::create([
            'program_id' => $this->program()->id, 'batch_name' => 'Corporate Batch',
            'price' => 150000, 'quota' => $quota, 'status' => 'open',
        ]);
    }

    public function test_percentage_coupon_snapshots_original_discount_and_invoice_amount(): void
    {
        $coupon = $this->coupon();
        $transaction = $this->invoice($this->user(), $this->program(), $coupon);
        $this->assertEquals(100000, $transaction->original_amount);
        $this->assertEquals(25000, $transaction->discount_amount);
        $this->assertEquals(75000, $transaction->amount);
        $this->assertDatabaseHas('coupon_redemptions', ['transaction_id' => $transaction->id, 'status' => 'reserved']);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_fixed_discount_is_capped_and_zero_invoice_is_fulfilled_once(): void
    {
        $coupon = $this->coupon(['discount_type' => 'fixed_amount', 'discount_value' => 200000]);
        $buyer = $this->user();
        $transaction = $this->invoice($buyer, $this->program(), $coupon);
        $this->assertTrue($transaction->isPaid());
        $this->assertEquals(0, $transaction->amount);
        $this->assertEquals(100000, $transaction->discount_amount);
        $transaction->markAsPaid();
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_manual_approval_redeems_the_reserved_coupon_idempotently(): void
    {
        $coupon = $this->coupon();
        $transaction = $this->invoice($this->user(), $this->program(), $coupon);
        $transaction->approveTransaction();
        $transaction->approveTransaction();
        app(FulfillEnrollment::class)->handle(new TransactionPaid(TransactionPaidData::fromTransaction($transaction->fresh())));
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertDatabaseHas('coupon_redemptions', ['transaction_id' => $transaction->id, 'status' => 'redeemed']);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_gateway_payment_redeems_coupon_without_revalidating_later_expiry(): void
    {
        $coupon = $this->coupon(['valid_until' => now()->addHour()]);
        $transaction = $this->invoice($this->user(), $this->program(), $coupon);
        $coupon->update(['is_active' => false, 'valid_until' => now()->subMinute()]);
        $transaction->markAsPaid(['transaction_id' => 'MID-COMMERCE']);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertTrue($transaction->fresh()->isPaid());
    }

    public function test_a_pending_reservation_prevents_another_invoice_from_using_the_last_coupon(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        $this->invoice($this->user(), $this->program(), $coupon);
        try {
            $this->invoice($this->user(), $this->program(), $coupon);
            $this->fail('Expected coupon quota rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('couponCode', $exception->errors());
        }
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('coupon_redemptions', 1);
    }

    public function test_failed_invoice_releases_coupon_quota_for_a_new_invoice(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        $first = $this->invoice($this->user(), $this->program(), $coupon);
        $first->rejectTransaction();
        $second = $this->invoice($this->user(), $this->program(), $coupon);
        $this->assertSame($coupon->id, $second->coupon_id);
        $this->assertDatabaseHas('coupon_redemptions', ['transaction_id' => $first->id, 'status' => 'released']);
        $second->markAsPaid();
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_released_coupon_cannot_be_redeemed_by_a_late_payment(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        $first = $this->invoice($this->user(), $this->program(), $coupon);
        $first->markAsExpired();
        app(CouponService::class)->release($first);
        $second = $this->invoice($this->user(), $this->program(), $coupon);
        $second->markAsPaid();
        $this->expectException(ValidationException::class);
        app(CouponService::class)->redeem($first);
    }

    public function test_coupon_cannot_be_stacked_or_replaced_on_an_invoice(): void
    {
        $transaction = $this->invoice($this->user(), $this->program(), $this->coupon());
        $this->expectException(ValidationException::class);
        app(CouponService::class)->reserve($transaction, $this->coupon()->code);
    }

    public function test_repeating_same_coupon_reservation_does_not_duplicate_it(): void
    {
        $coupon = $this->coupon();
        $transaction = $this->invoice($this->user(), $this->program(), $coupon);
        app(CouponService::class)->reserve($transaction, strtolower($coupon->code));
        $this->assertDatabaseCount('coupon_redemptions', 1);
        $this->assertEquals(75000, $transaction->fresh()->amount);
    }

    public function test_unknown_inactive_expired_and_invalid_percentage_coupons_are_rejected(): void
    {
        $buyer = $this->user();
        $program = $this->program();
        $coupons = [
            'UNKNOWN', $this->coupon(['is_active' => false])->code,
            $this->coupon(['valid_until' => now()->subSecond()])->code,
            $this->coupon(['discount_value' => 101])->code,
        ];
        foreach ($coupons as $code) {
            try {
                app(CheckoutService::class)->create($buyer, $program, $code, 'manual_transfer');
                $this->fail('Expected invalid coupon rejection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('couponCode', $exception->errors());
            }
        }
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_membership_checkout_uses_discount_and_activates_only_after_payment(): void
    {
        $buyer = $this->user();
        $plan = MembershipPlan::create(['level' => 'standard', 'billing_period' => 'monthly', 'price' => 100000, 'is_active' => true]);
        $transaction = $this->invoice($buyer, $plan, $this->coupon());
        $this->assertNull($buyer->fresh()->membership_plan_id);
        $transaction->markAsPaid();
        $this->assertSame($plan->id, $buyer->fresh()->membership_plan_id);
        $this->assertEquals(75000, $transaction->amount);
    }

    public function test_gateway_receives_the_discounted_persisted_invoice(): void
    {
        $mock = \Mockery::mock(CheckoutService::class, [app(CouponService::class)])->makePartial();
        $mock->shouldReceive('startGateway')->once()->withArgs(function (Transaction $transaction) {
            $this->assertEquals(75000, $transaction->amount);
            $this->assertNotNull($transaction->coupon_id);
            $transaction->update(['snap_token' => 'TEST-TOKEN']);

            return true;
        });
        $this->app->instance(CheckoutService::class, $mock);
        $transaction = app(CheckoutService::class)->create($this->user(), $this->program(), $this->coupon()->code);
        $this->assertSame('TEST-TOKEN', $transaction->snap_token);
    }

    public function test_corporate_invoice_charges_every_seat_without_enrolling_the_buyer(): void
    {
        $buyer = $this->user(null);
        $purchase = $this->purchase($buyer, 3);
        $this->assertEquals(300000, $purchase->total_amount);
        $this->assertEquals(300000, $purchase->transaction->amount);
        $this->assertSame($purchase->id, $purchase->transaction->corporate_purchase_id);
        $this->assertDatabaseCount('transactions', 1);
        $purchase->transaction->approveTransaction();
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('corporate_seats', 0);
    }

    public function test_checkout_rejects_corporate_coupons_before_creating_an_invoice(): void
    {
        $buyer = $this->user(null);
        $program = $this->program();
        $purchase = CorporatePurchase::create([
            'company_name' => 'PT Coupon Guard', 'buyer_user_id' => $buyer->id,
            'program_id' => $program->id, 'seat_count' => 2,
            'unit_amount' => 100000, 'total_amount' => 200000, 'status' => 'pending',
        ]);
        $coupon = $this->coupon();
        try {
            app(CheckoutService::class)->create($buyer, $program, $coupon->code, 'manual_transfer', $purchase);
            $this->fail('Expected corporate coupons to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('couponCode', $exception->errors());
        }
        $this->assertEquals(200000, $purchase->fresh()->total_amount);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('coupon_redemptions', 0);
    }

    public function test_coupon_reservation_cannot_discount_an_existing_corporate_invoice(): void
    {
        $purchase = $this->purchase($this->user(null), 2);
        $transaction = $purchase->transaction;
        $coupon = $this->coupon();
        try {
            app(CouponService::class)->reserve($transaction, $coupon->code);
            $this->fail('Expected direct corporate coupon reservation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('couponCode', $exception->errors());
        }
        $transaction->refresh();
        $this->assertEquals($purchase->total_amount, $transaction->amount);
        $this->assertEquals(200000, $transaction->amount);
        $this->assertEquals(0, $transaction->discount_amount);
        $this->assertNull($transaction->coupon_id);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('coupon_redemptions', 0);
    }

    public function test_corporate_gateway_uses_one_invoice_for_all_paid_seats(): void
    {
        $mock = \Mockery::mock(CheckoutService::class, [app(CouponService::class)])->makePartial();
        $mock->shouldReceive('startGateway')->once()->andReturnUsing(function (Transaction $transaction) {
            $this->assertEquals(400000, $transaction->amount);
            $this->assertNotNull($transaction->corporate_purchase_id);
            $transaction->update(['snap_token' => 'CORPORATE-TEST']);
        });
        $this->app->instance(CheckoutService::class, $mock);
        $buyer = $this->user(null);
        $purchase = app(CorporatePurchaseService::class)->create($buyer, $this->program(), 'PT Gateway', 4, 'gateway_va');
        $this->assertSame('CORPORATE-TEST', $purchase->transaction->snap_token);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_corporate_payment_amount_mismatch_rolls_back_payment(): void
    {
        $buyer = $this->user();
        $purchase = $this->purchase($buyer);
        $transaction = $purchase->transaction;
        $transaction->update(['amount' => 1]);
        try {
            $transaction->markAsPaid();
            $this->fail('Expected full invoice amount validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase', $exception->errors());
        }
        $this->assertSame('pending', $transaction->fresh()->payment_status);
        $this->assertSame('pending', $purchase->fresh()->status);
    }

    public function test_corporate_seat_count_must_be_positive(): void
    {
        $this->expectException(ValidationException::class);
        $this->purchase($this->user(), 0);
    }

    public function test_failed_batch_fulfillment_rolls_back_payment_and_coupon_redemption(): void
    {
        $batch = $this->batch(1);
        $this->invoice($this->user(), $batch)->markAsPaid();
        $coupon = $this->coupon();
        $transaction = $this->invoice($this->user(), $batch, $coupon);
        try {
            $transaction->markAsPaid();
            $this->fail('Expected full batch rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('waitlist', $exception->errors());
        }
        $this->assertSame('pending', $transaction->fresh()->payment_status);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseHas('coupon_redemptions', ['transaction_id' => $transaction->id, 'status' => 'reserved']);
    }

    public function test_admin_validates_percentage_bounds_and_unique_normalized_codes(): void
    {
        $coupon = $this->coupon(['code' => 'UNIQUE25']);
        $component = Livewire::actingAs($this->user('admin'))->test(CouponManager::class);
        $component->set('code', 'OTHER')->set('discountValue', '101')->call('save')->assertHasErrors('discountValue');
        $component->set('code', strtolower($coupon->code))->set('discountValue', '25')->call('save')->assertHasErrors('code');
    }

    public function test_corporate_allocation_before_payment_is_rejected(): void
    {
        $buyer = $this->user();
        $purchase = $this->purchase($buyer);
        $this->expectException(ValidationException::class);
        app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$this->user()->id]);
    }

    public function test_only_the_owning_buyer_can_allocate_even_if_another_actor_is_admin(): void
    {
        $buyer = $this->user();
        $purchase = $this->purchase($buyer);
        $purchase->transaction->approveTransaction();
        $this->expectException(AuthorizationException::class);
        app(CorporatePurchaseService::class)->allocate($this->user('admin'), $purchase, [$this->user()->id]);
    }

    public function test_paid_seats_are_allocated_idempotently_and_cannot_be_replaced(): void
    {
        $buyer = $this->user(null);
        $purchase = $this->purchase($buyer, 2);
        $purchase->transaction->approveTransaction();
        $first = $this->user();
        $second = $this->user();
        $service = app(CorporatePurchaseService::class);
        $this->assertSame(1, $service->allocate($buyer, $purchase, [$first->id]));
        $this->assertSame(0, $service->allocate($buyer, $purchase, [$first->id]));
        $this->assertSame(1, $service->allocate($buyer, $purchase, [$first->id, $second->id]));
        $this->assertSame('fulfilled', $purchase->fresh()->status);
        try {
            $service->allocate($buyer, $purchase, [$this->user()->id]);
            $this->fail('Expected paid seat limit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('participants', $exception->errors());
        }
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $purchase->seats()->pluck('user_id')->all());
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $buyer->id]);
    }

    public function test_overallocation_rolls_back_the_entire_participant_request(): void
    {
        $buyer = $this->user();
        $purchase = $this->purchase($buyer, 1);
        $purchase->transaction->approveTransaction();
        try {
            app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$this->user()->id, $this->user()->id]);
            $this->fail('Expected seat limit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('participants', $exception->errors());
        }
        $this->assertDatabaseCount('corporate_seats', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_duplicate_participant_ids_are_rejected(): void
    {
        $buyer = $this->user();
        $purchase = $this->purchase($buyer);
        $purchase->transaction->markAsPaid();
        $student = $this->user();
        $this->expectException(ValidationException::class);
        app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$student->id, $student->id]);
    }

    public function test_non_student_deleted_and_missing_accounts_cannot_receive_a_seat(): void
    {
        $buyer = $this->user();
        $purchase = $this->purchase($buyer);
        $purchase->transaction->markAsPaid();
        $deleted = $this->user();
        $deleted->delete();
        $inactive = $this->user();
        $inactive->update(['is_active' => false]);
        foreach ([$this->user('instructor')->id, $inactive->id, $deleted->id, (string) Str::uuid()] as $id) {
            try {
                app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$id]);
                $this->fail('Expected participant rejection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('participants', $exception->errors());
            }
        }
        $this->assertDatabaseCount('corporate_seats', 0);
    }

    public function test_an_existing_enrollment_cannot_consume_another_corporate_seat(): void
    {
        $buyer = $this->user();
        $program = $this->program();
        $student = $this->user();
        $this->invoice($student, $program)->markAsPaid();
        $purchase = $this->purchase($buyer, 1, $program);
        $purchase->transaction->markAsPaid();
        $this->expectException(ValidationException::class);
        app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$student->id]);
    }

    public function test_corporate_batch_seat_creates_batch_access_not_buyer_or_program_access(): void
    {
        $buyer = $this->user(null);
        $batch = $this->batch();
        $purchase = $this->purchase($buyer, 1, $batch);
        $purchase->transaction->approveTransaction();
        $student = $this->user();
        app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$student->id]);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'enrollable_type' => Batch::class, 'enrollable_id' => $batch->id, 'status' => 'active']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $buyer->id]);
        $this->assertSame(1, CorporateSeat::count());
        $this->assertTrue($student->fresh()->canAccessBatch($batch));
    }

    public function test_batch_capacity_reduction_rolls_back_all_seats_and_enrollments(): void
    {
        $buyer = $this->user();
        $batch = $this->batch(2);
        $purchase = $this->purchase($buyer, 2, $batch);
        $purchase->transaction->markAsPaid();
        $batch->update(['quota' => 1]);
        try {
            app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$this->user()->id, $this->user()->id]);
            $this->fail('Expected batch capacity failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('participants', $exception->errors());
        }
        $this->assertDatabaseCount('corporate_seats', 0);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertSame('paid', $purchase->fresh()->status);
    }

    public function test_corporate_batch_payment_requires_capacity_for_every_purchased_seat(): void
    {
        foreach (['markAsPaid', 'approveTransaction'] as $paymentAction) {
            $purchase = $this->purchase($this->user(null), 2, $this->batch(1));
            try {
                $purchase->transaction->{$paymentAction}();
                $this->fail('Expected capacity rejection before corporate payment fulfillment.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('purchase', $exception->errors());
            }
            $this->assertSame('pending', $purchase->transaction->fresh()->payment_status);
            $this->assertSame('pending', $purchase->fresh()->status);
            $this->assertNull($purchase->fresh()->paid_at);
        }
        $this->assertDatabaseCount('corporate_seats', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_paid_unallocated_corporate_seats_are_reserved_and_allocation_conserves_capacity(): void
    {
        $buyer = $this->user(null);
        $batch = $this->batch(2);
        $purchase = $this->purchase($buyer, 2, $batch);
        $capacity = app(\App\Services\BatchWaitlistService::class);
        $this->assertSame(0, $capacity->occupiedSeats($batch));
        $purchase->transaction->approveTransaction();
        $this->assertSame(2, $capacity->occupiedSeats($batch));
        $first = $this->user();
        $second = $this->user();
        app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$first->id]);
        $this->assertSame(2, $capacity->occupiedSeats($batch));
        app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$second->id]);
        $this->assertSame(2, $capacity->occupiedSeats($batch));
        $this->assertSame('fulfilled', $purchase->fresh()->status);
        $this->assertSame(2, $purchase->seats()->count());
    }

    public function test_paid_unallocated_corporate_seats_block_individual_batch_payment(): void
    {
        $batch = $this->batch(1);
        $purchase = $this->purchase($this->user(null), 1, $batch);
        $purchase->transaction->approveTransaction();
        $transaction = $this->invoice($this->user(), $batch);
        try {
            $transaction->markAsPaid();
            $this->fail('Expected paid corporate reservation to hold batch capacity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('waitlist', $exception->errors());
        }
        $this->assertSame('pending', $transaction->fresh()->payment_status);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_paid_unallocated_corporate_seats_block_another_corporate_payment(): void
    {
        $batch = $this->batch(2);
        $first = $this->purchase($this->user(null), 2, $batch);
        $second = $this->purchase($this->user(null), 1, $batch);
        $first->transaction->markAsPaid();
        try {
            $second->transaction->approveTransaction();
            $this->fail('Expected paid corporate reservations to prevent overselling.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase', $exception->errors());
        }
        $this->assertSame('pending', $second->transaction->fresh()->payment_status);
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_corporate_allocation_passes_the_paid_source_invoice_to_enrollment_service(): void
    {
        $buyer = $this->user(null);
        $purchase = $this->purchase($buyer, 1);
        $purchase->transaction->approveTransaction();
        $student = $this->user();
        $mock = \Mockery::mock(EnrollmentService::class)->makePartial();
        $mock->shouldReceive('enroll')->once()->withArgs(function ($user, $target, $batchId, $source) use ($student, $purchase) {
            return $user->id === $student->id && $target->id === $purchase->program_id
                && $batchId === null && $source instanceof Transaction
                && $source->id === $purchase->transaction->id && $source->isPaid();
        })->passthru();
        $this->app->instance(EnrollmentService::class, $mock);
        app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$student->id]);
        $this->assertDatabaseHas('corporate_seats', ['corporate_purchase_id' => $purchase->id, 'user_id' => $student->id]);
    }

    public function test_refunded_corporate_invoice_blocks_allocation_even_with_stale_paid_purchase(): void
    {
        $buyer = $this->user(null);
        $purchase = $this->purchase($buyer, 1);
        $purchase->transaction->approveTransaction();
        $purchase = $purchase->fresh(['transaction']);
        $purchase->transaction->forceFill(['refunded_at' => now()])->save();
        try {
            app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$this->user()->id]);
            $this->fail('Expected refunded invoice to block corporate allocation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('participants', $exception->errors());
        }
        $this->assertDatabaseCount('corporate_seats', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_refunded_invoice_blocks_remaining_allocations_and_idempotent_retries(): void
    {
        $buyer = $this->user(null);
        $purchase = $this->purchase($buyer, 2);
        $purchase->transaction->approveTransaction();
        $first = $this->user();
        app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$first->id]);
        $purchase->transaction->forceFill(['refunded_at' => now()])->save();
        foreach ([$first->id, $this->user()->id] as $id) {
            try {
                app(CorporatePurchaseService::class)->allocate($buyer, $purchase, [$id]);
                $this->fail('Expected refund to invalidate all allocation requests.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('participants', $exception->errors());
            }
        }
        $this->assertSame(1, $purchase->seats()->count());
    }

    public function test_hr_dashboard_rejects_allocations_for_refunded_invoices(): void
    {
        $buyer = $this->user(null);
        $purchase = $this->purchase($buyer, 1);
        $purchase->transaction->approveTransaction();
        $purchase->transaction->forceFill(['refunded_at' => now()])->save();
        Livewire::actingAs($buyer)->test(CorporateDashboard::class)
            ->set('purchaseId', $purchase->id)->set('participantEmail', $this->user()->email)
            ->call('allocate')->assertHasErrors('participants')->assertSee('Invoice telah direfund');
        $this->assertDatabaseCount('corporate_seats', 0);
    }

    public function test_refunded_unallocated_corporate_invoice_releases_batch_reservations(): void
    {
        $batch = $this->batch(2);
        $purchase = $this->purchase($this->user(null), 2, $batch);
        $purchase->transaction->markAsPaid();
        $capacity = app(\App\Services\BatchWaitlistService::class);
        $this->assertSame(2, $capacity->occupiedSeats($batch));
        $purchase->transaction->forceFill(['refunded_at' => now()])->save();
        $this->assertSame(0, $capacity->occupiedSeats($batch));
    }

    public function test_refunded_corporate_invoice_cannot_be_refulfilled_by_a_replayed_payment_event(): void
    {
        $purchase = $this->purchase($this->user(null), 1);
        $purchase->transaction->markAsPaid();
        $purchase->transaction->forceFill(['refunded_at' => now()])->save();
        $this->expectException(ValidationException::class);
        app(CorporatePurchaseService::class)->markPaid($purchase->transaction);
    }

    public function test_corporate_payment_listener_is_idempotent_and_never_enrolls_buyer(): void
    {
        $buyer = $this->user();
        $purchase = $this->purchase($buyer);
        $purchase->transaction->markAsPaid();
        $event = new TransactionPaid(TransactionPaidData::fromTransaction($purchase->transaction->fresh()));
        app(FulfillEnrollment::class)->handle($event);
        app(FulfillEnrollment::class)->handle($event);
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_free_and_closed_products_cannot_create_paid_corporate_invoices(): void
    {
        $buyer = $this->user();
        $closed = $this->batch();
        $closed->update(['status' => 'closed']);
        foreach ([$this->program(['price' => 0]), $closed] as $item) {
            try {
                $this->purchase($buyer, 1, $item);
                $this->fail('Expected unavailable corporate product.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('checkout', $exception->errors());
            }
        }
        $this->assertDatabaseCount('corporate_purchases', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_hr_routes_require_authentication_verification_and_allow_non_student_buyers(): void
    {
        $this->get('/corporate')->assertRedirect(route('login'));
        $buyer = $this->user(null);
        $buyer->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($buyer)->get('/corporate')->assertRedirect(route('verification.notice'));
        $buyer->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($buyer)->get('/corporate')->assertOk()->assertSee('Pembelian Korporat');
    }

    public function test_hr_dashboard_does_not_expose_other_buyers_invoices_or_participants(): void
    {
        $owner = $this->user(null);
        $purchase = $this->purchase($owner);
        $this->actingAs($this->user(null))->get('/corporate')->assertOk()->assertDontSee($purchase->transaction->invoice_code);
        Livewire::test(CorporateDashboard::class)->set('purchaseId', $purchase->id)
            ->set('participantEmail', $this->user()->email)->call('allocate')->assertNotFound();
    }

    public function test_hr_dashboard_creates_invoice_then_allocates_existing_student_after_approval(): void
    {
        $buyer = $this->user(null);
        $program = $this->program();
        $component = Livewire::actingAs($buyer)->test(CorporateDashboard::class)
            ->set('companyName', 'PT HR Test')->set('product', 'program:'.$program->id)
            ->set('seatCount', 2)->call('createPurchase')->assertHasNoErrors();
        $purchase = CorporatePurchase::where('buyer_user_id', $buyer->id)->firstOrFail();
        $purchase->transaction->approveTransaction();
        $student = $this->user();
        $component->set('purchaseId', $purchase->id)->set('participantEmail', $student->email)->call('allocate')->assertHasNoErrors();
        $this->assertDatabaseHas('corporate_seats', ['corporate_purchase_id' => $purchase->id, 'user_id' => $student->id]);
    }

    public function test_coupon_admin_crud_and_historical_deactivation(): void
    {
        $admin = $this->user('admin');
        $component = Livewire::actingAs($admin)->test(CouponManager::class)
            ->set('code', '  admin25  ')->set('discountValue', '25')->set('maxUses', '3')->call('save')->assertHasNoErrors();
        $coupon = Coupon::where('code', 'ADMIN25')->firstOrFail();
        $component->call('edit', $coupon->id)->set('discountValue', '30')->call('save')->assertHasNoErrors();
        $this->assertEquals(30, $coupon->fresh()->discount_value);
        $this->invoice($this->user(), $this->program(), $coupon);
        $component->call('delete', $coupon->id)->assertHasNoErrors();
        $this->assertFalse($coupon->fresh()->is_active);
        $unused = $this->coupon();
        $component->call('delete', $unused->id);
        $this->assertDatabaseMissing('coupons', ['id' => $unused->id]);
    }

    public function test_non_admin_cannot_access_coupon_manager_or_mutate_coupons(): void
    {
        $student = $this->user();
        $this->actingAs($student)->get('/admin/coupons')->assertForbidden();
        Livewire::actingAs($student)->test(CouponManager::class)->assertForbidden();
    }

    public function test_admin_cannot_reduce_coupon_quota_below_existing_reservations(): void
    {
        $coupon = $this->coupon(['max_uses' => 3]);
        $this->invoice($this->user(), $this->program(), $coupon);
        $this->invoice($this->user(), $this->program(), $coupon);
        Livewire::actingAs($this->user('admin'))->test(CouponManager::class)->call('edit', $coupon->id)
            ->set('maxUses', '1')->call('save')->assertHasErrors('maxUses');
        $this->assertSame(3, $coupon->fresh()->max_uses);
    }

    public function test_program_detail_checkout_creates_pending_invoice_instead_of_free_access(): void
    {
        $student = $this->user();
        $program = $this->program();
        $coupon = $this->coupon();
        Volt::actingAs($student)->test('student.program-detail', ['program' => $program])
            ->set('paymentMethod', 'manual_transfer')->set('couponCode', $coupon->code)->call('enroll')->assertHasNoErrors();
        $transaction = Transaction::where('user_id', $student->id)->firstOrFail();
        $this->assertEquals(75000, $transaction->amount);
        $this->assertFalse($transaction->isPaid());
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_membership_component_applies_coupon_before_opening_gateway(): void
    {
        MembershipPlan::create(['level' => 'standard', 'billing_period' => 'monthly', 'price' => 100000, 'is_active' => true]);
        $mock = \Mockery::mock(CheckoutService::class, [app(CouponService::class)])->makePartial();
        $mock->shouldReceive('startGateway')->once()->andReturnUsing(function (Transaction $transaction) {
            $this->assertEquals(75000, $transaction->amount);
            $transaction->update(['snap_token' => 'MEMBERSHIP-TEST']);
        });
        $this->app->instance(CheckoutService::class, $mock);
        Volt::actingAs($this->user())->test('membership')->set('couponCode', $this->coupon()->code)
            ->call('checkout', 'standard', 'monthly')->assertHasNoErrors()->assertDispatched('snap-token-received', token: 'MEMBERSHIP-TEST');
    }
}

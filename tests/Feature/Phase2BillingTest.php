<?php

namespace Tests\Feature;

use App\Data\TransactionPaidData;
use App\Events\TransactionPaid;
use App\Listeners\ActivateMembership;
use App\Livewire\Billing\Invoices;
use App\Models\MembershipGrant;
use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase2BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        $this->travelTo(now()->startOfSecond());
    }

    public function test_paid_membership_records_a_snapshot_and_schedules_at_membership_expiry(): void
    {
        [$user, $plan, $transaction, $subscription] = $this->paidMembership();
        $grant = MembershipGrant::where('transaction_id', $transaction->id)->sole();

        $this->assertSame($plan->id, $grant->membership_plan_id);
        $this->assertNull($grant->previous_plan_id);
        $this->assertNull($grant->previous_expires_at);
        $this->assertSame(1, $grant->grant_order);
        $this->assertTrue($grant->granted_from->equalTo(now()));
        $this->assertTrue($grant->granted_until->equalTo($user->fresh()->membership_expires_at));
        $this->assertTrue($subscription->next_billing_at->equalTo($grant->granted_until));
        $this->assertSame($transaction->id, $subscription->source_transaction_id);
        $this->assertSame(0, Transaction::where('subscription_id', $subscription->id)->count());
    }

    public function test_due_invoice_is_manual_single_outstanding_and_does_not_grant_access(): void
    {
        [$user, $plan, , $subscription] = $this->paidMembership();
        $this->travelTo($subscription->next_billing_at);
        $service = app(SubscriptionService::class);
        $invoice = $service->invoiceRenewal($subscription);
        $duplicate = $service->invoiceRenewal($subscription);

        $this->assertSame($invoice->id, $duplicate->id);
        $this->assertSame('pending', $invoice->payment_status);
        $this->assertSame('manual_transfer', $invoice->payment_method);
        $this->assertSame('manual', $invoice->gateway_name);
        $this->assertEquals($plan->price, $invoice->amount);
        $this->assertFalse($user->fresh()->hasActiveMembership());
        $this->assertSame(1, MembershipGrant::count());
        $this->assertTrue($subscription->fresh()->next_billing_at->equalTo($user->fresh()->membership_expires_at));
    }

    public function test_renewal_only_extends_membership_after_manual_approval(): void
    {
        [$user, , , $subscription] = $this->paidMembership();
        $previousExpiry = $user->fresh()->membership_expires_at;
        $this->travelTo($subscription->next_billing_at->copy()->addDays(3));
        $invoice = app(SubscriptionService::class)->invoiceRenewal($subscription);
        $invoice->approveTransaction();
        $grant = MembershipGrant::where('transaction_id', $invoice->id)->sole();

        $this->assertSame(2, $grant->grant_order);
        $this->assertSame($subscription->membership_plan_id, $grant->previous_plan_id);
        $this->assertTrue($grant->previous_expires_at->equalTo($previousExpiry));
        $this->assertTrue($user->fresh()->membership_expires_at->equalTo(now()->addMonth()));
        $this->assertTrue($subscription->fresh()->next_billing_at->equalTo($user->fresh()->membership_expires_at));
        $this->assertNull($subscription->fresh()->pending_transaction_id);
    }

    public function test_replayed_listener_does_not_duplicate_or_reapply_a_grant(): void
    {
        [$user, , $transaction] = $this->paidMembership();
        $expiry = $user->fresh()->membership_expires_at;
        app(ActivateMembership::class)->handle(new TransactionPaid(TransactionPaidData::fromTransaction($transaction->fresh())));

        $this->assertSame(1, MembershipGrant::count());
        $this->assertTrue($user->fresh()->membership_expires_at->equalTo($expiry));
    }

    public function test_early_purchase_stacks_expiry_and_captures_previous_snapshot(): void
    {
        [$user, $plan, , $subscription] = $this->paidMembership();
        $expiry = $user->fresh()->membership_expires_at;
        $purchase = $this->transaction($user, $plan);
        $purchase->markAsPaid();
        $grant = MembershipGrant::where('transaction_id', $purchase->id)->sole();

        $this->assertTrue($grant->previous_expires_at->equalTo($expiry));
        $this->assertTrue($grant->granted_from->equalTo($expiry));
        $this->assertTrue($grant->granted_until->equalTo($expiry->copy()->addMonth()));
        $this->assertTrue($subscription->fresh()->next_billing_at->equalTo($grant->granted_until));
    }

    public function test_lifetime_membership_never_generates_a_renewal_invoice(): void
    {
        [$user, , , $subscription] = $this->paidMembership('lifetime');
        $this->travel(2)->years();

        $this->assertNull($user->fresh()->membership_expires_at);
        $this->assertNull($subscription->next_billing_at);
        $this->assertFalse($subscription->isDueForRenewal());
        $this->assertNull(app(SubscriptionService::class)->invoiceRenewal($subscription));
    }

    public function test_inactive_plan_is_not_invoiced(): void
    {
        [, $plan, , $subscription] = $this->paidMembership();
        $plan->update(['is_active' => false]);
        $this->travelTo($subscription->next_billing_at);

        $this->assertNull(app(SubscriptionService::class)->invoiceRenewal($subscription));
        $this->assertSame(1, Transaction::count());
    }

    public function test_cancellation_preserves_access_but_voids_the_outstanding_invoice(): void
    {
        [$user, , , $subscription] = $this->paidMembership();
        $expiry = $user->fresh()->membership_expires_at;
        $this->travelTo($subscription->next_billing_at);
        $service = app(SubscriptionService::class);
        $invoice = $service->invoiceRenewal($subscription);
        $service->cancel($user, $subscription);

        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame('failed', $invoice->fresh()->payment_status);
        $this->assertTrue($user->fresh()->membership_expires_at->equalTo($expiry));
        $this->assertNull($service->invoiceRenewal($subscription));
    }

    public function test_reactivation_does_not_grant_access_or_make_a_cancelled_invoice_payable_again(): void
    {
        [$user, , , $subscription] = $this->paidMembership();
        $this->travelTo($subscription->next_billing_at);
        $service = app(SubscriptionService::class);
        $old = $service->invoiceRenewal($subscription);
        $service->cancel($user, $subscription);
        $service->reactivate($user, $subscription);
        $new = $service->invoiceRenewal($subscription);

        $this->assertNotSame($old->id, $new->id);
        $this->assertFalse($user->fresh()->hasActiveMembership());
        // Even a stale payment event cannot revive an invalidated invoice.
        $old->forceFill(['payment_status' => 'paid'])->save();
        $this->expectException(ValidationException::class);
        $service->activateFromPaidTransaction($old);
    }

    public function test_separate_checkout_invalidates_an_outstanding_renewal(): void
    {
        [$user, $plan, , $subscription] = $this->paidMembership();
        $this->travelTo($subscription->next_billing_at);
        $invoice = app(SubscriptionService::class)->invoiceRenewal($subscription);
        $this->transaction($user, $plan)->markAsPaid();

        $this->assertSame('failed', $invoice->fresh()->payment_status);
        $this->assertNull($subscription->fresh()->pending_transaction_id);
        $this->assertSame(2, MembershipGrant::count());
    }

    public function test_scheduler_command_is_repeatable_and_does_not_charge(): void
    {
        [, , , $subscription] = $this->paidMembership();
        $this->travelTo($subscription->next_billing_at);
        $this->artisan('subscriptions:invoice')->assertSuccessful();
        $this->artisan('subscriptions:invoice')->assertSuccessful();

        $this->assertSame(1, Transaction::where('subscription_id', $subscription->id)->where('payment_status', 'pending')->count());
        $this->assertSame(1, MembershipGrant::count());
    }

    public function test_billing_page_only_displays_the_authenticated_users_invoices(): void
    {
        [$owner, , $own] = $this->paidMembership();
        [, , $other] = $this->paidMembership();
        $this->actingAs($owner)->get(route('billing.index'))
            ->assertOk()->assertSee($own->invoice_code)->assertDontSee($other->invoice_code);
        $this->actingAs($owner)->get(route('billing.invoices.show', $other))->assertNotFound();
    }

    public function test_another_users_subscription_cannot_be_cancelled(): void
    {
        [$owner] = $this->paidMembership();
        [, , , $other] = $this->paidMembership();

        Livewire::actingAs($owner)->test(Invoices::class)
            ->call('cancelSubscription', $other->id)->assertNotFound();
        $this->assertSame('active', $other->fresh()->status);
    }

    public function test_proof_is_private_and_upload_does_not_mark_invoice_paid(): void
    {
        Storage::fake('local');
        [$owner, , , $subscription] = $this->paidMembership();
        $this->travelTo($subscription->next_billing_at);
        $invoice = app(SubscriptionService::class)->invoiceRenewal($subscription);
        Livewire::actingAs($owner)->test(Invoices::class, ['invoice' => $invoice])
            ->set('proof', UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'))
            ->call('uploadProof')->assertHasNoErrors();
        $invoice->refresh();

        $this->assertSame('pending', $invoice->payment_status);
        $this->assertStringStartsWith('billing/proofs/'.$owner->id.'/', $invoice->proof_file_url);
        Storage::disk('local')->assertExists($invoice->proof_file_url);
        $this->actingAs($owner)->get(route('billing.invoices.proof', $invoice))->assertOk();
        [$other] = $this->paidMembership();
        $this->actingAs($other)->get(route('billing.invoices.proof', $invoice))->assertNotFound();
    }

    public function test_admin_can_download_private_billing_proof_for_verification(): void
    {
        Storage::fake('local');
        [$owner, , $invoice] = $this->paidMembership();
        $path = 'billing/proofs/'.$owner->id.'/test-proof.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');
        $invoice->update(['proof_file_url' => $path]);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('billing.invoices.proof', $invoice))->assertOk();
    }

    public function test_proof_download_rejects_legacy_urls_and_paths_outside_billing(): void
    {
        Storage::fake('local');
        [$owner, , $invoice] = $this->paidMembership();
        Storage::disk('local')->put('private-other.pdf', 'test');
        $invoice->update(['proof_file_url' => 'billing/proofs/'.$owner->id.'/../../private-other.pdf']);

        $this->actingAs($owner)->get(route('billing.invoices.proof', $invoice))->assertNotFound();
        $invoice->update(['proof_file_url' => 'https://external.example/proof.pdf']);
        $this->get(route('billing.invoices.proof', $invoice))->assertNotFound();
    }

    public function test_not_yet_due_subscription_does_not_generate_an_invoice(): void
    {
        [, , , $subscription] = $this->paidMembership();

        $this->assertNull(app(SubscriptionService::class)->invoiceRenewal($subscription));
        $this->assertSame(1, Transaction::count());
    }

    public function test_invoice_uses_current_plan_price_and_does_not_advance_billing_date(): void
    {
        [$owner, $plan, , $subscription] = $this->paidMembership();
        $plan->update(['price' => 59000]);
        $this->travelTo($subscription->next_billing_at);
        $invoice = app(SubscriptionService::class)->invoiceRenewal($subscription);

        $this->assertEquals(59000, $invoice->amount);
        $this->assertTrue($subscription->fresh()->next_billing_at->equalTo($owner->fresh()->membership_expires_at));
    }

    public function test_bank_instructions_are_configuration_only_and_missing_configuration_is_explicit(): void
    {
        [$user, , $transaction] = $this->paidMembership();
        config(['billing.manual_transfer' => ['bank_name' => '', 'account_number' => '', 'account_name' => '', 'instructions' => '']]);
        $this->actingAs($user)->get(route('billing.invoices.show', $transaction))
            ->assertOk()->assertSee('Instruksi transfer belum dikonfigurasi');
        config(['billing.manual_transfer' => ['bank_name' => 'Bank Config Only', 'account_number' => 'TEST-ONLY', 'account_name' => 'Test Account', 'instructions' => 'Use invoice code']]);
        $this->get(route('billing.invoices.show', $transaction))
            ->assertOk()->assertSee('Bank Config Only')->assertSee('TEST-ONLY');
    }

    public function test_direct_proof_post_uses_private_local_storage_even_when_default_disk_is_google(): void
    {
        Storage::fake('local');
        Storage::fake('google');
        config(['filesystems.default' => 'google']);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $invoice = $this->pendingManualInvoice($owner);

        $this->actingAs($owner)->get(route('billing.invoices.show', $invoice))
            ->assertOk()->assertSee('enctype="multipart/form-data"', false)
            ->assertSee(route('billing.invoices.proof.store', $invoice), false)
            ->assertDontSee('wire:model="proof"', false)->assertDontSee('wire:submit="uploadProof"', false);
        $this->post(route('billing.invoices.proof.store', $invoice), [
            'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('billing.invoices.show', $invoice))->assertSessionHas('billingStatus');

        $invoice->refresh();
        $this->assertSame('pending', $invoice->payment_status);
        $this->assertNull($invoice->paid_at);
        $this->assertNull($invoice->membership_plan_id);
        $this->assertStringStartsWith('billing/proofs/'.$owner->id.'/', $invoice->proof_file_url);
        Storage::disk('local')->assertExists($invoice->proof_file_url);
        $this->assertSame([], Storage::disk('google')->allFiles());
        $this->actingAs($owner)->get(route('billing.invoices.proof', $invoice))->assertOk();
    }

    public function test_direct_proof_post_rejects_another_users_invoice(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $invoice = $this->pendingManualInvoice($owner);

        $this->actingAs($other)->post(route('billing.invoices.proof.store', $invoice), [
            'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertNotFound();

        $this->assertNull($invoice->fresh()->proof_file_url);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_admin_can_download_but_cannot_upload_proof_for_another_users_invoice(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
        $invoice = $this->pendingManualInvoice($owner);
        $path = 'billing/proofs/'.$owner->id.'/existing-proof.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');
        $invoice->update(['proof_file_url' => $path]);

        $this->actingAs($admin)->get(route('billing.invoices.proof', $invoice))->assertOk();
        $this->post(route('billing.invoices.proof.store', $invoice), [
            'proof' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
        ])->assertNotFound();

        $this->assertSame($path, $invoice->fresh()->proof_file_url);
        $this->assertSame([$path], Storage::disk('local')->allFiles());
    }

    public function test_direct_proof_post_requires_authentication_and_verified_email(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => null]);
        $invoice = $this->pendingManualInvoice($owner);
        $this->post(route('billing.invoices.proof.store', $invoice), [
            'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('login'));
        $this->actingAs($owner)->post(route('billing.invoices.proof.store', $invoice), [
            'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('verification.notice'));

        $this->assertNull($invoice->fresh()->proof_file_url);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_direct_proof_post_validates_required_type_and_size(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $invoice = $this->pendingManualInvoice($owner);
        $url = route('billing.invoices.show', $invoice);
        $this->actingAs($owner)->from($url);
        foreach ([null, UploadedFile::fake()->create('proof.txt', 10, 'text/plain'),
            UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')] as $file) {
            $this->post(route('billing.invoices.proof.store', $invoice), ['proof' => $file])
                ->assertRedirect($url)->assertSessionHasErrors('proof');
        }

        $this->assertNull($invoice->fresh()->proof_file_url);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_direct_proof_post_rejects_non_pending_non_manual_and_refunded_invoices(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($owner);
        foreach ([['payment_status' => 'paid'], ['payment_status' => 'failed'],
            ['payment_status' => 'verified_by_admin'], ['payment_method' => 'gateway_va'],
            ['refunded_at' => now()]] as $attributes) {
            $invoice = $this->pendingManualInvoice($owner);
            $invoice->forceFill($attributes)->save();
            $this->from(route('billing.invoices.show', $invoice))
                ->post(route('billing.invoices.proof.store', $invoice), [
                    'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
                ])->assertSessionHasErrors('proof');
            $this->assertNull($invoice->fresh()->proof_file_url);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_direct_proof_post_preserves_pending_subscription_checks(): void
    {
        Storage::fake('local');
        [$owner, , , $subscription] = $this->paidMembership();
        $this->travelTo($subscription->next_billing_at);
        $service = app(SubscriptionService::class);
        $invoice = $service->invoiceRenewal($subscription);
        $url = route('billing.invoices.show', $invoice);
        $this->actingAs($owner)->from($url)->post(route('billing.invoices.proof.store', $invoice), [
            'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertRedirect($url)->assertSessionHasNoErrors();
        $path = $invoice->fresh()->proof_file_url;
        $this->assertSame('pending', $invoice->fresh()->payment_status);
        $this->assertSame(1, MembershipGrant::count());

        $subscription->refresh()->update(['pending_transaction_id' => null]);
        $this->post(route('billing.invoices.proof.store', $invoice), [
            'proof' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('proof');
        $this->assertSame($path, $invoice->fresh()->proof_file_url);
        $subscription->update(['pending_transaction_id' => $invoice->id, 'status' => 'cancelled']);
        $this->post(route('billing.invoices.proof.store', $invoice), [
            'proof' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('proof');
        $this->assertSame($path, $invoice->fresh()->proof_file_url);
        $this->assertSame([$path], Storage::disk('local')->allFiles());
    }

    private function pendingManualInvoice(User $owner): Transaction
    {
        return Transaction::create([
            'invoice_code' => 'TEST-'.Str::uuid(), 'user_id' => $owner->id,
            'amount' => 49000, 'payment_method' => 'manual_transfer',
            'gateway_name' => 'manual', 'payment_status' => 'pending',
        ]);
    }

    private function paidMembership(string $period = 'monthly'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = MembershipPlan::firstOrCreate(
            ['level' => 'pro', 'billing_period' => $period],
            ['price' => 49000, 'is_active' => true],
        );
        $transaction = $this->transaction($user, $plan);
        $transaction->markAsPaid();

        return [$user, $plan, $transaction, Subscription::where('user_id', $user->id)->sole()];
    }

    private function transaction(User $user, MembershipPlan $plan): Transaction
    {
        return Transaction::create([
            'invoice_code' => 'TEST-'.Str::uuid(), 'user_id' => $user->id,
            'membership_plan_id' => $plan->id, 'amount' => $plan->price,
            'payment_method' => 'manual_transfer', 'gateway_name' => 'manual', 'payment_status' => 'pending',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Midtrans\Notification;
use Tests\TestCase;

class Phase2PaymentStateTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Transaction
    {
        Queue::fake();

        return Transaction::create([
            'invoice_code' => 'STATE-'.Str::uuid(), 'user_id' => User::factory()->create()->id,
            'program_id' => Program::create(['title' => 'State test'])->id,
            'amount' => 100000, 'payment_status' => 'pending', 'payment_method' => 'gateway_va',
        ]);
    }

    private function sendCallback(Transaction $invoice, string $status, ?string $fraud = null): void
    {
        $this->app->instance(Notification::class, (object) [
            'order_id' => $invoice->invoice_code, 'transaction_status' => $status,
            'fraud_status' => $fraud, 'transaction_id' => 'MID-STATE', 'settlement_time' => now()->toDateTimeString(),
        ]);
        $this->postJson(route('webhook.midtrans'), [])->assertOk();
    }

    public function test_reordered_pending_and_challenge_callbacks_do_not_downgrade_paid_invoice(): void
    {
        $invoice = $this->invoice();
        $invoice->markAsPaid();
        $this->sendCallback($invoice, 'pending');
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $this->sendCallback($invoice, 'capture', 'challenge');
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $this->assertDatabaseCount('enrollment_payment_sources', 1);
    }

    public function test_pending_callback_cannot_reopen_a_cancelled_invoice(): void
    {
        $invoice = $this->invoice();
        $invoice->rejectTransaction();
        $this->sendCallback($invoice, 'pending');
        $this->assertSame('failed', $invoice->fresh()->payment_status);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_stale_paid_instances_dispatch_fulfillment_only_once(): void
    {
        $invoice = $this->invoice();
        $stale = Transaction::findOrFail($invoice->id);
        $invoice->markAsPaid();
        $stale->approveTransaction();
        $this->assertSame('paid', $stale->fresh()->payment_status);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseCount('enrollment_payment_sources', 1);
    }
}

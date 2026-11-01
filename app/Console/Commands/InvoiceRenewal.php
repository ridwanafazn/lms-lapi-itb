<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InvoiceRenewal extends Command
{
    protected $signature = 'subscriptions:invoice';

    protected $description = 'Create due membership renewal invoices for manual payment (no automatic debit)';

    public function handle(SubscriptionService $subscriptions): int
    {
        $created = 0;
        $failed = 0;
        Subscription::where('status', 'active')->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())->chunkById(100, function ($batch) use ($subscriptions, &$created, &$failed) {
                foreach ($batch as $subscription) {
                    try {
                        $invoice = $subscriptions->invoiceRenewal($subscription);
                        if ($invoice?->wasRecentlyCreated) {
                            $created++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::error('Membership renewal invoice failed', [
                            'subscription_id' => $subscription->id, 'user_id' => $subscription->user_id,
                            'exception' => $exception,
                        ]);
                        $this->error('Failed subscription: '.$subscription->id);
                    }
                }
            });
        $this->info("Created {$created} manual renewal invoice(s); {$failed} failure(s). No payment was initiated.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

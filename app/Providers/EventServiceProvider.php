<?php

namespace App\Providers;

use App\Events\TransactionPaid;
use App\Listeners\ActivateMembership;
use App\Listeners\FulfillEnrollment;
use App\Listeners\RecordPaymentActivity;
use App\Listeners\SendPaymentConfirmation;
use App\Listeners\LogDomainEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Listener order matters for the synchronous listeners:
     * access must exist before anything reports on it.
     */
    protected $listen = [
        TransactionPaid::class => [
            FulfillEnrollment::class,
            ActivateMembership::class,
            RecordPaymentActivity::class,
            SendPaymentConfirmation::class,
            LogDomainEvent::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}

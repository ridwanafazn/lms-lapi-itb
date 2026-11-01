<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

class MembershipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // Starter
            ['level' => 'starter', 'billing_period' => 'lifetime', 'price' => 0],

            // Standard
            ['level' => 'standard', 'billing_period' => 'monthly', 'price' => 19000],
            ['level' => 'standard', 'billing_period' => 'quarterly', 'price' => 49000],
            ['level' => 'standard', 'billing_period' => 'semiannual', 'price' => 100000],
            ['level' => 'standard', 'billing_period' => 'yearly', 'price' => 189000],

            // Pro
            ['level' => 'pro', 'billing_period' => 'monthly', 'price' => 49000],
            ['level' => 'pro', 'billing_period' => 'quarterly', 'price' => 139000],
            ['level' => 'pro', 'billing_period' => 'semiannual', 'price' => 259000],
            ['level' => 'pro', 'billing_period' => 'yearly', 'price' => 499000],
        ];

        foreach ($plans as $plan) {
            MembershipPlan::updateOrCreate(
                ['level' => $plan['level'], 'billing_period' => $plan['billing_period']],
                ['price' => $plan['price'], 'is_active' => true]
            );
        }
    }
}

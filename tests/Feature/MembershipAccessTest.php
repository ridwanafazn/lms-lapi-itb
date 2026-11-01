<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\MembershipPlan;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlan(?string $level, ?string $expiresIn = '+1 month'): User
    {
        $user = User::create([
            'name' => 'Member ' . $level,
            'email' => 'member-' . uniqid() . '@example.test',
            'password' => 'password',
        ]);

        if ($level === null) {
            return $user;
        }

        $plan = MembershipPlan::create([
            'level' => $level,
            'billing_period' => 'monthly',
            'price' => 10_000,
        ]);

        $user->update([
            'membership_plan_id' => $plan->id,
            'membership_expires_at' => $expiresIn === null ? null : now()->modify($expiresIn),
        ]);

        return $user->refresh();
    }

    private function program(string $level, ?string $planId = null): Program
    {
        return Program::create([
            'title' => 'Program ' . $level,
            'price' => 1_000_000,
            'min_membership_level' => $level,
            'required_membership_plan_id' => $planId,
            'is_active' => true,
        ]);
    }

    public function test_standard_plan_unlocks_standard_program(): void
    {
        $user = $this->userWithPlan('standard');

        $this->assertTrue($this->program('standard')->isFreeForUser($user));
    }

    public function test_pro_plan_unlocks_standard_program(): void
    {
        $user = $this->userWithPlan('pro');

        $this->assertTrue($this->program('standard')->isFreeForUser($user));
    }

    public function test_standard_plan_does_not_unlock_pro_program(): void
    {
        $user = $this->userWithPlan('standard');

        $this->assertFalse($this->program('pro')->isFreeForUser($user));
    }

    public function test_starter_plan_unlocks_nothing(): void
    {
        $user = $this->userWithPlan('starter');

        $this->assertFalse($this->program('standard')->isFreeForUser($user));
        $this->assertFalse($this->program('pro')->isFreeForUser($user));
    }

    public function test_program_without_requirement_is_paid_only(): void
    {
        $user = $this->userWithPlan('pro');

        $this->assertFalse($this->program('none')->isFreeForUser($user));
    }

    public function test_expired_membership_gives_no_access(): void
    {
        $user = $this->userWithPlan('pro', '-1 day');

        $this->assertFalse($this->program('standard')->isFreeForUser($user));
    }

    public function test_explicit_plan_requirement_wins_over_level(): void
    {
        $explicitPlan = MembershipPlan::create([
            'level' => 'standard',
            'billing_period' => 'yearly',
            'price' => 100_000,
        ]);

        $proUser = $this->userWithPlan('pro');

        // Program hanya gratis untuk pemilik plan eksplisit, bukan semua level pro.
        $this->assertFalse($this->program('none', $explicitPlan->id)->isFreeForUser($proUser));
    }

    public function test_batch_requires_matching_membership_level(): void
    {
        $standardUser = $this->userWithPlan('standard');
        $proUser = $this->userWithPlan('pro');

        $standardBatch = Batch::create([
            'batch_name' => 'Batch Standard',
            'min_membership_level' => 'standard',
        ]);

        $proBatch = Batch::create([
            'batch_name' => 'Batch Pro',
            'min_membership_level' => 'pro',
        ]);

        $this->assertTrue($standardBatch->isFreeForUser($standardUser));
        $this->assertTrue($standardBatch->isFreeForUser($proUser));
        $this->assertFalse($proBatch->isFreeForUser($standardUser));
        $this->assertTrue($proBatch->isFreeForUser($proUser));
    }

    public function test_accessible_programs_accessor_uses_membership_level(): void
    {
        $user = $this->userWithPlan('pro');

        $standardProgram = $this->program('standard');
        $proProgram = $this->program('pro');
        $paidProgram = $this->program('none');

        $accessibleIds = $user->accessible_programs->pluck('id');

        $this->assertTrue($accessibleIds->contains($standardProgram->id));
        $this->assertTrue($accessibleIds->contains($proProgram->id));
        $this->assertFalse($accessibleIds->contains($paidProgram->id));
    }
}

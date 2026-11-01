<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBatchMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Admin Batch',
            'email' => 'admin-batch-'.uniqid().'@example.test',
            'password' => 'password',
        ]);

        $user->assignRole('admin');

        return $user;
    }

    private function program(): Program
    {
        return Program::create([
            'title' => 'Test Program',
            'type' => 'cohort',
            'price' => 500000,
            'min_membership_level' => 'standard',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_store_batch_with_price_and_membership_level()
    {
        $program = $this->program();

        $this->actingAs($this->admin());

        Livewire::test('admin.batches')
            ->set('batch_type', 'program')
            ->set('selected_entity_id', $program->id)
            ->set('name', 'Batch Pro 2026')
            ->set('start_date', '2026-02-01')
            ->set('end_date', '2026-03-01')
            ->set('status', 'open')
            ->set('price', 750000)
            ->set('min_membership_level', 'pro')
            ->call('save')
            ->assertHasNoErrors();

        $batch = Batch::where('batch_name', 'Batch Pro 2026')->first();
        $this->assertNotNull($batch);
        $this->assertEquals(750000, $batch->price);
        $this->assertEquals('pro', $batch->min_membership_level);
        $this->assertEquals($program->id, $batch->program_id);
    }

    public function test_admin_form_rejects_unknown_membership_level()
    {
        $program = $this->program();

        $this->actingAs($this->admin());

        Livewire::test('admin.batches')
            ->set('batch_type', 'program')
            ->set('selected_entity_id', $program->id)
            ->set('name', 'Batch Invalid')
            ->set('start_date', '2026-02-01')
            ->set('end_date', '2026-03-01')
            ->set('status', 'open')
            ->set('price', 100000)
            ->set('min_membership_level', 'platinum')
            ->call('save')
            ->assertHasErrors(['min_membership_level' => 'in']);
    }

    public function test_edit_loads_existing_price_and_membership_level()
    {
        $program = $this->program();

        $batch = Batch::create([
            'batch_name' => 'Existing Batch',
            'program_id' => $program->id,
            'start_date' => ['date' => '2026-02-01'],
            'end_date' => ['date' => '2026-03-01'],
            'status' => 'open',
            'price' => 600000,
            'min_membership_level' => 'standard',
        ]);

        $this->actingAs($this->admin());

        Livewire::test('admin.batches')
            ->call('edit', $batch->id)
            ->assertSet('name', 'Existing Batch')
            ->assertSet('price', 600000)
            ->assertSet('min_membership_level', 'standard')
            ->assertSet('batch_type', 'program')
            ->assertSet('selected_entity_id', $program->id);
    }

    public function test_batch_price_zero_inherits_from_program()
    {
        $program = $this->program();

        $this->actingAs($this->admin());

        Livewire::test('admin.batches')
            ->set('batch_type', 'program')
            ->set('selected_entity_id', $program->id)
            ->set('name', 'Batch Free')
            ->set('start_date', '2026-02-01')
            ->set('end_date', '2026-03-01')
            ->set('status', 'open')
            ->set('price', 0)
            ->set('min_membership_level', 'none')
            ->call('save')
            ->assertHasNoErrors();

        $batch = Batch::where('batch_name', 'Batch Free')->first();
        $this->assertEquals(0, $batch->price);
        $this->assertEquals(500000, $batch->effective_price);
    }

    public function test_batch_membership_level_none_means_paid_only()
    {
        $program = $this->program();

        $batch = Batch::create([
            'batch_name' => 'Paid Only Batch',
            'program_id' => $program->id,
            'start_date' => ['date' => '2026-02-01'],
            'end_date' => ['date' => '2026-03-01'],
            'status' => 'open',
            'price' => 100000,
            'min_membership_level' => 'none',
        ]);

        $student = User::create([
            'name' => 'Student',
            'email' => 'student-'.uniqid().'@example.test',
            'password' => 'password',
        ]);
        $this->assertFalse($batch->isFreeForUser($student));

        // User with membership but batch requires 'none' (paid only)
        $member = User::create([
            'name' => 'Member',
            'email' => 'member-'.uniqid().'@example.test',
            'password' => 'password',
            'membership_plan_id' => null,
        ]);
        $this->assertFalse($batch->isFreeForUser($member));
    }
}
<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProgramMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Admin Program',
            'email' => 'admin-program-'.uniqid().'@example.test',
            'password' => 'password',
        ]);

        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_store_program_with_membership_level(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('admin.programs')
            ->call('create')
            ->assertSee('Membership Level Diperlukan')
            ->set('title', 'Program Pro')
            ->set('price', 500000)
            ->set('program_type', 'self_paced')
            ->set('min_membership_level', 'pro')
            ->call('save')
            ->assertHasNoErrors();

        $program = Program::where('title', 'Program Pro')->firstOrFail();

        $this->assertSame('pro', $program->min_membership_level);
    }

    public function test_admin_form_rejects_unknown_membership_level(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('admin.programs')
            ->call('create')
            ->set('title', 'Program Invalid')
            ->set('price', 100000)
            ->set('min_membership_level', 'platinum')
            ->call('save')
            ->assertHasErrors(['min_membership_level']);

        $this->assertNull(Program::where('title', 'Program Invalid')->first());
    }

    public function test_edit_loads_existing_membership_level(): void
    {
        $this->actingAs($this->admin());

        $program = Program::create([
            'title' => 'Program Standard',
            'price' => 250000,
            'type' => 'self_paced',
            'min_membership_level' => 'standard',
            'is_active' => true,
        ]);

        Livewire::test('admin.programs')
            ->call('edit', $program->id)
            ->assertSet('min_membership_level', 'standard');
    }
}

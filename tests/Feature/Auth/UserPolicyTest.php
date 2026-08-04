<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_manage_users_while_admin_cannot_delete(): void
    {
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('agente');

        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($owner)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $target));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $target));

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $target));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $target));
    }

    public function test_agente_cannot_access_user_administration(): void
    {
        $agente = $this->userWithRole('agente');
        $target = $this->userWithRole('admin');

        $this->assertFalse(Gate::forUser($agente)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($agente)->allows('view', $target));
        $this->assertFalse(Gate::forUser($agente)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($agente)->allows('update', $target));
        $this->assertFalse(Gate::forUser($agente)->allows('delete', $target));
    }

    public function test_admin_cannot_update_delete_or_assign_owner_role(): void
    {
        $admin = $this->userWithRole('admin');
        $owner = $this->userWithRole('owner');
        $agente = $this->userWithRole('agente');

        $this->assertFalse(Gate::forUser($admin)->allows('update', $owner));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $owner));
        $this->assertFalse(Gate::forUser($admin)->allows('assignRole', [User::class, 'owner']));
        $this->assertTrue(Gate::forUser($admin)->allows('assignRole', [User::class, 'admin']));
        $this->assertTrue(Gate::forUser($admin)->allows('assignRole', [User::class, 'agente']));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $agente));
    }

    public function test_suspension_hooks_protect_owner_for_lote_d(): void
    {
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');
        $agente = $this->userWithRole('agente');

        $this->assertFalse(Gate::forUser($admin)->allows('suspend', $owner));
        $this->assertFalse(Gate::forUser($owner)->allows('suspend', $owner));
        $this->assertTrue(Gate::forUser($admin)->allows('suspend', $agente));
        $this->assertTrue(Gate::forUser($owner)->allows('suspend', $admin));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

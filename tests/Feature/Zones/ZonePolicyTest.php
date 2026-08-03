<?php

namespace Tests\Feature\Zones;

use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ZonePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_and_admin_can_manage_zones_while_agente_cannot(): void
    {
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');
        $agente = $this->userWithRole('agente');
        $zone = Zone::factory()->create();

        foreach ([$owner, $admin] as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Zone::class));
            $this->assertTrue(Gate::forUser($user)->allows('view', $zone));
            $this->assertTrue(Gate::forUser($user)->allows('create', Zone::class));
            $this->assertTrue(Gate::forUser($user)->allows('update', $zone));
        }

        $this->assertFalse(Gate::forUser($agente)->allows('viewAny', Zone::class));
        $this->assertFalse(Gate::forUser($agente)->allows('view', $zone));
        $this->assertFalse(Gate::forUser($agente)->allows('create', Zone::class));
        $this->assertFalse(Gate::forUser($agente)->allows('update', $zone));
    }

    public function test_only_owner_can_delete_and_restore_zones(): void
    {
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');
        $agente = $this->userWithRole('agente');
        $zone = Zone::factory()->create();

        $this->assertTrue(Gate::forUser($owner)->allows('delete', $zone));
        $this->assertTrue(Gate::forUser($owner)->allows('restore', $zone));

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $zone));
        $this->assertFalse(Gate::forUser($admin)->allows('restore', $zone));
        $this->assertFalse(Gate::forUser($agente)->allows('delete', $zone));
        $this->assertFalse(Gate::forUser($agente)->allows('restore', $zone));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

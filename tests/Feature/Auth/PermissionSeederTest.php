<?php

namespace Tests\Feature\Auth;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_seeder_is_idempotent_and_creates_expected_matrix(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        // 15 = 9 base + lonas.manage + lonas.place (RFC-062, Épica 9)
        //    + contratos.manage/cancel/ver-identificacion (Épica 10)
        //    + frontend.manage (Épica 12, solo owner).
        $this->assertSame(15, Permission::count());
        $this->assertSame(5, Role::count());

        $this->assertRoleHasPermissions('owner', PermissionSeeder::PERMISSIONS);
        $this->assertRoleHasPermissions('admin', [
            'users.view',
            'users.create',
            'users.update',
            'properties.manage',
            'owners.manage',
            'leads.manage',
            'zones.manage',
            'projects.manage',
            'lonas.manage',
            'contratos.manage',
            'contratos.cancel',
        ]);
        $this->assertRoleHasPermissions('agente', [
            'properties.manage',
            'owners.manage',
            'leads.manage',
            'lonas.place',
            'contratos.manage',
        ]);
        $this->assertRoleHasPermissions('arquitectura', [
            'projects.manage',
        ]);
        $this->assertRoleHasPermissions('proyectos', [
            'projects.manage',
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function assertRoleHasPermissions(string $roleName, array $permissions): void
    {
        $role = Role::findByName($roleName, PermissionSeeder::GUARD);

        $this->assertEqualsCanonicalizing(
            $permissions,
            $role->permissions()->pluck('name')->all(),
        );
    }
}

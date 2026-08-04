<?php

namespace Tests\Feature\Frontend;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * T-1b: production deploys run `migrate --force` WITHOUT seeders
 * (docs/deployment/CI-CD-PIPELINE.md:46-58), so the `frontend.manage`
 * permission must exist and be assigned to `owner` from migrations alone.
 */
class FrontendManagePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_alone_create_the_permission_and_assign_it_to_owner(): void
    {
        // RefreshDatabase has already run migrations and NO seeders at this point.
        $permission = Permission::where('name', 'frontend.manage')
            ->where('guard_name', 'web')
            ->first();

        $this->assertNotNull(
            $permission,
            'frontend.manage must be created by a migration, not a seeder (production runs migrate --force only).'
        );

        $owner = Role::where('name', 'owner')->where('guard_name', 'web')->first();

        $this->assertNotNull($owner, 'The owner role must exist after running migrations alone.');
        $this->assertTrue(
            $owner->hasPermissionTo('frontend.manage'),
            'owner must receive frontend.manage from the migration.'
        );
    }

    public function test_migration_is_idempotent_against_a_seeded_database(): void
    {
        // Simulates dev/test where the seeder ran BEFORE re-running migrations:
        // no duplicates and the assignment stays intact.
        $this->seed(PermissionSeeder::class);

        $this->assertSame(
            1,
            Permission::where('name', 'frontend.manage')->count(),
            'Seeder + migration must not duplicate the permission.'
        );

        $owner = Role::findByName('owner', 'web');
        $this->assertTrue($owner->hasPermissionTo('frontend.manage'));
    }

    public function test_no_other_role_receives_the_permission(): void
    {
        $this->seed(PermissionSeeder::class);

        foreach (['admin', 'agente', 'arquitectura', 'proyectos'] as $roleName) {
            $this->assertFalse(
                Role::findByName($roleName, 'web')->hasPermissionTo('frontend.manage'),
                "Role {$roleName} must NOT have frontend.manage."
            );
        }
    }
}

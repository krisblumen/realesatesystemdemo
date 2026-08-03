<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the `frontend.manage` permission and assigns it to the `owner` role.
 *
 * This is a data migration on purpose: production deploys run `migrate --force`
 * WITHOUT seeders (docs/deployment/CI-CD-PIPELINE.md), so the permission cannot
 * depend on PermissionSeeder. Idempotent: firstOrCreate + givePermissionTo are
 * both safe to re-run against a seeded or partially provisioned database.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'frontend.manage',
            'guard_name' => 'web',
        ]);

        $owner = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);

        if (! $owner->hasPermissionTo($permission)) {
            $owner->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Additive data migration: rolling back removes only what it added.
        Permission::where('name', 'frontend.manage')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

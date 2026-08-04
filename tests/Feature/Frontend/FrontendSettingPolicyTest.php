<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * T-1: owner-only is a DOUBLE gate — hasRole('owner') AND can('frontend.manage').
 * Permission alone must not open the door (a mis-granted permission is not
 * an authorization), and role alone must not either (the capability must be
 * revocable by removing the permission).
 */
class FrontendSettingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_passes_every_managing_ability(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $setting = FrontendSetting::current();

        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', FrontendSetting::class));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $setting));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $setting));
    }

    public function test_every_other_role_is_denied(): void
    {
        $setting = FrontendSetting::current();

        foreach (['admin', 'agente', 'arquitectura', 'proyectos'] as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->assertFalse(Gate::forUser($user)->allows('viewAny', FrontendSetting::class), $role);
            $this->assertFalse(Gate::forUser($user)->allows('view', $setting), $role);
            $this->assertFalse(Gate::forUser($user)->allows('update', $setting), $role);
        }
    }

    public function test_permission_without_owner_role_is_still_denied(): void
    {
        // Double gate, first half: an admin who mistakenly receives the
        // permission must NOT get in.
        $admin = User::factory()->withRole('admin')->create();
        $admin->givePermissionTo('frontend.manage');

        $this->assertFalse(Gate::forUser($admin)->allows('viewAny', FrontendSetting::class));
        $this->assertFalse(Gate::forUser($admin)->allows('update', FrontendSetting::current()));
    }

    public function test_owner_role_without_the_permission_is_denied(): void
    {
        // Double gate, second half: revoking the permission revokes the capability
        // even for owner. With spatie the permission flows THROUGH the role,
        // so the real revocation path is on the role itself.
        $owner = User::factory()->withRole('owner')->create();
        Role::findByName('owner', 'web')->revokePermissionTo('frontend.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(Gate::forUser($owner)->allows('viewAny', FrontendSetting::class));
        $this->assertFalse(Gate::forUser($owner)->allows('update', FrontendSetting::current()));
    }

    public function test_delete_and_force_delete_are_denied_even_for_owner(): void
    {
        // C-4: the singleton is not deletable, period. There is no role or
        // permission combination that reaches deleteAllMedia() through it.
        $owner = User::factory()->withRole('owner')->create();
        $setting = FrontendSetting::current();

        $this->assertFalse(Gate::forUser($owner)->allows('delete', $setting));
        $this->assertFalse(Gate::forUser($owner)->allows('forceDelete', $setting));
        $this->assertFalse(Gate::forUser($owner)->allows('restore', $setting));
    }
}

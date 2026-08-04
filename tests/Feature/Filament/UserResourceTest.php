<?php

namespace Tests\Feature\Filament;

use App\Enums\UserStatus;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_access_user_resource_pages(): void
    {
        $owner = $this->userWithRole('owner');
        $target = $this->userWithRole('agente');

        $this->actingAs($owner)
            ->get(UserResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(UserResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(UserResource::getUrl('edit', ['record' => $target]))
            ->assertOk();
    }

    public function test_agente_cannot_access_user_resource(): void
    {
        $agente = $this->userWithRole('agente');

        $this->actingAs($agente)
            ->get(UserResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_edit_owner_or_assign_owner_role(): void
    {
        $admin = $this->userWithRole('admin');
        $owner = $this->userWithRole('owner');

        $this->actingAs($admin)
            ->get(UserResource::getUrl('edit', ['record' => $owner]))
            ->assertForbidden();

        $this->actingAs($admin);

        $this->assertArrayNotHasKey('owner', UserResource::assignableRoleOptions());
        $this->assertArrayHasKey('admin', UserResource::assignableRoleOptions());
        $this->assertArrayHasKey('agente', UserResource::assignableRoleOptions());
    }

    public function test_owner_can_change_user_role_from_resource_form(): void
    {
        $owner = $this->userWithRole('owner');
        $agent = $this->userWithRole('agente');

        $this->actingAs($owner);

        Livewire::test(EditUser::class, ['record' => $agent->getRouteKey()])
            ->fillForm([
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'whatsapp' => $agent->whatsapp,
                'roles' => ['admin'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($agent->refresh()->hasRole('admin'));
        $this->assertTrue($agent->can('users.update'));
    }

    public function test_admin_can_edit_agent_phone_but_cannot_edit_owner(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agente');
        $owner = $this->userWithRole('owner');

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $agent->getRouteKey()])
            ->fillForm([
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => '+52 55 9999 0000',
                'whatsapp' => '+52 55 8888 0000',
                'roles' => ['agente'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('+52 55 9999 0000', $agent->refresh()->phone);

        $this->actingAs($admin)
            ->get(UserResource::getUrl('edit', ['record' => $owner]))
            ->assertForbidden();
    }

    public function test_user_create_form_validates_required_and_unique_email(): void
    {
        $owner = $this->userWithRole('owner');
        $existing = $this->userWithRole('agente');

        $this->actingAs($owner);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => '',
                'email' => $existing->email,
                'roles' => [],
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'email' => 'unique',
                'roles' => 'required',
            ]);
    }

    public function test_owner_can_soft_delete_and_restore_user_from_resource_table(): void
    {
        $owner = $this->userWithRole('owner');
        $target = $this->userWithRole('agente');

        $this->actingAs($owner);

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $target);

        $this->assertSoftDeleted('users', ['id' => $target->id]);

        Livewire::test(ListUsers::class)
            ->filterTable('trashed', false)
            ->callTableAction('restore', $target->fresh());

        $this->assertFalse($target->fresh()->trashed());
    }

    public function test_owner_can_filter_users_by_role_and_status(): void
    {
        $owner = $this->userWithRole('owner');
        $activeAgent = $this->userWithRole('agente');
        $suspendedAdmin = $this->userWithRole('admin');
        $suspendedAdmin->forceFill(['status' => UserStatus::Suspended])->save();

        $this->actingAs($owner);

        Livewire::test(ListUsers::class)
            ->filterTable('roles', Role::findByName('agente')->getKey())
            ->assertCanSeeTableRecords([$activeAgent])
            ->assertCanNotSeeTableRecords([$owner, $suspendedAdmin]);

        Livewire::test(ListUsers::class)
            ->filterTable('status', UserStatus::Suspended->value)
            ->assertCanSeeTableRecords([$suspendedAdmin])
            ->assertCanNotSeeTableRecords([$owner, $activeAgent]);
    }

    public function test_owner_can_suspend_and_reactivate_user_from_resource_table(): void
    {
        $owner = $this->userWithRole('owner');
        $target = $this->userWithRole('agente');

        $this->actingAs($owner);

        Livewire::test(ListUsers::class)
            ->callTableAction('suspend', $target, ['reason' => 'Revisión documental pendiente']);

        $this->assertSame(UserStatus::Suspended, $target->refresh()->status);
        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by' => $owner->id,
            'from_status' => UserStatus::Active->value,
            'to_status' => UserStatus::Suspended->value,
            'reason' => 'Revisión documental pendiente',
        ]);

        Livewire::test(ListUsers::class)
            ->filterTable('status', UserStatus::Suspended->value)
            ->callTableAction('reactivate', $target);

        $this->assertSame(UserStatus::Active, $target->refresh()->status);
        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by' => $owner->id,
            'from_status' => UserStatus::Suspended->value,
            'to_status' => UserStatus::Active->value,
        ]);
    }

    public function test_suspended_user_cannot_access_panel_and_sees_clear_login_message(): void
    {
        $suspended = $this->userWithRole('admin');
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();

        $this->actingAs($suspended)
            ->get(UserResource::getUrl('index'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $suspended->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email'])
            ->assertSee('Tu cuenta está suspendida. Contacta al administrador.');

        $this->assertGuest();
    }

    public function test_owner_can_create_user_with_role_from_resource(): void
    {
        Notification::fake();
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nuevo Agente',
                'email' => 'nuevo-agente@example.test',
                'phone' => '+52 55 1111 2222',
                'whatsapp' => '+52 55 3333 4444',
                'roles' => ['agente'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'nuevo-agente@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole('agente'));
        $this->assertSame('+52 55 1111 2222', $user->phone);
        $this->assertSame(UserStatus::Pending, $user->status);

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_owner_can_assign_multiple_roles_to_a_user(): void
    {
        $owner = $this->userWithRole('owner');
        $agent = $this->userWithRole('agente');

        $this->actingAs($owner);

        Livewire::test(EditUser::class, ['record' => $agent->getRouteKey()])
            ->fillForm([
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'whatsapp' => $agent->whatsapp,
                'roles' => ['admin', 'agente'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $agent->refresh();

        $this->assertTrue($agent->hasRole('admin'));
        $this->assertTrue($agent->hasRole('agente'));
        $this->assertCount(2, $agent->roles);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defaults_to_active_status_and_exposes_status_helpers(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isSuspended());

        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->assertFalse($user->refresh()->isActive());
        $this->assertTrue($user->isSuspended());
    }

    public function test_user_supports_profile_fields_soft_deletes_and_spatie_roles_contract(): void
    {
        $user = User::factory()->create([
            'phone' => '+52 55 1111 2222',
            'whatsapp' => '+52 55 3333 4444',
            'avatar' => 'avatars/user.png',
        ]);

        $this->assertSame('+52 55 1111 2222', $user->phone);
        $this->assertSame('+52 55 3333 4444', $user->whatsapp);
        $this->assertSame('avatars/user.png', $user->avatar);
        $this->seed(PermissionSeeder::class);
        $user->assignRole('agente');

        $this->assertInstanceOf(Relation::class, $user->roles());
        $this->assertTrue($user->hasRole('agente'));
        $this->assertInstanceOf(HasMany::class, $user->properties());
        $this->assertInstanceOf(HasMany::class, $user->leads());

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_user_factory_exposes_active_suspended_and_role_states(): void
    {
        $this->seed(
            PermissionSeeder::class,
        );

        $activeOwner = User::factory()->active()->withRole('owner')->create();
        $suspendedAgent = User::factory()->suspended()->withRole('agente')->create();

        $this->assertSame(UserStatus::Active, $activeOwner->status);
        $this->assertTrue($activeOwner->hasRole('owner'));
        $this->assertSame(UserStatus::Suspended, $suspendedAgent->status);
        $this->assertTrue($suspendedAgent->hasRole('agente'));
    }

    public function test_last_login_at_is_updated_when_login_event_is_dispatched(): void
    {
        $user = User::factory()->create(['last_login_at' => null]);

        event(new Login('web', $user, false));

        $this->assertNotNull($user->refresh()->last_login_at);
    }
}

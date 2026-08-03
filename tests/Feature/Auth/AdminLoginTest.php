<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_access_admin_panel(): void
    {
        $owner = $this->userWithRole('owner');

        $this->loginFromAdminPanel($owner)
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($owner);
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = $this->userWithRole('admin');

        $this->loginFromAdminPanel($admin)
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_agente_can_access_admin_panel(): void
    {
        $agente = $this->userWithRole('agente');

        $this->loginFromAdminPanel($agente)
            ->assertRedirect('/admin/mi-zona');

        $this->assertAuthenticatedAs($agente);
    }

    public function test_active_user_without_allowed_roles_is_blocked(): void
    {
        $user = User::factory()->active()->create();

        $this->loginFromAdminPanel($user)
            ->assertHasFormErrors(['email'])
            ->assertSee('Estas credenciales no coinciden con nuestros registros.');

        $this->assertGuest();
    }

    public function test_suspended_user_is_blocked_at_can_access_panel_without_creating_session(): void
    {
        $suspended = User::factory()->suspended()->withRole('admin')->create();

        $this->loginFromAdminPanel($suspended)
            ->assertHasFormErrors(['email'])
            ->assertSee('Tu cuenta está suspendida. Contacta al administrador.');

        $this->assertGuest();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_suspended_user_during_active_session_is_logged_out_by_middleware(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();

        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->get('/admin')
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHasErrors(['data.email' => 'Tu cuenta está suspendida. Contacta al administrador.']);

        $this->assertGuest();
    }

    public function test_last_login_at_is_updated_on_successful_login_event(): void
    {
        $admin = $this->userWithRole('admin');

        $this->assertNull($admin->last_login_at);

        $this->loginFromAdminPanel($admin)
            ->assertRedirect('/admin');

        $lastLoginAt = User::query()
            ->whereKey($admin)
            ->value('last_login_at');

        $this->assertNotNull($lastLoginAt);
        $this->assertTrue(CarbonImmutable::parse($lastLoginAt)->lessThanOrEqualTo(now()->addSeconds(5)));
    }

    public function test_owner_and_admin_redirect_to_admin_dashboard_after_login(): void
    {
        foreach (['owner', 'admin'] as $role) {
            Filament::auth()->logout();
            session()->flush();

            $user = $this->userWithRole($role);

            $this->loginFromAdminPanel($user)
                ->assertRedirect('/admin');
        }
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->active()->withRole($role)->create([
            'last_login_at' => null,
        ]);
    }

    private function loginFromAdminPanel(User $user): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate');
    }
}

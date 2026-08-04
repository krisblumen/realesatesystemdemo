<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        Route::middleware(['web', EnsureUserIsActive::class])->get('/__test/active-user-gate', fn (): string => 'ok');
    }

    public function test_active_user_passes_through_middleware(): void
    {
        $active = User::factory()->active()->withRole('admin')->create();

        $this->actingAs($active)
            ->get('/__test/active-user-gate')
            ->assertOk()
            ->assertSee('ok');

        $this->assertAuthenticatedAs($active);
    }

    public function test_suspended_user_is_logged_out_and_redirected_with_message(): void
    {
        $suspended = User::factory()->suspended()->withRole('admin')->create();

        $this->actingAs($suspended)
            ->get('/__test/active-user-gate')
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHasErrors(['data.email' => 'Tu cuenta está suspendida. Contacta al administrador.']);

        $this->assertGuest();
    }
}

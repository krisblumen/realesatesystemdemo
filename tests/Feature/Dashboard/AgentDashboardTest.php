<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Pages\AgentDashboard;
use App\Filament\Pages\Auth\Login;
use App\Filament\Widgets\AgentZonesWidget;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\GeoCatalogSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(GeoCatalogSeeder::class);
    }

    public function test_redirects_agente_to_agent_dashboard_after_login(): void
    {
        $agente = $this->userWithRole('agente');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $agente->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertRedirect('/admin/mi-zona');
    }

    public function test_lists_assigned_zones_of_authenticated_agente(): void
    {
        $agente = $this->userWithRole('agente');
        $activeZone = Zone::factory()->create([
            'name' => 'Zona Norte',
        ]);
        $inactiveZone = Zone::factory()->inactive()->create([
            'name' => 'Zona Pausada',
        ]);

        $agente->zones()->attach([$activeZone->id, $inactiveZone->id]);

        $this->actingAs($agente);

        Livewire::test(AgentZonesWidget::class)
            ->assertSee('Zona Norte')
            ->assertSee($activeZone->municipality->name)
            ->assertDontSee('Zona Pausada');
    }

    public function test_shows_empty_state_when_agente_has_no_zones(): void
    {
        $agente = $this->userWithRole('agente');

        $this->actingAs($agente);

        Livewire::test(AgentZonesWidget::class)
            ->assertSee('Aún no tienes zonas asignadas. Contacta al administrador.');
    }

    public function test_blocks_non_agente_from_accessing_agent_dashboard(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get($this->agentDashboardPath())
                ->assertForbidden();
        }
    }

    public function test_blocks_active_user_without_any_role_from_accessing_any_dashboard(): void
    {
        $user = User::factory()->active()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }

    private function agentDashboardPath(): string
    {
        return (string) parse_url(AgentDashboard::getUrl(), PHP_URL_PATH);
    }
}

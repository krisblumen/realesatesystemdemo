<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ZoneStatus;
use App\Filament\Widgets\AgentZonesWidget;
use App\Filament\Widgets\ZonesOverviewWidget;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ZonesWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    public function test_shows_zones_overview_widget_to_owner_and_admin(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertTrue(ZonesOverviewWidget::canView());

            Livewire::test(ZonesOverviewWidget::class)
                ->assertSee('Zonas totales')
                ->assertSee('Zonas activas')
                ->assertSee('Agentes asignados');
        }
    }

    public function test_hides_zones_overview_widget_from_agente(): void
    {
        $this->actingAs($this->userWithRole('agente'));

        $this->assertFalse(ZonesOverviewWidget::canView());
    }

    public function test_hides_agent_zones_widget_from_owner_and_admin(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertFalse(AgentZonesWidget::canView());
        }
    }

    public function test_zone_counts_match_the_database(): void
    {
        Zone::factory()->count(2)->create();
        Zone::factory()->inactive()->create();
        $this->userWithRole('agente');
        $this->userWithRole('agente');

        $this->actingAs($this->userWithRole('owner'));

        $stats = $this->statsByLabel();

        $this->assertSame(3, $stats['Zonas totales']->getValue());
        $this->assertSame(2, $stats['Zonas activas']->getValue());
        $this->assertSame(2, $stats['Agentes asignados']->getValue());
        $this->assertSame(Zone::count(), $stats['Zonas totales']->getValue());
        $this->assertSame(Zone::where('status', ZoneStatus::Active->value)->count(), $stats['Zonas activas']->getValue());
        $this->assertSame(User::role('agente')->count(), $stats['Agentes asignados']->getValue());
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }

    /**
     * @return array<string, Stat>
     */
    private function statsByLabel(): array
    {
        $widget = app(ZonesOverviewWidget::class);
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        $stats = [];

        foreach ($method->invoke($widget) as $stat) {
            $stats[(string) $stat->getLabel()] = $stat;
        }

        return $stats;
    }
}

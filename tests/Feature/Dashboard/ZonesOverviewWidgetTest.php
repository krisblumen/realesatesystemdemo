<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Widgets\ZonesOverviewWidget;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ZonesOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_and_admin_can_view_the_zones_overview_widget(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertTrue(ZonesOverviewWidget::canView());
        }
    }

    public function test_agente_cannot_view_the_zones_overview_widget(): void
    {
        $this->actingAs($this->userWithRole('agente'));

        $this->assertFalse(ZonesOverviewWidget::canView());
    }

    public function test_widget_renders_zone_and_agent_counts(): void
    {
        Zone::factory()->count(2)->create();
        Zone::factory()->inactive()->create();
        $this->userWithRole('agente');
        $this->userWithRole('agente');

        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ZonesOverviewWidget::class)
            ->assertSee('Zonas totales')
            ->assertSee('Zonas activas')
            ->assertSee('Agentes asignados');
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

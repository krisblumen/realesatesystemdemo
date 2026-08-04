<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ZoneStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AgentZonesListWidget;
use App\Filament\Widgets\AgentZonesWidget;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgentZonesMapWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    public function test_zone_maps_expose_each_assigned_zone_with_its_polygon(): void
    {
        $agent = User::factory()->withRole('agente')->create();
        $zone = Zone::factory()->withPolygon()->create(['status' => ZoneStatus::Active]);
        $agent->zones()->attach($zone);
        $this->actingAs($agent);

        $maps = (new AgentZonesWidget)->getZoneMaps();

        $this->assertCount(1, $maps);
        $this->assertSame($zone->name, $maps[0]['name']);
        $this->assertNotNull($maps[0]['geojson']);
        $this->assertStringContainsString('Polygon', $maps[0]['geojson']);
    }

    public function test_escritorio_shows_zone_names_but_not_the_maps_widget(): void
    {
        $this->actingAs(User::factory()->withRole('agente')->create());

        $widgets = (new Dashboard)->getWidgets();

        // En el escritorio: lista de nombres sí, mapas no.
        $this->assertContains(AgentZonesListWidget::class, $widgets);
        $this->assertNotContains(AgentZonesWidget::class, $widgets);
    }
}

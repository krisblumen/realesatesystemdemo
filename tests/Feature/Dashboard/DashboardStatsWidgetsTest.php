<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PropertyStatus;
use App\Filament\Widgets\LeadsStatsWidget;
use App\Filament\Widgets\PropertiesStatsWidget;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardStatsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    public function test_stats_widgets_are_visible_to_every_internal_role(): void
    {
        foreach (['owner', 'admin', 'agente'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->assertTrue(PropertiesStatsWidget::canView());
            $this->assertTrue(LeadsStatsWidget::canView());
        }
    }

    public function test_properties_stats_widget_counts_each_status_on_its_own(): void
    {
        // VENDIDOS Y RENTADOS SEPARADOS, que es el cambio y el motivo del cambio.
        //
        // Estaban sumados en una sola tarjeta. Son dos negocios distintos: una
        // inmobiliaria que vende mucho y renta poco no se parece en nada a una
        // que hace al revés, y sumarlos esconde justo el dato por el que alguien
        // mira este tablero.
        //
        // Se comprueban los NÚMEROS y no sólo los rótulos. El test anterior
        // sembraba cinco inmuebles y no verificaba una sola cifra: habría pasado
        // igual con todos los contadores en cero.
        Property::factory()->published()->count(3)->create();
        Property::factory()->count(2)->create(['status' => PropertyStatus::Borrador]);
        Property::factory()->count(4)->create(['status' => PropertyStatus::Vendido]);
        Property::factory()->count(1)->create(['status' => PropertyStatus::Rentado]);

        $this->actingAs($this->userWithRole('owner'));

        // `getStats()` es protegido; se llega por reflexión, igual que en
        // `ZonesWidgetsTest`. Leer los valores es lo único que distingue este
        // test de uno que sólo mira rótulos.
        $widget = Livewire::test(PropertiesStatsWidget::class)->instance();
        $metodo = new \ReflectionMethod($widget, 'getStats');
        $metodo->setAccessible(true);

        $tarjetas = collect($metodo->invoke($widget))
            ->mapWithKeys(fn ($stat) => [$stat->getLabel() => $stat->getValue()]);

        $this->assertSame(3, $tarjetas['Publicados']);
        $this->assertSame(2, $tarjetas['Borradores']);
        $this->assertSame(4, $tarjetas['Vendidos']);
        $this->assertSame(1, $tarjetas['Rentados']);

        // Y ya no está la suma de las otras tres, que no agregaba información y
        // ocupaba el lugar de una cuarta tarjeta: cuatro entran en una fila.
        $this->assertCount(4, $tarjetas);
    }

    public function test_leads_stats_widget_renders(): void
    {
        Lead::factory()->count(2)->create(['agent_id' => null]);
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(LeadsStatsWidget::class)
            ->assertSee('Leads nuevos')
            ->assertSee('Sin asignar')
            ->assertSee('Leads del mes');
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

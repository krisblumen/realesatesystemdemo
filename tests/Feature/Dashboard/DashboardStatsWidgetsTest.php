<?php

namespace Tests\Feature\Dashboard;

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

    public function test_properties_stats_widget_renders(): void
    {
        Property::factory()->count(3)->create();
        Property::factory()->published()->count(2)->create();
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(PropertiesStatsWidget::class)
            ->assertSee('Inmuebles totales')
            ->assertSee('Publicados')
            ->assertSee('Vendidos / Rentados')
            ->assertSee('Borradores');
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

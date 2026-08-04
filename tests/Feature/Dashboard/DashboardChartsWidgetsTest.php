<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Widgets\LeadsByAgentChart;
use App\Filament\Widgets\LeadsByMonthChart;
use App\Filament\Widgets\MyPerformanceWidget;
use App\Filament\Widgets\OwnersCommissionStatsWidget;
use App\Filament\Widgets\PropertiesByStatusChart;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardChartsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, class-string> */
    private array $sharedWidgets = [
        PropertiesByStatusChart::class,
        LeadsByMonthChart::class,
        OwnersCommissionStatsWidget::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    public function test_shared_widgets_are_visible_to_every_internal_role(): void
    {
        foreach (['owner', 'admin', 'agente'] as $role) {
            $this->actingAs($this->userWithRole($role));

            foreach ($this->sharedWidgets as $widget) {
                $this->assertTrue($widget::canView(), "{$widget} should be visible to {$role}");
            }
        }
    }

    public function test_leads_by_agent_chart_is_for_owner_and_admin_only(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        $this->assertTrue(LeadsByAgentChart::canView());

        $this->actingAs($this->userWithRole('agente'));
        $this->assertFalse(LeadsByAgentChart::canView());
    }

    public function test_my_performance_widget_is_for_agents_only(): void
    {
        $this->actingAs($this->userWithRole('agente'));
        $this->assertTrue(MyPerformanceWidget::canView());

        $this->actingAs($this->userWithRole('owner'));
        $this->assertFalse(MyPerformanceWidget::canView());
    }

    public function test_my_performance_widget_is_visible_to_an_admin_who_is_also_agent(): void
    {
        // Multi-rol: un admin que además es agente ve sus métricas propias en
        // "Mi Zona". En el Panel general sigue viendo las globales.
        $user = $this->userWithRole('admin');
        $user->assignRole('agente');

        $this->actingAs($user);

        $this->assertTrue(MyPerformanceWidget::canView());
    }

    public function test_leads_by_month_chart_title_is_personalised_for_agents(): void
    {
        $this->actingAs($this->userWithRole('agente'));
        $this->assertSame('Mis leads por mes', (new LeadsByMonthChart)->getHeading());

        $this->actingAs($this->userWithRole('owner'));
        $this->assertSame('Leads por mes', (new LeadsByMonthChart)->getHeading());
    }

    public function test_widgets_render_without_errors(): void
    {
        $agent = $this->userWithRole('agente');
        Property::factory()->count(2)->create(['agent_id' => $agent->id]);
        Lead::factory()->count(3)->create(['agent_id' => $agent->id]);

        $this->actingAs($agent);
        foreach ([...$this->sharedWidgets, MyPerformanceWidget::class] as $widget) {
            Livewire::test($widget)->assertOk();
        }

        $this->actingAs($this->userWithRole('owner'));
        Livewire::test(LeadsByAgentChart::class)->assertOk();
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

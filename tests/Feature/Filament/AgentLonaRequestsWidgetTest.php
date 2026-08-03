<?php

namespace Tests\Feature\Filament;

use App\Enums\OperationType;
use App\Filament\Widgets\AgentLonaRequestsWidget;
use App\Models\LonaRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgentLonaRequestsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_widget_is_hidden_when_agent_has_no_pending_request(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $this->actingAs($agent);

        $this->assertFalse(AgentLonaRequestsWidget::canView());
    }

    public function test_widget_is_visible_when_agent_has_a_pending_request(): void
    {
        $agent = User::factory()->activeAgent()->create();
        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $this->actingAs($agent);

        $this->assertTrue(AgentLonaRequestsWidget::canView());
    }

    public function test_widget_is_hidden_once_the_request_is_resolved(): void
    {
        $agent = User::factory()->activeAgent()->create();
        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->approved()->create();

        $this->actingAs($agent);

        $this->assertFalse(AgentLonaRequestsWidget::canView());
    }

    public function test_non_agent_never_sees_the_widget_even_with_a_pending_request(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin);

        $this->assertFalse(AgentLonaRequestsWidget::canView());
    }

    public function test_widget_shows_type_quantity_and_only_the_authenticated_agents_requests(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $other = User::factory()->activeAgent()->create();

        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create(['cantidad_solicitada' => 5]);
        LonaRequest::factory()->for($other, 'agent')->ofType(OperationType::Renta)->create(['cantidad_solicitada' => 9]);

        $this->actingAs($agent);

        Livewire::test(AgentLonaRequestsWidget::class)
            ->assertSee('Venta')
            ->assertSee('5 lonas')
            ->assertDontSee('9 lonas');
    }
}

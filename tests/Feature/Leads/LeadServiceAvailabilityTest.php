<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\LeadCaptureForm;
use App\Models\FrontendService;
use App\Models\Lead;
use App\Models\Property;
use App\Models\ServiceType;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fail-closed lead availability (§16.6, RFC-074, M-2): a lead is accepted only
 * when ServiceType.active AND FrontendService.allow_leads. A manipulated POST
 * for an inactive or info-only service must fail, and the existing
 * comercializacion rules (property_id) must not regress.
 */
class LeadServiceAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function submit(string $serviceType, array $extra = []): Testable
    {
        return Livewire::test(LeadCaptureForm::class)
            ->set('name', 'Juan Pérez')
            ->set('email', 'juan@example.com')
            ->set('phone', '+52 442 000 0000')
            ->set('service_type', $serviceType)
            ->set($extra)
            ->call('submit');
    }

    public function test_a_lead_eligible_service_is_accepted(): void
    {
        // The migration backfilled comercializacion with allow_leads=true.
        $this->submit('comercializacion')->assertHasNoErrors();

        $this->assertDatabaseHas('leads', ['service_type' => 'comercializacion', 'email' => 'juan@example.com']);
    }

    public function test_an_info_only_service_is_rejected(): void
    {
        // inversion is show_in_* true but allow_leads false.
        $this->submit('inversion')->assertHasErrors('service_type');

        $this->assertSame(0, Lead::query()->count());
    }

    public function test_a_service_without_a_frontend_row_is_rejected(): void
    {
        ServiceType::query()->create(['code' => 'orphan', 'label' => 'Orphan', 'active' => true]);

        $this->submit('orphan')->assertHasErrors('service_type');
        $this->assertSame(0, Lead::query()->count());
    }

    public function test_an_inactive_type_is_rejected_even_if_allow_leads_is_true(): void
    {
        FrontendService::query()->where('service_type_code', 'construccion')->update(['allow_leads' => true]);
        ServiceType::query()->where('code', 'construccion')->update(['active' => false]);

        $this->submit('construccion')->assertHasErrors('service_type');
        $this->assertSame(0, Lead::query()->count());
    }

    public function test_comercializacion_still_binds_a_published_property(): void
    {
        $property = Property::factory()->published()->create();

        $this->submit('comercializacion', ['property_id' => $property->id])->assertHasNoErrors();

        $this->assertDatabaseHas('leads', ['service_type' => 'comercializacion', 'property_id' => $property->id]);
    }

    public function test_a_non_comercializacion_service_cannot_carry_a_property(): void
    {
        $property = Property::factory()->published()->create();

        // arquitectura is lead-eligible but property_id is prohibited for it.
        $this->submit('arquitectura', ['property_id' => $property->id])->assertHasErrors('property_id');
        $this->assertSame(0, Lead::query()->count());
    }
}

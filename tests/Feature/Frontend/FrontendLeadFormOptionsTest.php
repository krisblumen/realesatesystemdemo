<?php

namespace Tests\Feature\Frontend;

use App\Livewire\Leads\LeadCaptureForm;
use App\Models\FrontendService;
use App\Models\ServiceType;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M-D1: the public lead form must OFFER only lead-eligible services — the same
 * fail-closed rule the submit enforces. An ineligible service (info-only,
 * inactive, or without a live FrontendService) must not appear as a selectable
 * radio, not merely be rejected on submit.
 */
class FrontendLeadFormOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_an_info_only_service_is_not_offered(): void
    {
        // inversion is active and shown, but allow_leads=false.
        Livewire::test(LeadCaptureForm::class)
            ->assertSee('Comercialización')
            ->assertDontSee('Inversión inmobiliaria');
    }

    public function test_an_inactive_service_is_not_offered(): void
    {
        ServiceType::query()->where('code', 'arquitectura')->update(['active' => false]);

        Livewire::test(LeadCaptureForm::class)
            ->assertSee('Comercialización')
            ->assertDontSee('Arquitectura');
    }

    public function test_a_service_without_a_frontend_row_is_not_offered(): void
    {
        FrontendService::query()->where('service_type_code', 'construccion')->forceDelete();

        Livewire::test(LeadCaptureForm::class)
            ->assertSee('Comercialización')
            ->assertDontSee('Construcción');
    }

    public function test_toggling_allow_leads_off_removes_the_option(): void
    {
        FrontendService::query()->where('service_type_code', 'comercializacion')->update(['allow_leads' => false]);

        Livewire::test(LeadCaptureForm::class)->assertDontSee('Comercialización');
    }
}

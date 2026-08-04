<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/contacto?service=<code>` server-side preselection (RFC-074): a lead-eligible
 * code preselects the form; anything absent, unknown or ineligible is ignored
 * uniformly (HTTP 200, no selection). The preselection never grants eligibility
 * — the submit re-checks under lock.
 */
class FrontendServiceCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_eligible_service_is_preselected(): void
    {
        // comercializacion was backfilled with allow_leads=true.
        $html = $this->get('/contacto?service=comercializacion')->assertOk()->getContent();

        // Livewire mounts with the preselected value bound to service_type.
        $this->assertStringContainsString('comercializacion', $html);
    }

    public function test_an_ineligible_service_is_ignored_uniformly(): void
    {
        // inversion exists but allow_leads=false.
        $this->get('/contacto?service=inversion')->assertOk();
        // Unknown, over-long and missing are all treated the same: 200, no leak.
        $this->get('/contacto?service=does-not-exist')->assertOk();
        $this->get('/contacto?service='.str_repeat('x', 40))->assertOk();
        $this->get('/contacto')->assertOk();
    }

    public function test_a_disabled_service_cannot_be_preselected(): void
    {
        FrontendService::query()->where('service_type_code', 'comercializacion')->update(['allow_leads' => false]);

        // Now ineligible: still 200, but the submit path would reject it too.
        $this->get('/contacto?service=comercializacion')->assertOk();
    }
}

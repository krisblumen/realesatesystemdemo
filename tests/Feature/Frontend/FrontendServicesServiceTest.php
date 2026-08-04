<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendService;
use App\Models\ServiceType;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendServicesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The single eligibility rule (§16.6, RFC-074), fail-closed:
 *
 *   visible in L    ⇔ ServiceType.active AND FrontendService.show_in_L
 *   lead-eligible   ⇔ ServiceType.active AND FrontendService.allow_leads
 *
 * `ServiceType.active = false` always wins, and a missing FrontendService means
 * "not eligible" — there is no fallback that grants a commercial permission.
 */
class FrontendServicesServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The migration seeds "inversion"; clear both tables so each test owns
        // the exact set it asserts on.
        DB::table('frontend_services')->delete();
        DB::table('service_types')->delete();
    }

    private function service(): FrontendServicesService
    {
        return app(FrontendServicesService::class);
    }

    private function makeService(string $code, array $attrs = [], bool $active = true): FrontendService
    {
        ServiceType::query()->firstOrCreate(['code' => $code], ['label' => ucfirst($code), 'active' => $active]);
        ServiceType::query()->where('code', $code)->update(['active' => $active]);

        return FrontendService::query()->create(array_merge([
            'service_type_code' => $code,
            'title' => ucfirst($code),
            'short_description' => 'x',
            'show_in_home' => true,
            'show_in_services' => true,
            'allow_leads' => true,
            'sort_order' => 1,
        ], $attrs));
    }

    private function bump(): void
    {
        app(FrontendCacheGeneration::class)->bump();
    }

    public function test_home_lists_only_active_services_shown_in_home_in_order(): void
    {
        $this->makeService('b', ['show_in_home' => true, 'sort_order' => 2]);
        $this->makeService('a', ['show_in_home' => true, 'sort_order' => 1]);
        $this->makeService('c', ['show_in_home' => false, 'sort_order' => 3]);
        $this->bump();

        $codes = array_column($this->service()->services('home'), 'code');

        $this->assertSame(['a', 'b'], $codes, 'Only show_in_home, ordered by sort_order.');
    }

    public function test_an_inactive_service_type_hides_the_service_everywhere(): void
    {
        $this->makeService('x', ['show_in_home' => true, 'allow_leads' => true], active: false);
        $this->bump();

        $this->assertSame([], $this->service()->services('home'));
        $this->assertFalse($this->service()->isLeadEligible('x'), 'active=false always wins.');
    }

    public function test_a_service_without_a_frontend_row_is_not_eligible(): void
    {
        // The type exists and is active, but there is no FrontendService: fail
        // closed — no commercial permission is granted by default. A decoy row
        // keeps the table INITIALIZED so this exercises fail-closed, not the
        // uninitialized-table fallback (§16.7).
        $this->makeService('decoy', ['show_in_home' => false]);
        ServiceType::query()->create(['code' => 'orphan', 'label' => 'Orphan', 'active' => true]);
        $this->bump();

        $this->assertSame([], $this->service()->services('home'));
        $this->assertFalse($this->service()->isLeadEligible('orphan'));
    }

    public function test_lead_eligibility_needs_both_active_and_allow_leads(): void
    {
        $this->makeService('yes', ['allow_leads' => true]);
        $this->makeService('no', ['allow_leads' => false]);
        $this->bump();

        $this->assertTrue($this->service()->isLeadEligible('yes'));
        $this->assertFalse($this->service()->isLeadEligible('no'));
        $this->assertFalse($this->service()->isLeadEligible('missing'));
    }

    public function test_the_derived_cta_only_appears_when_leads_are_allowed(): void
    {
        $this->makeService('lead', ['show_in_services' => true, 'allow_leads' => true]);
        $this->makeService('info', ['show_in_services' => true, 'allow_leads' => false]);
        $this->bump();

        $byCode = collect($this->service()->services('servicios'))->keyBy('code');

        $this->assertSame(route('leads.create', ['service' => 'lead']), $byCode['lead']['cta']['url']);
        $this->assertNull($byCode['info']['cta'], 'A service that does not accept leads has no forced-lead CTA.');
    }
}

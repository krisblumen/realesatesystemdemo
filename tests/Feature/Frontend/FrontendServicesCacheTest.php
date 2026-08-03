<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendService;
use App\Models\ServiceType;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendServicesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * services(location) caches each canonical location — `home` and `servicios`
 * (§16.8 / RFC-076, NOT the English `services`) — under its own generation key
 * `frontend:g{N}:services:{location}`. A service shown in one location cannot
 * appear in the other, and the two keys never collide.
 */
class FrontendServicesCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_servicios_locations_are_isolated_by_key(): void
    {
        Cache::flush();
        DB::table('frontend_services')->delete();
        DB::table('service_types')->delete();

        // One service is home-only, another is servicios-only: real isolation,
        // not just a non-colliding invalid call.
        ServiceType::query()->create(['code' => 'only_home', 'label' => 'Solo Home', 'active' => true]);
        ServiceType::query()->create(['code' => 'only_serv', 'label' => 'Solo Servicios', 'active' => true]);
        FrontendService::query()->create([
            'service_type_code' => 'only_home', 'title' => 'Solo Home',
            'show_in_home' => true, 'show_in_services' => false, 'allow_leads' => true, 'sort_order' => 1,
        ]);
        FrontendService::query()->create([
            'service_type_code' => 'only_serv', 'title' => 'Solo Servicios',
            'show_in_home' => false, 'show_in_services' => true, 'allow_leads' => true, 'sort_order' => 1,
        ]);
        app(FrontendCacheGeneration::class)->bump();

        $service = app(FrontendServicesService::class);
        $home = $service->services('home');
        $servicios = $service->services('servicios');

        // Each canonical location resolves ONLY its own service.
        $this->assertSame(['only_home'], array_column($home, 'code'));
        $this->assertSame(['only_serv'], array_column($servicios, 'code'), 'The servicios location resolves its own list, not empty.');

        // Both were cached under their own generation key, and the two keys hold
        // different values — no cross-location collision.
        $n = app(FrontendCacheGeneration::class)->current();
        $homeCached = Cache::get("frontend:g{$n}:services:home:v1");
        $serviciosCached = Cache::get("frontend:g{$n}:services:servicios:v1");

        $this->assertSame(['only_home'], array_column((array) $homeCached, 'code'));
        $this->assertSame(['only_serv'], array_column((array) $serviciosCached, 'code'));
        $this->assertNotSame($homeCached, $serviciosCached, 'The two location keys never hold the same value.');
    }

    public function test_the_english_alias_is_not_a_valid_location(): void
    {
        Cache::flush();

        // `services` was the pre-correction alias; it is NOT the public contract.
        // An unknown location yields an empty list, never the servicios data.
        $this->assertSame([], app(FrontendServicesService::class)->services('services'));
    }
}

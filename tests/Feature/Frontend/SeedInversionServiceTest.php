<?php

namespace Tests\Feature\Frontend;

use App\Actions\Frontend\SeedInversionService;
use App\Models\FrontendService;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reconciling "Inversión inmobiliaria" (B-5/M-5): the action is insert-if-
 * missing and NON-DESTRUCTIVE, so the migration, a seeder or a test can run it
 * any number of times without clobbering a row an owner customised.
 */
class SeedInversionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_already_seeded_inversion_as_a_non_lead_service(): void
    {
        // up() invokes the action, so a fresh database already has it.
        $this->assertTrue(ServiceType::query()->where('code', 'inversion')->where('active', true)->exists());

        $service = FrontendService::query()->where('service_type_code', 'inversion')->firstOrFail();
        $this->assertTrue($service->show_in_home);
        $this->assertTrue($service->show_in_services);
        $this->assertFalse($service->allow_leads, 'The current form does not offer inversion.');
    }

    public function test_running_it_again_creates_nothing_and_overwrites_nothing(): void
    {
        // Simulate an owner who turned leads on and renamed the service.
        FrontendService::query()->where('service_type_code', 'inversion')
            ->update(['allow_leads' => true, 'title' => 'Personalizado']);

        app(SeedInversionService::class)->run();
        app(SeedInversionService::class)->run();

        $this->assertSame(1, FrontendService::query()->where('service_type_code', 'inversion')->count());

        $service = FrontendService::query()->where('service_type_code', 'inversion')->firstOrFail();
        $this->assertTrue($service->allow_leads, 'A customised toggle must survive re-seeding.');
        $this->assertSame('Personalizado', $service->title, 'A customised title must survive re-seeding.');
    }
}

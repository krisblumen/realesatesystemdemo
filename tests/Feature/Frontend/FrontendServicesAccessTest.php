<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendServiceResource;
use App\Models\FrontendService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner-only, for real (§16.2 / RFC-074): the services module is edited only by
 * owner. admin keeps ServiceTypeResource but must not reach this module, and a
 * non-owner gets a real 403 on the resource pages.
 */
class FrontendServicesAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_only_the_owner_can_view_the_services_module(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());
        $this->assertTrue(FrontendServiceResource::canViewAny());

        foreach (['admin', 'agente', 'arquitectura'] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create());
            $this->assertFalse(FrontendServiceResource::canViewAny(), "`{$role}` must not reach the services module.");
        }
    }

    public function test_a_non_owner_is_forbidden_from_the_index(): void
    {
        $this->actingAs(User::factory()->withRole('admin')->create());

        $this->get(FrontendServiceResource::getUrl('index'))->assertForbidden();
    }

    public function test_the_owner_can_open_the_editor(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        $service = FrontendService::query()->where('service_type_code', 'comercializacion')->firstOrFail();

        $this->get(FrontendServiceResource::getUrl('edit', ['record' => $service]))->assertOk();
    }

    public function test_a_frontend_service_cannot_be_force_deleted(): void
    {
        // forceDelete would let Spatie drop the referenced media; the policy bars it.
        $owner = User::factory()->withRole('owner')->create();
        $service = FrontendService::query()->where('service_type_code', 'comercializacion')->firstOrFail();

        $this->assertFalse($owner->can('forceDelete', $service));
        $this->assertFalse($owner->can('delete', $service));
        $this->assertFalse($owner->can('create', FrontendService::class));
    }
}

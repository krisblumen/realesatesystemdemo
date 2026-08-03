<?php

namespace Tests\Feature\Owners;

use App\Enums\PropertyStatus;
use App\Enums\ZoneStatus;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\Zone;
use App\Services\PropertyStatusService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PropertyPublicationOwnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_cannot_publish_without_owner(): void
    {
        $property = $this->publishableProperty(['owner_id' => null]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('propietario');

        app(PropertyStatusService::class)->transition($property, PropertyStatus::Publicado);
    }

    public function test_cannot_publish_without_commission(): void
    {
        $property = $this->publishableProperty(['commission_percentage' => null]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('comisión');

        app(PropertyStatusService::class)->transition($property, PropertyStatus::Publicado);
    }

    public function test_publishes_with_owner_and_commission(): void
    {
        $property = $this->publishableProperty();

        app(PropertyStatusService::class)->transition($property, PropertyStatus::Publicado);

        $this->assertSame(PropertyStatus::Publicado, $property->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishableProperty(array $overrides = []): Property
    {
        $zone = Zone::factory()->withPolygon()->create(['status' => ZoneStatus::Active]);
        $agent = User::factory()->withRole('agente')->active()->create();
        $owner = PropertyOwner::factory()->create(['agent_id' => $agent->id]);

        $property = Property::factory()->create(array_merge([
            'zone_id' => $zone->id,
            'agent_id' => $agent->id,
            'owner_id' => $owner->id,
            'commission_percentage' => 5.0,
            'street' => 'Av. Constituyentes',
            'colonia' => 'Centro',
        ], $overrides));

        $property->addMediaFromString(file_get_contents(public_path('images/brand/favicon.png')))
            ->usingFileName('cover.png')
            ->toMediaCollection('cover');

        return $property->fresh();
    }
}

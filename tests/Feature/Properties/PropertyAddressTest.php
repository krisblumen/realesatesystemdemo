<?php

namespace Tests\Feature\Properties;

use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PropertyAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_properties_table_has_address_columns(): void
    {
        foreach (['street', 'exterior_number', 'interior_number', 'colonia', 'postal_code'] as $column) {
            $this->assertTrue(Schema::hasColumn('properties', $column), "Missing properties.{$column}");
        }
    }

    public function test_address_fields_are_fillable_and_persist(): void
    {
        $zone = Zone::factory()->create();
        $property = Property::factory()->create([
            'zone_id' => $zone->id,
            'street' => 'Av. Constituyentes',
            'exterior_number' => '123',
            'interior_number' => 'A',
            'colonia' => 'Centro',
            'postal_code' => '76000',
        ]);

        $property->refresh();
        $this->assertSame('Av. Constituyentes', $property->street);
        $this->assertSame('123', $property->exterior_number);
        $this->assertSame('A', $property->interior_number);
        $this->assertSame('Centro', $property->colonia);
        $this->assertSame('76000', $property->postal_code);
    }

    public function test_precise_address_visible_only_to_assigned_agent_owner_and_admin(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $admin = User::factory()->withRole('admin')->create();
        $assignedAgent = User::factory()->withRole('agente')->create();
        $otherAgent = User::factory()->withRole('agente')->create();

        $property = Property::factory()->create([
            'agent_id' => $assignedAgent->id,
            'street' => 'Calle Privada',
            'exterior_number' => '45',
            'colonia' => 'Centro',
        ]);

        $this->assertTrue($property->preciseAddressVisibleTo($owner));
        $this->assertTrue($property->preciseAddressVisibleTo($admin));
        $this->assertTrue($property->preciseAddressVisibleTo($assignedAgent));
        $this->assertFalse($property->preciseAddressVisibleTo($otherAgent));
    }

    public function test_publishing_requires_street_and_colonia(): void
    {
        // published() cumple todos los invariantes (incluida la dirección);
        // al quitar la dirección, la publicación debe quedar inválida.
        $property = Property::factory()->published()->create();
        $property->forceFill(['street' => null, 'colonia' => null])->saveQuietly();

        $this->expectException(ValidationException::class);
        $property->refresh()->assertPublishedInvariant();
    }
}

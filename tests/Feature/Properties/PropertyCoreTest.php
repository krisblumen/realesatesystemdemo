<?php

namespace Tests\Feature\Properties;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_properties_table_exposes_the_approved_contract(): void
    {
        $this->assertTrue(Schema::hasTable('properties'));

        foreach ([
            'id', 'title', 'slug', 'description', 'operation_type', 'property_type', 'status',
            'price', 'bedrooms', 'bathrooms', 'parking_spaces', 'land_area', 'construction_area',
            'zone_id', 'agent_id', 'meta_title', 'meta_description', 'canonical_url',
            'created_at', 'updated_at', 'deleted_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('properties', $column), "Missing properties.{$column} column.");
        }
    }

    public function test_factory_persists_default_status_casts_and_relationships(): void
    {
        $zone = Zone::factory()->create();
        $agent = User::factory()->create();
        $property = Property::factory()->create([
            'zone_id' => $zone->id,
            'agent_id' => $agent->id,
            'bathrooms' => 2.5,
        ]);

        $this->assertSame(PropertyStatus::Borrador, $property->status);
        $this->assertSame(OperationType::Venta, $property->operation_type);
        $this->assertSame(PropertyType::Casa, $property->property_type);
        $this->assertSame('2.5', $property->bathrooms);
        $this->assertNotSame('', $property->slug);
        $this->assertInstanceOf(BelongsTo::class, $property->zone());
        $this->assertInstanceOf(BelongsTo::class, $property->agent());
        $this->assertTrue($property->zone->is($zone));
        $this->assertTrue($property->agent->is($agent));
    }

    public function test_user_and_zone_property_contracts_resolve_the_real_model(): void
    {
        $zone = Zone::factory()->create();
        $agent = User::factory()->create();
        $property = Property::factory()->create([
            'zone_id' => $zone->id,
            'agent_id' => $agent->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $zone->properties());
        $this->assertInstanceOf(HasMany::class, $agent->properties());
        $this->assertSame(Property::class, $zone->properties()->getRelated()::class);
        $this->assertSame(Property::class, $agent->properties()->getRelated()::class);
        $this->assertTrue($zone->properties()->firstOrFail()->is($property));
        $this->assertTrue($agent->properties()->firstOrFail()->is($property));
    }

    public function test_status_and_slug_are_not_mass_assignable(): void
    {
        $property = Property::factory()->create();
        $originalSlug = $property->slug;

        $property->fill([
            'status' => PropertyStatus::Publicado->value,
            'slug' => 'forced-slug',
        ]);

        $this->assertSame(PropertyStatus::Borrador, $property->status);
        $this->assertSame($originalSlug, $property->slug);

        $property->update(['status' => PropertyStatus::Publicado->value]);

        $this->assertSame(PropertyStatus::Borrador, $property->refresh()->status);
    }

    public function test_database_rejects_non_positive_price(): void
    {
        $base = [
            'title' => 'Invalid property',
            'slug' => 'invalid-property',
            'operation_type' => OperationType::Venta->value,
            'property_type' => PropertyType::Casa->value,
            'status' => PropertyStatus::Borrador->value,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->expectException(QueryException::class);
        DB::table('properties')->insert($base + ['price' => 0]);
    }

    public function test_database_rejects_negative_metrics(): void
    {
        $this->expectException(QueryException::class);

        DB::table('properties')->insert([
            'title' => 'Invalid property',
            'slug' => 'negative-area',
            'operation_type' => OperationType::Venta->value,
            'property_type' => PropertyType::Casa->value,
            'status' => PropertyStatus::Borrador->value,
            'price' => 100,
            'land_area' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature\Properties;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Support\PropertySlugGenerator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertySlugConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_persist_retries_when_another_insert_wins_the_slug_race(): void
    {
        $property = new Property([
            'title' => 'Concurrent property',
            'operation_type' => OperationType::Venta,
            'property_type' => PropertyType::Casa,
            'price' => 1_000_000,
        ]);

        $generator = new class extends PropertySlugGenerator
        {
            private bool $competitorInserted = false;

            public function generate(Property $property, ?int $ignoreId = null): string
            {
                $slug = parent::generate($property, $ignoreId);

                if (! $this->competitorInserted) {
                    $this->competitorInserted = true;
                    DB::table('properties')->insert([
                        'title' => 'Competing property',
                        'slug' => $slug,
                        'operation_type' => OperationType::Venta->value,
                        'property_type' => PropertyType::Casa->value,
                        'status' => PropertyStatus::Borrador->value,
                        'price' => 1_000_000,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $slug;
            }
        };

        $generator->persist($property);

        $this->assertSame('casa-concurrent-property-2', $property->slug);
        $this->assertDatabaseCount('properties', 2);
    }
}

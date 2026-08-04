<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\PostalCode;
use Database\Seeders\MunicipalitySeeder;
use Database\Seeders\PostalCodeSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostalCodeSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedGeoCatalog(): void
    {
        Country::create(['name' => 'México', 'iso2' => 'MX', 'clave' => 'MEX']);
        $this->seed(StateSeeder::class);
        $this->seed(MunicipalitySeeder::class);
    }

    public function test_postal_codes_table_has_unique_composite_constraint(): void
    {
        $this->assertTrue(Schema::hasTable('postal_codes'));
        $this->assertTrue(Schema::hasColumn('postal_codes', 'postal_code'));
        $this->assertTrue(Schema::hasColumn('postal_codes', 'colonia'));

        PostalCode::create(['postal_code' => '76000', 'colonia' => 'Centro']);

        $this->expectException(QueryException::class);

        PostalCode::create(['postal_code' => '76000', 'colonia' => 'Centro']);
    }

    public function test_same_postal_code_admits_multiple_colonias(): void
    {
        PostalCode::create(['postal_code' => '76000', 'colonia' => 'Centro']);
        PostalCode::create(['postal_code' => '76000', 'colonia' => 'Niños Héroes']);

        $this->assertSame(2, PostalCode::query()->where('postal_code', '76000')->count());
    }

    public function test_seeds_queretaro_postal_codes_with_resolved_fks(): void
    {
        $this->seedGeoCatalog();

        $this->call_seeder();

        $this->assertGreaterThan(0, PostalCode::query()->count());

        $sample = PostalCode::query()->whereNotNull('municipality_id')->first();
        $this->assertNotNull($sample);
        $this->assertNotNull($sample->state_id);
        $this->assertSame('22', $sample->state->inegi_code);
    }

    private function call_seeder(): void
    {
        $this->seed(PostalCodeSeeder::class);
    }
}

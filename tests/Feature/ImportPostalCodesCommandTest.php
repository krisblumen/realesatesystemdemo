<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Municipality;
use App\Models\PostalCode;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportPostalCodesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fixturePath(): string
    {
        return base_path('tests/Fixtures/postal_codes');
    }

    public function test_imports_from_geojson_fixture(): void
    {
        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        // 4 distinct CPs: 20049, 20050, 20051 (merged from 2 features), 06600 (padded).
        $this->assertSame(4, DB::table('postal_code_areas')->count());

        $this->assertDatabaseHas('postal_code_areas', ['postal_code' => '20049']);
        $this->assertDatabaseHas('postal_code_areas', ['postal_code' => '20050']);
        $this->assertDatabaseHas('postal_code_areas', ['postal_code' => '20051']);
        $this->assertDatabaseHas('postal_code_areas', ['postal_code' => '06600']);

        foreach (['20049', '20050', '20051', '06600'] as $cp) {
            $polygon = DB::table('postal_code_areas')
                ->where('postal_code', $cp)
                ->value('polygon');

            $this->assertNotNull($polygon, "polygon for {$cp} should not be null");
        }
    }

    public function test_import_is_idempotent(): void
    {
        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        $firstCount = DB::table('postal_code_areas')->count();

        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        $secondCount = DB::table('postal_code_areas')->count();

        $this->assertSame($firstCount, $secondCount);
    }

    public function test_linkage_sets_municipality_id_when_clave_matches(): void
    {
        $country = Country::create(['name' => 'Mexico', 'iso2' => 'MX', 'clave' => 'MEX']);
        $state = State::create(['country_id' => $country->id, 'name' => 'Aguascalientes', 'clave' => 'AGS', 'source_id' => 1]);
        $municipality = Municipality::create(['state_id' => $state->id, 'name' => 'Aguascalientes', 'clave' => '20049', 'source_id' => 1]);

        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        $this->assertDatabaseHas('postal_code_areas', [
            'postal_code' => '20049',
            'municipality_id' => $municipality->id,
            'state_id' => $state->id,
        ]);
    }

    public function test_municipality_id_is_null_when_no_clave_match(): void
    {
        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        $this->assertDatabaseHas('postal_code_areas', [
            'postal_code' => '20051',
            'municipality_id' => null,
            'state_id' => null,
        ]);
    }

    public function test_polygon_geometry_type_stored_as_multipolygon(): void
    {
        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        // CP 20049 comes from a Polygon feature; must be normalized to MultiPolygon.
        $geometryType = DB::selectOne(
            <<<'SQL'
            SELECT ST_GeometryType(polygon) AS geom_type
            FROM postal_code_areas
            WHERE postal_code = ?
            SQL,
            ['20049'],
        );

        $this->assertSame('ST_MultiPolygon', $geometryType->geom_type);
    }

    public function test_normalizes_cp_without_leading_zero(): void
    {
        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        // Fixture has "d_codigo": 6600 (integer, no leading zero) -> must normalize to '06600'.
        $this->assertDatabaseHas('postal_code_areas', ['postal_code' => '06600']);
        $this->assertDatabaseMissing('postal_code_areas', ['postal_code' => '6600']);
    }

    public function test_linkage_prioritizes_postal_codes_table_over_legacy_clave(): void
    {
        $country = Country::create(['name' => 'Mexico', 'iso2' => 'MX', 'clave' => 'MEX']);
        $state = State::create(['country_id' => $country->id, 'name' => 'Aguascalientes', 'clave' => 'AGS', 'source_id' => 1]);
        // Legacy clave match would resolve to this municipality...
        $legacyMunicipality = Municipality::create(['state_id' => $state->id, 'name' => 'Aguascalientes', 'clave' => '20049', 'source_id' => 1]);
        // ...but a postal_codes row exists for the same CP and must win instead.
        $preferredMunicipality = Municipality::create(['state_id' => $state->id, 'name' => 'Jesus Maria', 'clave' => null, 'source_id' => 2]);
        PostalCode::create([
            'postal_code' => '20049',
            'colonia' => 'Centro',
            'municipality_id' => $preferredMunicipality->id,
            'state_id' => $state->id,
        ]);

        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        $this->assertDatabaseHas('postal_code_areas', [
            'postal_code' => '20049',
            'municipality_id' => $preferredMunicipality->id,
        ]);
        $this->assertDatabaseMissing('postal_code_areas', [
            'postal_code' => '20049',
            'municipality_id' => $legacyMunicipality->id,
        ]);
    }

    public function test_linkage_falls_back_to_legacy_clave_when_no_postal_codes_match(): void
    {
        $country = Country::create(['name' => 'Mexico', 'iso2' => 'MX', 'clave' => 'MEX']);
        $state = State::create(['country_id' => $country->id, 'name' => 'Aguascalientes', 'clave' => 'AGS', 'source_id' => 1]);
        $municipality = Municipality::create(['state_id' => $state->id, 'name' => 'Aguascalientes', 'clave' => '20049', 'source_id' => 1]);

        // No PostalCode row for '20049' -> must fall back to the legacy clave match.
        $this->artisan('geo:import-postal-codes', ['--path' => $this->fixturePath()])
            ->assertExitCode(0);

        $this->assertDatabaseHas('postal_code_areas', [
            'postal_code' => '20049',
            'municipality_id' => $municipality->id,
        ]);
    }
}

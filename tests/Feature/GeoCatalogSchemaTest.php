<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Municipality;
use App\Models\State;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeoCatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_states_table_has_unique_inegi_code_column(): void
    {
        $this->assertTrue(Schema::hasColumn('states', 'inegi_code'));

        $country = Country::create(['name' => 'Mexico', 'iso2' => 'MX', 'clave' => 'MEX']);

        State::create(['country_id' => $country->id, 'name' => 'Aguascalientes', 'inegi_code' => '01']);

        $this->expectException(QueryException::class);

        State::create(['country_id' => $country->id, 'name' => 'Baja California', 'inegi_code' => '01']);
    }

    public function test_municipalities_table_has_inegi_code_unique_per_state(): void
    {
        $this->assertTrue(Schema::hasColumn('municipalities', 'inegi_code'));

        $country = Country::create(['name' => 'Mexico', 'iso2' => 'MX', 'clave' => 'MEX']);
        $stateA = State::create(['country_id' => $country->id, 'name' => 'Aguascalientes', 'inegi_code' => '01']);
        $stateB = State::create(['country_id' => $country->id, 'name' => 'Baja California', 'inegi_code' => '02']);

        // Same inegi_code in two different states must be allowed (unique is composite).
        Municipality::create(['state_id' => $stateA->id, 'name' => 'Aguascalientes', 'inegi_code' => '001']);
        Municipality::create(['state_id' => $stateB->id, 'name' => 'Ensenada', 'inegi_code' => '001']);

        $this->assertSame(2, Municipality::query()->where('inegi_code', '001')->count());

        $this->expectException(QueryException::class);

        Municipality::create(['state_id' => $stateA->id, 'name' => 'Calvillo', 'inegi_code' => '001']);
    }
}

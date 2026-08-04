<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Municipality;
use App\Models\State;
use Database\Seeders\GeoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_only_mexico_one_country_and_thirty_two_states(): void
    {
        $this->seed(GeoCatalogSeeder::class);

        $this->assertSame(1, Country::query()->count());
        $this->assertSame(32, State::query()->count());
        $this->assertTrue(Country::query()->where('name', 'México')->where('clave', 'MEX')->exists());
        $this->assertFalse(Country::query()->where('name', 'Estados Unidos')->orWhere('clave', 'USA')->exists());
    }

    public function test_every_municipality_belongs_to_a_mexican_state(): void
    {
        $this->seed(GeoCatalogSeeder::class);

        $orphanCount = Municipality::query()->whereDoesntHave('state.country', function ($query): void {
            $query->where('name', 'México');
        })->count();

        $this->assertSame(0, $orphanCount);
        $this->assertGreaterThan(0, Municipality::query()->count());
    }

    public function test_states_have_inegi_code_populated_after_seeding(): void
    {
        $this->seed(GeoCatalogSeeder::class);

        $this->assertSame(32, State::query()->count());
        $this->assertSame(32, State::query()->whereNotNull('inegi_code')->count());
    }

    public function test_municipalities_have_inegi_code_populated_after_seeding(): void
    {
        $this->seed(GeoCatalogSeeder::class);

        $total = Municipality::query()->count();
        $withInegiCode = Municipality::query()->whereNotNull('inegi_code')->count();

        $this->assertGreaterThan(0, $total);
        $this->assertSame($total, $withInegiCode);
    }

    public function test_re_running_seeder_is_idempotent(): void
    {
        $this->seed(GeoCatalogSeeder::class);

        $countryCount = Country::query()->count();
        $stateCount = State::query()->count();
        $municipalityCount = Municipality::query()->count();

        $this->seed(GeoCatalogSeeder::class);

        $this->assertSame($countryCount, Country::query()->count());
        $this->assertSame($stateCount, State::query()->count());
        $this->assertSame($municipalityCount, Municipality::query()->count());
    }
}

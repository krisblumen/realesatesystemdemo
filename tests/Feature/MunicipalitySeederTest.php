<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Municipality;
use App\Models\State;
use Database\Seeders\MunicipalitySeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_municipalities_linked_to_real_state_via_inegi_code(): void
    {
        Country::create(['name' => 'México', 'iso2' => 'MX', 'clave' => 'MEX']);
        $this->seed(StateSeeder::class);

        $this->seed(MunicipalitySeeder::class);

        // The xlsx has 2,478 data rows, but the seeder upserts by (state_id, name) per the
        // design's "IMPORTANTE" callout. Oaxaca (inegi '20') has 2 genuine name collisions in
        // the raw xlsx: "SAN JUAN MIXTEPEC" (municipality_id 208 and 209) and
        // "SAN PEDRO MIXTEPEC" (318 and 319) -- these are distinct INEGI municipalities whose
        // bare names collide once normalized, so the second row's inegi_code wins the upsert
        // and only 2,476 rows persist. See PR3 task notes for the full row-count diff.
        $this->assertSame(2476, Municipality::query()->count());
        $this->assertSame(2476, Municipality::query()->whereNotNull('inegi_code')->count());

        $queretaroStateId = State::query()->where('inegi_code', '22')->value('id');
        $this->assertNotNull($queretaroStateId);
        $this->assertGreaterThan(0, Municipality::query()->where('state_id', $queretaroStateId)->count());
    }

    public function test_municipality_name_is_not_raw_uppercase(): void
    {
        Country::create(['name' => 'México', 'iso2' => 'MX', 'clave' => 'MEX']);
        $this->seed(StateSeeder::class);

        $this->seed(MunicipalitySeeder::class);

        $jesusMaria = Municipality::query()->where('name', 'Jesús María')->first();
        $this->assertNotNull($jesusMaria);
        $this->assertNotSame('JESÚS MARÍA', $jesusMaria->name);
    }

    public function test_re_running_seeder_does_not_duplicate_rows(): void
    {
        Country::create(['name' => 'México', 'iso2' => 'MX', 'clave' => 'MEX']);
        $this->seed(StateSeeder::class);

        $this->seed(MunicipalitySeeder::class);
        $this->seed(MunicipalitySeeder::class);

        $this->assertSame(2476, Municipality::query()->count());
    }
}

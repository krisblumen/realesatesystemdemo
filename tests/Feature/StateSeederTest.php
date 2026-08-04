<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\State;
use Database\Seeders\StateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_thirty_two_states_with_inegi_code(): void
    {
        Country::create(['name' => 'México', 'iso2' => 'MX', 'clave' => 'MEX']);

        $this->seed(StateSeeder::class);

        $this->assertSame(32, State::query()->count());
        $this->assertSame(32, State::query()->whereNotNull('inegi_code')->count());

        $queretaro = State::query()->where('inegi_code', '22')->first();
        $this->assertNotNull($queretaro);
        $this->assertSame('Querétaro', $queretaro->name);

        $mexico = State::query()->where('inegi_code', '15')->first();
        $this->assertNotNull($mexico);
        $this->assertSame('México', $mexico->name);
    }

    public function test_re_running_seeder_does_not_duplicate_rows(): void
    {
        Country::create(['name' => 'México', 'iso2' => 'MX', 'clave' => 'MEX']);

        $this->seed(StateSeeder::class);
        $this->seed(StateSeeder::class);

        $this->assertSame(32, State::query()->count());
    }
}

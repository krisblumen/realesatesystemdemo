<?php

namespace Tests\Feature\Regression;

use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Epica3RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_crud_still_works(): void
    {
        $zone = Zone::factory()->create([
            'name' => 'Zona CRUD',
            'slug' => 'zona-crud',
        ]);

        $this->assertDatabaseHas('zones', ['id' => $zone->id, 'slug' => 'zona-crud']);

        $zone->update(['name' => 'Zona CRUD Actualizada']);

        $this->assertSame('Zona CRUD Actualizada', $zone->refresh()->name);

        $zone->delete();

        $this->assertSoftDeleted('zones', ['id' => $zone->id]);
    }

    public function test_postgis_extension_is_active(): void
    {
        $extension = DB::selectOne("SELECT extname FROM pg_extension WHERE extname = 'postgis'");

        $this->assertSame('postgis', $extension?->extname);
    }
}

<?php

namespace Tests\Feature\Zones;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ZonePolygonRequiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_polygon_column_is_not_nullable(): void
    {
        $column = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns WHERE table_name = 'zones' AND column_name = 'polygon'",
        );

        $this->assertSame('NO', $column->is_nullable);
    }

    public function test_database_rejects_a_zone_without_polygon(): void
    {
        $this->expectException(QueryException::class);

        DB::table('zones')->insert([
            'name' => 'Sin polígono',
            'slug' => 'sin-poligono',
            'municipality' => 'Querétaro',
            'status' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

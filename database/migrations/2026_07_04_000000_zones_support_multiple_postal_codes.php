<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una zona pasa a componerse de varios códigos postales: se agrega el pivote
     * zone_postal_code y la geometría de la zona pasa de Polygon a MultiPolygon
     * (agregado de los polígonos de los CP seleccionados).
     */
    public function up(): void
    {
        Schema::create('zone_postal_code', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('postal_code', 5);
            $table->timestamps();

            $table->unique(['zone_id', 'postal_code']);
            $table->index('postal_code');
        });

        // Backfill: el CP único actual de cada zona pasa a ser su primer CP del pivote.
        DB::table('zones')
            ->whereNotNull('postal_code')
            ->orderBy('id')
            ->select(['id', 'postal_code'])
            ->each(function (object $zone): void {
                DB::table('zone_postal_code')->insertOrIgnore([
                    'zone_id' => $zone->id,
                    'postal_code' => $zone->postal_code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // El CP único deja de ser obligatorio (ahora la fuente de verdad es el pivote).
        Schema::table('zones', function (Blueprint $table): void {
            $table->string('postal_code', 5)->nullable()->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            // Polygon -> MultiPolygon, convirtiendo lo existente con ST_Multi.
            DB::statement('DROP INDEX IF EXISTS zones_polygon_gist_idx');
            DB::statement('ALTER TABLE zones ALTER COLUMN polygon TYPE geometry(MultiPolygon, 4326) USING ST_Multi(polygon)');
            DB::statement('CREATE INDEX zones_polygon_gist_idx ON zones USING GIST (polygon)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS zones_polygon_gist_idx');
            DB::statement('ALTER TABLE zones ALTER COLUMN polygon TYPE geometry(Polygon, 4326) USING ST_GeometryN(polygon, 1)');
            DB::statement('CREATE INDEX zones_polygon_gist_idx ON zones USING GIST (polygon)');
        }

        Schema::dropIfExists('zone_postal_code');
    }
};

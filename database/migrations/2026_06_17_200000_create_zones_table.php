<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('municipality');
            $table->string('status', 20)->default('activa');
            $table->softDeletes();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
            DB::statement('ALTER TABLE zones ADD COLUMN polygon geometry(Polygon, 4326)');
            DB::statement('ALTER TABLE zones ADD COLUMN center_point geometry(Point, 4326)');
            DB::statement("ALTER TABLE zones ADD CONSTRAINT zones_status_check CHECK (status IN ('activa', 'inactiva'))");
            DB::statement('CREATE INDEX zones_polygon_gist_idx ON zones USING GIST (polygon)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};

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
        Schema::create('postal_code_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('postal_code', 5);
            $table->foreignId('municipality_id')
                ->nullable()
                ->constrained('municipalities')
                ->nullOnDelete();
            $table->foreignId('state_id')
                ->nullable()
                ->constrained('states')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique('postal_code');
            $table->index('municipality_id');
            $table->index('state_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
            DB::statement('ALTER TABLE postal_code_areas ADD COLUMN polygon geometry(MultiPolygon, 4326) NOT NULL');
            DB::statement('CREATE INDEX postal_code_areas_polygon_gist_idx ON postal_code_areas USING GIST (polygon)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postal_code_areas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Durable cache generation for the frontend kernel (RFC-076).
 *
 * A single physical row (id = 1, enforced by CHECK) holds the generation
 * counter used to build cache keys (frontend:g{N}:...). The migration seeds
 * the row itself: production deploys run migrate --force without seeders,
 * so the runtime must never depend on an initialization it cannot assume.
 *
 * Bumps are a single `UPDATE ... SET generation = generation + 1 ... RETURNING`
 * (see FrontendCacheGeneration): Cache::increment() is banned because on the
 * database store it returns false when the key does not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_cache_generation', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->bigInteger('generation')->default(1);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE frontend_cache_generation
            ADD CONSTRAINT frontend_cache_generation_single_row_check
            CHECK (id = 1)
        SQL);

        // Idempotent seed: re-running against a provisioned database is a no-op.
        DB::statement(<<<'SQL'
            INSERT INTO frontend_cache_generation (id, generation)
            VALUES (1, 1)
            ON CONFLICT (id) DO NOTHING
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_cache_generation');
    }
};

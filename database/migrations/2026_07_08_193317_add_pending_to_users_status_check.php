<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('activo', 'suspendido', 'pendiente'))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('activo', 'suspendido'))"
            );
        }
    }
};

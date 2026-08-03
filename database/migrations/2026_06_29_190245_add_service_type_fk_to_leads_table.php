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
        // Los datos de catálogo de los que depende este FK deben existir antes
        // de crear el constraint. `php artisan migrate` no corre seeders, así que
        // los garantizamos aquí (idempotente) para no violar el FK con leads que
        // ya traen el default 'comercializacion'.
        $now = now();

        $types = [
            ['code' => 'comercializacion', 'label' => 'Comercialización', 'color' => 'info', 'sort_order' => 1],
            ['code' => 'arquitectura', 'label' => 'Arquitectura', 'color' => 'warning', 'sort_order' => 2],
            ['code' => 'construccion', 'label' => 'Construcción', 'color' => 'success', 'sort_order' => 3],
        ];

        foreach ($types as $type) {
            DB::table('service_types')->updateOrInsert(
                ['code' => $type['code']],
                [...$type, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        Schema::table('leads', function (Blueprint $table): void {
            $table->foreign('service_type')
                ->references('code')
                ->on('service_types')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropForeign(['service_type']);
        });
    }
};

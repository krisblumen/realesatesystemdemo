<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Dirección física precisa del inmueble. Estado/municipio NO se duplican:
            // se derivan de la zona (deben coincidir). colonia y postal_code son
            // snapshot tomado del catálogo postal_codes del municipio de la zona.
            $table->string('street')->nullable()->after('zone_id');
            $table->string('exterior_number', 30)->nullable()->after('street');
            $table->string('interior_number', 30)->nullable()->after('exterior_number');
            $table->string('colonia')->nullable()->after('interior_number');
            $table->string('postal_code', 10)->nullable()->after('colonia');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['street', 'exterior_number', 'interior_number', 'colonia', 'postal_code']);
        });
    }
};

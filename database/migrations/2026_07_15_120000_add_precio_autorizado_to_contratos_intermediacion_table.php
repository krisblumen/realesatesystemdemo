<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_intermediacion', function (Blueprint $table) {
            // Monto autorizado de venta o renta (RFC-063 / machote: precio_autorizado).
            $table->decimal('precio_autorizado', 15, 2)->nullable()->after('comision_porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_intermediacion', function (Blueprint $table) {
            $table->dropColumn('precio_autorizado');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_firma_evidencias', function (Blueprint $table) {
            $table->id();
            // C-1: tabla real 'contratos_intermediacion' (plural irregular), explícita.
            $table->foreignId('contrato_intermediacion_id')
                ->constrained('contratos_intermediacion')
                ->cascadeOnDelete();
            $table->string('ip', 45);              // IPv6-safe
            $table->string('user_agent', 500);
            $table->timestamp('firmado_at');       // hora de SERVIDOR (no del cliente)
            $table->string('firma_hash', 64);      // SHA-256 del PNG del trazo
            $table->timestamps();
            // La imagen del trazo vive en Media Library, colección 'firma' (disco privado).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_firma_evidencias');
    }
};

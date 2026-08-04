<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_eventos', function (Blueprint $table) {
            $table->id();
            // C-1: la tabla se llama 'contratos_intermediacion' (plural irregular). Laravel
            // inferiría 'contrato_intermediacions' desde la columna; se fuerza la tabla real.
            $table->foreignId('contrato_intermediacion_id')
                ->constrained('contratos_intermediacion')
                ->cascadeOnDelete();
            // generado|enviado|leido|firmado|rechazado|cancelado|reenviado|qr_impreso|
            // privacidad_aceptada|eliminacion_confirmada
            $table->string('tipo');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['contrato_intermediacion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_eventos');
    }
};

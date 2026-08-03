<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_accesos', function (Blueprint $table) {
            $table->id();
            // C-1: tabla real 'contratos_intermediacion' (plural irregular), explícita.
            $table->foreignId('contrato_intermediacion_id')
                ->constrained('contratos_intermediacion')
                ->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();  // SHA-256 del token; el claro nunca se guarda
            $table->timestamp('expira_at');
            $table->timestamp('usado_at')->nullable();    // sella el "un solo uso"
            $table->string('emitido_por');                // App\Enums\OrigenAccesoContrato: inicial|reenvio
            $table->timestamps();

            $table->index(['contrato_intermediacion_id', 'usado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_accesos');
    }
};

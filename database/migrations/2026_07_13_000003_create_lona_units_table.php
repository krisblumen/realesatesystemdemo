<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lona_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lona_batch_id')->constrained('lona_batches')->cascadeOnDelete();
            // Denormalizado desde el lote: evita un join en la consulta de elegibilidad
            // ("¿tiene unidades sin colocar de este tipo?").
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('operation_type'); // App\Enums\OperationType, denormalizado del lote
            $table->string('status')->default('pendiente_colocacion'); // App\Enums\LonaUnitStatus
            // Inmueble/ubicación REAL de colocación de esta unidad. Nace null y lo fija el
            // agente al colocar la lona (RFC-062 5.1/5.4). NO se copia del lote ni del QR.
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('ubicacion_referencia')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agent_id', 'operation_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lona_units');
    }
};

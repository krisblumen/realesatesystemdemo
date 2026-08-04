<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lona_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('operation_type'); // App\Enums\OperationType (venta/renta)
            $table->unsignedInteger('cantidad_solicitada');
            $table->string('estado')->default('pendiente'); // App\Enums\LonaRequestStatus
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete(); // opcional, para el QR del PDF
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('motivo_rechazo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agent_id', 'operation_type']);
        });

        // Garantía real contra solicitudes redundantes/concurrentes (RFC-062 R-5, C-1):
        // a lo sumo una solicitud "pendiente" por agente + tipo. Un exists() a nivel de
        // aplicación tiene ventana de carrera; este índice parcial la cierra en el motor.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX lona_requests_agent_tipo_pendiente_unique
            ON lona_requests (agent_id, operation_type)
            WHERE estado = 'pendiente' AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('lona_requests');
    }
};

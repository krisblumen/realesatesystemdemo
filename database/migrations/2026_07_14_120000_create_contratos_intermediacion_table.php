<?php

use App\Enums\EstadoContrato;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_intermediacion', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('folio', 8)->unique();                     // único GLOBAL (RFC-058)
            $table->string('estado')->default(EstadoContrato::Generado->value)->index();

            // Cliente (datos propios del contrato)
            $table->string('cliente_nombre');
            $table->string('cliente_telefono')->nullable();
            $table->string('cliente_email')->nullable();
            $table->string('cliente_direccion')->nullable();
            // Identificación oficial: NO columna — va a Media Library en disco privado.

            // Inmueble a promover (SIN FK a properties — D-2, contrato independiente del catálogo)
            $table->string('inmueble_tipo');
            $table->string('tipo_operacion');                         // App\Enums\TipoOperacionContrato
            $table->string('inmueble_direccion');
            $table->decimal('comision_porcentaje', 5, 2);

            // Condiciones
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();
            $table->boolean('exclusividad')->default(false);
            $table->string('plantilla_version')->default('v1');

            // Trazabilidad
            $table->foreignId('agente_id')->constrained('users');
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamp('firmado_at')->nullable();
            $table->timestamp('rechazado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->timestamp('expirado_at')->nullable();
            $table->timestamp('vencido_at')->nullable();
            $table->text('motivo_rechazo')->nullable();

            // Documento final (RFC-068)
            $table->string('documento_hash', 64)->nullable();          // SHA-256 hex del PDF final
            $table->timestamp('retencion_revisar_at')->nullable();     // firmado_at + 2 años
            $table->boolean('eliminacion_pendiente')->default(false)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index('agente_id');
            $table->index('tipo_operacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_intermediacion');
    }
};

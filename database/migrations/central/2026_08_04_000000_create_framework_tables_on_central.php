<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las tablas de infraestructura del framework, en la central.
 *
 * El diseño del lote A lo dice —«la tabla `sessions` y la de caché tienen que
 * existir en la central Y en la plantilla del inquilino»— y no estaba
 * implementado: las migraciones de la central sólo creaban `tenants`.
 *
 * SE NOTA RECIÉN EN EL SERVIDOR. La sesión y el caché usan la conexión POR
 * DEFECTO, y en el host central esa conexión es la central. Sin estas tablas,
 * la primera petición al host central muere con «relation sessions does not
 * exist» — antes de llegar a ninguna ruta, porque la sesión arranca primero.
 *
 * La cola va aparte y por otro motivo: está anclada a la central a propósito,
 * porque un worker no tiene subdominio del cual resolver un inquilino y porque
 * el trabajo que crea la base de un inquilino corre cuando esa base todavía no
 * existe.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        // La sesión del host central. Vive acá y no en ningún inquilino: quien
        // opera el padrón no pertenece a ninguno.
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // La cola. Anclada acá a propósito (RFC-03).
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['failed_jobs', 'job_batches', 'jobs', 'cache_locks', 'cache', 'sessions'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};

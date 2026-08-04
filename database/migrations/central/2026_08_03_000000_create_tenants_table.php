<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El padrón de inquilinos.
 *
 * Vive SÓLO en la base central, y por eso esta migración está en su propio
 * directorio: `php artisan migrate` recorre `database/migrations` con un glob
 * que no entra en subdirectorios, así que nunca alcanza a este archivo. Si
 * llegara a la plantilla, cada inquilino nacería con su propio padrón viéndose
 * sólo a sí mismo.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();

            // El slug es el subdominio Y el nombre de la base. Lo genera el
            // servidor con un formato cerrado (RFC-05).
            $table->string('slug', 32)->unique();

            // Se guarda y no se recalcula desde el slug al leer: si mañana
            // cambia la forma de nombrar las bases, los inquilinos viejos
            // tienen que seguir encontrando la suya.
            $table->string('database', 64)->unique();

            $table->string('estado', 20)->index();
            $table->string('email', 180);

            // Fase 2. Con invitación no hay altas repetidas que limitar, y
            // guardar un dato personal que nadie usa es guardarlo por nada.
            $table->string('origen_hash', 64)->nullable();

            $table->string('template_version', 20);

            // Indexado porque la tarea de expiración barre por acá.
            $table->timestamp('expira_en')->index();
            $table->timestamp('borrado_en')->nullable();

            // Por qué falló el alta o el borrado. La pide el padrón del
            // operador (RFC-12): sin esta columna esa pantalla no tiene qué
            // mostrar cuando algo sale mal.
            $table->text('motivo_falla')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

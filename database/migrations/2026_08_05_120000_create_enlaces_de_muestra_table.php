<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los enlaces con los que un inquilino le muestra su sitio a otra persona.
 *
 * Vive en la base DEL INQUILINO, no en la central: el enlace es suyo, se canjea
 * en su subdominio, y la base ya es la frontera. Un enlace de un inquilino no
 * puede alcanzar a otro porque no está en la misma base.
 *
 * Del token sólo se guarda el SHA-256, igual que en los accesos a contratos: el
 * claro se muestra una vez a quien lo genera y no queda escrito en ningún lado.
 * Si la base se filtra, los enlaces no sirven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enlaces_de_muestra', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expira_en');
            // Se REVOCA en vez de borrarse: quien generó un enlace nuevo puede
            // querer saber que el anterior existió, y borrar la fila deja la
            // pregunta «¿lo habré compartido?» sin respuesta.
            $table->timestamp('revocado_en')->nullable();
            $table->timestamps();

            $table->index(['revocado_en', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_de_muestra');
    }
};

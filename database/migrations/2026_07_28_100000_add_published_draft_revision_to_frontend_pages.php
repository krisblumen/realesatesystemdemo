<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra QUÉ borrador se publicó, para poder avisar que hay cambios sin
 * publicar.
 *
 * Hasta ahora no se podía saber. `revision` cuenta PUBLICACIONES y
 * `draft_revision` cuenta EDICIONES: son contadores independientes, así que
 * compararlos no dice nada —una página con 14 ediciones y 12 publicaciones
 * puede estar perfectamente al día o tener dos cambios pendientes—. El panel no
 * mostraba ningún aviso, y quien editaba se iba creyendo que el sitio ya
 * mostraba su cambio.
 *
 * **Se inicializa en NULL a propósito, incluso para páginas ya publicadas.**
 * Ponerle el `draft_revision` actual afirmaría «todo publicado» sobre páginas
 * que pueden tener cambios pendientes justo ahora, y un aviso que miente es peor
 * que no tenerlo. NULL significa «no se sabe», y el panel lo trata como
 * pendiente: publicar de nuevo es barato y deja el dato correcto desde la
 * primera vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frontend_pages', function (Blueprint $table): void {
            $table->unsignedBigInteger('published_draft_revision')->nullable()->after('draft_revision');
        });
    }

    public function down(): void
    {
        Schema::table('frontend_pages', function (Blueprint $table): void {
            $table->dropColumn('published_draft_revision');
        });
    }
};

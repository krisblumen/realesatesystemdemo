<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unicidad física de `frontend_services.image_media_id` (Épica 12.3 §5).
 *
 * La primera versión del diseño afirmó que dos servicios apuntando a la misma
 * imagen era «imposible por construcción». No lo era: la columna sólo tenía FK.
 * `FrontendMediaReference::isEligible()` valida una asignación individual desde
 * la aplicación, pero no impide que una escritura directa por SQL —o un camino
 * que saltee el servicio de dominio— deje dos filas vivas compartiendo uuid.
 *
 * La validación protege el camino de la aplicación; el índice protege la base.
 * Llamar «invariante» a la primera sola es llamar invariante a una convención.
 *
 * PARCIAL y en SQL crudo, con el mismo criterio que el índice de
 * `service_type_code` que ya existe (§16.1.2): un `unique()` de Blueprint
 * generaría un índice TOTAL, que bloquearía dos filas soft-deleted con la misma
 * imagen y dos filas con `image_media_id` nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX frontend_services_image_media_id_unique
                ON frontend_services (image_media_id)
                WHERE deleted_at IS NULL AND image_media_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS frontend_services_image_media_id_unique');
    }
};

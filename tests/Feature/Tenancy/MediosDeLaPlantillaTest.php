<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Support\Frontend\RutaDeMediosPorInquilino;
use App\Tenancy\InquilinoActual;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Dónde escribe sus archivos cada quien.
 *
 * La librería de medios deriva la ruta del identificador de la fila, y con una
 * base por inquilino esos identificadores ARRANCAN EN 1 EN CADA BASE. El prefijo
 * por inquilino resuelve el caso de dos inquilinos — pero sólo aplica cuando hay
 * uno resuelto, y construir una plantilla es un proceso de consola.
 *
 * SE COMPROBÓ EN DISCO, no en teoría: `storage/app/public/1/` terminó con
 * `casa-jardin.jpg` de una plantilla y el logo de otra base, juntos. Dos bases
 * distintas escribiendo en la misma carpeta, que es exactamente lo que este
 * generador existe para impedir.
 */
class MediosDeLaPlantillaTest extends TestCase
{
    private function rutaDe(int $id): string
    {
        $medio = new Media;
        $medio->id = $id;

        return (new RutaDeMediosPorInquilino)->getPath($medio);
    }

    protected function tearDown(): void
    {
        config(['tenancy.medios_de_plantilla' => null]);

        parent::tearDown();
    }

    public function test_the_template_writes_under_its_own_name(): void
    {
        config(['tenancy.medios_de_plantilla' => 'demo_template_v3']);

        $this->assertStringStartsWith('plantillas/demo_template_v3/', $this->rutaDe(1));
    }

    public function test_a_resolved_tenant_wins_over_the_template(): void
    {
        // Un inquilino resuelto no debería ver nunca el prefijo de plantilla: si
        // lo viera, escribiría sus subidas dentro de la plantilla y se las
        // llevaría el siguiente que la reconstruya.
        config(['tenancy.medios_de_plantilla' => 'demo_template_v3']);

        app(InquilinoActual::class)->fijar(
            new Tenant(['slug' => 'aaaabbbbcccc']),
        );

        $this->assertStringStartsWith('tenants/aaaabbbbcccc/', $this->rutaDe(1));
    }

    public function test_without_tenant_or_template_nothing_changes(): void
    {
        // El comportamiento de siempre para cualquier otro proceso: no se le
        // inventa un prefijo a quien no lo pidió.
        $this->assertStringStartsWith('1/', $this->rutaDe(1));
    }

    public function test_a_template_name_that_could_escape_the_directory_is_rejected(): void
    {
        // El valor termina siendo una RUTA DE DISCO. La validación va pegada al
        // uso y no sólo en el origen, porque el segundo camino hasta acá lo va a
        // escribir alguien que no leyó esto.
        //
        // No se reusa `GeneradorDeSlug::validar()`: valida slugs de inquilino
        // —sin guiones bajos— y una plantilla se llama `demo_template_v3`.
        foreach (['../otro', 'demo/../..', 'DEMO_MAYUS', '', '1empieza_con_numero'] as $malo) {
            config(['tenancy.medios_de_plantilla' => $malo]);

            if ($malo === '') {
                $this->assertStringStartsWith('1/', $this->rutaDe(1));

                continue;
            }

            try {
                $this->rutaDe(1);
                $this->fail("«{$malo}» tendría que haber sido rechazado: termina siendo una ruta.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use App\Support\Frontend\RutaDeMediosPorInquilino;
use App\Tenancy\InquilinoActual;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * Dos inquilinos que suben su primera imagen no se pisan el archivo.
 *
 * La librería de medios deriva la ruta del identificador de la fila, y con una
 * base por inquilino esos identificadores arrancan en 1 EN CADA BASE. Sin
 * prefijo, los dos escriben en `1/` sobre el mismo disco.
 */
class AislamientoDeArchivosTest extends TestCase
{
    use UsaBaseCentral;

    private function medioConId(int $id): Media
    {
        $media = new Media;
        $media->id = $id;

        return $media;
    }

    private function comoInquilino(string $slug): void
    {
        app(InquilinoActual::class)->fijar(new Tenant([
            'slug' => $slug,
            'database' => 'demo_t_'.$slug,
            'email' => $slug.'@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => now()->addDay(),
            'estado' => TenantEstado::Activo,
        ]));
    }

    public function test_the_generator_in_use_is_the_one_that_decides_where_files_are_written(): void
    {
        // `path_generator` y no `url_generator`: cambiar sólo el de URL haría
        // que las direcciones se vean distintas y el disco colisione igual.
        $this->assertSame(
            RutaDeMediosPorInquilino::class,
            config('media-library.path_generator'),
        );
    }

    public function test_two_tenants_first_upload_does_not_collide(): void
    {
        // EL DEFECTO: los dos medios tienen id 1 —el primero de cada base— y sin
        // prefijo escriben en la misma carpeta del mismo disco.
        $generador = new RutaDeMediosPorInquilino;

        $this->comoInquilino('primerinquil');
        $rutaA = $generador->getPath($this->medioConId(1));

        $this->comoInquilino('segundoinqui');
        $rutaB = $generador->getPath($this->medioConId(1));

        $this->assertNotSame($rutaA, $rutaB);
        $this->assertStringStartsWith('tenants/primerinquil/', $rutaA);
        $this->assertStringStartsWith('tenants/segundoinqui/', $rutaB);
    }

    public function test_conversions_and_responsive_images_travel_with_their_tenant(): void
    {
        // Si sólo se prefijara la ruta principal, las conversiones —miniaturas,
        // recortes— seguirían colisionando, y son más archivos que los
        // originales.
        $generador = new RutaDeMediosPorInquilino;
        $this->comoInquilino('conversiones');

        $medio = $this->medioConId(7);

        $this->assertStringStartsWith('tenants/conversiones/', $generador->getPathForConversions($medio));
        $this->assertStringStartsWith('tenants/conversiones/', $generador->getPathForResponsiveImages($medio));
    }

    public function test_outside_a_tenant_the_path_is_left_as_it_was(): void
    {
        // Sin inquilino no hay nada que prefijar, y inventar uno movería de
        // lugar los archivos que ya existen.
        $generador = new RutaDeMediosPorInquilino;

        $this->assertSame('1/', $generador->getPath($this->medioConId(1)));
    }

    public function test_a_malformed_slug_never_becomes_a_path(): void
    {
        // El slug termina siendo una ruta de disco. Se valida acá aunque venga
        // del padrón: la validación va pegada al uso, porque el segundo camino
        // hasta acá lo va a escribir alguien que no leyó la clase.
        $this->comoInquilino('../../etc');

        $this->expectException(InvalidArgumentException::class);

        (new RutaDeMediosPorInquilino)->getPath($this->medioConId(1));
    }
}

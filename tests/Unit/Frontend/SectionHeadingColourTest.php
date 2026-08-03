<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Todo encabezado de una sección declara su color.
 *
 * No es una regla de estilo: `resources/css/app.css` le da a `h1`–`h5` un
 * `color: var(--color-navy)` en la capa base, y una regla propia le gana a lo
 * que el elemento heredaría de su padre. Un encabezado sin color explícito
 * dentro de una superficie oscura sale AZUL MARINO SOBRE AZUL MARINO —contraste
 * 1:1— y desaparece.
 *
 * Ya pasó: el título de la tarjeta «Resultado esperado» de `audience_outcomes`
 * estuvo invisible en la página publicada de Inversionistas. Ocupaba sus 56px,
 * así que en la tarjeta se veía un hueco que parecía un error de espaciado, y
 * ninguna prueba lo notó — la suite entera pasaba en verde.
 *
 * Se verifica sobre la FUENTE y no sobre el render por dos razones: alcanza a
 * los encabezados de todas las secciones sin publicar ninguna, y avisa cuando
 * alguien escribe el encabezado, no cuando alguien mira la página.
 */
class SectionHeadingColourTest extends TestCase
{
    /** @return list<array{0: string}> */
    public static function vistasDeSeccion(): array
    {
        $vistas = glob(dirname(__DIR__, 3).'/resources/views/frontend/sections/*.blade.php') ?: [];

        return array_map(fn (string $ruta): array => [$ruta], $vistas);
    }

    #[DataProvider('vistasDeSeccion')]
    public function test_every_heading_declares_its_own_colour(string $ruta): void
    {
        $fuente = (string) file_get_contents($ruta);
        $vista = basename($ruta);

        preg_match_all('/<h[1-6]\b[^>]*>/', $fuente, $encabezados);

        // Hay secciones SIN encabezado propio y está bien: `metrics` y
        // `partners` son bandas de contenido, no bloques editoriales. No tener
        // nada que revisar no es un fallo.
        if (($encabezados[0] ?? []) === []) {
            $this->assertTrue(true, "«{$vista}» no declara encabezados.");

            return;
        }

        foreach ($encabezados[0] as $etiqueta) {
            // Vale una clase `text-*` literal o una variable que la traiga: los
            // tipos que invierten su tinta según el fondo la resuelven en PHP
            // (`$tinta['titulo']`) y no pueden escribirla a mano.
            $declara = preg_match('/\btext-[a-z0-9-]*(ink|primary|white|stone|graphite)/i', $etiqueta) === 1
                || preg_match('/\$tinta\[|\$tituloPropio|SectionTypography/', $etiqueta) === 1;

            $this->assertTrue(
                $declara,
                "«{$vista}» tiene un encabezado sin color propio y podría salir invisible sobre un fondo oscuro:\n  {$etiqueta}",
            );
        }
    }
}

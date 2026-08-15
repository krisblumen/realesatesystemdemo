<?php

namespace Tests\Feature\Lonas;

use App\Enums\OperationType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EncajeDeSvg;
use App\Tenancy\InquilinoActual;
use Tests\TestCase;

/**
 * El arte de la lona: que el logo entre donde tiene que entrar, y que diga que
 * es una demostración.
 *
 * UNA LONA SE IMPRIME. Son 90×120cm colgados en la calle: es la pieza del demo
 * con más chance de terminar en el mundo real sin que nadie recuerde de dónde
 * salió. Por eso la marca va grande y repetida, y por eso vale probarla.
 */
class DisenoDeLaLonaTest extends TestCase
{
    private function dibujar(): string
    {
        $agente = new User(['name' => 'María Fernández Ruiz', 'email' => 'maria@ejemplo.com']);
        $agente->phone = '5551234567';

        return view('pdf.lona-design', [
            'agent' => $agente,
            'operationType' => OperationType::Venta,
            'property' => null,
            'qrDataUri' => null,
        ])->render();
    }

    private function enUnInquilino(): void
    {
        app(InquilinoActual::class)->fijar(new Tenant([
            'slug' => 'probelona123',
            'database' => 'demo_probe_lona',
        ]));
    }

    public function test_the_logo_never_reaches_the_word_venta(): void
    {
        // EL DEFECTO QUE ESTE TEST CIERRA, visto en una lona generada de verdad.
        //
        // El CSS fijaba `width: 950pt` del logo y nada más. Con el logo
        // horizontal original entraba; la des-marcación lo cambió por uno casi
        // cuadrado (proporción 0.95) y esos 950pt de ancho pasaron a ser 998pt
        // de ALTO. El logo bajaba hasta 1328pt y VENTA arranca en 1060pt: 268pt
        // encima de la palabra.
        //
        // Se leen las DOS posiciones del HTML renderizado en vez de compararlas
        // contra números escritos acá. Así el test sigue valiendo cuando alguien
        // mueva la ranura o el texto: lo que se prueba es que no se pisen, no que
        // estén en un lugar concreto.
        $html = $this->dibujar();

        preg_match('/class="logo"[^>]*style="[^"]*top:\s*([\d.]+)pt[^"]*height:\s*([\d.]+)pt/', $html, $l);
        preg_match('/\.tipo\s*\{[^}]*top:\s*([\d.]+)pt/', $html, $t);

        $this->assertNotEmpty($l, 'El logo tiene que llevar alto explícito: sin alto, la caja no acota.');
        $this->assertNotEmpty($t, 'No se pudo leer dónde arranca el tipo de operación.');

        $bordeInferiorDelLogo = (float) $l[1] + (float) $l[2];

        $this->assertLessThanOrEqual(
            (float) $t[1],
            $bordeInferiorDelLogo,
            sprintf(
                'El logo termina en %.0fpt y «VENTA» arranca en %.0fpt: se pisan por %.0fpt.',
                $bordeInferiorDelLogo, (float) $t[1], $bordeInferiorDelLogo - (float) $t[1],
            ),
        );
    }

    public function test_a_lona_of_a_demo_says_that_it_is_one(): void
    {
        $this->enUnInquilino();

        $html = $this->dibujar();

        // Se cuenta el ELEMENTO y no la palabra: el comentario del CSS que
        // explica la marca también dice «DEMOSTRACIÓN», y contarla suelta daba
        // verde sin que hubiera una sola marca dibujada.
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($html, '>DEMOSTRACIÓN<'),
            'Una sola marca en una pieza de 90×120cm se recorta o se pasa por alto.',
        );
    }

    public function test_an_installation_without_tenants_prints_no_watermark(): void
    {
        // La plataforma corriendo para un cliente propio emite lonas de verdad.
        // La marca se decide por si hay inquilino y no por una bandera, así que
        // nadie tiene que acordarse de apagarla.
        $this->assertStringNotContainsString('>DEMOSTRACIÓN<', $this->dibujar());
    }

    public function test_a_tall_logo_and_a_wide_one_both_fit_the_same_slot(): void
    {
        // LO QUE HACE QUE ESTO NO VUELVA A PASAR. El defecto no fue que el logo
        // estuviera mal medido: fue que la caja tenía UNA sola dimensión. Con
        // las dos, cualquier forma entra — y la próxima des-marcación no rompe
        // nada.
        $dir = sys_get_temp_dir().'/probe-lonas-'.getmypid();
        @mkdir($dir, 0777, true);

        $formas = [
            'altisimo' => '<svg viewBox="0 0 10 100"></svg>',
            'anchisimo' => '<svg viewBox="0 0 100 10"></svg>',
            'cuadrado' => '<svg viewBox="0 0 50 50"></svg>',
            'sin_viewbox' => '<svg width="80" height="20"></svg>',
            'ilegible' => '<svg></svg>',
        ];

        try {
            foreach ($formas as $nombre => $contenido) {
                $ruta = "{$dir}/{$nombre}.svg";
                file_put_contents($ruta, $contenido);

                $caja = EncajeDeSvg::contener($ruta, 1400, 700, 575.5, 300);

                $this->assertLessThanOrEqual(1400.0001, $caja['ancho'], "«{$nombre}» se sale de ancho.");
                $this->assertLessThanOrEqual(700.0001, $caja['alto'], "«{$nombre}» se sale de alto.");
                $this->assertGreaterThanOrEqual(575.5, $caja['izquierda'], "«{$nombre}» arranca antes de la ranura.");
                $this->assertGreaterThanOrEqual(300.0, $caja['arriba'], "«{$nombre}» arranca más arriba que la ranura.");
            }
        } finally {
            array_map('unlink', (array) glob("{$dir}/*.svg"));
            @rmdir($dir);
        }
    }

    public function test_it_does_not_deform_the_logo_to_fill_the_slot(): void
    {
        // Estirar para llenar sería la salida fácil y es peor que el defecto
        // original: un logo deformado se ve mal en TODAS las lonas, no sólo
        // cuando choca.
        $dir = sys_get_temp_dir().'/probe-lonas-p-'.getmypid();
        @mkdir($dir, 0777, true);
        $ruta = "{$dir}/logo.svg";
        file_put_contents($ruta, '<svg viewBox="0 0 40 10"></svg>');

        try {
            $caja = EncajeDeSvg::contener($ruta, 1400, 700);

            $this->assertEqualsWithDelta(4.0, $caja['ancho'] / $caja['alto'], 0.001);
        } finally {
            unlink($ruta);
            @rmdir($dir);
        }
    }
}

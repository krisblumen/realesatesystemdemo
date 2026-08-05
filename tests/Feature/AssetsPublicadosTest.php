<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Los assets de Livewire publicados están y coinciden con la versión instalada.
 *
 * POR QUÉ SE PUBLICAN. Sin publicar, Livewire sirve su JavaScript desde una
 * ruta de PHP (`/livewire/livewire.min.js`). En el servidor eso choca de frente
 * con el bloque de nginx que atiende por extensión todo lo que termina en `.js`
 * sin llegar nunca a PHP: la respuesta era 404, el script no cargaba, Livewire
 * no arrancaba, y el formulario de acceso se enviaba de forma nativa a una ruta
 * que sólo acepta GET. Resultado: 405 al intentar entrar.
 *
 * Publicados, la dirección pasa a ser `/vendor/livewire/livewire.min.js` —un
 * archivo de verdad en disco— y ese mismo bloque de nginx lo sirve bien, con el
 * tipo de contenido correcto y sin tocar PHP. La solución es dejar de pedirle a
 * la aplicación algo que el servidor web hace mejor.
 *
 * POR QUÉ ESTE TEST. Livewire detecta el desajuste, pero avisa por la consola
 * del navegador: sólo se entera quien tenga las herramientas abiertas. Un
 * `composer update` que mueva Livewire deja los archivos publicados viejos y
 * nadie lo nota hasta que algo se comporta raro. Acá se entera CI.
 *
 * Si esto falla: `php artisan livewire:publish --assets` y commitear el
 * resultado.
 */
class AssetsPublicadosTest extends TestCase
{
    public function test_the_published_livewire_assets_match_the_installed_version(): void
    {
        $publicado = public_path('vendor/livewire/manifest.json');
        $instalado = base_path('vendor/livewire/livewire/dist/manifest.json');

        $this->assertFileExists(
            $publicado,
            'Sin esto Livewire sirve su JavaScript por PHP, y nginx no lo deja llegar.',
        );

        $this->assertSame(
            json_decode((string) file_get_contents($instalado), true),
            json_decode((string) file_get_contents($publicado), true),
            'Los assets publicados quedaron viejos: corré `php artisan livewire:publish --assets`.',
        );
    }

    public function test_the_script_that_the_browser_asks_for_exists_on_disk(): void
    {
        // La minificada es la que sirve producción; la otra, desarrollo. Las dos
        // tienen que estar, porque el mismo repositorio corre en los dos lados.
        foreach (['livewire.js', 'livewire.min.js'] as $archivo) {
            $this->assertFileExists(
                public_path('vendor/livewire/'.$archivo),
                "Falta «{$archivo}»: el navegador lo va a pedir y no va a estar.",
            );
        }
    }
}

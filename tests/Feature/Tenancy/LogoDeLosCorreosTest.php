<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Notifications\InvitacionAlDemo;
use App\Tenancy\GeneradorDeClave;
use Tests\TestCase;

/**
 * El logo de los correos, y por qué salía aplastado.
 *
 * La plantilla de correo trae una caja fija —`width` y `height` en el CSS— y el
 * logo que había adentro era el VERTICAL, casi cuadrado. Forzar una imagen de
 * proporción 0.95 en una caja de 1.40 la estira: se veía deformada en cada
 * invitación que sale.
 *
 * Este test compara la proporción REAL del archivo contra la de la caja. Es la
 * única forma de que «se ve aplastado» no vuelva: cambiar el logo por otro de
 * otra forma, sin tocar el CSS, es exactamente cómo apareció.
 */
class LogoDeLosCorreosTest extends TestCase
{
    private const HEADER = 'resources/views/vendor/mail/html/header.blade.php';

    private const CSS = 'resources/views/vendor/mail/html/themes/default.css';

    public function test_the_mail_logo_is_not_squashed(): void
    {
        $header = (string) file_get_contents(base_path(self::HEADER));

        preg_match("/images\/brand\/([^']+)/", $header, $m);

        $this->assertNotEmpty($m, 'El encabezado tiene que apuntar a un logo de la marca.');

        $tamano = getimagesize(public_path('images/brand/'.$m[1]));

        $this->assertNotFalse($tamano, "No se pudo leer «{$m[1]}».");

        $proporcionDelArchivo = $tamano[0] / $tamano[1];

        $css = (string) file_get_contents(base_path(self::CSS));

        preg_match('/\.logo\s*\{([^}]*)\}/', $css, $regla);
        preg_match('/width:\s*(\d+)px/', $regla[1], $ancho);
        preg_match('/height:\s*(\d+)px/', $regla[1], $alto);

        $this->assertNotEmpty($ancho, 'La caja del logo declara un ancho.');
        $this->assertNotEmpty($alto, 'Y un alto: los clientes de correo no calculan proporciones.');

        $proporcionDeLaCaja = (int) $ancho[1] / (int) $alto[1];

        $this->assertEqualsWithDelta(
            $proporcionDelArchivo,
            $proporcionDeLaCaja,
            0.08,
            sprintf(
                'El logo es %d×%d (proporción %.2f) y la caja %s×%s (proporción %.2f): se va a ver deformado.',
                $tamano[0], $tamano[1], $proporcionDelArchivo,
                $ancho[1], $alto[1], $proporcionDeLaCaja,
            ),
        );
    }

    public function test_the_mail_logo_is_a_format_every_client_renders(): void
    {
        // Outlook de escritorio en Windows renderiza con el motor de Word, que
        // NO muestra WebP. Gmail y Apple Mail sí. En un correo que va a gente
        // que no conocemos, el formato lo decide el cliente más pobre — y una
        // invitación con el logo roto arranca mal.
        $header = (string) file_get_contents(base_path(self::HEADER));

        preg_match("/images\/brand\/([^']+)/", $header, $m);

        $this->assertMatchesRegularExpression(
            '/\.(png|jpe?g|gif)$/i',
            $m[1],
            "«{$m[1]}» no se ve en todos los clientes de correo.",
        );
    }

    public function test_the_invitation_reads_in_spanish_end_to_end(): void
    {
        // LO QUE ESTE TEST CUIDA. El cuerpo del mensaje lo escribimos nosotros,
        // pero el ANDAMIAJE —la despedida, el pie, el texto de respaldo del
        // botón— lo pone Laravel, y sale en inglés si el idioma de la aplicación
        // no está en español.
        //
        // El resultado era una invitación mitad y mitad. Es el primer mensaje
        // que recibe alguien que no conoce el producto: ahí se juega si parece
        // terminado o improvisado.
        $t = new Tenant([
            'slug' => 'abcdefgh1234',
            'email' => 'invitado@ejemplo.com',
            'expira_en' => now()->addDays(15),
        ]);

        $html = (new InvitacionAlDemo($t, 'una-contrasena'))->toMail($t)->render();

        // Y firmado con el nombre del producto: el pie y la despedida usan
        // `app.name`, cuyo default era «Laravel». Una invitación firmada por el
        // framework no le dice nada a quien la recibe.
        $this->assertStringContainsString(config('app.name'), $html);
        $this->assertNotSame('Laravel', config('app.name'));

        foreach (['Regards', 'All rights reserved', 'having trouble'] as $ingles) {
            $this->assertStringNotContainsString(
                $ingles,
                $html,
                "«{$ingles}» sale del andamiaje de Laravel y queda en medio de un correo en español.",
            );
        }
    }

    public function test_a_password_with_markdown_characters_arrives_whole(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE, y estuvo a punto de salir.
        //
        // El cuerpo del correo se escribe en markdown, y `Str::password()` genera
        // símbolos que markdown interpreta: `* _ [ ] \ < >`. Una contraseña con
        // `ab*cd*ef` se renderizaba como `ab`, con `cd` en cursiva y el resto
        // comido.
        //
        // El síntoma es cruel: quien invita ve la contraseña CORRECTA en
        // pantalla, el invitado recibe otra por correo, y ninguno de los dos
        // sabe por qué no entra.
        //
        // Se apareció por un test intermitente — pasaba o no según qué símbolos
        // le tocaran a la contraseña generada.
        $t = new Tenant([
            'slug' => 'abcdefgh1234',
            'email' => 'invitado@ejemplo.com',
            'expira_en' => now()->addDays(15),
        ]);

        // EN PARES, que es lo que markdown necesita para interpretar. Con un
        // solo `*` los deja literales, y un test con una clave así pasa sin
        // reproducir nada — me pasó al escribirlo.
        $clave = 'ab*cd*ef_gh_ij[kl]mn';

        $html = (new InvitacionAlDemo($t, $clave))->toMail($t)->render();

        $visible = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString(
            $clave,
            $visible,
            'La contraseña tiene que llegar entera: si markdown se come un carácter, quien la reciba no entra.',
        );
    }

    public function test_every_generated_password_survives_the_email(): void
    {
        // LA RAÍZ, y no el síntoma.
        //
        // Los acentos graves protegen de markdown pero NO del doble escapado:
        // el correo escapa una vez al interpolar y otra al convertir, así que
        // un `>` llegaba escrito `&gt;`. Se podría escapar mejor — o generar
        // contraseñas que no tengan caracteres hostiles, que además son las que
        // una persona puede transcribir sin equivocarse.
        //
        // Se prueban muchas porque el defecto era INTERMITENTE: dependía de qué
        // símbolos le tocaran a cada contraseña generada.
        $t = new Tenant([
            'slug' => 'abcdefgh1234',
            'email' => 'invitado@ejemplo.com',
            'expira_en' => now()->addDays(15),
        ]);

        $generador = new GeneradorDeClave;

        for ($i = 0; $i < 40; $i++) {
            $clave = $generador->generar();

            $this->assertTrue(GeneradorDeClave::esSegura($clave), "«{$clave}» tiene caracteres hostiles.");

            $html = (new InvitacionAlDemo($t, $clave))->toMail($t)->render();
            $visible = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $this->assertStringContainsString(
                $clave,
                $visible,
                "«{$clave}» no llega entera al correo: quien la reciba no entra.",
            );
        }
    }

    public function test_the_alt_text_is_not_from_the_other_brand(): void
    {
        // SE ESCAPÓ DE LA DES-MARCACIÓN, y vale saber por qué: la búsqueda
        // excluía `vendor` para saltear las dependencias de Composer, y ese
        // patrón también saltea `resources/views/vendor/` — que es código
        // NUESTRO, plantillas publicadas de Laravel.
        //
        // El `alt` se lee cuando la imagen no carga, que en correo es a menudo.
        $header = (string) file_get_contents(base_path(self::HEADER));

        $this->assertStringNotContainsStringIgnoringCase('new hauz', $header);
        $this->assertStringNotContainsStringIgnoringCase('newhauz', $header);
        $this->assertStringContainsString('alt="Landra"', $header);
    }
}

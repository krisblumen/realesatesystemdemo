<?php

namespace Tests\Feature\Panel;

use App\Models\FrontendPage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El botón flotante de acción principal del panel.
 *
 * Lo inyecta un render hook (AdminPanelProvider), no la vista de cada
 * formulario, así que nada en los formularios avisa si deja de funcionar. Estas
 * pruebas existen porque al construirlo aparecieron TRES defectos que el código
 * no delataba y sólo se veían haciendo clic:
 *
 *   1. El botón se dibujaba perfecto y no guardaba NADA: Filament tiene dos
 *      props parecidas —`form` es el target de Livewire que enciende el spinner
 *      y `formId` es el atributo HTML que decide qué se envía— y se había usado
 *      la primera.
 *   2. Aparecía flotando sobre los modales, ofreciendo guardar el formulario de
 *      la página de atrás mientras el owner editaba otra cosa.
 *   3. En la pantalla de una página del sitio contradecía al botón del
 *      encabezado sobre si había cambios sin publicar.
 *
 * Lo que NO se puede cubrir acá —que se esconda con un modal abierto y que el
 * espejo se resincronice— vive en el navegador: depende de Alpine y de que
 * Livewire vuelva a dibujar. Queda como verificación manual, y está dicho para
 * que nadie lo dé por probado.
 */
class FloatingSaveButtonTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
    }

    private function html(string $url): string
    {
        return $this->actingAs($this->owner)->get($url)->assertOk()->getContent();
    }

    // ------------------------------------------------------ dónde aparece ----

    public function test_a_resource_edit_screen_gets_the_floating_button(): void
    {
        $property = Property::factory()->create();

        $this->assertStringContainsString(
            'nh-floating-save',
            $this->html("/admin/properties/{$property->getKey()}/edit"),
        );
    }

    public function test_a_resource_create_screen_gets_the_floating_button(): void
    {
        $this->assertStringContainsString('nh-floating-save', $this->html('/admin/properties/create'));
    }

    public function test_a_listing_and_the_dashboard_get_no_floating_button(): void
    {
        // Un botón que no guarda nada es peor que ninguno: promete una acción
        // que la pantalla no tiene.
        foreach (['/admin/properties', '/admin'] as $url) {
            $this->assertStringNotContainsString(
                'nh-floating-save',
                $this->html($url),
                "«{$url}» no debería ofrecer el botón de guardar.",
            );
        }
    }

    // -------------------------------------------------------- a qué apunta ----

    public function test_the_button_targets_the_form_it_must_submit(): void
    {
        // LA REGRESIÓN QUE YA NOS MORDIÓ: con la prop equivocada el botón salía
        // igual de lindo y el atributo `form` no llegaba al HTML, así que el
        // clic no enviaba nada y el owner perdía su trabajo sin un solo aviso.
        $property = Property::factory()->create();
        $html = $this->html("/admin/properties/{$property->getKey()}/edit");

        $this->assertMatchesRegularExpression(
            '/<button[^>]*\sform="form"/',
            $html,
            'El botón flotante no declara a qué formulario pertenece.',
        );

        $this->assertMatchesRegularExpression(
            '/<form[^>]*\sid="form"/',
            $html,
            'No existe el formulario al que el botón dice apuntar.',
        );
    }

    public function test_the_site_settings_screen_offers_exactly_one_save_button(): void
    {
        // Esta pantalla tenía su propia copia del botón antes de que existiera
        // el compartido. Si alguien la repone, acá saldrían dos.
        $html = $this->html('/admin/frontend/configuracion');

        // Se cuenta el CONTENEDOR y no el nombre de la clase suelto: el
        // componente la nombra dos veces por inyección —una en su regla CSS y
        // otra en el `class` del div—, así que contar el nombre daba dos y
        // parecía una duplicación que no existía.
        $this->assertSame(
            1,
            substr_count($html, 'class="nh-floating-save"'),
            'La configuración del sitio muestra el botón flotante más de una vez.',
        );

        $this->assertMatchesRegularExpression('/<form[^>]*\sid="form"/', $html);
    }

    // --------------------------------------------------------- en qué modo ----

    public function test_the_page_editor_shows_publish_and_not_save(): void
    {
        // Guardar ahí sólo aplica el interruptor de visible/oculto; lo que
        // cambia el sitio público es PUBLICAR. El botón flotante espeja al del
        // encabezado en vez de traer texto propio: con texto propio los dos se
        // contradecían sobre si había trabajo sin publicar, porque este bloque
        // se dibuja fuera del componente y no se vuelve a dibujar.
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $html = $this->html("/admin/frontend/paginas/{$page->getKey()}/edit");

        $this->assertStringContainsString('nh-floating-save', $html);
        $this->assertStringContainsString(
            'mountAction(\'publish\')',
            $html,
            'El botón flotante de esta pantalla debe disparar la acción de publicar.',
        );
        $this->assertStringContainsString(
            'etiqueta',
            $html,
            'Debe estar en modo espejo: sin la sincronización, su texto queda viejo apenas el owner guarde una sección.',
        );
    }
}

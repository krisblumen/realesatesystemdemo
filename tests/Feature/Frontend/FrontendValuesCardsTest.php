<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `values` se muestra de DOS formas, y la elige el owner.
 *
 * El sitio ya las tenía las dos, cableadas: «Nuestros valores» en Nosotros va
 * como texto suelto de a cuatro, y «¿Qué incluye?» en Inversionistas como
 * tarjetas de a dos. Era la misma sección viéndose distinto según la página,
 * sin que el panel ofreciera nada al respecto — de hecho la sección de
 * Inversionistas se publicaba PERDIENDO sus tarjetas.
 *
 * LA CLAVE AUSENTE CUENTA COMO APAGADO. Es lo que hace que sumar esta opción no
 * le cambie el aspecto a ninguna sección ya publicada (§16.7), y por eso hay una
 * prueba dedicada: es la garantía que se rompería sin querer.
 */
class FrontendValuesCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
    }

    /** @param  array<string, mixed>  $extra */
    private function publicar(array $extra = []): string
    {
        $page = FrontendPage::query()->where('key', 'nosotros')->firstOrFail();
        $seccion = $page->sections()->where('section_key', 'values')->firstOrFail();

        $seccion->forceFill(['payload' => $extra + [
            'title' => 'Nuestros valores',
            'items' => [
                ['title' => 'Confianza', 'description' => 'Acompañamiento en cada operación.'],
                ['title' => 'Excelencia', 'description' => 'Un solo estándar de calidad.'],
                ['title' => 'Cercanía', 'description' => 'Trato directo con tu asesor.'],
            ],
        ]])->saveQuietly();

        $page->refresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        return $this->get('/nosotros')->assertOk()->getContent();
    }

    public function test_without_the_toggle_the_values_stay_as_loose_text(): void
    {
        // LO QUE NO DEBE ROMPERSE: todo lo publicado hasta hoy. Sin la clave, la
        // sección se ve como siempre — de a cuatro y sin caja.
        $html = $this->publicar();

        // Con TRES valores y filas de cuatro, la regla de reparto los pone en
        // tercios: la fila está incompleta, así que se reparten el ancho. Que
        // no sean cuartos no es un defecto, es la misma regla de siempre.
        $this->assertSame(3, substr_count($html, 'sm:col-span-4'), 'Sin tarjetas, los tres se reparten la fila.');
        // Se afirma sobre `shadow-md` y NO sobre `bg-navy-50`: ese fondo lo usa
        // también la placa del ícono, así que como marcador de tarjeta daría un
        // positivo falso.
        $this->assertStringNotContainsString('shadow-md', $html, 'Sin tarjetas no hay caja que dibujar.');
    }

    public function test_the_toggle_turns_each_value_into_a_card(): void
    {
        $html = $this->publicar([
            'as_cards' => true,
            'card_bg_color' => 'navy',
            'card_border' => true,
            'card_border_width' => 1,
            'card_border_color' => 'primary-l2',
        ]);

        $this->assertStringContainsString('bg-navy-50', $html);
        $this->assertStringContainsString('border-[1px] border-brand-primary-l2', $html);
        $this->assertStringContainsString('shadow-md', $html);
    }

    public function test_carded_values_go_two_per_row_and_the_odd_one_spans_the_width(): void
    {
        // La misma regla que «Qué hacemos» y el listado de proyectos, con filas
        // de dos: con tres tarjetas, la tercera ocupa el ancho entero en vez de
        // dejar un hueco al lado.
        $html = $this->publicar(['as_cards' => true]);

        $this->assertSame(2, substr_count($html, 'sm:col-span-6'), 'Las dos primeras van a media fila.');
        $this->assertSame(1, substr_count($html, 'sm:col-span-12'), 'La tercera se reparte el ancho entero.');
    }

    public function test_the_border_can_be_turned_off_without_losing_the_card(): void
    {
        // Son dos decisiones distintas: la caja y su contorno.
        $html = $this->publicar(['as_cards' => true, 'card_border' => false]);

        $this->assertStringContainsString('shadow-md', $html, 'La tarjeta sigue estando.');
        $this->assertStringNotContainsString('border-[1px] border-brand-primary-l2', $html);
    }

    public function test_the_new_keys_are_accepted_by_the_schema(): void
    {
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('values', [
            'title' => 'Nuestros valores',
            'as_cards' => true,
            'card_bg_color' => 'navy',
            'card_border' => true,
            'card_border_width' => 2,
            'card_border_color' => 'accent',
            'items' => [['title' => 'Confianza', 'description' => 'Texto.']],
        ]));
    }
}

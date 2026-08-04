<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El fondo de «Nuestros valores» lo elige el owner de la paleta cerrada.
 *
 * Lo que se guarda es una CLAVE de la paleta y la clase la pone un mapa fijo:
 * nada del payload se interpola en un nombre de clase. Por eso el guard que
 * importa acá no es sólo «pinta el color elegido», sino también «una clave
 * inventada no llega a publicarse».
 *
 * El default es `site` y no `white`: el cuerpo de la página usa
 * `bg-site-background`, que es tematizable, así que un default blanco le habría
 * abierto un recuadro claro a cualquier cliente con un tema de fondo distinto.
 */
class FrontendValuesBackgroundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    /** Publica el payload dado en «valores» y devuelve el HTML de /nosotros. */
    private function renderWith(array $payload): string
    {
        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('type', 'values')->firstOrFail();

        $section->forceFill(['payload' => $payload + [
            'items' => [['title' => 'Confianza', 'description' => 'Lo que prometemos, lo cumplimos.']],
        ]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        return $this->get('/nosotros')->assertOk()->getContent();
    }

    /** @return array<string, array{string, string}> */
    public static function colours(): array
    {
        return [
            'fondo del sitio' => ['site', 'bg-site-background'],
            'blanco' => ['neutral-0', 'bg-white'],
            'gris claro' => ['neutral-2', 'bg-cloud'],
            'acento' => ['accent', 'bg-brand-accent'],
            'principal oscuro' => ['primary-d1', 'bg-brand-primary-d1'],
        ];
    }

    /**
     * Se afirma que la clase ESTÁ en el atributo, no que sea la única: la
     * sección también lleva el filete gris cuando su fondo difiere del general
     * del sitio, y fijar el atributo entero hacía fallar esta prueba por un
     * agregado legítimo en vez de por un fondo equivocado.
     */
    #[DataProvider('colours')]
    public function test_the_section_wears_the_chosen_background(string $clave, string $clase): void
    {
        $html = $this->renderWith(['title' => 'Nuestros valores', 'background_color' => $clave]);

        $this->assertMatchesRegularExpression('/<section class="[^"]*\b'.preg_quote($clase, '/').'\b/', $html);
    }

    public function test_a_section_nobody_touched_keeps_the_site_background(): void
    {
        // Sin la clave, la sección tiene que salir como salía antes de que este
        // selector existiera: al ras del cuerpo de la página, sin banda propia.
        $html = $this->renderWith(['title' => 'Nuestros valores']);

        $this->assertStringContainsString('<section class="bg-site-background">', $html);
        // Y SIN filete: sobre el mismo color del sitio no hay banda que
        // delimitar, así que una línea ahí cruzaría la página porque sí.
        $this->assertStringNotContainsString('border-y border-cloud', $html);
    }

    public function test_a_section_with_its_own_background_gets_the_grey_edges(): void
    {
        // La otra mitad de la regla: con un fondo distinto al del sitio, la
        // sección se lee como una banda y necesita su borde.
        $html = $this->renderWith(['title' => 'Nuestros valores', 'background_color' => 'primary']);

        $this->assertStringContainsString('border-y border-cloud', $html);
    }

    public function test_a_colour_outside_the_palette_never_reaches_the_payload(): void
    {
        $schema = app(FrontendSectionSchema::class);
        $base = [
            'title' => 'Nuestros valores',
            'items' => [['title' => 'Confianza', 'description' => 'Lo que prometemos, lo cumplimos.']],
        ];

        // El caso válido va primero a propósito: sin él, «rechaza el inválido»
        // pasaría igual aunque el schema rechazara TODO.
        $this->assertSame([], $schema->validate('values', $base + ['background_color' => 'site']));

        $this->assertNotEmpty(
            $schema->validate('values', $base + ['background_color' => 'verde-fluor']),
            'Una clave fuera de la paleta tendría que quedar afuera.',
        );
    }

    public function test_every_palette_colour_has_its_background_class_compiled(): void
    {
        // Tailwind compila leyendo archivos: una clase que no aparezca escrita
        // literalmente deja la sección SIN fondo, y en el panel se ve elegida.
        $css = collect(glob(public_path('build/assets/*.css')))
            ->map(fn (string $ruta): string => (string) file_get_contents($ruta))
            ->implode('');

        $this->assertNotSame('', $css, 'Falta compilar los assets: corré `npm run build`.');

        foreach ((array) config('frontend-sections.brand_palette') as $clave => $color) {
            $this->assertStringContainsString(
                $color['bg'],
                $css,
                "La clase de fondo de «{$clave}» no está compilada: la sección saldría sin color.",
            );
        }
    }
}

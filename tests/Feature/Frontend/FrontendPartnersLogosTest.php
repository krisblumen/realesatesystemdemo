<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Los aliados se muestran como logotipos en tarjetas blancas.
 *
 * Cinco a la vista; a partir del sexto la tira avanza sola. El bucle es CSS
 * puro —el sitio se sirve sin `unsafe-inline`, y un carrusel con script sería lo
 * único de la página que obligaría a relajar esa política por un adorno.
 *
 * El LOGO ES OPCIONAL. Los aliados que ya estaban cargados sólo tienen nombre, y
 * exigir imagen los habría borrado de la página al publicar.
 */
class FrontendPartnersLogosTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    private function section(): FrontendSection
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'partners')->firstOrFail();
    }

    /**
     * La RUTA que deja Filament tras subir un archivo, que es lo que el
     * compilador recibe de verdad.
     *
     * No se le puede pasar un `UploadedFile`: cuando el compilador corre, el
     * FileUpload ya guardó el archivo en el disco privado y su estado es la
     * ruta. Pasarle el objeto probaría un contrato que no existe.
     */
    private function logoSubido(string $nombre): string
    {
        return UploadedFile::fake()->image($nombre, 400, 160)->store('', 'frontend-private');
    }

    private function schema(): FrontendSectionSchema
    {
        return app(FrontendSectionSchema::class);
    }

    /** Publica `n` aliados (sin logo) y devuelve el HTML de la home. */
    private function renderWith(int $n, array $extra = []): string
    {
        $items = array_map(fn (int $i): array => ['name' => "Aliado {$i}"], range(1, $n));

        $section = $this->section();
        $section->forceFill(['payload' => $extra + ['items' => $items]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        return $this->get('/')->assertOk()->getContent();
    }

    // -------------------------------------------------- lo que no se rompe ----

    public function test_partners_that_only_have_a_name_still_render(): void
    {
        // LO QUE NO DEBE ROMPERSE: son los que ya estaban publicados.
        $this->assertSame([], $this->schema()->validate('partners', [
            'items' => [['name' => 'Grupo Ibrac']],
        ]));

        $this->assertStringContainsString('Aliado 1', $this->renderWith(3));
    }

    public function test_a_logo_without_a_name_is_discarded(): void
    {
        // El nombre identifica al aliado Y describe su imagen: sin él la fila
        // está a medio llenar.
        $payload = app(SectionPayloadCompiler::class)->compile($this->section(), [
            'items' => [['name' => ''], ['name' => 'Grupo Ibrac']],
        ]);

        $this->assertSame([['name' => 'Grupo Ibrac']], $payload['items']);
    }

    // ------------------------------------------------------------ el logo ----

    public function test_a_logo_is_stored_and_its_alt_is_the_partner_name(): void
    {
        // No se le pide `alt` al owner: para el logotipo de un aliado, su texto
        // alternativo ES el nombre. Preguntarlo aparte sería pedir dos veces lo
        // mismo para que la segunda quede peor.
        $section = $this->section();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'items' => [[
                'name' => 'Grupo Ibrac',
                'upload' => [$this->logoSubido('logo.png')],
            ]],
        ]);

        $this->assertArrayHasKey('media_id', $payload['items'][0]);
        $this->assertSame('Grupo Ibrac', $payload['items'][0]['alt']);

        // Y el resultado pasa su propio schema, incluida la regla universal de
        // accesibilidad que exige `alt` a todo objeto con imagen.
        $this->assertSame([], $this->schema()->validate('partners', $payload));
    }

    public function test_the_logo_is_shown_when_there_is_one_and_the_name_when_there_is_not(): void
    {
        $section = $this->section();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'items' => [
                ['name' => 'Con logo', 'upload' => [$this->logoSubido('l.png')]],
                ['name' => 'Sin logo'],
            ],
        ]);

        $section->forceFill(['payload' => $payload])->saveQuietly();
        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('alt="Con logo"', $html);
        $this->assertStringContainsString('Sin logo', $html);
    }

    // ---------------------------------------------------------- el carrusel ----

    #[DataProvider('cantidades')]
    public function test_the_strip_only_moves_past_five(int $cuantos, bool $seMueve): void
    {
        $html = $this->renderWith($cuantos);

        $this->assertSame(
            $seMueve,
            str_contains($html, 'nh-partners-track'),
            "Con {$cuantos} aliados la tira no se comportó como corresponde.",
        );
    }

    public static function cantidades(): array
    {
        return [
            'uno' => [1, false],
            'cinco entran quietos' => [5, false],
            'seis ya se mueven' => [6, true],
        ];
    }

    public function test_the_moving_strip_draws_the_list_twice_without_repeating_it_to_a_screen_reader(): void
    {
        // La segunda copia es lo que hace que el bucle no tenga costura. Va
        // `aria-hidden` para que un lector no lea a cada aliado dos veces.
        $html = $this->renderWith(6);

        $this->assertSame(2, substr_count($html, 'Aliado 6'));
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_reduced_motion_stops_the_loop_but_keeps_every_logo_reachable(): void
    {
        // Detener la animación sin más dejaría cinco logos visibles y el resto
        // inalcanzable: peor que el movimiento que se quiso evitar.
        $html = $this->renderWith(6);
        $this->assertStringContainsString('motion-reduce:overflow-x-auto', $html);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\) \{\s*\.nh-partners-track \{\s*animation: none;/',
            $css,
        );
    }

    public function test_the_loop_needs_no_javascript(): void
    {
        // El sitio se sirve sin `unsafe-inline`: un carrusel con script sería lo
        // único de la página que obligaría a relajar esa política por un adorno.
        $this->assertStringNotContainsString(
            '<script',
            file_get_contents(resource_path('views/frontend/sections/partners.blade.php')),
        );

        $this->assertMatchesRegularExpression(
            '/@keyframes nh-partners-scroll \{.*?translateX\(.*?\).*?\}/s',
            file_get_contents(resource_path('css/app.css')),
        );
    }

    public function test_the_loop_shifts_a_whole_copy_and_not_half_the_track(): void
    {
        // Con N tarjetas por copia, la pista mide 2N tarjetas y 2N-1 huecos: su
        // mitad se queda corta por MEDIO hueco. Medido con 8 aliados, la pista
        // daba 3688 px, el -50% desplazaba 1844 y la vuelta real eran 1856 — un
        // salto de 12 px en cada vuelta.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@keyframes nh-partners-scroll \{.*?translateX\(calc\(-50% - 0\.75rem\)\)/s',
            $css,
            'El bucle volvió a desplazar media pista y saltará en cada vuelta.',
        );

        // Ese 0.75rem es la MITAD del hueco: si la vista cambia su separación,
        // el valor deja de corresponder y el salto vuelve.
        $this->assertStringContainsString(
            'gap-6',
            file_get_contents(resource_path('views/frontend/sections/partners.blade.php')),
            'La separación entre tarjetas cambió: el desplazamiento del bucle ya no cierra.',
        );
    }

    // -------------------------------------------------------------- el borde ----

    public function test_the_border_uses_the_same_closed_palette_as_the_other_cards(): void
    {
        $html = $this->renderWith(3, [
            'card_border' => true, 'card_border_width' => 3, 'card_border_color' => 'primary-d1',
        ]);

        $this->assertStringContainsString('border-[3px]', $html);
        $this->assertStringContainsString('border-brand-primary-d1', $html);
    }

    public function test_a_colour_outside_the_palette_is_rejected(): void
    {
        $this->assertNotSame([], $this->schema()->validate('partners', [
            'items' => [['name' => 'A']],
            'card_border_color' => 'verde-fluor',
        ]));
    }

    public function test_without_a_border_the_white_card_still_has_an_edge(): void
    {
        // La tarjeta es blanca sobre un fondo casi blanco: sin nada que la
        // delimite se pierde contra la página.
        $this->assertStringContainsString('border border-black/5', $this->renderWith(3));
    }

    public function test_every_palette_border_class_is_compiled(): void
    {
        // Tailwind las emite leyendo la vista. Una que falte deja el borde sin
        // color, algo que no se ve mirando el HTML.
        $bundle = collect(glob(public_path('build/assets/*.css')))
            ->map(fn (string $f): string => file_get_contents($f))
            ->implode('');

        foreach ((array) config('frontend-sections.brand_palette') as $clave => $color) {
            $this->assertStringContainsString($color['border'], $bundle, "Falta la clase de «{$clave}».");
        }
    }
}

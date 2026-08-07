<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\BrandPalette;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El bloque de cierre se parte en dos cuando lleva datos destacados.
 *
 * Sin datos se ve como siempre —centrado y a todo el ancho—, que es la forma que
 * usan los cierres de las otras cuatro páginas. Con datos, el texto va a la
 * izquierda y los datos a la derecha.
 *
 * LO QUE NO PUEDE PASAR es que la tarjeta crezca de alto: su alto lo fija la
 * columna de texto, así que a partir del cuarto dato la tipografía baja de
 * escala en vez de empujar la caja. Eso se prueba mirando que las clases de
 * escala cambien, porque es lo único observable sin un navegador midiendo.
 */
class FrontendCtaBulletsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    private function schema(): FrontendSectionSchema
    {
        return app(FrontendSectionSchema::class);
    }

    /** Publica el home con estos datos en el bloque de inversionistas. */
    private function publishWithBullets(array $bullets): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();

        $page->sections()->where('section_key', 'investors_block')->firstOrFail()
            ->forceFill(['payload' => [
                'eyebrow' => 'INVERSIONISTAS',
                'title' => 'Invierte en donde otros solo ven tierra.',
                'body' => 'Creamos valor donde otros ven metros cuadrados.',
                'bullets' => $bullets,
            ]])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);
    }

    private function bullets(int $cuantos): array
    {
        return array_map(
            fn (int $i): array => ['value' => "+{$i}0%", 'text' => "Explicación número {$i}."],
            range(1, $cuantos),
        );
    }

    // ------------------------------------------------------- el schema ----

    public function test_a_closing_block_without_bullets_is_still_valid(): void
    {
        // LO QUE NO DEBE ROMPERSE. El mismo tipo cierra cuatro páginas más: si
        // los datos fueran obligatorios, esos cierres quedarían inválidos de
        // golpe y no se podrían ni guardar ni publicar.
        $this->assertSame([], $this->schema()->validate('cta', [
            'title' => 'Hablemos de tu próxima inversión',
        ]));
    }

    public function test_five_bullets_are_accepted_and_six_are_not(): void
    {
        $this->assertSame([], $this->schema()->validate('cta', ['bullets' => $this->bullets(5)]));
        $this->assertNotSame([], $this->schema()->validate('cta', ['bullets' => $this->bullets(6)]));
    }

    public function test_a_bullet_needs_both_halves(): void
    {
        // Un dato sin su explicación —o al revés— se publicaría como una fila
        // coja: un «+150» solo no dice nada.
        $this->assertNotSame([], $this->schema()->validate('cta', ['bullets' => [['value' => '+150']]]));
        $this->assertNotSame([], $this->schema()->validate('cta', ['bullets' => [['text' => 'Operaciones cerradas']]]));
    }

    // ----------------------------------------------------- el formulario ----

    public function test_an_incomplete_row_never_reaches_the_payload(): void
    {
        $section = FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'investors_block')->firstOrFail();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'title' => 'Invierte',
            'bullets' => [
                ['value' => '+12%', 'text' => 'Plusvalía anual promedio.'],
                ['value' => '+150', 'text' => ''],
                ['value' => '', 'text' => 'Sin dato.'],
            ],
        ]);

        $this->assertSame([['value' => '+12%', 'text' => 'Plusvalía anual promedio.']], $payload['bullets']);
        $this->assertSame([], $this->schema()->validate('cta', $payload));
    }

    public function test_opening_and_saving_the_form_keeps_the_bullets(): void
    {
        // LA REGRESIÓN QUE YA NOS MORDIÓ con otros repeaters: las filas se
        // dibujaban vacías al abrir y guardar sin tocar nada BORRABA el
        // contenido. Acá se abre el editor con datos ya cargados y se guarda tal
        // cual: tienen que seguir estando, y en el mismo orden.
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $section = $page->sections()->where('section_key', 'investors_block')->firstOrFail();

        $guardados = [
            ['value' => '+12%', 'text' => 'Plusvalía anual promedio.'],
            ['value' => '+150', 'text' => 'Operaciones cerradas.'],
        ];

        $section->forceFill(['payload' => ['title' => 'Invierte', 'bullets' => $guardados]])->saveQuietly();

        $editor = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());

        // Se hidrataron con su contenido, no vacías.
        $estado = $editor->get('mountedTableActionsData')[0]['payload']['bullets'] ?? [];
        $this->assertCount(2, $estado);
        $this->assertSame('+12%', array_values($estado)[0]['value'] ?? null);

        // Y guardar sin tocar nada no los pierde.
        $editor->callMountedTableAction()->assertHasNoTableActionErrors();

        // Se comparan las claves ORDENADAS: la columna es `jsonb` y PostgreSQL
        // normaliza el orden de las claves dentro de cada objeto —primero las
        // más cortas—, así que `text` vuelve antes que `value` por más que el
        // compilador las escriba al revés. Comparar el orden probaría un detalle
        // del motor, no que el contenido sobrevivió.
        $vuelta = array_map(function (array $fila): array {
            ksort($fila);

            return $fila;
        }, $section->fresh()->payload['bullets']);

        $esperado = array_map(function (array $fila): array {
            ksort($fila);

            return $fila;
        }, $guardados);

        $this->assertSame($esperado, $vuelta);
    }

    public function test_no_bullets_leaves_the_key_out_entirely(): void
    {
        // El payload canónico no lleva listas vacías, y además la ausencia de la
        // clave es lo que distingue la tarjeta partida de la centrada.
        $section = FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'final_cta')->firstOrFail();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'title' => 'Hablemos',
            'bullets' => [['value' => '', 'text' => '']],
        ]);

        $this->assertArrayNotHasKey('bullets', $payload);
    }

    // --------------------------------------------------------- el render ----

    public function test_the_bullets_are_rendered_next_to_the_text(): void
    {
        $this->publishWithBullets([
            ['value' => '+12%', 'text' => 'Plusvalía anual promedio en Querétaro.'],
            ['value' => '+150', 'text' => 'Operaciones cerradas.'],
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('+12%', $html);
        $this->assertStringContainsString('Plusvalía anual promedio en Querétaro.', $html);
        // Se afirma sobre el separador de los datos y no sobre `lg:grid-cols-2`:
        // esa grilla la usan otras tres secciones, así que hoy pasaría por estar
        // sola en el home y dejaría de probar nada el día que se agregue una.
        $this->assertStringContainsString('divide-on-brand-primary/15', $html);
    }

    public function test_without_bullets_the_block_stays_centered(): void
    {
        // La forma de siempre, la que usan los otros cuatro cierres.
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $page->sections()->where('section_key', 'investors_block')->firstOrFail()
            ->forceFill(['payload' => [
                'title' => 'Invierte con nosotros',
                // Con `body`: el párrafo centrado es el marcador de esta forma,
                // y sin texto no se dibuja ninguno.
                'body' => 'Creamos valor donde otros ven metros cuadrados.',
            ]])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Invierte con nosotros', $html);
        // El párrafo centrado con su ancho máximo es exclusivo de esta forma…
        $this->assertStringContainsString('mx-auto max-w-[560px]', $html);
        // …y NADA de la columna de datos quedó dibujado.
        $this->assertStringNotContainsString('divide-on-brand-primary/15', $html);
        // Tampoco la regla vertical: sin dos columnas no hay nada que separar,
        // y una regla al medio de un bloque centrado lo partiría al cuete.
        $this->assertStringNotContainsString('v_divider', $html);
    }

    public function test_the_vertical_rule_only_exists_when_there_are_bullets(): void
    {
        // La misma regla que usa `metrics`, para que las dos secciones se vean
        // cortadas por la misma mano. Es decorativa, así que va sin texto
        // alternativo y oculta a la tecnología asistiva: lo que la lista
        // significa ya lo dice el `dl`.
        $this->publishWithBullets($this->bullets(3));

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('images/assets/v_divider.png', $html);
        $this->assertMatchesRegularExpression(
            '/<img[^>]*v_divider\.png[^>]*alt=""[^>]*aria-hidden="true"/',
            $html,
            'La regla debe ser decorativa: sin alt y fuera del árbol de accesibilidad.',
        );
        // Apilado no hay dos columnas que separar, así que sólo existe en `lg`.
        $this->assertMatchesRegularExpression('/<img[^>]*v_divider\.png[^>]*\bhidden\b[^>]*lg:block/', $html);
    }

    // ------------------------------------------- la tarjeta no crece ----

    /**
     * Hasta tres, escala grande. Con cuatro y con cinco, cada vez más chica:
     * es lo que impide que la tarjeta se estire hacia abajo.
     */
    #[DataProvider('escalas')]
    public function test_the_type_shrinks_instead_of_stretching_the_card(int $cuantos, string $esperada): void
    {
        $this->publishWithBullets($this->bullets($cuantos));

        $this->assertStringContainsString($esperada, $this->get('/')->assertOk()->getContent());
    }

    /**
     * La escalera bajó un peldaño entero cuando el caso de hasta tres se alineó
     * con el fallback de la portada (§16.7: publicar no cambia el aspecto).
     * Antes arrancaba en `text-4xl sm:text-5xl`; ahora en `text-4xl` a secas.
     *
     * Bajar SÓLO el de arriba dejaba tres y cuatro datos del mismo tamaño en
     * escritorio, y ahí moría la regla que esta prueba cuida. Lo que se
     * verifica no son los números en sí, sino que cada escalón sea más chico
     * que el anterior —eso lo comprueba
     * test_each_step_of_the_ladder_is_smaller_than_the_previous_one—.
     */
    public static function escalas(): array
    {
        return [
            'una' => [1, 'text-4xl'],
            'tres' => [3, 'text-4xl'],
            'cuatro baja una' => [4, 'text-3xl'],
            'cinco baja dos' => [5, 'text-2xl'],
        ];
    }

    public function test_each_step_of_the_ladder_is_smaller_than_the_previous_one(): void
    {
        // El invariante REAL, dicho como tal y no como una lista de clases: si
        // alguien vuelve a mover un peldaño solo, esto falla aunque las clases
        // nuevas existan y se vean bien.
        $escalaDe = function (int $cuantos): int {
            $this->publishWithBullets($this->bullets($cuantos));
            $html = $this->get('/')->assertOk()->getContent();

            foreach ([6 => 'text-6xl', 5 => 'text-5xl', 4 => 'text-4xl', 3 => 'text-3xl', 2 => 'text-2xl', 1 => 'text-xl'] as $peso => $clase) {
                if (str_contains($html, '<dt class="font-brand-heading font-extrabold leading-none') && str_contains($html, " {$clase}\"")) {
                    return $peso;
                }
            }

            return 0;
        };

        $tres = $escalaDe(3);
        $cuatro = $escalaDe(4);
        $cinco = $escalaDe(5);

        $this->assertGreaterThan($cuatro, $tres, 'Con cuatro datos el número debe achicarse respecto de tres.');
        $this->assertGreaterThan($cinco, $cuatro, 'Con cinco datos el número debe achicarse respecto de cuatro.');
    }

    public function test_every_scale_class_is_written_literally_in_the_view(): void
    {
        // Tailwind compila leyendo el archivo: una clase armada por
        // concatenación no existiría en el CSS final y el texto saldría del
        // tamaño por defecto, justo lo que se quería evitar.
        $fuente = file_get_contents(resource_path('views/frontend/sections/cta.blade.php'));

        foreach (['text-4xl', 'text-3xl', 'text-2xl',
            'grid-cols-[auto_1fr]', 'grid-cols-subgrid'] as $clase) {
            $this->assertStringContainsString($clase, $fuente, "La clase «{$clase}» no está literal en la vista.");
        }
    }

    // ----------------------------------------------------- el color de fondo ----

    public function test_the_chosen_colour_paints_the_card(): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $page->sections()->where('section_key', 'final_cta')->firstOrFail()
            ->forceFill(['payload' => ['title' => 'Hablemos', 'background_color' => 'accent-d2']])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('bg-brand-accent-d2', $html);
        // El degradado es SIEMPRE el mismo par: negro transparente hacia negro
        // suave. Así el mismo par sirve para las diez opciones en vez de pedir
        // diez pares de clases, uno por color.
        $this->assertStringContainsString('from-black/0 to-black/30', $html);
    }

    public function test_an_unlisted_colour_is_rejected(): void
    {
        // La paleta es cerrada: el render mapea la clave a una clase fija, así
        // que una desconocida dibujaría una tarjeta sin fondo.
        $this->assertNotSame([], $this->schema()->validate('cta', [
            'title' => 'Hablemos',
            'background_color' => 'verde-fluor',
        ]));
    }

    public function test_a_light_background_flips_the_text_to_dark(): void
    {
        // EL CASO QUE IMPORTA: el acento por defecto es un ámbar con contraste
        // 2.1:1 contra blanco. Un título blanco encima quedaría ilegible, así
        // que sobre fondo claro la tinta se invierte.
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $page->sections()->where('section_key', 'final_cta')->firstOrFail()
            ->forceFill(['payload' => [
                'title' => 'Hablemos',
                'body' => 'Te acompañamos.',
                'background_color' => 'accent-l2',
            ]])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('text-brand-primary/75', $html);
    }

    public function test_the_default_navy_keeps_the_light_text(): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $page->sections()->where('section_key', 'final_cta')->firstOrFail()
            ->forceFill(['payload' => [
                'title' => 'Hablemos',
                'body' => 'Te acompañamos.',
                'background_color' => 'primary',
            ]])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('text-on-brand-primary/75', $html);
    }

    public function test_the_contrast_rule_matches_the_real_colours(): void
    {
        // Se calcula, no se lista: el owner cambia su acento y las diez
        // variantes se recalculan. Una tabla escrita a mano quedaría mintiendo.
        $paleta = app(BrandPalette::class);

        $this->assertTrue($paleta->needsDarkText('accent'), 'El ámbar de marca pide tinta oscura.');
        $this->assertTrue($paleta->needsDarkText('accent-l2'));
        $this->assertFalse($paleta->needsDarkText('primary'), 'El navy pide tinta clara.');
        $this->assertFalse($paleta->needsDarkText('primary-d2'));
    }

    public function test_every_background_class_of_the_palette_is_compiled(): void
    {
        // Tailwind compila leyendo los archivos. Si una clase `bg-*` de la
        // paleta no aparece literal en la vista, no existe en el CSS final y la
        // tarjeta sale SIN FONDO — algo que no se ve mirando el HTML.
        $bundle = collect(glob(public_path('build/assets/*.css')))
            ->map(fn (string $f): string => file_get_contents($f))
            ->implode('');

        foreach ((array) config('frontend-sections.brand_palette') as $clave => $color) {
            $this->assertStringContainsString(
                $color['bg'],
                $bundle,
                "La clase de fondo de «{$clave}» no está compilada: la tarjeta saldría sin color.",
            );
        }
    }

    // ----------------------------------------------------- el brillo de marca ----

    public function test_the_card_carries_the_accent_glow(): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $page->sections()->where('section_key', 'final_cta')->firstOrFail()
            ->forceFill(['payload' => ['title' => 'Hablemos']])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);

        $html = $this->get('/')->assertOk()->getContent();

        // Dos tarjetas de cierre en la home —el bloque de inversionistas y el
        // cierre—, así que el brillo tiene que estar en las dos.
        $this->assertSame(2, substr_count($html, 'brand-glow'));

        // Recortado contra las esquinas redondeadas, si no asomaría en la punta.
        $this->assertStringContainsString('overflow-hidden rounded-brand-xl', $html);
    }

    public function test_the_glow_is_decoration_and_nothing_else(): void
    {
        // Sin esto quedaría un elemento vacío anunciado por un lector de
        // pantalla, y una capa a pantalla completa robándole clics al contenido.
        $fuente = file_get_contents(resource_path('views/frontend/sections/cta.blade.php'));

        $this->assertMatchesRegularExpression(
            '/<span aria-hidden="true" class="\{\{ \$brillo \}\} pointer-events-none/',
            $fuente,
        );
    }

    public function test_the_glow_is_always_the_accent_and_fades_to_nothing(): void
    {
        // Siempre el acento, del 20% de alpha a 0%, con el centro metido un
        // tercio hacia adentro: en el vértice exacto, la mitad de la mancha
        // caía fuera de la tarjeta y sólo se veía su borde.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@utility brand-glow \{.*?radial-gradient\(.*?at 67% 33%.*?var\(--theme-accent.*?20%, transparent.*?\}/s',
            $css,
            'El brillo dejó de nacer donde debe, de ser del acento, o de apagarse.',
        );
    }

    public function test_on_an_accent_background_the_glow_lightens_so_it_shows(): void
    {
        // EL CASO QUE LO MOTIVÓ: con la tarjeta en acento, un brillo de acento
        // es acento sobre acento. No queda sutil — no está.
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $page->sections()->where('section_key', 'final_cta')->firstOrFail()
            ->forceFill(['payload' => ['title' => 'Hablemos', 'background_color' => 'accent']])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);

        $this->assertStringContainsString('brand-glow-light', $this->get('/')->assertOk()->getContent());
    }

    public function test_on_a_primary_background_the_glow_stays_the_plain_accent(): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $page->sections()->where('section_key', 'final_cta')->firstOrFail()
            ->forceFill(['payload' => ['title' => 'Hablemos', 'background_color' => 'primary-d1']])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);

        $html = $this->get('/')->assertOk()->getContent();

        // El bloque de inversionistas también es navy, así que las dos tarjetas
        // usan el brillo normal y ninguna el aclarado.
        $this->assertStringNotContainsString('brand-glow-light', $html);
        $this->assertSame(2, substr_count($html, 'brand-glow'));
    }

    public function test_the_lighter_glow_is_still_the_clients_accent(): void
    {
        // Aclarado con la misma mezcla que las variantes de la paleta: sigue
        // siendo SU acento, no un amarillo inventado que ignore su marca.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@utility brand-glow-light \{.*?color-mix\(in srgb, color-mix\(in srgb, var\(--theme-accent[^)]*\) 45%, #ffffff\) 20%, transparent\).*?\}/s',
            $css,
        );
    }

    // ------------------------------------------------- los otros cierres ----

    public function test_the_other_pages_closing_blocks_are_untouched(): void
    {
        // Ninguno tiene datos destacados, así que ninguno debería haberse
        // partido por este cambio.
        foreach (['nosotros', 'servicios', 'inversionistas'] as $pageKey) {
            $cierre = FrontendPage::query()->where('key', $pageKey)->firstOrFail()
                ->sections()->where('section_key', 'final_cta')->firstOrFail();

            $this->assertSame([], $this->schema()->validate('cta', $cierre->payload ?? []));
            $this->assertArrayNotHasKey('bullets', (array) $cierre->payload);
        }
    }

    public function test_a_section_of_another_type_does_not_get_bullets(): void
    {
        // El campo es del cierre, no de cualquier sección: pegarlo en otra tiene
        // que rebotar contra su propio schema.
        $section = FrontendSection::query()->where('type', 'rich_text')->firstOrFail();

        $this->assertNotSame([], $this->schema()->validate($section->type, [
            'body' => 'Texto',
            'bullets' => $this->bullets(2),
        ]));
    }
}

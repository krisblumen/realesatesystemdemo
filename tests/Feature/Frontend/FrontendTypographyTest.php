<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Pages\FrontendSettingsPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\FrontendSetting;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use App\Services\Frontend\FrontendThemeService;
use App\Support\Frontend\SectionTypography;
use App\Support\Frontend\ThemeContract;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La tipografía del encabezado se decide en DOS alturas.
 *
 *   el sitio    la familia y el grosor por defecto de todos los títulos y de
 *               todos los antetítulos, una sola vez.
 *   la sección  puede llevarle la contra al grosor, sólo para ella.
 *
 * Lo que más se prueba acá es el estado AUSENTE, que es el que hace que el
 * default global siga mandando: si una sección guardara el valor heredado,
 * cambiar la configuración dejaría de moverla y el owner tendría que volver a
 * recorrer sección por sección — exactamente lo que configurar en un solo lugar
 * venía a evitar.
 */
class FrontendTypographyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function theme(array $theme): void
    {
        FrontendSetting::query()->updateOrCreate(
            ['singleton_key' => 'default'],
            ['theme' => $theme, 'site_name' => 'Landra'],
        );

        app()->forgetInstance(FrontendThemeService::class);
    }

    // ------------------------------------------------ la lista de fuentes --

    public function test_every_downloadable_font_is_actually_compiled(): void
    {
        // Una familia en la lista pero no en Vite es peor que no ofrecerla: el
        // owner la elige, el navegador no la encuentra y cae al fallback, así
        // que el panel le promete algo que el sitio nunca muestra.
        $vite = (string) file_get_contents(base_path('vite.config.js'));

        foreach (ThemeContract::FONTS as $font) {
            if (in_array($font, ThemeContract::SYSTEM_FONTS, true)) {
                continue;
            }

            $this->assertStringContainsString(
                "bunny('{$font}'",
                $vite,
                "«{$font}» se puede elegir pero no la compila Vite: saldría con la tipografía de reserva.",
            );
        }
    }

    public function test_the_system_fonts_are_not_downloaded(): void
    {
        // Su gracia es pesar cero. Si alguna terminara en Vite dejaría de tenerla.
        $vite = (string) file_get_contents(base_path('vite.config.js'));

        foreach (ThemeContract::SYSTEM_FONTS as $font) {
            $this->assertStringNotContainsString("bunny('{$font}'", $vite);
            $this->assertContains($font, ThemeContract::FONTS);
        }
    }

    public function test_a_font_outside_the_list_never_reaches_the_stylesheet(): void
    {
        $this->theme(['heading_font' => 'Comic Sans MS', 'eyebrow_font' => '</style><script>']);

        $vars = app(FrontendThemeService::class)->cssVariables();

        $this->assertSame('Montserrat', $vars['--nh-font-heading']);
        $this->assertSame('Montserrat', $vars['--nh-font-eyebrow']);
    }

    public function test_a_font_name_reaches_the_page_unescaped(): void
    {
        // El layout emite las variables con `{{ }}`. Entrecomillar el nombre acá
        // hacía que Blade escapara la comilla a `&#039;`, y el `font-family`
        // entero quedaba inválido: el navegador descartaba la declaración y todo
        // el sitio caía a la tipografía heredada.
        $this->theme(['heading_font' => 'Playfair Display', 'eyebrow_font' => 'Space Grotesk']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('--nh-font-heading: Playfair Display;', $html);
        $this->assertStringContainsString('--nh-font-eyebrow: Space Grotesk;', $html);
        $this->assertStringNotContainsString('&#039;', $html);
    }

    public function test_the_page_actually_loads_the_font_it_declares(): void
    {
        // EL BUG: `@vite` no trae las tipografías. El plugin las compila en un
        // chunk aparte que ninguna entrada importa, así que el `@font-face` nunca
        // llegaba a la página y el sitio dibujaba todo con la reserva del
        // sistema. La variable CSS estaba bien, el nombre de la familia estaba
        // bien, y aun así no se veía — por eso no alcanza con afirmar la variable.
        $this->theme(['heading_font' => 'Caveat', 'body_font' => 'Montserrat', 'eyebrow_font' => 'Montserrat']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('--nh-font-heading: Caveat;', $html);
        $this->assertMatchesRegularExpression('/@font-face\s*\{[^}]*Caveat/i', $html, 'La página declara Caveat pero no la carga: saldría con la tipografía del sistema.');
        $this->assertStringContainsString('as="font"', $html, 'Faltan los preload de las tipografías.');
    }

    public function test_only_the_configured_families_are_downloaded(): void
    {
        // El catálogo tiene seis descargables. Ofrecerle variedad al owner no
        // puede costarle seis descargas a quien entra al sitio.
        $this->theme(['heading_font' => 'Caveat', 'body_font' => 'Caveat', 'eyebrow_font' => 'Caveat']);

        $html = $this->get('/')->assertOk()->getContent();

        foreach (['Playfair Display', 'Lora', 'Space Grotesk', 'Inter'] as $ausente) {
            $this->assertDoesNotMatchRegularExpression(
                '/@font-face\s*\{[^}]*'.preg_quote($ausente, '/').'/i',
                $html,
                "«{$ausente}» se descarga sin que el sitio la use.",
            );
        }
    }

    public function test_the_aliases_skip_the_system_fonts(): void
    {
        // `Vite::fonts()` lanza una excepción con un alias que no está en su
        // manifiesto, y Arial no tiene archivo que cargar: elegirla no puede
        // tumbar la página entera.
        $this->theme(['heading_font' => 'Arial', 'body_font' => 'Georgia', 'eyebrow_font' => 'Caveat']);

        $this->assertSame(['caveat'], app(FrontendThemeService::class)->fontAliases());

        $this->get('/')->assertOk();
    }

    public function test_a_site_using_only_system_fonts_still_renders(): void
    {
        $this->theme(['heading_font' => 'Arial', 'body_font' => 'Georgia', 'eyebrow_font' => 'Arial']);

        // Sin alias, `Vite::fonts()` cargaría LAS SEIS. Por eso no se lo llama.
        $this->assertSame([], app(FrontendThemeService::class)->fontAliases());

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('as="font"', $html);
    }

    // -------------------------------------------------- el default global --

    public function test_the_site_sets_the_weight_of_titles_and_eyebrows_apart(): void
    {
        $this->theme(['heading_bold' => false, 'eyebrow_bold' => true]);

        $vars = app(FrontendThemeService::class)->cssVariables();

        $this->assertSame('400', $vars['--nh-weight-heading']);
        $this->assertSame('700', $vars['--nh-weight-eyebrow']);
    }

    public function test_the_eyebrow_no_longer_borrows_the_title_font(): void
    {
        // Antes `.eyebrow` apuntaba al token fijo de display, así que elegir otra
        // tipografía de títulos NO lo tocaba y el owner veía a medias el cambio
        // que había pedido.
        $this->theme(['heading_font' => 'Caveat', 'eyebrow_font' => 'Lora']);

        $vars = app(FrontendThemeService::class)->cssVariables();

        $this->assertSame('Caveat', $vars['--nh-font-heading']);
        $this->assertSame('Lora', $vars['--nh-font-eyebrow']);

        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('font-family: var(--font-brand-eyebrow);', $css);
    }

    // --------------------------------------------- lo que elige la sección --

    /** @return array<string, array{mixed, string, string}> */
    public static function weights(): array
    {
        return [
            'sin elegir' => [null, 'font-weight-heading', ''],
            'negrita' => [true, 'font-bold!', 'font-bold!'],
            'normal' => [false, 'font-normal!', 'font-normal!'],
        ];
    }

    #[DataProvider('weights')]
    public function test_the_section_resolves_its_own_weight(mixed $bold, string $titulo, string $antetitulo): void
    {
        $this->assertSame($titulo, SectionTypography::title(['title_bold' => $bold]));
        $this->assertSame($antetitulo, SectionTypography::eyebrow(['eyebrow_bold' => $bold]));
    }

    public function test_an_untouched_section_inherits_instead_of_deciding(): void
    {
        // Sin la clave, el título lleva la clase que LEE la variable del sitio y
        // el antetítulo no lleva ninguna —se la pone la utility `eyebrow`, que es
        // la misma que usan las páginas que no pasan por el CMS.
        $this->assertSame('font-weight-heading', SectionTypography::title([]));
        $this->assertSame('', SectionTypography::eyebrow([]));
    }

    public function test_the_override_wins_over_the_inherited_weight(): void
    {
        // El `!` no es decoración: `eyebrow` y `font-weight-heading` también fijan
        // `font-weight`, y entre utilities de la misma especificidad gana la que
        // Tailwind haya dejado última — un orden que cambia al agregar utilities.
        foreach ([SectionTypography::title(['title_bold' => true]), SectionTypography::eyebrow(['eyebrow_bold' => true])] as $clase) {
            $this->assertStringEndsWith('!', $clase);
        }

        // Y las cuatro clases tienen que estar realmente en el CSS compilado: una
        // que Tailwind no encuentre escrita deja la sección con el peso heredado
        // y el selector del panel sin efecto visible.
        $css = collect(glob(public_path('build/assets/*.css')))
            ->map(fn (string $ruta): string => (string) file_get_contents($ruta))
            ->implode('');

        $this->assertNotSame('', $css, 'Falta compilar los assets: corré `npm run build`.');

        $this->assertStringContainsString('font-weight-heading', $css, 'La utility del peso heredado no está compilada.');
        $this->assertMatchesRegularExpression('/font-weight:\s*var\(--nh-weight-heading/', $css);

        // El antetítulo hereda por `eyebrow`, no por una clase propia: su peso
        // tiene que salir de la variable dentro de esa utility.
        $this->assertMatchesRegularExpression('/font-weight:\s*var\(--nh-weight-eyebrow/', $css);
    }

    // ------------------------------------------------ guardado y schema --

    public function test_not_choosing_a_weight_is_not_saved(): void
    {
        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('type', 'values')->firstOrFail();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'title' => 'Nuestros valores',
            'title_bold' => null,
            'eyebrow_bold' => '',
            'items' => [['title' => 'Confianza', 'description' => 'Cumplimos.']],
        ]);

        $this->assertArrayNotHasKey('title_bold', $payload);
        $this->assertArrayNotHasKey('eyebrow_bold', $payload);
    }

    public function test_the_selector_strings_become_real_booleans(): void
    {
        // El selector devuelve '1'/'0' —claves de opciones, texto— y el schema
        // exige booleanos. Un '0' guardado como texto sería «verdadero» al leerlo.
        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('type', 'values')->firstOrFail();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'title' => 'Nuestros valores',
            'title_bold' => '1',
            'eyebrow_bold' => '0',
            'items' => [['title' => 'Confianza', 'description' => 'Cumplimos.']],
        ]);

        $this->assertTrue($payload['title_bold']);
        $this->assertFalse($payload['eyebrow_bold']);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('values', $payload));
    }

    public function test_every_type_with_a_heading_declares_both_weights(): void
    {
        // La lista del formulario y la del schema tienen que decir lo mismo: si
        // se agrega un tipo con encabezado a una sola, el owner ve el selector y
        // el guardado lo rechaza — o peor, el selector no aparece nunca.
        $reflection = new \ReflectionClass(SectionsRelationManager::class);
        $conEncabezado = $reflection->getConstant('CON_ENCABEZADO');

        $this->assertNotEmpty($conEncabezado);

        $specs = new \ReflectionClass(FrontendSectionSchema::class);
        $todos = $specs->getConstant('SPECS');

        foreach ($conEncabezado as $type) {
            $this->assertArrayHasKey('title_bold', $todos[$type], "«{$type}» ofrece el selector pero el schema lo rechaza.");
            $this->assertArrayHasKey('eyebrow_bold', $todos[$type]);
        }

        foreach ($todos as $type => $spec) {
            if (array_key_exists('title_bold', $spec)) {
                $this->assertContains($type, $conEncabezado, "«{$type}» acepta el grosor pero no muestra el selector.");
            }
        }
    }

    // ------------------------------------- los controles de la configuración --

    public function test_the_settings_page_offers_the_four_typography_controls(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.theme.heading_font', 'Playfair Display')
            ->set('data.theme.heading_bold', false)
            ->set('data.theme.eyebrow_font', 'Caveat')
            ->set('data.theme.eyebrow_bold', true)
            ->call('save')
            ->assertHasNoErrors();

        app()->forgetInstance(FrontendThemeService::class);
        $vars = app(FrontendThemeService::class)->cssVariables();

        $this->assertSame('Playfair Display', $vars['--nh-font-heading']);
        $this->assertSame('400', $vars['--nh-weight-heading']);
        $this->assertSame('Caveat', $vars['--nh-font-eyebrow']);
        $this->assertSame('700', $vars['--nh-weight-eyebrow']);
    }

    public function test_the_settings_page_rejects_a_font_outside_the_list(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.theme.eyebrow_font', 'Comic Sans MS')
            ->call('save')
            ->assertHasErrors(['data.theme.eyebrow_font']);
    }

    // ------------------------------------------- la vista previa del sitio --

    private function preview(array $theme): string
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        return Livewire::test(FrontendSettingsPage::class)
            ->set('data.theme', $theme)
            ->html();
    }

    public function test_the_preview_shows_the_chosen_colours_and_fonts(): void
    {
        $html = $this->preview([
            'primary' => '#123456', 'on_primary' => '#ffffff',
            'accent' => '#f5a624', 'on_accent' => '#171d23',
            'background' => '#fafafa', 'text' => '#222222',
            'heading_font' => 'Playfair Display', 'eyebrow_font' => 'Caveat',
            'body_font' => 'Lora', 'radius' => 'xl',
        ]);

        $this->assertStringContainsString('background:#123456', $html);
        $this->assertStringContainsString('background:#fafafa', $html);
        $this->assertStringContainsString("'Playfair Display'", $html);
        $this->assertStringContainsString("'Caveat'", $html);
        $this->assertStringContainsString("'Lora'", $html);
        // El redondeo `xl` de la escala cerrada, no el nombre del preset.
        $this->assertStringContainsString('border-radius:32px', $html);
    }

    public function test_the_preview_shows_what_the_site_will_publish_not_what_was_typed(): void
    {
        // ESTA es la razón de que el preview pida el tema normalizado. Con texto
        // casi blanco sobre fondo blanco, el sitio cambia la tinta por una legible
        // — si el preview dibujara el color tal cual se eligió, mostraría un
        // bloque en blanco y el owner creería que rompió algo.
        $html = $this->preview([
            'primary' => '#ffffff', 'on_primary' => '#fefefe',
            'accent' => '#f5a624', 'on_accent' => '#171d23',
            'background' => '#ffffff', 'text' => '#fdfdfd',
            'radius' => 'medium',
        ]);

        $this->assertStringNotContainsString('color:#fefefe', $html);
        $this->assertStringNotContainsString('color:#fdfdfd', $html);
    }

    public function test_the_preview_honours_the_low_contrast_opt_out(): void
    {
        // Con el permiso activado el sitio NO sustituye nada, así que el preview
        // tampoco: es la decisión del owner y tiene que poder verla.
        $html = $this->preview([
            'primary' => '#ffffff', 'on_primary' => '#fefefe',
            'accent' => '#f5a624', 'on_accent' => '#171d23',
            'background' => '#ffffff', 'text' => '#fdfdfd',
            'radius' => 'medium', 'allow_low_contrast' => true,
        ]);

        $this->assertStringContainsString('color:#fefefe', $html);
    }

    public function test_a_font_the_panel_cannot_draw_is_never_offered(): void
    {
        // El panel compila su propio CSS: una familia que se puede elegir pero que
        // el panel no carga saldría dibujada en la del panel, y la vista previa
        // mostraría una tipografía distinta de la que el sitio va a publicar.
        $panel = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));

        foreach (ThemeContract::FONTS as $font) {
            if (in_array($font, ThemeContract::SYSTEM_FONTS, true)) {
                continue;
            }

            $this->assertStringContainsString(
                str_replace(' ', '+', $font),
                $panel,
                "El panel no carga «{$font}»: la vista previa la dibujaría con otra tipografía.",
            );
        }
    }

    public function test_the_corner_sample_lives_inside_the_site_preview(): void
    {
        // Antes era una maqueta aparte, con un gris que no existe en el tema. Dos
        // vistas previas obligaban a mirar en dos lados una decisión que se
        // entiende mejor junto al resto — y el gris no mostraba ningún color real.
        $html = $this->preview([
            'primary' => '#123456', 'background' => '#fafafa',
            'accent' => '#f5a624', 'on_accent' => '#171d23',
            'radius' => 'xl',
        ]);

        $this->assertStringContainsString('Esquinas', $html);

        // El markup exacto de la maqueta vieja, que era la única que usaba ese
        // par de grises. `#e5e7eb` a secas no sirve como señal: lo usan los
        // bordes de media página.
        $this->assertStringNotContainsString('background:#e5e7eb;border:1px solid #d1d5db', $html);
        $this->assertStringNotContainsString('background:#9ca3af', $html);

        // Y la muestra usa un color REAL del tema, no un gris inventado.
        $this->assertStringContainsString('background:#fafafa', $html);

        // La muestra usa la escala del preset elegido, igual que el resto.
        $this->assertStringContainsString('border-radius:32px', $html);
        $this->assertStringContainsString('border-radius:24px', $html);
    }

    public function test_the_preview_never_leaks_a_blade_comment(): void
    {
        // El HTML se arma en un heredoc de PHP: un `{{--` ahí adentro no es un
        // comentario, se imprime tal cual en el panel.
        $html = $this->preview(['radius' => 'medium']);

        $this->assertStringNotContainsString('{{--', $html);
    }

    public function test_the_preview_reflects_the_weight_of_each_one(): void
    {
        $negrita = $this->preview(['heading_bold' => true, 'eyebrow_bold' => true, 'radius' => 'medium']);
        $normal = $this->preview(['heading_bold' => false, 'eyebrow_bold' => false, 'radius' => 'medium']);

        $this->assertStringContainsString('font-weight:700;font-size:26px', $negrita);
        $this->assertStringContainsString('font-weight:400;font-size:26px', $normal);
        $this->assertStringContainsString('font-weight:700;letter-spacing', $negrita);
        $this->assertStringContainsString('font-weight:600;letter-spacing', $normal);
    }

    // --------------------------------------------- el color del título de cta --

    public function test_the_cta_title_can_carry_its_own_colour(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $this->actingAs($owner);

        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('type', 'cta')->firstOrFail();
        $section->forceFill(['payload' => [
            'title' => 'Trabajemos juntos',
            'background_color' => 'primary',
            'title_color' => 'accent',
        ]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $owner);

        $html = $this->get('/nosotros')->assertOk()->getContent();

        $this->assertStringContainsString('text-brand-accent">Trabajemos juntos<', $html);
    }

    public function test_without_a_choice_the_cta_title_keeps_the_readable_ink(): void
    {
        // Sin elegir, el título sigue saliendo del juego de tinta que decide el
        // fondo — que es lo que garantiza que se lea sobre él.
        $owner = User::factory()->withRole('owner')->create();
        $this->actingAs($owner);

        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('type', 'cta')->firstOrFail();
        $section->forceFill(['payload' => [
            'title' => 'Trabajemos juntos',
            'background_color' => 'primary',
        ]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $owner);

        $html = $this->get('/nosotros')->assertOk()->getContent();

        $this->assertStringContainsString('text-on-brand-primary">Trabajemos juntos<', $html);
    }

    public function test_the_cta_colour_touches_only_the_title(): void
    {
        $section = FrontendSection::query()->where('type', 'cta')->firstOrFail();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'title' => 'Trabajemos juntos',
            'eyebrow' => 'EL MOMENTO ES AHORA',
            'body' => 'Contanos qué buscás.',
            'title_color' => 'accent',
        ]);

        // Un color por texto habría terminado en tres que no se hablan entre sí.
        $this->assertSame('accent', $payload['title_color']);
        $this->assertArrayNotHasKey('eyebrow_color', $payload);
        $this->assertArrayNotHasKey('body_color', $payload);
    }
}

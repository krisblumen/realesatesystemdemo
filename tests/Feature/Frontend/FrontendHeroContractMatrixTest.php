<?php

namespace Tests\Feature\Frontend;

use App\Filament\Pages\FrontendSettingsPage;
use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendPageRenderer;
use App\Services\Frontend\FrontendSettingsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Feature\Frontend\Concerns\MountsSectionEditor;
use Tests\TestCase;

/**
 * Épica 12.1, Lote B — M-B-3: the parts of the contract the first round asserted
 * on PHP structures instead of on observable behaviour.
 *
 * The distinction matters: a correct presenter is not the same as a correct
 * public page. The five routes are exercised over HTTP, the editor over
 * Livewire, and the JS/CSS guarantees that no PHP test can execute are asserted
 * as CONTRACT GUARDS on their source — labelled as such, never dressed up as
 * behavioural proof.
 */
class FrontendHeroContractMatrixTest extends TestCase
{
    use MountsSectionEditor;
    use RefreshDatabase;

    private User $owner;

    /**
     * The canonical routes and the page key each one renders.
     *
     * Escrita a mano y no derivada de `config('frontend-sections.pages')` a
     * propósito: el mapa de ruta a clave es justamente lo que se está
     * probando, y derivarlo de la misma config que usa el código haría que la
     * prueba se mueva sola cuando el código se mueve. El costo es acordarse de
     * sumar acá cada página nueva — lo mismo que se olvidó con la vista previa
     * (`FrontendPreview::pages()`), que sí se unificó porque ahí la
     * duplicación no compraba nada.
     */
    private const ROUTES = [
        '/' => 'home',
        '/nosotros' => 'nosotros',
        '/servicios' => 'servicios',
        '/inversionistas' => 'inversionistas',
        '/contacto' => 'contacto',
        '/proyectos' => 'proyectos',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
    }

    private function page(string $key): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function hero(string $key): FrontendSection
    {
        return $this->page($key)->sections()->where('section_key', 'hero')->firstOrFail();
    }

    /** @param  array<string, mixed>|null  $payload */
    private function publish(string $key, ?array $payload): void
    {
        $this->hero($key)->update(['payload' => $payload]);
        $page = $this->page($key)->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
        app(FrontendCacheGeneration::class)->bump();
    }

    // ------------------------------------------------------------- TB-4 -----

    public function test_every_route_renders_one_hero_through_the_shared_partial(): void
    {
        // C-B-1: before this batch, an install with nothing published rendered a
        // SECOND hero written into each page's Blade. Every route must go through
        // the shared partial whether it has a snapshot or not.
        foreach (self::ROUTES as $path => $key) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('data-nh-hero', $html, "Sin hero compartido en {$path}");
            $this->assertSame(1, substr_count($html, '<section data-nh-hero'), "Más de un hero en {$path}");
            $this->assertStringNotContainsString('nhHeroFade', $html, "Quedó el fade legacy en {$path}");
        }
    }

    public function test_no_public_route_ships_an_inline_style_or_script_in_its_hero(): void
    {
        foreach (self::ROUTES as $path => $key) {
            $html = $this->get($path)->assertOk()->getContent();
            $start = strpos($html, '<section data-nh-hero');
            $hero = substr($html, $start, strpos($html, '</section>', $start) - $start);

            $this->assertStringNotContainsString('<style', $hero, "Estilo inline en el hero de {$path}");
            $this->assertStringNotContainsString('<script', $hero, "Script inline en el hero de {$path}");
            $this->assertStringNotContainsString('style="', $hero, "Atributo style en el hero de {$path}");
            $this->assertStringNotContainsString('animation-delay', $hero, "Delay inline en el hero de {$path}");
        }
    }

    /**
     * Cada página, con su propio marcador de fallback. `contacto` no tiene fondo
     * hoy, así que su fallback es la ausencia de imagen: no es un caso especial,
     * es «el valor hardcodeado actual» de esa página.
     *
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function rutas(): array
    {
        return [
            'home' => ['/', 'home', 'unsplash'],
            'nosotros' => ['/nosotros', 'nosotros', 'header_nosotros'],
            'servicios' => ['/servicios', 'servicios', 'header_servicios'],
            'inversionistas' => ['/inversionistas', 'inversionistas', 'header_inversionistas'],
            'contacto' => ['/contacto', 'contacto', null],
        ];
    }

    #[DataProvider('rutas')]
    public function test_the_five_states_of_the_fallback_matrix_on_every_route(string $path, string $key, ?string $marker): void
    {
        $sinImagen = fn (string $html, string $estado) => $this->assertStringNotContainsString(
            'nh-hero-slide', $html, "{$path} debería quedar SIN imagen en el estado: {$estado}"
        );

        // 1. Sin publicación → el fondo propio de la página (o ninguno).
        $html = $this->get($path)->assertOk()->getContent();
        $marker === null
            ? $sinImagen($html, 'sin publicar')
            : $this->assertStringContainsString($marker, $html, "{$path} sin publicar debe usar su propio fondo");

        // 2. Publicado SIN la clave `slides` → el mismo fallback: «no inicializado».
        $this->publish($key, ['title' => 'Título de prueba']);
        $html = $this->get($path)->assertOk()->getContent();
        $marker === null
            ? $sinImagen($html, 'clave slides ausente')
            : $this->assertStringContainsString($marker, $html, "{$path} sin la clave slides debe usar su propio fondo");

        // 3. Publicado con `slides: []` → SIN imagen. Apagar es una decisión.
        $this->publish($key, ['title' => 'Título de prueba', 'slides' => []]);
        $html = $this->get($path)->assertOk()->getContent();
        $sinImagen($html, 'slides vacío');
        if ($marker !== null) {
            $this->assertStringNotContainsString($marker, $html, "{$path}: un slides vacío no revive el fallback");
        }

        // 4. Publicado con media NO promovida → se omite, y tampoco revive nada.
        Queue::fake();
        $media = $this->hero($key)->addMedia(UploadedFile::fake()->image('s.png', 1600, 900))
            ->toMediaCollection('images');
        $this->publish($key, [
            'title' => 'Título de prueba',
            'slides' => [['media_id' => (string) $media->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]);
        $html = $this->get($path)->assertOk()->getContent();
        $sinImagen($html, 'media sin promover');

        // 5. Ya promovida → se renderiza.
        app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
        app(FrontendCacheGeneration::class)->bump();
        $this->assertStringContainsString('nh-hero-slide', $this->get($path)->assertOk()->getContent(), "{$path}: una media promovida debe renderizarse");
    }

    public function test_a_published_hero_that_was_never_edited_keeps_its_text_and_h1(): void
    {
        // Regresión del defecto real: publicar una página cuyo hero nunca se tocó
        // dejaba `payload: null`, y la portada salía con fondo, sin texto y SIN
        // <h1>. Un payload vacío es «no inicializado», igual que una clave
        // `slides` ausente.
        $this->publish('home', null);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<h1', $html, 'Un hero nunca editado no puede dejar la página sin H1.');
        $this->assertStringContainsString('Construimos patrimonio', $html);
    }

    // ------------------------------------------------------------- TB-2 -----

    public function test_the_editor_rehydrates_zero_one_and_six_slides(): void
    {
        $this->actingAs($this->owner);

        $section = $this->hero('home');

        foreach ([0, 1, 6] as $count) {
            $slides = [];
            for ($i = 0; $i < $count; $i++) {
                $media = $section->addMedia(UploadedFile::fake()->image("s{$i}.png", 1600, 900))
                    ->toMediaCollection('images');
                $slides[] = ['media_id' => (string) $media->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => $i];
            }

            $section->update(['payload' => ['title' => 'T', 'slides' => $slides]]);

            $state = $this->sectionEditor($section)
                ->mountTableAction('edit', $section->getKey())
                ->get('mountedTableActionsData')[0] ?? [];

            $this->assertCount($count, $state['payload']['slides'] ?? [], "Rehidratación con {$count} slides");
        }
    }

    public function test_replacing_an_image_creates_a_new_uuid_without_destroying_the_old_one(): void
    {
        $this->actingAs($this->owner);

        $section = $this->hero('home');
        $old = $section->addMedia(UploadedFile::fake()->image('old.png', 1600, 900))->toMediaCollection('images');
        $section->update(['payload' => [
            'title' => 'T',
            'slides' => [['media_id' => (string) $old->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]]);

        // Reemplazar es mutar el ÍTEM EXISTENTE del repeater, no mandar un array
        // nuevo: el repeater indexa por claves propias, así que un array plano se
        // agregaría como una segunda slide en vez de sustituir la imagen.
        $component = $this->sectionEditor($section)->mountTableAction('edit', $section->getKey());

        $data = $component->get('mountedTableActionsData')[0];
        $itemKey = array_key_first($data['payload']['slides']);
        $data['payload']['slides'][$itemKey]['upload'] = [UploadedFile::fake()->image('new.png', 1600, 900)];

        $component->setTableActionData($data)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertCount(1, $section->fresh()->payload['slides'], 'Reemplazar no debe agregar una slide.');

        $newUuid = $section->fresh()->payload['slides'][0]['media_id'];

        $this->assertNotSame((string) $old->uuid, $newUuid, 'Reemplazar debe apuntar a un uuid nuevo.');
        // v1 nunca borra: la anterior sigue existiendo, solo deja de referenciarse.
        $this->assertNotNull(Media::query()->where('uuid', $old->uuid)->first(), 'La media anterior no debe borrarse.');
        $this->assertTrue(Storage::disk('frontend-private')->exists($old->getPathRelativeToRoot()));
    }

    // ------------------------------------------------------------ TB-10 -----

    public function test_without_a_heading_the_logo_carries_the_brand_name(): void
    {
        // El caso real: sin H1 el logo es la única identidad visible, así que se
        // nombra. La versión anterior de esta prueba publicaba un título, con lo
        // que jamás ejercitaba esta rama.
        $this->publish('nosotros', ['title' => 'Temporal', 'logo_enabled' => true]);

        $page = $this->page('nosotros')->fresh();
        $snapshot = $page->published_revision;
        foreach ($snapshot['sections'] as $i => $section) {
            if ($section['section_key'] === 'hero') {
                $snapshot['sections'][$i]['payload']['title'] = '';
            }
        }
        $page->update(['published_revision' => $snapshot]);
        app(FrontendCacheGeneration::class)->bump();

        $html = $this->get('/nosotros')->assertOk()->getContent();
        $start = strpos($html, '<section data-nh-hero');
        $hero = substr($html, $start, strpos($html, '</section>', $start) - $start);

        $this->assertStringNotContainsString('<h1', $hero, 'El caso a probar es justamente el hero sin encabezado.');

        // Se afirma sobre la ETIQUETA DEL LOGO, no sobre el hero entero: las
        // slides decorativas también llevan alt="" y aria-hidden, así que una
        // búsqueda global no probaría nada del logo.
        $settings = app(FrontendSettingsService::class)->settings();
        $logoUrl = $settings['brand']['logo_dark_url'];
        $siteName = $settings['site_name'];

        $at = strpos($hero, $logoUrl);
        $this->assertNotFalse($at, 'El logo debe renderizarse cuando está activado.');

        $tagStart = strrpos(substr($hero, 0, $at), '<img');
        $logoTag = substr($hero, $tagStart, strpos($hero, '>', $at) - $tagStart);

        $this->assertStringContainsString('alt="'.e($siteName).'"', $logoTag, 'Sin H1 el logo debe nombrar la marca.');
        $this->assertStringNotContainsString('aria-hidden', $logoTag, 'Sin H1 el logo NO es decorativo.');
    }

    // ------------------------------------------------- hero-logo-propio -----

    /**
     * Cambio cms-pagina-proyectos, Fase 2 — la matriz de 4 combinaciones de la
     * decisión #1090: `logo_enabled` es el ÚNICO interruptor y gobierna los
     * DOS logos. La regla del spec original («el propio ignora el
     * interruptor») queda descartada: no dejaba forma de apagarlo, porque
     * borrar la imagen propia revivía el logo por el fallback §16.7 sin que
     * el interruptor pudiera evitarlo.
     *
     * @return array<string, array{0: bool, 1: bool, 2: string}>
     */
    public static function logoPrecedence(): array
    {
        return [
            'propio + habilitado → propio' => [true, true, 'own'],
            'propio + deshabilitado → ninguno (corrige spec)' => [true, false, 'none'],
            'sin propio + habilitado → marca' => [false, true, 'brand'],
            'sin propio + deshabilitado → ninguno' => [false, false, 'none'],
        ];
    }

    #[DataProvider('logoPrecedence')]
    public function test_the_own_logo_precedence_follows_decision_1090(bool $ownLogo, bool $logoEnabled, string $expected): void
    {
        Queue::fake();

        $section = $this->hero('nosotros');
        $payload = ['title' => 'T', 'logo_enabled' => $logoEnabled];
        $media = null;

        if ($ownLogo) {
            $media = $section->addMedia(UploadedFile::fake()->image('logo.png', 400, 200))->toMediaCollection('images');
            $payload['logo'] = ['media_id' => (string) $media->uuid, 'alt' => 'Logo propio'];
        }

        $this->publish('nosotros', $payload);

        if ($media !== null) {
            app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
            app(FrontendCacheGeneration::class)->bump();
        }

        $html = $this->get('/nosotros')->assertOk()->getContent();
        $start = strpos($html, '<section data-nh-hero');
        $hero = substr($html, $start, strpos($html, '</section>', $start) - $start);

        $brandLogoUrl = app(FrontendSettingsService::class)->settings()['brand']['logo_dark_url'];

        if ($expected === 'own') {
            $this->assertStringContainsString($media->getUrl(), $hero, 'Con logo propio resuelto y el interruptor prendido, debe ganar el propio.');
            $this->assertStringNotContainsString($brandLogoUrl, $hero, 'El logo de marca no debe convivir con el propio.');
        } elseif ($expected === 'brand') {
            $this->assertStringContainsString($brandLogoUrl, $hero, 'Sin logo propio y el interruptor prendido, debe mostrarse el de marca.');
        } else {
            $this->assertStringNotContainsString($brandLogoUrl, $hero, 'El interruptor apagado no debe mostrar ningún logo.');
            if ($media !== null) {
                // El badge A-74 (hallazgo #5, Fase 3) es un mecanismo APARTE,
                // independiente de `logo_enabled`: puede seguir mostrando la
                // misma url (test_the_a74_badge_follows_the_resolved_own_logo_not_the_toggle).
                // Lo que la decisión #1090 prohíbe es el logo GRANDE (marca
                // `mb-9`), así que la búsqueda se acota a la etiqueta que
                // contiene la url en vez de a todo el fragmento del hero.
                $at = strpos($hero, $media->getUrl());
                if ($at !== false) {
                    $tagStart = strrpos(substr($hero, 0, $at), '<img');
                    $tag = substr($hero, $tagStart, strpos($hero, '>', $at) - $tagStart);
                    $this->assertStringNotContainsString('mb-9', $tag, 'El interruptor apagado debe tapar también el logo GRANDE propio (decisión #1090).');
                }
            }
        }
    }

    public function test_an_own_logo_not_yet_promoted_falls_back_to_the_brand_logo(): void
    {
        // Se mantiene del spec: «logo presente» se juzga por media_url
        // RESUELTO, no por la existencia cruda de media_id.
        Queue::fake();

        $section = $this->hero('nosotros');
        $media = $section->addMedia(UploadedFile::fake()->image('logo.png', 400, 200))->toMediaCollection('images');

        $this->publish('nosotros', [
            'title' => 'T',
            'logo_enabled' => true,
            'logo' => ['media_id' => (string) $media->uuid, 'alt' => 'Logo propio'],
        ]);
        // Deliberadamente SIN promover: el media sigue en el disco privado.

        $html = $this->get('/nosotros')->assertOk()->getContent();
        $start = strpos($html, '<section data-nh-hero');
        $hero = substr($html, $start, strpos($html, '</section>', $start) - $start);

        $brandLogoUrl = app(FrontendSettingsService::class)->settings()['brand']['logo_dark_url'];
        $this->assertStringContainsString($brandLogoUrl, $hero, 'Sin promover, el logo propio debe caer al de marca.');
    }

    /**
     * Cambio cms-pagina-proyectos, Fase 3 — hallazgo #5 y design D5: el
     * distintivo A-74 (`site/proyectos.blade.php:37-42` en su forma original)
     * es un badge PROPIO, distinto del logo grande del hero. Se muestra sólo
     * con un logo propio RESUELTO, sin importar `logo_enabled` — apagar el
     * interruptor tapa el logo grande, no el badge.
     *
     * @return array<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function badgeVisibility(): array
    {
        return [
            'propio + habilitado → badge sí' => [true, true, true],
            'propio + deshabilitado → badge sigue (independiente de $showLogo)' => [true, false, true],
            'sin propio + habilitado → badge no' => [false, true, false],
            'sin propio + deshabilitado → badge no' => [false, false, false],
        ];
    }

    #[DataProvider('badgeVisibility')]
    public function test_the_a74_badge_follows_the_resolved_own_logo_not_the_toggle(bool $ownLogo, bool $logoEnabled, bool $expectBadge): void
    {
        Queue::fake();

        $section = $this->hero('nosotros');
        $payload = ['title' => 'T', 'logo_enabled' => $logoEnabled];
        $media = null;

        if ($ownLogo) {
            $media = $section->addMedia(UploadedFile::fake()->image('logo.png', 400, 200))->toMediaCollection('images');
            $payload['logo'] = ['media_id' => (string) $media->uuid, 'alt' => 'A-74 Arquitectura'];
        }

        $this->publish('nosotros', $payload);

        if ($media !== null) {
            app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
            app(FrontendCacheGeneration::class)->bump();
        }

        $html = $this->get('/nosotros')->assertOk()->getContent();
        $start = strpos($html, '<section data-nh-hero');
        $hero = substr($html, $start, strpos($html, '</section>', $start) - $start);

        if ($expectBadge) {
            // El badge se reconoce por su `opacity-80`, no por el filtro: el
            // filtro DEPENDE de si el logo es del fallback o del owner (ver
            // test_an_owner_uploaded_logo_keeps_its_colours_in_the_badge), así
            // que usarlo como marcador ataba esta prueba a la otra regla.
            $this->assertStringContainsString('opacity-80', $hero, 'El badge debe aparecer con un logo propio resuelto.');
            $this->assertStringContainsString('A-74 Arquitectura', $hero);
        } else {
            $this->assertStringNotContainsString('opacity-80', $hero, 'Sin un logo propio resuelto no debe haber badge.');
        }
    }

    /**
     * El filtro `brightness-0 invert` convierte CUALQUIER imagen en una silueta
     * blanca. Sobre el logo del fallback es correcto —así se veía el distintivo
     * en el blade estático, y §16.7 manda conservarlo—, pero aplicado al logo
     * que sube el owner le borra su color de marca: justo lo que la función
     * «logo propio» existe para mostrar.
     *
     * La regla, entonces, es por ORIGEN y no por posición: el fallback se
     * blanquea, lo del owner se respeta.
     */
    public function test_an_owner_uploaded_logo_keeps_its_colours_in_the_badge(): void
    {
        Queue::fake();

        $section = $this->hero('nosotros');
        $media = $section->addMedia(UploadedFile::fake()->image('logo.png', 400, 200))->toMediaCollection('images');

        $this->publish('nosotros', [
            'title' => 'T',
            'logo_enabled' => true,
            'logo' => ['media_id' => (string) $media->uuid, 'alt' => 'A-74 Arquitectura'],
        ]);

        app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
        app(FrontendCacheGeneration::class)->bump();

        $html = $this->get('/nosotros')->assertOk()->getContent();
        $start = strpos($html, '<section data-nh-hero');
        $hero = substr($html, $start, strpos($html, '</section>', $start) - $start);

        $this->assertStringContainsString('opacity-80', $hero, 'El badge debe seguir apareciendo.');
        $this->assertStringNotContainsString(
            'brightness-0 invert',
            $hero,
            'El logo que sube el owner no debe salir blanqueado: el badge le borraría su color de marca.',
        );
    }

    public function test_the_a74_badge_does_not_appear_for_an_unpromoted_own_logo(): void
    {
        // Misma regla que el logo grande: «propio» se juzga por media_url
        // resuelto, no por la existencia cruda de media_id (spec, mantenido).
        Queue::fake();

        $section = $this->hero('nosotros');
        $media = $section->addMedia(UploadedFile::fake()->image('logo.png', 400, 200))->toMediaCollection('images');

        $this->publish('nosotros', [
            'title' => 'T',
            'logo_enabled' => true,
            'logo' => ['media_id' => (string) $media->uuid, 'alt' => 'A-74 Arquitectura'],
        ]);
        // Deliberadamente sin promover.

        $html = $this->get('/nosotros')->assertOk()->getContent();
        $start = strpos($html, '<section data-nh-hero');
        $hero = substr($html, $start, strpos($html, '</section>', $start) - $start);

        $this->assertStringNotContainsString('brightness-0 invert', $hero);
    }

    /**
     * Design D5: la variante `standard` (la que usa `/proyectos`, ausente de
     * `hero_variants`) necesita su PROPIA rampa para `logo_size: xl` — la
     * genérica (`h-20 sm:h-24 lg:h-28`) no llega a los 14rem fijos que el
     * logo grande de A-74 tiene hoy. En móvil queda en 12rem (mejor que un
     * alto fijo) y en `sm+` iguala los 14rem originales.
     */
    public function test_the_standard_variant_ramps_an_xl_logo_to_14rem(): void
    {
        $this->publish('nosotros', ['title' => 'T', 'logo_enabled' => true, 'logo_size' => 'xl']);

        $html = $this->get('/nosotros')->assertOk()->getContent();
        $start = strpos($html, '<section data-nh-hero');
        $hero = substr($html, $start, strpos($html, '</section>', $start) - $start);

        $this->assertStringContainsString('h-48 sm:h-56', $hero);
        $this->assertStringNotContainsString('h-20 sm:h-24 lg:h-28', $hero, 'La rampa genérica no debe seguir aplicando a xl en la variante standard.');
    }

    // ------------------------------------------------------------- TB-5 -----

    public function test_duplicate_sort_orders_still_produce_a_deterministic_order(): void
    {
        // Un snapshot heredado o editado a mano puede traer `sort_order`
        // repetidos. El presenter desempata por `media_id`, así que el resultado
        // es estable en vez de depender del orden del array.
        $section = $this->hero('home');
        $a = $section->addMedia(UploadedFile::fake()->image('a.png', 1600, 900))->toMediaCollection('images');
        $b = $section->addMedia(UploadedFile::fake()->image('b.png', 1600, 900))->toMediaCollection('images');

        $slides = fn (array $orden): array => [
            'title' => 'T',
            'slides' => [
                ['media_id' => (string) $a->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => $orden[0]],
                ['media_id' => (string) $b->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => $orden[1]],
            ],
        ];

        $orden = function (): array {
            $data = collect(app(FrontendPageRenderer::class)->render('home')['sections'])
                ->firstWhere('key', 'hero')['data'] ?? [];

            return collect($data['slides'] ?? [])->pluck('media_id')->all();
        };

        $this->publish('home', $slides([0, 0]));
        $primera = $orden();

        // Mismo payload con los elementos invertidos en el array: el resultado
        // debe ser idéntico, porque el desempate no depende de la posición.
        $this->hero('home')->update(['payload' => [
            'title' => 'T',
            'slides' => array_reverse($slides([0, 0])['slides']),
        ]]);
        $page = $this->page('home')->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
        app(FrontendCacheGeneration::class)->bump();

        $this->assertSame($primera, $orden(), 'Con `sort_order` duplicado el orden debe seguir siendo determinista.');
    }

    public function test_saving_renumbers_sort_order_from_zero(): void
    {
        $this->actingAs($this->owner);

        $section = $this->hero('home');
        $a = $section->addMedia(UploadedFile::fake()->image('a.png', 1600, 900))->toMediaCollection('images');
        $b = $section->addMedia(UploadedFile::fake()->image('b.png', 1600, 900))->toMediaCollection('images');

        // Se guardan con órdenes arbitrarios (7 y 3): lo que el owner ve en el
        // repeater ES el orden, así que al guardar se reenumera 0..n-1.
        $section->update(['payload' => [
            'title' => 'T',
            'slides' => [
                ['media_id' => (string) $a->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 7],
                ['media_id' => (string) $b->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 3],
            ],
        ]]);

        $component = $this->sectionEditor($section)->mountTableAction('edit', $section->getKey());

        $component->setTableActionData($component->get('mountedTableActionsData')[0])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            [0, 1],
            collect($section->fresh()->payload['slides'])->pluck('sort_order')->all(),
            'Al guardar, el orden visual del repeater se reenumera 0..n-1.'
        );
    }

    // ------------------------------------------------------------ TB-11 -----

    public function test_the_cta_guidance_reacts_in_both_consumers_for_every_type(): void
    {
        $this->actingAs($this->owner);

        // El componente es compartido: si la guía sólo funcionara en uno de los
        // dos formularios, extraerlo no habría servido de nada. (Este test se
        // perdió una vez en una edición por rangos y la suite siguió verde con
        // un test menos: por eso ahora recorre los CINCO tipos explícitamente.)
        $esperado = [
            'route' => 'nombre de una página del sitio',
            'url' => 'empezando con https://',
            'whatsapp' => 'lada de país',
            'mailto' => 'correo electrónico al que quieres',
            'tel' => 'número telefónico',
        ];

        $hero = $this->sectionEditor($this->hero('home'))->mountTableAction('edit', $this->hero('home')->getKey());

        // El estado inicial se declara acá en vez de darlo por sentado: desde
        // que el borrador del hero se siembra con el contenido de la página, su
        // CTA viene con tipo elegido, y este test necesita partir SIN tipo para
        // ver la guía inicial.
        $hero->set('mountedTableActionsData.0.payload.primary_cta.type', null)
            ->assertSee('Primero elige el tipo de enlace');

        foreach ($esperado as $tipo => $texto) {
            $hero->set('mountedTableActionsData.0.payload.primary_cta.type', $tipo)
                ->assertSee($texto);
        }

        $settings = Livewire::test(FrontendSettingsPage::class);
        $settings->assertSee('Primero elige el tipo de enlace');

        foreach ($esperado as $tipo => $texto) {
            $settings->set('data.primary_cta.type', $tipo)->assertSee($texto);
        }
    }

    // ------------------------------------------------------------- TB-8 -----

    public function test_the_reduced_motion_contract_is_present_in_the_shipped_css_and_js(): void
    {
        // GUARD DE CONTRATO, no prueba de comportamiento: el proyecto no tiene
        // runner de JS, así que ejecutar la media query o el temporizador no es
        // posible desde PHPUnit. El comportamiento se verificó en navegador; esto
        // impide que la garantía desaparezca sin que nadie lo note.
        $css = file_get_contents(resource_path('css/app.css'));
        $reduced = substr($css, strpos($css, '@media (prefers-reduced-motion: reduce)'));

        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('animation: none', $reduced, 'Sin movimiento debe cortar la animación.');
        $this->assertStringContainsString('.nh-hero-delay-0', $reduced, 'La primera slide queda visible y estática.');

        $js = file_get_contents(resource_path('js/hero-carousel.js'));
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $js);
        $this->assertStringContainsString('toggle.hidden = true', $js, 'Sin movimiento no hay nada que pausar.');
    }
}

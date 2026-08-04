<?php

namespace Tests\Feature\Frontend;

use App\Enums\PropertyStatus;
use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\Zone;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use App\Support\Frontend\CtaResolver;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El listado de propiedades destacadas lleva al catálogo completo.
 *
 * Sin ese botón la sección es un callejón: muestra unas pocas propiedades y no
 * dice que haya más.
 *
 * EL DESTINO NO SE PREGUNTA. «El catálogo» es una sola página del sitio, así que
 * ofrecer elegirlo sería ofrecer equivocarse en algo que no tiene alternativa —
 * y estas secciones dinámicas tienen prohibido, por diseño, dejar que el owner
 * decida a dónde apuntan. Lo único que elige es cómo se llama el botón.
 */
class FrontendFeaturedPropertiesCtaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    private function section(): FrontendSection
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'featured_properties')->firstOrFail();
    }

    private function compile(array $state): array
    {
        return app(SectionPayloadCompiler::class)->compile($this->section(), $state);
    }

    /** Una propiedad destacada publicada, para que la sección tenga qué mostrar. */
    private function conPropiedadDestacada(): Property
    {
        $property = Property::factory()->create([
            'zone_id' => Zone::factory(),
            'owner_id' => PropertyOwner::factory(),
            'street' => 'Av. de la Concordia 10',
            'colonia' => 'Zibatá',
            'commission_percentage' => 5,
            'is_featured' => true,
        ]);

        $property->addMedia(UploadedFile::fake()->image('casa.jpg', 1200, 800))->toMediaCollection('cover');
        $property->fresh()->forceFill(['status' => PropertyStatus::Publicado])->save();

        return $property->fresh();
    }

    private function publicar(array $payload): string
    {
        $section = $this->section();
        $section->forceFill(['payload' => $payload])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        return $this->get('/')->assertOk()->getContent();
    }

    // ----------------------------------------------------------- el destino ----

    public function test_the_destination_is_set_by_the_compiler_not_the_owner(): void
    {
        $payload = $this->compile(['title' => 'Destacadas', 'cta_label' => 'Ver todo']);

        $this->assertSame('route', $payload['primary_cta']['type']);
        $this->assertSame('inmuebles', $payload['primary_cta']['target']);
        $this->assertSame('Ver todo', $payload['primary_cta']['label']);
    }

    public function test_a_target_sent_by_hand_is_ignored(): void
    {
        // El formulario no ofrece el destino, pero la regla no puede depender de
        // eso: un POST armado a mano tampoco debe repuntar el botón.
        $payload = $this->compile([
            'title' => 'Destacadas',
            'cta_label' => 'Ver todo',
            'primary_cta' => ['label' => 'Ir a otro lado', 'type' => 'url', 'target' => 'https://ejemplo.test'],
        ]);

        $this->assertSame('inmuebles', $payload['primary_cta']['target']);
        $this->assertSame('route', $payload['primary_cta']['type']);
    }

    public function test_an_empty_label_falls_back_to_a_readable_one(): void
    {
        // Un botón sin texto es un botón invisible.
        $payload = $this->compile(['title' => 'Destacadas', 'cta_label' => '   ']);

        $this->assertSame('Ver todas las propiedades', $payload['primary_cta']['label']);
    }

    public function test_the_payload_stays_valid(): void
    {
        $this->assertSame([], app(FrontendSectionSchema::class)->validate(
            'featured_properties',
            $this->compile(['title' => 'Destacadas', 'cta_label' => 'Ver todo']),
        ));
    }

    // ------------------------------------------------------------ el render ----

    public function test_the_button_points_at_the_catalogue(): void
    {
        $this->conPropiedadDestacada();

        $html = $this->publicar([
            'title' => 'Propiedades destacadas',
            'primary_cta' => ['label' => 'Ver todas las propiedades', 'type' => 'route', 'target' => 'inmuebles'],
        ]);

        $this->assertStringContainsString('Ver todas las propiedades', $html);
        $this->assertStringContainsString(route('inmuebles.index'), $html);
    }

    public function test_the_button_sits_beside_the_title_in_the_brand_colour(): void
    {
        // Arriba a la derecha, a la altura del título, y en el color principal
        // del tema — que se tematiza, así que sigue al primario del cliente en
        // vez de quedar clavado a un azul.
        $this->conPropiedadDestacada();

        $html = $this->publicar([
            'title' => 'Propiedades destacadas',
            'primary_cta' => ['label' => 'Ver todas las propiedades', 'type' => 'route', 'target' => 'inmuebles'],
        ]);

        // La fila del encabezado reparte título y botón por sus extremos.
        $this->assertMatchesRegularExpression(
            '/flex flex-wrap items-end justify-between.*?Propiedades destacadas.*?Ver todas las propiedades/s',
            $html,
            'El botón dejó de compartir la línea del título.',
        );

        // Y va en la superficie de marca, no en un contorno.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*bg-brand-primary[^>]*text-on-brand-primary[^>]*>[^<]*Ver todas las propiedades/s',
            $html,
            'El botón no usa el color principal del tema.',
        );
    }

    public function test_a_section_published_before_this_existed_simply_has_no_button(): void
    {
        // LO QUE NO DEBE ROMPERSE: los snapshots publicados sin `primary_cta`
        // siguen renderizando, sin un botón vacío ni un enlace muerto.
        $this->conPropiedadDestacada();

        $html = $this->publicar(['title' => 'Propiedades destacadas']);

        $this->assertStringContainsString('Propiedades destacadas', $html);
        $this->assertStringNotContainsString('Ver todas las propiedades', $html);
    }

    // -------------------------------------------------------- el formulario ----

    public function test_the_label_survives_opening_and_saving_the_editor(): void
    {
        // La regresión que ya nos mordió: el payload guarda el CTA entero y el
        // formulario muestra sólo su etiqueta. Sin hidratarla, el editor abre en
        // blanco y guardar la reemplaza por el texto de ejemplo, borrando en
        // silencio lo que el owner escribió.
        $section = $this->section();
        $section->forceFill(['payload' => [
            'title' => 'Destacadas',
            'primary_cta' => ['label' => 'Mirá todo el catálogo', 'type' => 'route', 'target' => 'inmuebles'],
        ]])->saveQuietly();

        $editor = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());

        $this->assertSame(
            'Mirá todo el catálogo',
            $editor->get('mountedTableActionsData')[0]['payload']['cta_label'] ?? null,
            'El editor abrió sin el texto que estaba guardado.',
        );

        $editor->callMountedTableAction()->assertHasNoTableActionErrors();

        $this->assertSame('Mirá todo el catálogo', $section->fresh()->payload['primary_cta']['label']);
    }

    // -------------------------------------------- el catálogo con filtro ----

    public function test_the_opportunities_button_carries_its_filter(): void
    {
        // Mandar al catálogo ENTERO perdería en el camino justo lo que la
        // sección promete.
        $section = FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'opportunity_properties')->firstOrFail();

        $payload = app(SectionPayloadCompiler::class)->compile($section, ['title' => 'Oportunidades']);

        $this->assertSame(['oportunidad' => '1'], $payload['primary_cta']['query']);
        $this->assertSame('inmuebles', $payload['primary_cta']['target']);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('opportunity_properties', $payload));
    }

    public function test_a_filter_outside_the_allowlist_never_reaches_the_url(): void
    {
        // El filtro no se ofrece en ningún formulario, pero la regla no puede
        // depender de eso: un parámetro libre en la URL sería texto del owner
        // llegando a una consulta.
        $resuelto = app(CtaResolver::class)->resolve([
            'label' => 'Ir', 'type' => 'route', 'target' => 'inmuebles',
            'query' => ['oportunidad' => '1', 'inventado' => 'x', 'orden' => 'precio'],
        ]);

        $this->assertStringContainsString('oportunidad=1', $resuelto['url']);
        $this->assertStringNotContainsString('inventado', $resuelto['url']);
        $this->assertStringNotContainsString('orden', $resuelto['url']);
    }

    public function test_the_catalogue_actually_filters_by_opportunity(): void
    {
        // El botón no serviría de nada si el catálogo ignorara el parámetro.
        $destacada = $this->conPropiedadDestacada();
        $oportunidad = Property::factory()->create([
            'zone_id' => Zone::factory(),
            'owner_id' => PropertyOwner::factory(),
            'street' => 'Calle 5', 'colonia' => 'Zibatá', 'commission_percentage' => 5,
            'is_opportunity' => true, 'title' => 'Terreno con plusvalía',
        ]);
        $oportunidad->addMedia(UploadedFile::fake()->image('t.jpg', 1200, 800))->toMediaCollection('cover');
        $oportunidad->fresh()->forceFill(['status' => PropertyStatus::Publicado])->save();

        $html = $this->get(route('inmuebles.index', ['oportunidad' => '1']))->assertOk()->getContent();

        $this->assertStringContainsString('Terreno con plusvalía', $html);
        // Lo que prueba que FILTRA: la destacada no es oportunidad y no aparece.
        // Se afirma sobre su ausencia y no sobre cuántas veces sale la otra —
        // el catálogo repite títulos en su carrusel de cabecera, así que contar
        // mediría eso y no el filtro.
        $this->assertStringNotContainsString($destacada->title, $html);
    }

    public function test_the_visitor_can_turn_the_filter_on_from_the_sidebar(): void
    {
        // No sólo llegando desde el home: es un filtro más del catálogo.
        $this->conPropiedadDestacada();

        $html = $this->get(route('inmuebles.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input type="checkbox" name="oportunidad" value="1"/',
            $html,
            'El catálogo no ofrece filtrar por oportunidades.',
        );
        $this->assertStringContainsString('Solo oportunidades', $html);
    }

    public function test_the_filter_shows_itself_as_active_and_survives_the_others(): void
    {
        // El formulario reenvía todo por GET. Si la casilla no llegara marcada,
        // el primer filtro que el visitante aplicara lo devolvería al catálogo
        // entero sin avisar — y además el recorte sería invisible.
        $this->conPropiedadDestacada();

        $html = $this->get(route('inmuebles.index', ['oportunidad' => '1']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input type="checkbox" name="oportunidad" value="1"[^>]*\bchecked\b/',
            $html,
            'El catálogo llegó filtrado pero la casilla no lo muestra.',
        );
    }

    public function test_unchecking_the_box_really_clears_the_filter(): void
    {
        // LO QUE UN CAMPO OCULTO HABRÍA ROTO: con un `hidden` además de la
        // casilla, destildarla no habría quitado nada nunca — el oculto seguiría
        // mandando el filtro. La casilla tiene que ser la única fuente.
        $this->conPropiedadDestacada();

        $html = $this->get(route('inmuebles.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('type="hidden" name="oportunidad"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/name="oportunidad" value="1"[^>]*\bchecked\b/',
            $html,
            'La casilla aparece marcada sin que el filtro esté activo.',
        );
    }
}

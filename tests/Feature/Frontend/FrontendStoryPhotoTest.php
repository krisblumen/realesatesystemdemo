<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Frontend\Concerns\MountsSectionEditor;
use Tests\TestCase;

/**
 * Los campos que le faltaban a las secciones de «Nosotros»: antetítulo y
 * fotografía en «Nuestra historia», antetítulo en «Valores», y en «Equipo» el
 * antetítulo de la sección más el antetítulo y el LOGO de su destacado.
 *
 * TODOS SON OPCIONALES, y no por comodidad: cada uno de esos tipos arma también
 * otra sección del sitio —`rich_text` la entrada de contacto, `values` el
 * alcance del servicio de Inversionistas— y exigirlos habría dejado esas otras
 * inválidas de golpe, sin poder guardarse ni publicarse.
 *
 * Sin foto, «Nuestra historia» sigue centrada a 720 px, exactamente como estaba.
 *
 * El destacado del equipo pasó de tres claves sueltas a un objeto ANIDADO, y esa
 * decisión no es de estilo: está probada abajo. Todo el pipeline de imágenes
 * encuentra las fotos buscando la clave `media_id`, así que un
 * `spotlight_media_id` plano habría sido invisible para la validación, para la
 * promoción al publicar y para el reporte de huérfanas.
 */
class FrontendStoryPhotoTest extends TestCase
{
    use MountsSectionEditor;
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

    private function story(): FrontendSection
    {
        return FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('section_key', 'story')->firstOrFail();
    }

    private function schema(): FrontendSectionSchema
    {
        return app(FrontendSectionSchema::class);
    }

    /** La ruta que deja Filament tras subir: es lo que el compilador recibe. */
    private function fotoSubida(): string
    {
        return UploadedFile::fake()->image('equipo.jpg', 1400, 900)->store('', 'frontend-private');
    }

    private function publicar(array $payload): string
    {
        $section = $this->story();
        $section->forceFill(['payload' => $payload])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        return $this->get('/nosotros')->assertOk()->getContent();
    }

    // -------------------------------------------------- lo que no se rompe ----

    public function test_the_contact_intro_stays_valid_without_either(): void
    {
        // LO QUE NO DEBE ROMPERSE: comparte el tipo y no usa ninguno de los dos.
        $contacto = FrontendPage::query()->where('key', 'contacto')->firstOrFail()
            ->sections()->where('section_key', 'contact_intro')->firstOrFail();

        $this->assertSame([], $this->schema()->validate($contacto->type, ['body' => 'Escríbenos.']));
    }

    public function test_without_a_photo_the_text_stays_centred(): void
    {
        $html = $this->publicar(['title' => 'Nuestra historia', 'body' => 'Empezamos en 2015.']);

        $this->assertStringContainsString('mx-auto max-w-[720px]', $html);
        $this->assertStringNotContainsString('lg:grid-cols-2 lg:gap-16', $html);
    }

    // ---------------------------------------------------------- lo que suma ----

    public function test_the_eyebrow_is_rendered(): void
    {
        $html = $this->publicar([
            'eyebrow' => 'DESDE 2015',
            'title' => 'Nuestra historia',
            'body' => 'Empezamos en un despacho chico.',
        ]);

        $this->assertStringContainsString('DESDE 2015', $html);
    }

    public function test_a_photo_splits_the_section_in_two(): void
    {
        $payload = app(SectionPayloadCompiler::class)->compile($this->story(), [
            'eyebrow' => 'DESDE 2015',
            'title' => 'Nuestra historia',
            'body' => 'Empezamos en un despacho chico.',
            'upload' => [$this->fotoSubida()],
            'alt' => 'El equipo de New Hauz en su oficina',
        ]);

        $this->assertArrayHasKey('media_id', $payload);
        $this->assertSame('El equipo de New Hauz en su oficina', $payload['alt']);
        $this->assertSame([], $this->schema()->validate('rich_text', $payload));

        $html = $this->publicar($payload);

        $this->assertStringContainsString('lg:grid-cols-2 lg:gap-16', $html);
        $this->assertStringContainsString('alt="El equipo de New Hauz en su oficina"', $html);
    }

    public function test_a_photo_without_a_description_is_refused(): void
    {
        // La regla universal de accesibilidad: toda imagen se describe. Sin
        // esto, un lector de pantalla leería el nombre del archivo.
        $this->assertNotSame([], $this->schema()->validate('rich_text', [
            'body' => 'Texto',
            'media_id' => '11111111-1111-4111-8111-111111111111',
        ]));
    }

    // -------------------------------------------------------- el formulario ----

    public function test_the_editor_asks_for_both_under_the_payload(): void
    {
        // Los campos de imagen se declaran con nombres RELATIVOS porque nacieron
        // para vivir dentro de un repeater. Acá van sueltos, así que si el
        // contenedor no los anclara a `payload` caerían en la raíz y el
        // compilador —que recibe el payload— no los encontraría.
        $story = $this->story();
        $story->forceFill(['payload' => [
            'eyebrow' => 'DESDE 2015',
            'title' => 'Nuestra historia',
            'body' => 'Empezamos en un despacho chico.',
        ]])->saveQuietly();

        $estado = $this->sectionEditor($story)
            ->mountTableAction('edit', $story->getKey())
            ->get('mountedTableActionsData')[0]['payload'] ?? [];

        $this->assertSame('DESDE 2015', $estado['eyebrow'] ?? null);
        $this->assertArrayHasKey('media_id', $estado, 'La foto no quedó anclada al payload.');
        $this->assertArrayHasKey('upload', $estado);
    }

    public function test_opening_and_saving_keeps_the_photo_and_its_description(): void
    {
        // La regresión de siempre: abrir el editor y guardar sin tocar nada no
        // puede perder ni la foto ni su descripción.
        $story = $this->story();

        $payload = app(SectionPayloadCompiler::class)->compile($story, [
            'title' => 'Nuestra historia',
            'body' => 'Empezamos en un despacho chico.',
            'upload' => [$this->fotoSubida()],
            'alt' => 'El equipo en su oficina',
        ]);

        $story->forceFill(['payload' => $payload])->saveQuietly();
        $mediaId = $payload['media_id'];

        $this->sectionEditor($story->fresh())
            ->mountTableAction('edit', $story->getKey())
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $guardado = $story->fresh()->payload;

        $this->assertSame($mediaId, $guardado['media_id'], 'Se perdió la foto al guardar.');
        $this->assertSame('El equipo en su oficina', $guardado['alt']);
    }

    // ------------------------------------------------------------ valores ----

    public function test_the_values_section_also_takes_an_eyebrow(): void
    {
        // Lo comparten «Valores» de Nosotros y «Alcance del servicio» de
        // Inversionistas: es opcional, así que ninguna de las dos se rompe.
        foreach ([['nosotros', 'values'], ['inversionistas', 'service_scope']] as [$pageKey, $sectionKey]) {
            $section = FrontendPage::query()->where('key', $pageKey)->firstOrFail()
                ->sections()->where('section_key', $sectionKey)->firstOrFail();

            $payload = app(SectionPayloadCompiler::class)->compile($section, [
                'eyebrow' => 'LO QUE NOS MUEVE',
                'title' => 'Nuestros valores',
                'items' => [['title' => 'Transparencia', 'description' => 'Números claros.']],
            ]);

            $this->assertSame('LO QUE NOS MUEVE', $payload['eyebrow'], "«{$sectionKey}» perdió el antetítulo.");
            $this->assertSame([], $this->schema()->validate('values', $payload));
        }
    }

    public function test_the_values_eyebrow_reaches_the_page(): void
    {
        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('section_key', 'values')->firstOrFail();

        $section->forceFill(['payload' => [
            'eyebrow' => 'LO QUE NOS MUEVE',
            'title' => 'Nuestros valores',
            'items' => [['title' => 'Transparencia', 'description' => 'Números claros.']],
        ]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $this->assertStringContainsString('LO QUE NOS MUEVE', $this->get('/nosotros')->assertOk()->getContent());
    }

    public function test_values_published_without_an_eyebrow_still_render(): void
    {
        // LO QUE NO DEBE ROMPERSE: los snapshots que ya estaban publicados.
        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('section_key', 'values')->firstOrFail();

        $section->forceFill(['payload' => [
            'title' => 'Nuestros valores',
            'items' => [['title' => 'Transparencia', 'description' => 'Números claros.']],
        ]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $this->assertStringContainsString('Nuestros valores', $this->get('/nosotros')->assertOk()->getContent());
    }

    // -------------------------------------------------------------- equipo ----

    private function equipo(): FrontendSection
    {
        return FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('section_key', 'team')->firstOrFail();
    }

    public function test_the_team_section_takes_an_eyebrow(): void
    {
        $payload = app(SectionPayloadCompiler::class)->compile($this->equipo(), [
            'eyebrow' => 'QUIÉNES SOMOS',
            'title' => 'Las personas detrás de New Hauz',
            'members' => [['name' => 'Kristian Alvarez', 'role' => 'Dirección']],
        ]);

        $this->assertSame('QUIÉNES SOMOS', $payload['eyebrow']);
        $this->assertSame([], $this->schema()->validate('team', $payload));
    }

    public function test_the_spotlight_carries_its_own_logo(): void
    {
        // Es una división con imagen comercial propia, no la marca principal.
        $payload = app(SectionPayloadCompiler::class)->compile($this->equipo(), [
            'title' => 'Las personas detrás de New Hauz',
            'members' => [['name' => 'Kristian Alvarez']],
            'spotlight' => [
                'eyebrow' => 'DIVISIÓN',
                'title' => 'A-74 Arquitectura',
                'body' => 'El brazo de diseño de la firma.',
                'upload' => [$this->fotoSubida()],
                'alt' => 'Logotipo de A-74 Arquitectura',
            ],
        ]);

        $this->assertSame('DIVISIÓN', $payload['spotlight']['eyebrow']);
        $this->assertArrayHasKey('media_id', $payload['spotlight']);
        $this->assertSame([], $this->schema()->validate('team', $payload));
    }

    public function test_the_spotlight_logo_is_visible_to_the_media_pipeline(): void
    {
        // LO QUE DECIDIÓ LA FORMA. La validación, la promoción al publicar y el
        // reporte de huérfanas recorren el payload buscando la clave `media_id`.
        // Con un `spotlight_media_id` plano, el logo habría sido invisible para
        // los tres: nunca validado, nunca publicado, y dado por borrable.
        $payload = app(SectionPayloadCompiler::class)->compile($this->equipo(), [
            'title' => 'Equipo',
            'members' => [['name' => 'Kristian Alvarez']],
            'spotlight' => [
                'title' => 'A-74 Arquitectura',
                'upload' => [$this->fotoSubida()],
                'alt' => 'Logotipo de A-74',
            ],
        ]);

        $encontradas = app(FrontendPageContentService::class)->mediaIds($payload);

        $this->assertContains($payload['spotlight']['media_id'], $encontradas, 'El pipeline no ve el logo del destacado.');
    }

    public function test_a_spotlight_logo_without_a_description_is_refused(): void
    {
        // Anidado, el logo hereda la regla universal de accesibilidad — que con
        // una clave plana no se habría aplicado.
        $this->assertNotSame([], $this->schema()->validate('team', [
            'spotlight' => ['title' => 'A-74', 'media_id' => '11111111-1111-4111-8111-111111111111'],
        ]));
    }

    public function test_an_empty_spotlight_is_omitted(): void
    {
        // El payload canónico no lleva objetos vacíos; el render trataría uno
        // como contenido y dibujaría una caja en blanco.
        $payload = app(SectionPayloadCompiler::class)->compile($this->equipo(), [
            'title' => 'Equipo',
            'members' => [['name' => 'Kristian Alvarez']],
            'spotlight' => ['title' => '', 'body' => ''],
        ]);

        $this->assertArrayNotHasKey('spotlight', $payload);
    }

    public function test_the_team_keeps_the_grey_band_and_its_cards(): void
    {
        // El diseño del sitio: banda gris a todo el ancho y todo lo de adentro
        // en tarjetas blancas con sombra. El render del CMS lo había perdido —
        // fondo plano y nombres sueltos sobre la página.
        $equipo = $this->equipo();
        $equipo->forceFill(['payload' => [
            'eyebrow' => 'EL EQUIPO',
            'title' => 'Las personas detrás de New Hauz',
            'spotlight' => ['title' => 'A-74 Arquitectura'],
            'members' => [['name' => 'Kristian Alvarez', 'role' => 'Dirección']],
        ]])->saveQuietly();

        $page = $equipo->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $html = $this->get('/nosotros')->assertOk()->getContent();

        // La banda gris envuelve la sección entera. Se afirma que la clase está
        // en el atributo y no que sea la única: el gris difiere del fondo del
        // sitio, así que la banda también lleva su filete —y fijar el atributo
        // completo hacía fallar esto por ese agregado, no por perder la banda.
        $this->assertMatchesRegularExpression('/<section class="[^"]*\bbg-fog\b/', $html);
        // Y el integrante va en tarjeta blanca con sombra, no suelto.
        $this->assertMatchesRegularExpression(
            '/rounded-brand-lg bg-white shadow-sm.*?Kristian Alvarez/s',
            $html,
            'Los integrantes dejaron de ir en tarjeta.',
        );
    }
}

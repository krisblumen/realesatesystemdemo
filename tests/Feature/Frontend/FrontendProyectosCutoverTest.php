<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\Project;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use App\Support\Frontend\CtaResolver;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cambio cms-pagina-proyectos, Fase 3 (Work Unit 3) — el cutover público de
 * `/proyectos` (§16.7 REGLA DE ORO): hasta que el owner publique, la ruta
 * sirve el fallback hardcodeado ORIGINAL sin cambios; una vez publicada,
 * renderiza el snapshot del CMS. Mismo patrón que `servicios`/`inversionistas`
 * (FrontendRenderFallbackTest).
 */
class FrontendProyectosCutoverTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
    }

    private function page(): FrontendPage
    {
        return FrontendPage::query()->where('key', 'proyectos')->firstOrFail();
    }

    private function section(string $key): FrontendSection
    {
        return $this->page()->sections()->where('section_key', $key)->firstOrFail();
    }

    private function publish(string $sectionKey, array $payload): void
    {
        $this->section($sectionKey)->forceFill(['payload' => $payload])->saveQuietly();

        $page = $this->page()->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
    }

    // ---------------------------------------------------------- fallback ----

    public function test_the_unpublished_route_still_shows_the_hardcoded_hero_copy(): void
    {
        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringContainsString('Desarrollos &amp; obra', $html);
        $this->assertStringContainsString('Proyectos con visión, diseño y propósito.', $html);
        $this->assertStringContainsString('Desarrollos residenciales, propiedades y soluciones arquitectónicas', $html);
    }

    public function test_the_unpublished_route_still_shows_the_a74_badge_and_the_14rem_logo(): void
    {
        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringContainsString('brightness-0 invert opacity-80', $html, 'El badge A-74 debe seguir apareciendo sin publicar.');
        $this->assertStringContainsString('h-48 sm:h-56', $html, 'El logo grande debe usar la rampa de 14rem, no el style= inline original.');
        $this->assertStringNotContainsString('style="height:14rem"', $html, 'El style= inline debía morir con el cutover del hero (§6.1).');
    }

    public function test_the_unpublished_route_still_shows_the_grid_header_and_literal_gradient(): void
    {
        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringContainsString('Despacho de arquitectura · New Hauz', $html);
        $this->assertStringContainsString('A-74 lleva cada proyecto del concepto arquitectónico a la obra terminada.', $html);
        $this->assertStringContainsString('bg-[linear-gradient(180deg,#e0e0e0_0%,#f0f0f0_50%,#dcdcdc_100%)]', $html);
    }

    public function test_the_unpublished_route_still_shows_the_closing_cta_with_its_literal_gradient(): void
    {
        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringContainsString('¿Tienes un terreno o un proyecto en mente?', $html);
        $this->assertStringContainsString('Agenda una cita', $html);
        // El de 4 paradas no existe en brand_palette (§16.7 + design D7): la
        // rama de fallback lo conserva literal, nunca vía cta.blade.php.
        $this->assertStringContainsString('bg-[linear-gradient(135deg,#2e2e2e_0%,#4a4a4a_35%,#383838_65%,#525252_100%)]', $html);
    }

    public function test_the_unpublished_route_lists_every_published_project_not_only_the_featured_ones(): void
    {
        Project::query()->create(['title' => 'Destacado', 'description' => 'x', 'is_featured' => true]);
        Project::query()->create(['title' => 'No destacado', 'description' => 'y', 'is_featured' => false]);

        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringContainsString('Destacado', $html);
        $this->assertStringContainsString('No destacado', $html);
    }

    public function test_the_unpublished_route_has_no_stray_hero_carousel_script(): void
    {
        // El carrusel inline propio del hero ORIGINAL queda muerto una vez que
        // el hero pasa por el partial compartido (mecanismo CSS + JS del
        // sitio, sin <script> por página) — dejarlo sería doble motor.
        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringNotContainsString('hero-carousel', $html);
    }

    // ----------------------------------------------------------- publish ----

    public function test_publishing_an_untouched_hero_keeps_the_h1_and_eyebrow(): void
    {
        // «Recién cortado, owner sin tocar nada» (spec): el hero sembrado
        // (Fase 1) debe seguir mostrando el mismo texto una vez publicado.
        $page = $this->page();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Proyectos con visión, diseño y propósito.', $html);
    }

    public function test_a_published_hero_title_reaches_the_dom(): void
    {
        $this->publish('hero', ['title' => 'PROYECTOS-DESDE-EL-CMS']);

        $this->get('/proyectos')
            ->assertOk()
            ->assertSee('PROYECTOS-DESDE-EL-CMS')
            ->assertDontSee('Proyectos con visión, diseño y propósito.');
    }

    public function test_a_published_catalog_with_no_projects_shows_the_catalog_empty_state(): void
    {
        $page = $this->page();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringContainsString('Pronto publicaremos nuestros proyectos', $html);
    }

    // ------------------------------------------------------ test-only 3.9 ----

    /**
     * Test-only (task 3.9): el botón del hero al asociado reutiliza
     * `CtaResolver` genérico, sin código nuevo — sólo `^https://` +
     * FILTER_VALIDATE_URL se acepta.
     */
    public function test_the_hero_cta_to_the_associate_uses_the_generic_url_resolver(): void
    {
        $this->publish('hero', [
            'title' => 'T',
            'primary_cta' => ['label' => 'Visitar A-74', 'type' => 'url', 'target' => 'https://a74.example.com'],
        ]);

        $html = $this->get('/proyectos')->assertOk()->getContent();

        $this->assertStringContainsString('https://a74.example.com', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public static function unsafeCtaTargets(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,x'],
            'protocol-relative' => ['//evil.example.com'],
        ];
    }

    /**
     * A nivel del resolver directo (no vía publish): un `background_color`
     * inseguro nunca llega siquiera a guardarse (`checkCta` lo rechaza en
     * `updateSectionPayload`/`publish`, defensa en profundidad) — lo que este
     * test prueba es el mecanismo genérico que hace eso posible, igual que
     * los escenarios del spec («resuelven null, botón omitido»).
     */
    #[DataProvider('unsafeCtaTargets')]
    public function test_an_unsafe_hero_cta_target_resolves_to_null(string $target): void
    {
        $resolved = app(CtaResolver::class)->resolve([
            'label' => 'Visitar A-74', 'type' => 'url', 'target' => $target,
        ]);

        $this->assertNull($resolved, 'Un destino inseguro debe resolver null, para que el botón se omita.');
    }

    // ----------------------------------------------------- test-only 3.10 ----

    /**
     * Test-only (task 3.10): `background_color` en `projects_list`/`final_cta`
     * de `proyectos` respeta la MISMA paleta cerrada que cualquier otra
     * página — sin código nuevo, el schema ya es genérico por tipo.
     */
    public function test_projects_list_background_color_is_rejected_outside_the_palette(): void
    {
        $errores = app(FrontendSectionSchema::class)->validate('featured_projects', [
            'title' => 'Proyectos',
            'background_color' => 'verde-fluor',
        ]);

        $this->assertNotSame([], $errores);
    }

    public function test_final_cta_background_color_is_rejected_outside_the_palette(): void
    {
        $errores = app(FrontendSectionSchema::class)->validate('cta', [
            'title' => 'Cierre',
            'background_color' => 'verde-fluor',
        ]);

        $this->assertNotSame([], $errores);
    }

    public function test_final_cta_background_color_accepts_the_seeded_neutral(): void
    {
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('cta', [
            'title' => 'Cierre',
            'background_color' => 'neutral-4',
        ]));
    }
}

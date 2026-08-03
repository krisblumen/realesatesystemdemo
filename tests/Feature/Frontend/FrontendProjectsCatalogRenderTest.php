<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\Project;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendPageRenderer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cambio cms-pagina-proyectos, Fase 3 (Work Unit 3) — design D6/D7: la
 * variante `catalog` de `featured_projects` diverge del resumen de `home` en
 * PRESENTACIÓN (además de la autoridad de datos ya cerrada en
 * FrontendProjectsCatalogAuthorityTest, Fase 2): carrusel paginado de 6 en vez
 * de grilla, estado vacío propio, y el gradiente literal de 3 paradas como
 * fondo por defecto — nunca `is_featured`, nunca ausencia total.
 *
 * Se prueba contra el PARTIAL directamente (renderer → vista, sin HTTP en
 * `/proyectos`) por la misma razón que FrontendProjectsCatalogAuthorityTest:
 * el cutover del blade es una tarea aparte de esta misma fase (3.7/3.8) y
 * `catalog` es la única variante que usa `proyectos` — no hay otra página ya
 * publicada contra la que probarlo por HTTP.
 */
class FrontendProjectsCatalogRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
    }

    private function page(string $key): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function section(string $pageKey, string $sectionKey): FrontendSection
    {
        return $this->page($pageKey)->sections()->where('section_key', $sectionKey)->firstOrFail();
    }

    /** Publica el payload dado y devuelve el HTML del PARTIAL de esa sección. */
    private function render(string $pageKey, string $sectionKey, array $payload): string
    {
        $this->section($pageKey, $sectionKey)->forceFill(['payload' => $payload])->saveQuietly();

        $page = $this->page($pageKey)->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $sections = app(FrontendPageRenderer::class)->render($pageKey)['sections'];
        $entry = collect($sections)->firstWhere('key', $sectionKey);

        if ($entry === null) {
            return '';
        }

        return view('frontend.sections.'.$entry['type'], ['s' => $entry['data'], 'sectionKey' => $sectionKey])->render();
    }

    private function project(string $title, bool $featured = false): Project
    {
        return Project::query()->create(['title' => $title, 'description' => 'x', 'is_featured' => $featured]);
    }

    public function test_the_catalog_variant_renders_a_carousel_not_a_plain_grid(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->project("Proyecto {$i}");
        }

        $html = $this->render('proyectos', 'projects_list', ['title' => 'Proyectos']);

        $this->assertStringContainsString('data-carousel', $html);
        $this->assertStringContainsString('data-track', $html);
        $this->assertStringContainsString('data-swipe', $html, 'El carrusel móvil debe aceptar gesto táctil.');
        // 7 proyectos, de a 6 por página desktop => 2 páginas.
        $this->assertSame(2, substr_count($html, 'grid w-full shrink-0 gap-6 sm:grid-cols-2 lg:grid-cols-3'));
    }

    public function test_the_catalog_variant_shows_an_empty_state_instead_of_nothing(): void
    {
        $html = $this->render('proyectos', 'projects_list', ['title' => 'Proyectos']);

        $this->assertStringContainsString('Pronto publicaremos nuestros proyectos', $html);
        $this->assertStringContainsString('Estamos preparando el portafolio de A-74 Arquitectura.', $html);
    }

    public function test_pages_without_the_catalog_variant_still_render_nothing_when_empty(): void
    {
        // Regresión: `home.featured_projects` no debe adoptar el estado vacío
        // de `catalog` — sigue sin dibujar nada, como hoy.
        $html = $this->render('home', 'featured_projects', ['title' => 'Proyectos destacados']);

        $this->assertSame('', trim($html));
    }

    public function test_the_default_background_is_the_literal_three_stop_gradient(): void
    {
        $this->project('Uno');

        $html = $this->render('proyectos', 'projects_list', ['title' => 'Proyectos']);

        $this->assertStringContainsString('bg-[linear-gradient(180deg,#e0e0e0_0%,#f0f0f0_50%,#dcdcdc_100%)]', $html);
    }

    public function test_an_explicit_background_colour_overrides_the_literal_gradient(): void
    {
        $this->project('Uno');

        $html = $this->render('proyectos', 'projects_list', ['title' => 'Proyectos', 'background_color' => 'primary-l1']);

        $this->assertStringContainsString('bg-brand-primary-l1', $html);
        $this->assertStringNotContainsString('bg-[linear-gradient(180deg,#e0e0e0_0%,#f0f0f0_50%,#dcdcdc_100%)]', $html);
    }

    public function test_the_catalog_variant_includes_a_non_featured_project_in_the_rendered_cards(): void
    {
        // Complementa FrontendProjectsCatalogAuthorityTest (Fase 2, autoridad):
        // acá se confirma que el proyecto no destacado también llega al DOM.
        $this->project('Destacado', featured: true);
        $this->project('No destacado');

        $html = $this->render('proyectos', 'projects_list', ['title' => 'Proyectos']);

        $this->assertStringContainsString('Destacado', $html);
        $this->assertStringContainsString('No destacado', $html);
    }
}

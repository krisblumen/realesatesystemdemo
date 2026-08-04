<?php

namespace Tests\Feature\Frontend;

use App\Actions\Frontend\SeedFrontendPages;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Services\Frontend\FrontendPageContentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * `proyectos` pasa a ser la sexta página canónica del CMS (RFC-075, extensión
 * cms-pagina-proyectos — Work Unit 1: Fundación).
 *
 * Este test cubre lo que el registro + la migración de sembrado deben
 * garantizar ANTES de que exista ninguna variante de render (esa es la
 * Fase 2/3 de este cambio): el alta de la página y sus tres secciones, la
 * idempotencia, el rechazo de una clave no registrada bajo `proyectos` y el
 * contenido con el que arrancan `hero`, `projects_list` y `final_cta` — para
 * que la primera publicación no dibuje una tarjeta vacía ni apague el fondo
 * del hero (§16.7: el cutover, recién en el Work Unit 3, no puede cambiar
 * nada de lo que hoy se ve en `/proyectos`).
 */
class FrontendProyectosPageSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function page(): FrontendPage
    {
        return FrontendPage::query()->where('key', 'proyectos')->firstOrFail();
    }

    private function section(string $key): FrontendSection
    {
        return $this->page()->sections()->where('section_key', $key)->firstOrFail();
    }

    // ----- 1.1: registro canónico + seed -----

    public function test_the_seed_creates_the_page_and_its_three_canonical_sections(): void
    {
        $page = $this->page();

        $this->assertTrue($page->is_enabled);

        $secciones = $page->sections()->orderBy('sort_order')->get();
        $this->assertCount(3, $secciones);
        $this->assertSame(['hero', 'projects_list', 'final_cta'], $secciones->pluck('section_key')->all());
        $this->assertSame(['hero', 'featured_projects', 'cta'], $secciones->pluck('type')->all());
    }

    public function test_the_seed_is_idempotent(): void
    {
        app(SeedFrontendPages::class)->run();
        app(SeedFrontendPages::class)->run();

        $this->assertSame(1, FrontendPage::query()->where('key', 'proyectos')->count());
        $this->assertSame(3, $this->page()->sections()->count());
    }

    public function test_the_five_previous_pages_are_untouched(): void
    {
        // Recorrido exhaustivo, no una muestra: agregar la sexta página no debe
        // sumar, quitar ni renombrar una sola sección de las cinco existentes.
        $esperado = [
            'home' => 8, 'nosotros' => 6, 'servicios' => 3,
            'inversionistas' => 5, 'contacto' => 2,
        ];

        $this->assertSame(6, FrontendPage::query()->count(), 'La sexta página no se sumó, o se sumó de más.');

        foreach ($esperado as $key => $cantidad) {
            $page = FrontendPage::query()->where('key', $key)->firstOrFail();
            $this->assertSame($cantidad, $page->sections()->count(), "{$key} cambió su cantidad de secciones.");
        }
    }

    // ----- 1.2: registro canónico rechaza una clave no registrada -----

    public function test_an_unregistered_section_key_under_proyectos_is_rejected(): void
    {
        // `proyectos.hero_2` no está en el registro — se rechaza igual que en
        // cualquier otra página (C-E2 de FrontendPageContractTest).
        $rogue = FrontendSection::query()->create([
            'frontend_page_id' => $this->page()->id,
            'section_key' => 'hero_2', 'type' => 'hero', 'is_enabled' => true, 'sort_order' => 99,
        ]);

        $this->expectException(ValidationException::class);
        app(FrontendPageContentService::class)->updateSectionPayload($rogue, ['title' => 'X']);
    }

    // ----- 1.6: los payloads sembrados -----

    public function test_the_projects_list_seed_carries_eyebrow_and_title_without_a_background_color(): void
    {
        $payload = $this->section('projects_list')->payload;

        $this->assertNotNull($payload, 'projects_list quedó sin sembrar: la primera publicación dibujaría una tarjeta vacía.');
        $this->assertArrayHasKey('eyebrow', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayNotHasKey('background_color', $payload, 'Con background_color elegido pierde el gradiente literal por defecto (D7).');
    }

    public function test_the_final_cta_seed_carries_the_current_copy_and_a_neutral_background(): void
    {
        $payload = $this->section('final_cta')->payload;

        $this->assertNotNull($payload, 'final_cta quedó sin sembrar: la primera publicación dibujaría una tarjeta navy vacía.');
        $this->assertSame('¿Tienes un terreno o un proyecto en mente?', $payload['title'] ?? null);
        $this->assertSame('neutral-4', $payload['background_color'] ?? null);
    }

    public function test_the_hero_draft_is_seeded_without_a_slides_key(): void
    {
        $payload = $this->section('hero')->payload;

        $this->assertNotNull($payload, 'El hero de proyectos abre vacío: mismo defecto que 2026_07_28_100100 cerró para las otras cinco.');
        $this->assertArrayHasKey('title', $payload);
        // A propósito AUSENTE, no vacía: `slides: []` publicado apaga el fondo
        // §16.7 en la primera publicación; ausente, presentHero() sigue
        // aplicando el fondo por fallback (D3 del design).
        $this->assertArrayNotHasKey('slides', $payload, 'slides sembrado (aunque sea vacío) apaga el fondo del hero en la primera publicación.');
    }
}

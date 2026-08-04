<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Frontend\Concerns\MountsSectionEditor;
use Tests\TestCase;

/**
 * El editor del hero abre con el texto que la página YA muestra.
 *
 * Antes abría vacío mientras el sitio servía el fallback de configuración. El
 * resultado observado en un caso real: el owner copió lo que veía en pantalla y
 * los campos quedaron corridos un lugar —el antetítulo en «Título», el título en
 * «Subtítulo»—. No fue distracción: un formulario en blanco sobre una página con
 * texto obliga a adivinar qué campo produce qué.
 */
class FrontendHeroDraftSeedTest extends TestCase
{
    use MountsSectionEditor;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function hero(string $pageKey): FrontendSection
    {
        return FrontendPage::query()->where('key', $pageKey)->firstOrFail()
            ->sections()->where('section_key', 'hero')->firstOrFail();
    }

    #[DataProvider('pages')]
    public function test_the_hero_draft_carries_what_the_page_shows(string $pageKey): void
    {
        $payload = $this->hero($pageKey)->payload;
        $fallback = config("frontend-sections.hero_fallback.{$pageKey}");

        $this->assertNotNull($payload, "El hero de {$pageKey} abre vacío.");
        $this->assertSame($fallback['title'], $payload['title'] ?? null);
        $this->assertSame($fallback['eyebrow'] ?? null, $payload['eyebrow'] ?? null);
    }

    public static function pages(): array
    {
        return [
            'home' => ['home'],
            'nosotros' => ['nosotros'],
            'servicios' => ['servicios'],
            'inversionistas' => ['inversionistas'],
            'contacto' => ['contacto'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_seeded_draft_is_valid_for_the_schema(string $pageKey): void
    {
        // Si el sembrado produjera un payload inválido, el owner abriría, tocaría
        // una coma y no podría guardar.
        $this->assertSame(
            [],
            app(FrontendSectionSchema::class)->validate('hero', $this->hero($pageKey)->payload),
        );
    }

    public function test_the_editor_opens_with_the_text_already_in_place(): void
    {
        $hero = $this->hero('home');

        $data = $this->sectionEditor($hero)
            ->mountTableAction('edit', $hero->getKey())
            ->get('mountedTableActionsData')[0] ?? [];

        $this->assertSame(
            config('frontend-sections.hero_fallback.home.title'),
            $data['payload']['title'] ?? null,
        );
    }

    public function test_no_external_image_is_seeded_as_media(): void
    {
        // Las fotos del fallback son URLs externas y el payload sólo admite
        // `media_id` de media propia: sembrarlas guardaría una referencia que el
        // schema rechaza. El hero sigue mostrando sus fotos por fallback.
        //
        // `proyectos` (cambio cms-pagina-proyectos, Work Unit 1) OMITE la clave
        // `slides` en vez de sembrarla vacía —a propósito, ver el design D3 de
        // ese cambio—, así que acá se tolera tanto AUSENTE como vacía: las dos
        // formas garantizan la misma invariante, que no haya una imagen externa
        // sembrada como media.
        foreach (array_keys((array) config('frontend-sections.hero_fallback')) as $pageKey) {
            $this->assertSame([], $this->hero($pageKey)->payload['slides'] ?? [], "{$pageKey} sembró una imagen externa como media.");
        }
    }

    public function test_an_already_edited_hero_is_not_overwritten(): void
    {
        // El sembrado sólo alcanza a los heroes con payload NULL. Se comprueba
        // con la migración corriendo sobre contenido propio.
        $hero = $this->hero('home');
        $hero->forceFill(['payload' => [
            'title' => 'Mi título propio',
            'text_align' => 'left',
            'logo_enabled' => false,
            'logo_size' => 'md',
            'slides' => [],
        ]])->saveQuietly();

        (require database_path('migrations/2026_07_28_100100_seed_hero_drafts_from_fallback.php'))->up();

        $this->assertSame('Mi título propio', $hero->fresh()->payload['title']);
    }
}

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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cambio cms-pagina-proyectos, Fase 2 (Work Unit 2) — design D6 y hallazgo #2
 * del owner.
 *
 * `ProjectController@index` hoy lista TODOS los proyectos (`latest()->get()`,
 * sin filtro ni tope), mientras que el tipo `featured_projects` reusado tal
 * cual filtra `is_featured` y acota a 12. Reusarlo sin más en `/proyectos`
 * habría ocultado en silencio cualquier proyecto no destacado del catálogo:
 * una regresión de CONTENIDO real en el cutover, no sólo visual.
 *
 * La variante `catalog` (`config('frontend-sections.project_list_variants')`)
 * resuelve la autoridad de datos POR PÁGINA: `proyectos` lista todos,
 * `home` (sin entrada) sigue mostrando sólo destacados — sin cambios.
 *
 * Se prueba contra el RENDERER directamente y no vía HTTP en `/proyectos`
 * porque el cutover del blade es Work Unit 3 (todavía no ocurrió).
 */
class FrontendProjectsCatalogAuthorityTest extends TestCase
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

    private function publish(string $pageKey, string $sectionKey, array $payload): void
    {
        $this->section($pageKey, $sectionKey)->forceFill(['payload' => $payload])->saveQuietly();

        $page = $this->page($pageKey)->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
    }

    /** @return list<string> */
    private function renderedTitles(string $pageKey, string $sectionKey): array
    {
        $sections = app(FrontendPageRenderer::class)->render($pageKey)['sections'];
        $items = collect($sections)->firstWhere('key', $sectionKey)['data']['items'] ?? [];

        return collect($items)->pluck('title')->all();
    }

    public function test_the_projects_catalogue_lists_every_published_project_not_only_the_featured_ones(): void
    {
        Project::query()->create(['title' => 'Destacado', 'description' => 'x', 'is_featured' => true]);
        Project::query()->create(['title' => 'No destacado', 'description' => 'y', 'is_featured' => false]);

        $this->publish('proyectos', 'projects_list', ['title' => 'Proyectos']);

        $titles = $this->renderedTitles('proyectos', 'projects_list');

        $this->assertContains('Destacado', $titles);
        $this->assertContains('No destacado', $titles, 'El catálogo de /proyectos no debe filtrar por destacado (hallazgo #2).');
    }

    public function test_pages_without_a_catalog_variant_still_filter_by_featured(): void
    {
        Project::query()->create(['title' => 'Destacado en home', 'description' => 'x', 'is_featured' => true]);
        Project::query()->create(['title' => 'Sin destacar', 'description' => 'y', 'is_featured' => false]);

        $this->publish('home', 'featured_projects', ['title' => 'Proyectos destacados']);

        $titles = $this->renderedTitles('home', 'featured_projects');

        $this->assertContains('Destacado en home', $titles);
        $this->assertNotContains('Sin destacar', $titles, 'home debe seguir filtrando por destacado — sin regresión.');
    }

    public function test_the_catalogue_is_unlimited_without_an_explicit_limit(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            Project::query()->create(['title' => "Proyecto {$i}", 'description' => 'x', 'is_featured' => false]);
        }

        $this->publish('proyectos', 'projects_list', ['title' => 'Proyectos']);

        $titles = $this->renderedTitles('proyectos', 'projects_list');

        $this->assertCount(13, $titles, 'Sin `limit`, el catálogo no debe adoptar el tope de 12 del resumen de home.');
    }

    public function test_the_catalogue_respects_an_explicit_limit_without_filtering_by_featured(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Project::query()->create(['title' => "Proyecto {$i}", 'description' => 'x', 'is_featured' => false]);
        }

        $this->publish('proyectos', 'projects_list', ['title' => 'Proyectos', 'limit' => 2]);

        $this->assertCount(2, $this->renderedTitles('proyectos', 'projects_list'));
    }

    /**
     * El tipo de proyecto se lee por cada ítem (`$p->projectType?->label`), así
     * que sin eager loading el catálogo dispara una consulta EXTRA por proyecto.
     *
     * Duele acá y no en home por la misma razón que hizo falta la variante:
     * `catalog` sin `limit` es ILIMITADO, mientras que el resumen de home está
     * topeado en 12. El controlador al que este render reemplaza sí eager
     * cargaba (`ProjectController@index:16`), así que perderlo en el cutover
     * era una regresión de rendimiento, no una optimización pendiente.
     *
     * Se mide el CRECIMIENTO y no un número fijo: cuántas consultas hace el
     * resto del render es un detalle que puede cambiar sin que a esta regla le
     * importe. Lo que no puede cambiar es que agregar proyectos no agregue
     * consultas.
     */
    public function test_the_catalogue_does_not_query_once_per_project(): void
    {
        $contar = function (int $proyectos): int {
            Project::query()->delete();

            for ($i = 1; $i <= $proyectos; $i++) {
                Project::query()->create(['title' => "Proyecto {$i}", 'description' => 'x', 'is_featured' => false]);
            }

            $consultas = 0;
            DB::listen(function () use (&$consultas): void {
                $consultas++;
            });

            $this->renderedTitles('proyectos', 'projects_list');

            return $consultas;
        };

        $this->publish('proyectos', 'projects_list', ['title' => 'Proyectos']);

        // Una pasada de descarte antes de medir: la primera renderización paga
        // cachés que la segunda ya encuentra tibios, y esa diferencia no tiene
        // nada que ver con la regla que se está probando.
        $contar(1);

        $conPocos = $contar(2);
        $conMuchos = $contar(10);

        $this->assertLessThanOrEqual(
            $conPocos,
            $conMuchos,
            "El catálogo consulta una vez por proyecto: {$conPocos} consultas con 2 proyectos y {$conMuchos} con 10. Falta eager loading en la query.",
        );
    }
}

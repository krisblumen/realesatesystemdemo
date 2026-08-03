<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Frontend\Concerns\MountsSectionEditor;
use Tests\TestCase;

/**
 * Las listas del editor ocupan el ancho completo del modal.
 *
 * El formulario del panel tiene DOS columnas, así que todo componente que no
 * declare `columnSpanFull()` ocupa la mitad. Con una fila que lleva vista previa,
 * archivo y varios campos, esa mitad los aplasta: la foto se ve en miniatura
 * junto a campos truncados, y el owner no puede juzgar si la imagen sirve.
 *
 * Se prueba con una aserción estructural y no mirando el HTML porque el ancho
 * real lo calcula el navegador; lo que el código controla —y lo que se puede
 * romper sin darse cuenta al agregar un tipo— es la declaración del span.
 */
class FrontendEditorLayoutTest extends TestCase
{
    use MountsSectionEditor;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    /** @return list<Repeater> */
    private function repeatersOf(string $pageKey, string $sectionKey): array
    {
        $page = FrontendPage::query()->where('key', $pageKey)->firstOrFail();
        $section = $page->sections()->where('section_key', $sectionKey)->firstOrFail();

        $encontrados = [];
        $walk = function ($components) use (&$walk, &$encontrados): void {
            foreach ($components as $child) {
                if ($child instanceof Repeater) {
                    $encontrados[] = $child;
                }
                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $walk(
            $this->sectionEditor($section)
                ->mountTableAction('edit', $section->getKey())
                ->instance()
                ->getMountedTableActionForm()
                ->getComponents()
        );

        return $encontrados;
    }

    #[DataProvider('sectionsWithLists')]
    public function test_every_list_uses_the_full_width(string $pageKey, string $sectionKey): void
    {
        $repeaters = $this->repeatersOf($pageKey, $sectionKey);

        $this->assertNotEmpty($repeaters, "«{$sectionKey}» no tiene ninguna lista.");

        foreach ($repeaters as $repeater) {
            // `getColumnSpan()` devuelve el span por breakpoint; el que manda
            // cuando no hay override es `default`.
            $this->assertSame(
                'full',
                $repeater->getColumnSpan()['default'] ?? null,
                "La lista «{$repeater->getLabel()}» de {$sectionKey} ocupa media columna: sus filas se ven angostas.",
            );
        }
    }

    public static function sectionsWithLists(): array
    {
        return [
            'hero (fotos de fondo)' => ['home', 'hero'],
            'equipo' => ['nosotros', 'team'],
            'secuencia de pasos' => ['inversionistas', 'investment_path'],
            'valores' => ['nosotros', 'values'],
            'cifras' => ['nosotros', 'metrics'],
            'aliados' => ['home', 'partners'],
            'qué hacemos' => ['home', 'what_we_do'],
        ];
    }

    public function test_the_editor_modal_is_wide_enough_for_a_photo_row(): void
    {
        // A 700 px la vista previa quedaba en miniatura junto a los campos.
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();

        $accion = $this->sectionEditor($hero)
            ->mountTableAction('edit', $hero->getKey())
            ->instance()
            ->getMountedTableAction();

        $ancho = $accion->getModalWidth();

        $this->assertSame('6xl', is_string($ancho) ? $ancho : $ancho?->value);
    }

    public function test_the_preview_frame_matches_the_shape_of_a_hero(): void
    {
        // El recuadro muestra la proporción en que la foto se verá DE VERDAD;
        // con otra, la vista previa enseña un encuadre que el sitio no usa.
        // Apaisada por defecto, que es como se ven casi todas las fotos.
        $fuente = $this->fuenteDelEditor();

        $this->assertMatchesRegularExpression(
            "/private function preview\(string \\\$proporcion = '16\/9'\)/",
            $fuente,
            'La vista previa dejó de ser apaisada por defecto.',
        );
    }

    public function test_the_frame_ratio_comes_from_a_closed_list(): void
    {
        // Ese valor termina DENTRO de un atributo `style`. Sale de una lista
        // cerrada para que un valor inesperado no llegue nunca ahí: cualquier
        // otro se trata como apaisado.
        $this->assertMatchesRegularExpression(
            "/in_array\(\\\$proporcion, \['16\/9', '9\/16', '3\/4', '1\/1'\], true\)/",
            $this->fuenteDelEditor(),
        );
    }

    public function test_the_team_portraits_are_previewed_upright(): void
    {
        // Los retratos del equipo se toman en 9:16 y el sitio los publica así.
        // Un recuadro con otra proporción en el panel prometería un encuadre
        // distinto del que se va a ver.
        $this->assertStringContainsString("\$this->preview('9/16')", $this->fuenteDelEditor());

        $this->assertStringContainsString(
            'aspect-[9/16]',
            file_get_contents(resource_path('views/frontend/sections/team.blade.php')),
            'El sitio recorta los retratos con otra proporción que la del panel.',
        );
    }

    private function fuenteDelEditor(): string
    {
        return file_get_contents(app_path(
            'Filament/Resources/FrontendPageResource/RelationManagers/SectionsRelationManager.php',
        ));
    }
}

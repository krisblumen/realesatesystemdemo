<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\HeroRelationManager;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Tables\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El orden de las secciones se mueve entre vecinos, no se escribe.
 *
 * Escribirlo a mano tenía tres salidas malas, y las tres llegaban al owner:
 * repetir un número ocupado reventaba contra el índice único (página, orden) con
 * un SQLSTATE crudo, uno enorme se pasaba del rango de la columna, y uno
 * NEGATIVO se guardaba sin quejarse y dejaba esa sección dibujada ARRIBA de la
 * portada — justo lo que el candado del hero quería impedir, porque el candado
 * sólo miraba el valor del propio hero.
 *
 * Moverse un lugar no puede expresar ninguna de las tres.
 */
class FrontendHeroOrderLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function page(string $key = 'home'): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function hero(string $pageKey): FrontendSection
    {
        return $this->page($pageKey)->sections()->where('section_key', 'hero')->firstOrFail();
    }

    /** Las claves de sección tal como se ven, de arriba hacia abajo. */
    private function order(string $pageKey = 'home'): array
    {
        return $this->page($pageKey)->sections()
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('section_key')->all();
    }

    private function listado(FrontendPage $page): Testable
    {
        return Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditFrontendPage::class,
        ]);
    }

    /** El bloque propio de la portada, arriba del listado. */
    private function portada(FrontendPage $page): Testable
    {
        return Livewire::test(HeroRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditFrontendPage::class,
        ]);
    }

    private function move(FrontendSection $section, int $direction): void
    {
        app(FrontendPageContentService::class)->moveSectionDraft($section, $direction);
    }

    // ------------------------------------------- el número ya no se pide ----

    #[DataProvider('pages')]
    public function test_the_form_no_longer_asks_for_an_order_number(string $pageKey): void
    {
        // Ni deshabilitado ni oculto: el campo no existe. Mientras existiera,
        // seguía siendo la vía por la que entraban los tres errores de arriba.
        $campos = [];

        $walk = function ($components) use (&$walk, &$campos): void {
            foreach ($components as $child) {
                if ($child instanceof Field) {
                    $campos[] = $child->getName();
                }
                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $walk(
            $this->portada($this->page($pageKey))
                ->mountTableAction('edit', $this->hero($pageKey)->getKey())
                ->instance()->getMountedTableActionForm()->getComponents()
        );

        $this->assertNotContains('sort_order', $campos, "El formulario de {$pageKey} sigue pidiendo el orden.");
    }

    public function test_saving_a_section_never_moves_it(): void
    {
        // Un POST armado a mano tampoco: el servicio dejó de aceptar el campo.
        $seccion = $this->page()->sections()->where('section_key', 'partners')->firstOrFail();
        $antes = $seccion->sort_order;

        $this->listado($this->page())
            ->mountTableAction('edit', $seccion->getKey())
            ->setTableActionData([
                'sort_order' => 2,
                'is_enabled' => true,
                'payload' => ['items' => [['name' => 'Aliado']]],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame($antes, $seccion->fresh()->sort_order);
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

    // ------------------------------------------------------ la portada ----

    #[DataProvider('pages')]
    public function test_the_hero_lives_in_its_own_block_out_of_the_list(string $pageKey): void
    {
        // Estaba en el listado como una fila más con las flechas apagadas. Una
        // fila dentro de una lista que se acomoda se lee como algo acomodable,
        // y la portada no lo es: fuera de la lista, la regla no hay que
        // explicarla.
        $pagina = $this->page($pageKey);
        $hero = $this->hero($pageKey);

        $this->listado($pagina)->assertCanNotSeeTableRecords([$hero]);
        $this->portada($pagina)->assertCanSeeTableRecords([$hero]);
    }

    #[DataProvider('pages')]
    public function test_the_hero_block_has_no_arrows(string $pageKey): void
    {
        $this->portada($this->page($pageKey))
            ->assertTableActionDoesNotExist('subir')
            ->assertTableActionDoesNotExist('bajar');
    }

    #[DataProvider('pages')]
    public function test_the_hero_is_first_and_cannot_be_moved(string $pageKey): void
    {
        $hero = $this->hero($pageKey);
        $this->assertSame(0, $hero->sort_order, "El hero de {$pageKey} no está primero.");

        $this->move($hero, 1);
        $this->move($hero, -1);

        $this->assertSame(0, $hero->fresh()->sort_order, "El hero de {$pageKey} se movió.");
        $this->assertSame('hero', $this->order($pageKey)[0]);
    }

    public function test_nothing_can_climb_above_the_hero(): void
    {
        // La sección que sigue a la portada sube… y no pasa nada. La portada no
        // participa de la lista que se permuta, así que no hay lugar al que ir.
        $segunda = $this->page()->sections()->where('sort_order', 1)->firstOrFail();

        $this->move($segunda, -1);

        $this->assertSame(['hero', 'what_we_do'], array_slice($this->order(), 0, 2));
    }

    // --------------------------------------------------------- el movimiento ----

    public function test_moving_down_swaps_with_the_next_one(): void
    {
        $antes = $this->order();
        $seccion = $this->page()->sections()->where('section_key', 'what_we_do')->firstOrFail();

        $this->move($seccion, 1);

        $esperado = $antes;
        [$esperado[1], $esperado[2]] = [$esperado[2], $esperado[1]];

        $this->assertSame($esperado, $this->order());
    }

    public function test_moving_up_undoes_it(): void
    {
        $antes = $this->order();
        $seccion = $this->page()->sections()->where('section_key', 'what_we_do')->firstOrFail();

        $this->move($seccion, 1);
        $this->move($seccion->fresh(), -1);

        $this->assertSame($antes, $this->order());
    }

    public function test_the_last_one_stays_where_it_is(): void
    {
        $ultima = $this->page()->sections()->orderByDesc('sort_order')->firstOrFail();
        $antes = $this->order();

        $this->move($ultima, 1);

        $this->assertSame($antes, $this->order());
    }

    // ------------------------------------------------------- lo que sanea ----

    public function test_a_move_leaves_the_page_numbered_without_gaps(): void
    {
        // El índice único (página, orden) es PARCIAL sobre las filas vivas, y
        // PostgreSQL no difiere índices: escribir los definitivos de a uno
        // chocaría contra el que todavía ocupa el lugar. Si la escritura en dos
        // pasos estuviera mal, esto reventaría con un 23505 en vez de fallar.
        $pagina = $this->page();
        $seccion = $pagina->sections()->where('section_key', 'partners')->firstOrFail();

        // Huecos como los que dejaba escribir el número a mano.
        FrontendSection::query()->whereKey($seccion->getKey())->update(['sort_order' => 40]);
        FrontendSection::query()
            ->where('frontend_page_id', $pagina->getKey())
            ->where('section_key', 'featured_projects')
            ->update(['sort_order' => 30]);

        $this->move($seccion->fresh(), -1);

        $ordenes = $pagina->sections()->orderBy('sort_order')->pluck('sort_order')->all();
        $this->assertSame(range(0, count($ordenes) - 1), $ordenes);
    }

    public function test_a_move_heals_a_section_sitting_above_the_hero(): void
    {
        // Un orden negativo entra: en PostgreSQL un `unsignedInteger` es un
        // `integer` con signo. Así se colaba una sección por encima de la
        // portada pese al candado. Cualquier movimiento la vuelve a su lugar.
        $pagina = $this->page();
        $intrusa = $pagina->sections()->where('section_key', 'partners')->firstOrFail();

        FrontendSection::query()->whereKey($intrusa->getKey())->update(['sort_order' => -1]);
        $this->assertSame('partners', $this->order()[0], 'El escenario no se reprodujo.');

        $this->move($intrusa->fresh(), 1);

        $this->assertSame('hero', $this->order()[0]);
        $this->assertSame(0, $this->hero('home')->sort_order);
    }

    // ---------------------------------------------------- y hay que publicar ----

    public function test_moving_marks_the_page_as_pending_and_tells_the_screen(): void
    {
        // Mover es un cambio de contenido: sube `draft_revision` y hay que
        // publicarlo. Y avisa a la pantalla, porque si no el botón Publicar
        // mandaría la revisión vieja y el publisher la rechazaría.
        $pagina = $this->page();
        $seccion = $pagina->sections()->where('section_key', 'what_we_do')->firstOrFail();
        $antes = $pagina->draft_revision;

        $this->listado($pagina)
            ->callTableAction('bajar', $seccion->getKey())
            ->assertDispatched('borrador-actualizado');

        $this->assertGreaterThan($antes, $pagina->fresh()->draft_revision);
        $this->assertTrue($pagina->fresh()->hasUnpublishedChanges());
    }

    public function test_the_arrows_are_not_drawn_where_there_is_nowhere_to_go(): void
    {
        // En los extremos la flecha no se dibuja. Una apagada igual invita a
        // apretarla y no hace nada.
        $pagina = $this->page();
        $listado = $this->listado($pagina)->instance();

        $primera = $pagina->sections()->where('sort_order', 1)->firstOrFail();
        $ultima = $pagina->sections()->orderByDesc('sort_order')->firstOrFail();

        $flecha = function (FrontendSection $record, string $accion) use ($listado): Action {
            $action = $listado->getTable()->getAction($accion);
            $action->record($record);

            return $action;
        };

        // La portada no está en esta lista, así que el borde de arriba es la
        // primera sección movible: no tiene a dónde subir.
        $this->assertTrue($flecha($primera, 'subir')->isHidden(), 'La primera movible muestra flecha de subir.');
        $this->assertFalse($flecha($primera, 'bajar')->isHidden());
        $this->assertTrue($flecha($ultima, 'bajar')->isHidden(), 'La última muestra flecha de bajar.');
        $this->assertFalse($flecha($ultima, 'subir')->isHidden());
    }

    public function test_a_hidden_arrow_cannot_be_fired_by_hand(): void
    {
        // Esconder no puede ser sólo cosmético: si la acción igual corriera, un
        // request armado a mano movería la sección desde un botón que no está.
        //
        // Se llama el método de Livewire DIRECTO y no `callTableAction`, porque
        // el helper de Filament primero afirma que la acción se ve: fallaría
        // por esa afirmación y no probaría que el guard existe.
        $pagina = $this->page();
        $primera = $pagina->sections()->where('sort_order', 1)->firstOrFail();
        $antes = $this->order();

        $this->listado($pagina)->call('mountTableAction', 'subir', (string) $primera->getKey());

        $this->assertSame($antes, $this->order());
    }
}

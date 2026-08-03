<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\Pages\ListFrontendPages;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Avisar que hay cambios sin publicar.
 *
 * El caso real que lo motivó: alguien editó el hero, guardó, cerró, miró el
 * sitio y no vio su cambio. Estaba todo bien —faltaba publicar— pero **nada en
 * la pantalla lo decía**: el botón se veía igual con o sin trabajo pendiente y
 * el listado no lo mostraba. Un estado del sistema que el usuario no puede ver
 * es un estado que va a interpretar como un bug.
 *
 * Lo primero que se prueba acá es por qué la pregunta necesitaba una columna
 * nueva: `revision` cuenta PUBLICACIONES y `draft_revision` cuenta EDICIONES,
 * así que compararlos entre sí no responde nada.
 */
class FrontendUnpublishedChangesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    private function page(string $key = 'home'): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function publish(FrontendPage $page): void
    {
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
    }

    /** Guarda una sección por el camino real, que es el que sube `draft_revision`. */
    private function editASection(FrontendPage $page): void
    {
        $section = $page->sections()->where('section_key', 'partners')->firstOrFail();

        Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditFrontendPage::class,
        ])
            ->mountTableAction('edit', $section->getKey())
            ->setTableActionData(['is_enabled' => true, 'payload' => ['items' => [['name' => 'Aliado nuevo']]]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();
    }

    public function test_publishing_leaves_the_page_up_to_date(): void
    {
        $page = $this->page();
        $this->publish($page);

        $this->assertFalse($page->fresh()->hasUnpublishedChanges());
    }

    public function test_editing_after_publishing_marks_the_page_as_pending(): void
    {
        $page = $this->page();
        $this->publish($page);
        $this->assertFalse($page->fresh()->hasUnpublishedChanges());

        $this->editASection($page->fresh());

        $this->assertTrue($page->fresh()->hasUnpublishedChanges());
    }

    public function test_publishing_again_clears_the_pending_state(): void
    {
        $page = $this->page();
        $this->publish($page);
        $this->editASection($page->fresh());
        $this->publish($page->fresh());

        $this->assertFalse($page->fresh()->hasUnpublishedChanges());
    }

    public function test_a_page_never_published_counts_as_pending(): void
    {
        $this->assertTrue($this->page('contacto')->hasUnpublishedChanges());
    }

    public function test_the_two_counters_alone_cannot_answer_the_question(): void
    {
        // La razón de existir de `published_draft_revision`. Tras publicar dos
        // veces sin editar en el medio, los contadores QUEDAN DISTINTOS y aun así
        // no hay nada pendiente: compararlos habría dado un aviso falso.
        $page = $this->page();
        $this->publish($page);
        $this->publish($page->fresh());

        $fresco = $page->fresh();

        $this->assertNotSame($fresco->draft_revision, $fresco->revision);
        $this->assertFalse($fresco->hasUnpublishedChanges());
    }

    public function test_the_publisher_records_which_draft_it_published(): void
    {
        $page = $this->page();
        $this->publish($page);

        $this->assertSame($page->fresh()->draft_revision, $page->fresh()->published_draft_revision);
    }

    // ------------------------------------------------------- en pantalla --

    public function test_the_list_shows_which_pages_have_pending_changes(): void
    {
        $page = $this->page();
        $this->publish($page);
        $this->editASection($page->fresh());

        Livewire::test(ListFrontendPages::class)
            ->assertSee('Cambios sin publicar');
    }

    public function test_the_list_shows_a_published_page_as_up_to_date(): void
    {
        foreach (FrontendPage::all() as $page) {
            $this->publish($page->fresh());
        }

        Livewire::test(ListFrontendPages::class)
            ->assertSee('Publicada')
            ->assertDontSee('Cambios sin publicar');
    }

    public function test_the_publish_button_changes_when_there_is_work_pending(): void
    {
        $page = $this->page();
        $this->publish($page);
        $this->editASection($page->fresh());

        Livewire::test(EditFrontendPage::class, ['record' => $page->getKey()])
            ->assertSee('Publicar cambios');
    }
}

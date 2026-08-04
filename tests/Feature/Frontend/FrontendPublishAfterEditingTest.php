<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Publicar funciona inmediatamente después de editar una sección.
 *
 * El caso real: el owner editaba las tarjetas, guardaba, el botón se ponía en
 * «Publicar cambios»… y no pasaba nada hasta recargar la página. No era que el
 * botón no funcionara: mandaba la revisión que la pantalla había capturado AL
 * ABRIRSE, y el publisher la rechazaba porque el borrador ya había avanzado.
 *
 * Ese rechazo existe para que OTRA sesión no te pise: si alguien más editó el
 * borrador mientras tenías la pantalla abierta, publicarías contenido que no
 * viste. Un cambio hecho en la misma pantalla no es ese caso — el owner acaba de
 * hacerlo y de verlo.
 *
 * La regresión importante está al final: la protección contra otra sesión sigue
 * intacta.
 */
class FrontendPublishAfterEditingTest extends TestCase
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

    private function page(): FrontendPage
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail();
    }

    public function test_saving_a_section_announces_that_the_draft_moved(): void
    {
        $page = $this->page();
        $section = $page->sections()->where('section_key', 'partners')->firstOrFail();

        Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditFrontendPage::class,
        ])
            ->mountTableAction('edit', $section->getKey())
            ->setTableActionData(['is_enabled' => true, 'payload' => ['items' => [['name' => 'Aliado']]]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertDispatched('borrador-actualizado');
    }

    public function test_the_screen_catches_up_when_it_hears_the_announcement(): void
    {
        $page = $this->page();

        $pantalla = Livewire::test(EditFrontendPage::class, ['record' => $page->getKey()]);
        $alAbrir = $pantalla->get('loadedDraftRevision');

        // Alguien —el propio owner, en esta pantalla— movió el borrador.
        app(FrontendPageContentService::class)->saveSectionDraft(
            $page->sections()->where('section_key', 'partners')->firstOrFail(),
            ['payload' => ['items' => [['name' => 'Aliado']]], 'is_enabled' => true],
        );

        $pantalla->dispatch('borrador-actualizado');

        $this->assertGreaterThan($alAbrir, $pantalla->get('loadedDraftRevision'));
        $this->assertSame($page->fresh()->draft_revision, $pantalla->get('loadedDraftRevision'));
    }

    public function test_publishing_right_after_editing_works_without_reloading(): void
    {
        // El escenario completo del reporte: editar, y publicar sin recargar.
        $page = $this->page();

        $pantalla = Livewire::test(EditFrontendPage::class, ['record' => $page->getKey()]);

        app(FrontendPageContentService::class)->saveSectionDraft(
            $page->sections()->where('section_key', 'partners')->firstOrFail(),
            ['payload' => ['items' => [['name' => 'Aliado nuevo']]], 'is_enabled' => true],
        );

        $pantalla->dispatch('borrador-actualizado')
            ->callAction('publish')
            ->assertHasNoActionErrors();

        $this->assertFalse($page->fresh()->hasUnpublishedChanges());
    }

    public function test_without_the_announcement_the_publish_is_refused(): void
    {
        // La prueba de que el problema era ese y no otro: sin poner al día la
        // revisión, publicar falla exactamente como le pasaba al owner.
        $page = $this->page();

        $pantalla = Livewire::test(EditFrontendPage::class, ['record' => $page->getKey()]);

        app(FrontendPageContentService::class)->saveSectionDraft(
            $page->sections()->where('section_key', 'partners')->firstOrFail(),
            ['payload' => ['items' => [['name' => 'Aliado']]], 'is_enabled' => true],
        );

        $pantalla->callAction('publish');

        $this->assertTrue($page->fresh()->hasUnpublishedChanges(), 'Publicó con una revisión vieja.');
    }

    public function test_the_button_stops_saying_pending_after_publishing(): void
    {
        // El resto del mismo problema: publicar funcionaba, pero el botón seguía
        // en «pendiente» porque la etiqueta se calcula sobre el registro en
        // memoria y se estaba refrescando una copia.
        $page = $this->page();

        Livewire::test(EditFrontendPage::class, ['record' => $page->getKey()])
            ->callAction('publish')
            ->assertHasNoActionErrors()
            ->assertDontSee('Publicar cambios')
            ->assertSee('Publicar');

        $this->assertFalse($page->fresh()->hasUnpublishedChanges());
    }

    // ---------------------------------------------------- la regresión ----

    public function test_another_session_editing_still_blocks_the_publish(): void
    {
        // LO QUE NO DEBE ROMPERSE. Otra sesión que edite el mismo borrador no
        // dispara el evento de ESTA pantalla, así que su cambio sigue frenando
        // la publicación: el owner no puede publicar contenido que no vio.
        $page = $this->page();

        $pantalla = Livewire::test(EditFrontendPage::class, ['record' => $page->getKey()]);

        // Otra sesión mueve el borrador. Nadie avisa a esta pantalla.
        app(FrontendPageContentService::class)->saveSectionDraft(
            $page->sections()->where('section_key', 'final_cta')->firstOrFail(),
            ['payload' => ['title' => 'Cambiado por otro'], 'is_enabled' => true],
        );

        $pantalla->callAction('publish');

        $this->assertTrue(
            $page->fresh()->hasUnpublishedChanges(),
            'Se publicó pese al cambio de otra sesión: el versionado optimista dejó de proteger.',
        );
    }
}

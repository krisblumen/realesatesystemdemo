<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M-E1: the publish flow must send the draft_revision the SCREEN loaded, not the
 * value at click time. A screen that went stale — because another session edited
 * the draft after it loaded — must be refused, not silently overwrite it.
 */
class FrontendPagePublishUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    public function test_the_editor_holds_the_revision_it_loaded_and_a_stale_publish_is_refused(): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();

        $component = Livewire::test(EditFrontendPage::class, ['record' => $page->getRouteKey()]);
        $component->assertSet('loadedDraftRevision', $page->draft_revision);

        // Another session edits the draft AFTER this screen loaded.
        $hero = FrontendSection::query()->where('frontend_page_id', $page->id)->where('section_key', 'hero')->firstOrFail();
        app(FrontendPageContentService::class)->updateSectionPayload($hero, ['title' => 'Editado por otra sesión']);

        // Publishing from the stale screen must not create a publication.
        $component->callAction('publish');

        $this->assertSame(0, $page->fresh()->revision, 'A stale publish must not go through.');
        $this->assertNull($page->fresh()->published_revision);
    }

    public function test_publishing_a_fresh_screen_succeeds(): void
    {
        $page = FrontendPage::query()->where('key', 'nosotros')->firstOrFail();

        Livewire::test(EditFrontendPage::class, ['record' => $page->getRouteKey()])
            ->callAction('publish');

        $this->assertSame(1, $page->fresh()->revision);
        $this->assertIsArray($page->fresh()->published_revision);
    }
}

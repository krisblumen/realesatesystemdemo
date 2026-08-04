<?php

namespace Tests\Feature\Frontend;

use App\Filament\Pages\FrontendPreview;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner-only draft preview (RFC-077, Lote G): only the owner reaches it, it
 * is never indexable, a non-canonical pageKey is a uniform 404, and there is no
 * reusable public token — an anonymous request never renders a draft.
 */
class FrontendPreviewAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_the_owner_can_preview_a_page_and_it_is_not_indexable(): void
    {
        $owner = User::factory()->withRole('owner')->create();

        $response = $this->actingAs($owner)->get('/admin/frontend/preview/servicios');

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, nofollow"', false);
        $response->assertSee('Vista previa'); // the not-production banner
    }

    public function test_every_non_owner_role_is_forbidden(): void
    {
        // The whole non-owner matrix, not just a sample (Mn-G-2).
        foreach (['admin', 'agente', 'arquitectura', 'proyectos'] as $role) {
            $user = User::factory()->withRole($role)->create();
            $this->actingAs($user)->get('/admin/frontend/preview/servicios')
                ->assertForbidden();
        }
    }

    public function test_an_anonymous_request_never_reaches_the_preview(): void
    {
        // No reusable public token: without an owner session the controller
        // refuses outright — it never renders a draft.
        $this->get('/admin/frontend/preview/servicios')
            ->assertForbidden()
            ->assertDontSee('Vista previa');
    }

    public function test_a_non_canonical_page_key_is_a_uniform_404(): void
    {
        $owner = User::factory()->withRole('owner')->create();

        $this->actingAs($owner)->get('/admin/frontend/preview/no-existe')->assertNotFound();
        $this->actingAs($owner)->get('/admin/frontend/preview/inmuebles')->assertNotFound(); // real route, but not a CMS page
    }

    // ---------------------------------------------- cms-pagina-proyectos ----

    /**
     * Hallazgo #1 (discovery «tres trampas»): `FrontendPreview::pages()` y
     * `FrontendPreviewController::TITLES` tenían las cinco páginas escritas a
     * mano, por fuera de `config('frontend-sections.pages')` — el registro
     * canónico. Sin `proyectos` acá, el owner no puede previsualizar la
     * página que se le acaba de volver editable.
     */
    public function test_the_projects_page_appears_in_the_preview_selector(): void
    {
        $pages = FrontendPreview::pages();

        $this->assertArrayHasKey('proyectos', $pages, 'Sin esta entrada el owner no puede elegir «Proyectos» en el selector.');
        $this->assertSame('Proyectos', $pages['proyectos']);
    }

    public function test_the_owner_can_preview_the_projects_page(): void
    {
        $owner = User::factory()->withRole('owner')->create();

        $response = $this->actingAs($owner)->get('/admin/frontend/preview/proyectos');

        $response->assertOk();
        // El <title> confirma que TITLES (o su equivalente) también conoce la
        // página: sin la entrada, degrada a «Vista previa» (M-G-2).
        $response->assertSee('<title>Proyectos', false);
    }
}

<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Ayuda;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class AyudaPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_guest_cannot_access_ayuda(): void
    {
        $this->get(Ayuda::getUrl())->assertRedirect();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function panelRolesProvider(): iterable
    {
        yield 'owner' => ['owner'];
        yield 'admin' => ['admin'];
        yield 'agente' => ['agente'];
        yield 'arquitectura' => ['arquitectura'];
        yield 'proyectos' => ['proyectos'];
    }

    #[DataProvider('panelRolesProvider')]
    public function test_any_panel_user_can_open_ayuda(string $role): void
    {
        $this->actingAs($this->userWithRole($role))
            ->get(Ayuda::getUrl())
            ->assertOk();
    }

    public function test_ayuda_nav_item_is_present_in_sidebar(): void
    {
        // Ayuda es una página sin grupo; Filament la renderiza al inicio del nav
        // (comportamiento real, no "al fondo" — ver docs/audits/epica-11-auditoria-diseno.md).
        // Este test solo confirma presencia, no orden (el orden real ya está documentado
        // y depende de un detalle interno de Filament que no debe re-litigarse en tests).
        $response = $this->actingAs($this->userWithRole('owner'))->get(Ayuda::getUrl());

        $response->assertOk();
        $response->assertSee('Ayuda');
    }

    public function test_owner_sees_all_sections(): void
    {
        $response = $this->actingAs($this->userWithRole('owner'))->get(Ayuda::getUrl());

        $response->assertOk();

        foreach ([
            'inmuebles', 'leads', 'zonas', 'propietarios', 'proyectos', 'contratos',
            'lonas-asignadas', 'solicitudes-lonas', 'evidencias',
            'caracteristicas', 'tipos-servicio', 'tipos-proyecto', 'usuarios',
        ] as $key) {
            $this->assertSectionLinkPresent($response, $key);
        }
    }

    public function test_agente_sees_operational_but_not_config_or_users(): void
    {
        $response = $this->actingAs($this->userWithRole('agente'))->get(Ayuda::getUrl());

        $response->assertOk();
        $this->assertSectionLinkPresent($response, 'inmuebles');
        $this->assertSectionLinkPresent($response, 'leads');
        $this->assertSectionLinkPresent($response, 'mi-zona');
        $this->assertSectionLinkPresent($response, 'mis-lonas');
        $this->assertSectionLinkAbsent($response, 'tipos-proyecto');
        $this->assertSectionLinkAbsent($response, 'usuarios');
    }

    public function test_arquitectura_role_section_visibility(): void
    {
        $response = $this->actingAs($this->userWithRole('arquitectura'))->get(Ayuda::getUrl());

        $response->assertOk();
        $this->assertSectionLinkPresent($response, 'proyectos');

        foreach ([
            'inmuebles', 'leads', 'zonas', 'propietarios', 'contratos',
            'lonas-asignadas', 'solicitudes-lonas', 'evidencias',
            'caracteristicas', 'tipos-proyecto', 'tipos-servicio', 'usuarios',
            'mi-zona', 'mis-lonas',
        ] as $key) {
            $this->assertSectionLinkAbsent($response, $key);
        }
    }

    public function test_proyectos_role_section_visibility(): void
    {
        $response = $this->actingAs($this->userWithRole('proyectos'))->get(Ayuda::getUrl());

        $response->assertOk();
        $this->assertSectionLinkPresent($response, 'proyectos');

        foreach ([
            'inmuebles', 'leads', 'zonas', 'propietarios', 'contratos',
            'lonas-asignadas', 'solicitudes-lonas', 'evidencias',
            'caracteristicas', 'tipos-proyecto', 'tipos-servicio', 'usuarios',
            'mi-zona', 'mis-lonas',
        ] as $key) {
            $this->assertSectionLinkAbsent($response, $key);
        }
    }

    public function test_requesting_permitted_seccion_renders_markdown(): void
    {
        $response = $this->actingAs($this->userWithRole('owner'))
            ->get(Ayuda::getUrl(['seccion' => 'inmuebles']));

        $response->assertOk();
        $response->assertSee('Manual de Inmuebles');
    }

    public function test_requesting_forbidden_seccion_shows_index_not_content(): void
    {
        $response = $this->actingAs($this->userWithRole('agente'))
            ->get(Ayuda::getUrl(['seccion' => 'usuarios']));

        $response->assertOk();
        $response->assertDontSee('Manual de Usuarios');
        $response->assertSee('Esa sección no está disponible para tu cuenta.');
        $this->assertSectionLinkPresent($response, 'inmuebles');
    }

    public function test_missing_markdown_file_renders_placeholder(): void
    {
        // No depende de que una sección real (p. ej. "zonas") siga sin su .md:
        // eso se rompe en cuanto el contenido se autora (Work Unit 4). En vez de
        // eso, invoca renderMarkdown() directamente con un nombre de archivo que
        // nunca puede existir en el repo, sin atravesar el registry ni el gate.
        $page = new Ayuda;
        $method = new ReflectionMethod(Ayuda::class, 'renderMarkdown');
        $method->setAccessible(true);

        $html = $method->invoke($page, 'archivo-que-nunca-existira-en-resources-help');

        $this->assertSame('<p>Este contenido todavía no está disponible.</p>', $html);
    }

    public function test_index_groups_match_sidebar(): void
    {
        $response = $this->actingAs($this->userWithRole('owner'))->get(Ayuda::getUrl());

        $response->assertOk();

        foreach (['General', 'Operación', 'Lonas', 'Configuración', 'Seguridad'] as $group) {
            $response->assertSee($group);
        }
    }

    public function test_global_search_finds_section_by_content(): void
    {
        $this->actingAs($this->userWithRole('owner'));

        $keys = array_column(Ayuda::globalSearchResults('exclusividad'), 'key');

        $this->assertContains('contratos', $keys);
    }

    public function test_global_search_matches_all_words_not_the_exact_phrase(): void
    {
        $this->actingAs($this->userWithRole('owner'));

        // El texto dice "se envía a firmar", no la frase literal "firma contrato".
        $keys = array_column(Ayuda::globalSearchResults('firma contrato'), 'key');

        $this->assertContains('contratos', $keys);
    }

    public function test_global_search_is_role_aware(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        $ownerKeys = array_column(Ayuda::globalSearchResults('usuarios'), 'key');
        $this->assertContains('usuarios', $ownerKeys, 'El owner debería encontrar la sección Usuarios.');

        $this->actingAs($this->userWithRole('agente'));
        $agentKeys = array_column(Ayuda::globalSearchResults('usuarios'), 'key');
        $this->assertNotContains('usuarios', $agentKeys, 'El agente NO debe encontrar la sección Usuarios.');
    }

    public function test_global_search_is_accent_insensitive(): void
    {
        $this->actingAs($this->userWithRole('owner'));

        $keys = array_column(Ayuda::globalSearchResults('direccion'), 'key');

        $this->assertContains('inmuebles', $keys);
    }

    public function test_global_search_ignores_too_short_queries(): void
    {
        $this->actingAs($this->userWithRole('owner'));

        $this->assertSame([], Ayuda::globalSearchResults('a'));
        $this->assertSame([], Ayuda::globalSearchResults('  '));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    /**
     * Asserts a section's index link is present, keyed by its `?seccion=` value.
     * Avoids false positives/negatives from unrelated substrings elsewhere on the page
     * (e.g. JS comments mentioning resource names in prose).
     */
    private function assertSectionLinkPresent(TestResponse $response, string $key): void
    {
        $response->assertSee("seccion=$key", false);
    }

    private function assertSectionLinkAbsent(TestResponse $response, string $key): void
    {
        $response->assertDontSee("seccion=$key", false);
    }
}

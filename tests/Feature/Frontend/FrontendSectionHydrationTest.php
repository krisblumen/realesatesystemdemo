<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Models\FrontendPage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Frontend\Concerns\MountsSectionEditor;
use Tests\TestCase;

/**
 * Que lo GUARDADO vuelva a aparecer al abrir el editor.
 *
 * Esta clase existe por un defecto que llegó a producción de pruebas y pasó un
 * gate: cinco tipos declaraban un repeater sobre el MISMO `payload.items`, y al
 * convivir en el schema se pisaban. Las filas aparecían pero **vacías**, así que
 * quien abría «Cifras» veía los campos en blanco y al guardar borraba su
 * contenido. El estado real era esto:
 *
 *     items: { uuid: { name: {label:…, value:…}, title:null, value:null, … } }
 *
 * —el ítem entero metido bajo `name`, que es el campo de `partners`.
 *
 * **Por qué no se detectó antes:** toda la matriz de 12.2 probaba el GUARDADO
 * (poner datos y ver qué queda en el payload) y ninguna prueba abría el editor
 * para verificar que los datos VUELVEN. Un formulario que guarda bien y carga
 * mal es exactamente igual de inservible, y se nota recién cuando alguien pierde
 * su trabajo.
 */
class FrontendSectionHydrationTest extends TestCase
{
    use MountsSectionEditor;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    /** El estado del formulario al abrir la sección, ya hidratado. */
    private function hydratedPayload(string $pageKey, string $sectionKey, array $payload): array
    {
        $page = FrontendPage::query()->where('key', $pageKey)->firstOrFail();
        $section = $page->sections()->where('section_key', $sectionKey)->firstOrFail();
        $section->forceFill(['payload' => $payload])->saveQuietly();

        $data = $this->sectionEditor($section)
            ->mountTableAction('edit', $section->getKey())
            ->get('mountedTableActionsData')[0] ?? [];

        return $data['payload'] ?? [];
    }

    /** Las filas de un repeater, sin sus claves uuid. */
    private function rows(array $payload, string $key = 'items'): array
    {
        return array_values($payload[$key] ?? []);
    }

    #[DataProvider('repeaterSections')]
    public function test_a_saved_repeater_comes_back_with_its_values(
        string $pageKey,
        string $sectionKey,
        array $payload,
        string $repeaterKey,
        array $expectedFirstRow,
    ): void {
        $hydrated = $this->hydratedPayload($pageKey, $sectionKey, $payload);
        $rows = $this->rows($hydrated, $repeaterKey);

        $this->assertNotEmpty($rows, "El repeater «{$repeaterKey}» de {$sectionKey} no hidrató ninguna fila.");

        foreach ($expectedFirstRow as $field => $value) {
            $this->assertSame(
                $value,
                $rows[0][$field] ?? null,
                "«{$sectionKey}» perdió «{$field}» al abrir el editor: quien guarde ahora borra su contenido.",
            );
        }
    }

    public static function repeaterSections(): array
    {
        return [
            'metrics' => ['nosotros', 'metrics',
                ['items' => [['value' => '+150', 'label' => 'Operaciones cerradas']]],
                'items', ['value' => '+150', 'label' => 'Operaciones cerradas']],

            'values' => ['nosotros', 'values',
                ['items' => [['title' => 'Transparencia', 'description' => 'Números claros.']]],
                'items', ['title' => 'Transparencia', 'description' => 'Números claros.']],

            'capability_cards' => ['home', 'what_we_do',
                ['items' => [['title' => 'Arquitectura', 'description' => 'Diseño a la medida.']]],
                'items', ['title' => 'Arquitectura', 'description' => 'Diseño a la medida.']],

            'team' => ['nosotros', 'team',
                ['members' => [['name' => 'Ana', 'role' => 'Directora']]],
                'members', ['name' => 'Ana', 'role' => 'Directora']],
        ];
    }

    public function test_a_simple_repeater_comes_back_with_its_values(): void
    {
        // `partners` guarda objetos `{name, media_id?}`: el formulario usa un
        // repeater normal para tener exactamente esa forma, sin traducir. La
        // versión vieja usaba `simple()`, que hidrata desde una lista PLANA, y
        // metía el objeto entero bajo `name` al abrir el editor.
        $hydrated = $this->hydratedPayload('home', 'partners', [
            'items' => [['name' => 'Grupo Ibrac'], ['name' => 'Banco del Bajío']],
        ]);

        // Se comparan los NOMBRES y su orden, no la fila entera: desde que los
        // aliados llevan logo, cada fila trae además los campos de imagen del
        // formulario. Afirmar la fila completa probaría de qué campos está hecho
        // el editor, no que el contenido sobrevivió al viaje.
        $this->assertSame(
            ['Grupo Ibrac', 'Banco del Bajío'],
            array_column(array_values($hydrated['items'] ?? []), 'name'),
        );
    }

    public function test_the_editor_only_carries_the_fields_of_its_own_type(): void
    {
        // La causa raíz: si el schema declara los campos de todos los tipos, dos
        // repeaters comparten `payload.items` y se pisan. El estado de una
        // sección no debe contener claves de otro tipo.
        $hydrated = $this->hydratedPayload('nosotros', 'metrics', [
            'items' => [['value' => '+150', 'label' => 'Operaciones']],
        ]);

        foreach (['audience_items', 'result', 'members', 'slides', 'primary_cta'] as $ajeno) {
            $this->assertArrayNotHasKey(
                $ajeno,
                $hydrated,
                "El editor de «metrics» arrastra «{$ajeno}», que es de otro tipo.",
            );
        }
    }

    public function test_the_header_fields_hydrate_too(): void
    {
        $hydrated = $this->hydratedPayload('home', 'what_we_do', [
            'eyebrow' => 'QUÉ HACEMOS',
            'title' => 'Cuatro disciplinas, un solo equipo',
            'body' => 'Del terreno a la entrega de llaves.',
            'items' => [['title' => 'Arquitectura']],
        ]);

        $this->assertSame('QUÉ HACEMOS', $hydrated['eyebrow'] ?? null);
        $this->assertSame('Cuatro disciplinas, un solo equipo', $hydrated['title'] ?? null);
        $this->assertSame('Del terreno a la entrega de llaves.', $hydrated['body'] ?? null);
    }

    public function test_opening_and_saving_without_touching_anything_preserves_the_content(): void
    {
        // La prueba que resume el defecto: abrir y guardar sin tocar nada NO debe
        // perder contenido. Con el error anterior, este guardado dejaba el
        // payload vacío.
        $page = FrontendPage::query()->where('key', 'nosotros')->firstOrFail();
        $section = $page->sections()->where('section_key', 'metrics')->firstOrFail();
        $section->forceFill(['payload' => ['items' => [
            ['value' => '+150', 'label' => 'Operaciones cerradas'],
            ['value' => '9 años', 'label' => 'En el mercado'],
        ]]])->saveQuietly();

        $this->sectionEditor($section)
            ->mountTableAction('edit', $section->getKey())
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $after = $section->fresh()->payload['items'] ?? [];

        $this->assertCount(2, $after);
        $this->assertSame('+150', $after[0]['value']);
        $this->assertSame('Operaciones cerradas', $after[0]['label']);
    }

    public function test_every_canonical_section_still_opens(): void
    {
        // El schema pasó a armarse por tipo: si algún tipo canónico quedara fuera
        // del `match`, su sección abriría sin campos y sería ineditable.
        $compiler = app(SectionPayloadCompiler::class);

        foreach ((array) config('frontend-sections.pages') as $pageKey => $sections) {
            $page = FrontendPage::query()->where('key', $pageKey)->firstOrFail();

            foreach ($sections as $sectionKey => $type) {
                $section = $page->sections()->where('section_key', $sectionKey)->firstOrFail();

                $data = $this->sectionEditor($section)
                    ->mountTableAction('edit', $section->getKey())
                    ->assertHasNoTableActionErrors()
                    ->get('mountedTableActionsData')[0] ?? [];

                $this->assertArrayHasKey(
                    'payload',
                    $data,
                    "«{$pageKey}/{$sectionKey}» ({$type}) abrió sin campos de contenido.",
                );
                $this->assertTrue($compiler->handles($type));
            }
        }
    }
}

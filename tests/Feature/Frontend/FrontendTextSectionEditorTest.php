<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Components\CtaFields;
use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Épica 12.2, Lote A — TB2A-1…TB2A-8: the six section types WITHOUT media.
 *
 * Same bet as the hero: the form is bound straight to `payload.*`, so what the
 * owner fills in IS the canonical payload of its `SPECS` entry. The schema is
 * the gate at the end of every save, which is why most assertions here run the
 * real save through Livewire instead of hand-writing a payload — a hand-written
 * payload proves the schema works, not that the FORM produces it.
 */
class FrontendTextSectionEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    /** The canonical (page, section_key) of each migrated type. */
    private const SECTIONS = [
        'cta' => ['home', 'final_cta'],
        'rich_text' => ['nosotros', 'story'],
        'values' => ['nosotros', 'values'],
        'metrics' => ['nosotros', 'metrics'],
        'partners' => ['home', 'partners'],
        'audience_outcomes' => ['inversionistas', 'audience_outcomes'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    private function page(string $key): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function section(string $pageKey, string $sectionKey): FrontendSection
    {
        return $this->page($pageKey)->sections()->where('section_key', $sectionKey)->firstOrFail();
    }

    /** The section editor with the edit action already mounted on a section. */
    private function editor(string $pageKey, string $sectionKey): Testable
    {
        $section = $this->section($pageKey, $sectionKey);

        return Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $this->page($pageKey),
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());
    }

    /** Saves `payload` through the real form and returns what landed in the row. */
    private function save(string $pageKey, string $sectionKey, array $payload): ?array
    {
        $this->editor($pageKey, $sectionKey)
            ->setTableActionData(['payload' => $payload])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        return $this->section($pageKey, $sectionKey)->fresh()->payload;
    }

    // ------------------------------------------------------------ TB2A-1 ----

    #[DataProvider('migratedTypes')]
    public function test_each_migrated_type_shows_fields_instead_of_json(string $type, string $pageKey, string $sectionKey, string $expectedLabel): void
    {
        $this->editor($pageKey, $sectionKey)
            ->assertSee($expectedLabel)
            ->assertDontSee('Contenido (JSON)');
    }

    /**
     * Los mismos tipos, pero SOLO el nombre.
     *
     * Un provider se consume con la aridad exacta del método: compartir el de
     * cuatro valores con un test que recibe uno hacía que PHPUnit 12 emitiera un
     * warning por dataset y devolviera código de salida 1 con todas las
     * aserciones en verde. Se deriva del otro para que sigan siendo una sola
     * fuente y no puedan quedar desalineados.
     */
    public static function migratedTypeNames(): array
    {
        return array_map(static fn (array $dataset): array => [$dataset[0]], self::migratedTypes());
    }

    public static function migratedTypes(): array
    {
        return [
            'cta' => ['cta', 'home', 'final_cta', 'Llamado a la acción'],
            'rich_text' => ['rich_text', 'nosotros', 'story', 'Contenido'],
            'values' => ['values', 'nosotros', 'values', 'Valores'],
            'metrics' => ['metrics', 'nosotros', 'metrics', 'Cifras'],
            'partners' => ['partners', 'home', 'partners', 'Aliados'],
            'audience_outcomes' => ['audience_outcomes', 'inversionistas', 'audience_outcomes', 'A quién le sirve'],
        ];
    }

    public function test_no_canonical_section_falls_back_to_the_json_editor(): void
    {
        // Cerrados los lotes A, B y C, TODA sección canónica del registro tiene
        // formulario. El Textarea sigue en el código como red de seguridad hasta
        // el lote 12.2-D, pero ya no hay tipo que caiga en él — y este test es lo
        // que impide que uno nuevo se cuele sin su formulario.
        foreach ((array) config('frontend-sections.pages') as $pageKey => $sections) {
            foreach (array_keys($sections) as $sectionKey) {
                $this->editor($pageKey, $sectionKey)
                    ->assertDontSee('Contenido (JSON)', "«{$pageKey}/{$sectionKey}» sigue editándose como JSON.");
            }
        }
    }

    // ------------------------------------------------------------ TB2A-2 ----

    public function test_saving_a_cta_produces_its_canonical_payload(): void
    {
        $payload = $this->save('home', 'final_cta', [
            'eyebrow' => 'Da el paso',
            'title' => 'Hablemos de tu próxima inversión',
            'body' => 'Te acompañamos desde la primera visita.',
            'primary_cta' => ['label' => 'Contáctanos', 'type' => 'route', 'target' => 'contacto'],
            'secondary_cta' => ['label' => '', 'type' => 'route', 'target' => ''],
        ]);

        $this->assertEqualsCanonicalizing([
            'eyebrow' => 'Da el paso',
            'title' => 'Hablemos de tu próxima inversión',
            'body' => 'Te acompañamos desde la primera visita.',
            'primary_cta' => ['label' => 'Contáctanos', 'type' => 'route', 'target' => 'contacto'],
            // El fondo SIEMPRE viaja, con su valor por defecto si nadie eligió:
            // el render tiene que resolver una clase sí o sí, y `primary` es el
            // color con el que la tarjeta ya se veía.
            'background_color' => 'primary',
        ], $payload);

        // An EMPTY optional CTA is absent, not `{label:'', target:''}`: the owner
        // who left it blank never asked for a second button.
        $this->assertArrayNotHasKey('secondary_cta', $payload);
    }

    public function test_saving_a_rich_text_produces_its_canonical_payload(): void
    {
        $payload = $this->save('nosotros', 'story', [
            'title' => 'Nuestra historia',
            'body' => "Empezamos en 2015 con una oficina y tres personas.\nHoy operamos en todo el Bajío.",
        ]);

        $this->assertSame('Nuestra historia', $payload['title']);
        $this->assertStringContainsString('Bajío', $payload['body']);
        // `text_align` siempre está: es una presentación con default, como el
        // del hero. `layout` NO, porque sin foto no hay nada que ubicar y una
        // clave suelta invitaría a creer que hace algo.
        $this->assertEqualsCanonicalizing(['title', 'body', 'text_align'], array_keys($payload));
        $this->assertSame('left', $payload['text_align']);
        $this->assertArrayNotHasKey('layout', $payload);
    }

    public function test_saving_values_and_metrics_produces_their_canonical_payloads(): void
    {
        $values = $this->save('nosotros', 'values', [
            'title' => 'Lo que nos mueve',
            'items' => [
                ['title' => 'Transparencia', 'description' => 'Números claros desde el primer día.'],
                ['title' => 'Cercanía', 'description' => 'Una sola persona te acompaña todo el proceso.'],
            ],
        ]);

        $this->assertSame('Lo que nos mueve', $values['title']);
        $this->assertCount(2, $values['items']);
        $this->assertEqualsCanonicalizing(['title', 'description'], array_keys($values['items'][0]));

        $metrics = $this->save('nosotros', 'metrics', [
            'items' => [
                ['value' => '+150', 'label' => 'Operaciones cerradas'],
                ['value' => '9 años', 'label' => 'En el mercado'],
            ],
        ]);

        // El formulario no mandó ningún color: el fondo cae en el de siempre —una
        // banda que nadie tocó tiene que verse igual que antes de que existiera
        // el selector— y el de la cifra NO se guarda, porque sin elección la
        // vista lo deduce del fondo para que el número no desaparezca.
        $this->assertEqualsCanonicalizing(['items', 'background_color'], array_keys($metrics));
        $this->assertSame('navy', $metrics['background_color']);
        $this->assertArrayNotHasKey('value_color', $metrics);
        $this->assertSame('+150', $metrics['items'][0]['value']);
        $this->assertSame('Operaciones cerradas', $metrics['items'][0]['label']);
    }

    public function test_saving_partners_produces_a_flat_list_of_names(): void
    {
        $payload = $this->save('home', 'partners', [
            'items' => [['name' => 'Grupo Ibrac'], ['name' => 'Banco del Bajío'], ['name' => 'Notaría 12']],
        ]);

        $this->assertSame([
            ['name' => 'Grupo Ibrac'],
            ['name' => 'Banco del Bajío'],
            ['name' => 'Notaría 12'],
        ], $payload['items']);
    }

    public function test_saving_audience_outcomes_produces_its_nested_payload(): void
    {
        $payload = $this->save('inversionistas', 'audience_outcomes', [
            'eyebrow' => 'Para quién',
            'title' => 'Inversionistas que buscan fundamento',
            'audience_items' => [['item' => 'Family offices'], ['item' => 'Inversionistas patrimoniales']],
            'result' => [
                'title' => 'Qué obtienes',
                'items' => [['item' => 'Análisis de zona'], ['item' => 'Proyección de retorno']],
                'quote' => 'Decidir con datos, no con corazonadas.',
            ],
        ]);

        $this->assertSame(['Family offices', 'Inversionistas patrimoniales'], $payload['audience_items']);
        $this->assertSame(['Análisis de zona', 'Proyección de retorno'], $payload['result']['items']);
        $this->assertSame('Decidir con datos, no con corazonadas.', $payload['result']['quote']);
    }

    /**
     * The transversal rules of the compiler, asserted on the real save: an empty
     * optional field is OMITTED (never `''`, which the render would draw as a
     * blank paragraph), and a repeater row missing its required field is
     * DISCARDED (rejecting the whole save would lose the rest of the work).
     */
    public function test_an_empty_optional_field_is_omitted_not_saved_blank(): void
    {
        $values = $this->save('nosotros', 'values', [
            'title' => '   ',
            'items' => [['title' => 'Transparencia', 'description' => 'Números claros.']],
        ]);

        $this->assertArrayNotHasKey('title', $values);
    }

    public function test_an_incomplete_repeater_row_never_reaches_the_payload(): void
    {
        // Por el formulario el owner NO puede llegar acá: el campo es
        // `required()` y recibe un error explícito, que es mejor que ver
        // desaparecer su fila en silencio. Esto prueba la red de defensa del
        // compilador, para el estado que no viene del formulario.
        $section = $this->section('nosotros', 'values');
        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'items' => [
                ['title' => 'Transparencia', 'description' => 'Números claros.'],
                ['title' => '', 'description' => 'Una fila que nadie terminó.'],
            ],
        ]);

        $this->assertCount(1, $payload['items']);
        $this->assertSame('Transparencia', $payload['items'][0]['title']);
    }

    // ------------------------------------------------------------ TB2A-3 ----

    #[DataProvider('migratedTypeNames')]
    public function test_html_is_rejected_in_every_migrated_type(string $type): void
    {
        $schema = app(FrontendSectionSchema::class);
        $html = '<script>alert(1)</script>';

        $payloads = [
            'cta' => ['title' => $html],
            'rich_text' => ['body' => $html],
            'values' => ['items' => [['title' => $html, 'description' => 'ok']]],
            'metrics' => ['items' => [['label' => $html, 'value' => '1']]],
            'partners' => ['items' => [['name' => $html]]],
            'audience_outcomes' => ['audience_items' => [$html], 'result' => ['items' => ['ok']]],
        ];

        $this->assertNotSame([], $schema->validate($type, $payloads[$type]), "HTML aceptado en {$type}");
    }

    public function test_html_typed_into_the_form_is_rejected_at_save(): void
    {
        // The UI is not the gate — the schema is. A save carrying HTML fails at
        // the action, so nothing reaches the draft.
        $this->editor('nosotros', 'story')
            ->setTableActionData(['payload' => ['title' => 'Historia', 'body' => '<b>negrita</b>']])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();

        $this->assertNull($this->section('nosotros', 'story')->fresh()->payload);
    }

    // ------------------------------------------------------------ TB2A-4 ----

    public function test_cardinality_limits_are_enforced(): void
    {
        $schema = app(FrontendSectionSchema::class);

        $rows = fn (int $n, callable $row): array => array_map($row, range(1, $n));

        $value = fn (int $i): array => ['title' => "T{$i}", 'description' => "D{$i}"];
        $this->assertSame([], $schema->validate('values', ['items' => $rows(12, $value)]));
        $this->assertNotSame([], $schema->validate('values', ['items' => $rows(13, $value)]));

        $metric = fn (int $i): array => ['label' => "L{$i}", 'value' => (string) $i];
        $this->assertSame([], $schema->validate('metrics', ['items' => $rows(12, $metric)]));
        $this->assertNotSame([], $schema->validate('metrics', ['items' => $rows(13, $metric)]));

        $partner = fn (int $i): array => ['name' => "Aliado {$i}"];
        $this->assertSame([], $schema->validate('partners', ['items' => $rows(24, $partner)]));
        $this->assertNotSame([], $schema->validate('partners', ['items' => $rows(25, $partner)]));
    }

    public function test_the_form_declares_the_same_limits_as_the_schema(): void
    {
        // A form that let the owner add a 13th value and only failed at save
        // would be a trap: the limit belongs in the UI too.
        $this->assertSame(12, $this->repeatersOf('nosotros', 'values')['payload.items']);
        $this->assertSame(12, $this->repeatersOf('nosotros', 'metrics')['payload.items']);
        $this->assertSame(24, $this->repeatersOf('home', 'partners')['payload.items']);
    }

    /** @return array<string, int|null> repeater statePath => maxItems */
    private function repeatersOf(string $pageKey, string $sectionKey): array
    {
        $component = $this->editor($pageKey, $sectionKey)->instance();
        $found = [];

        $walk = function ($components) use (&$walk, &$found): void {
            foreach ($components as $child) {
                if ($child instanceof Repeater) {
                    $found[$child->getName()] = $child->getMaxItems();
                }

                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $walk($component->getMountedTableActionForm()->getComponents());

        return $found;
    }

    // ------------------------------------------------------------ TB2A-5 ----

    public function test_audience_outcomes_requires_its_result_object(): void
    {
        $schema = app(FrontendSectionSchema::class);

        // Missing entirely.
        $this->assertNotSame([], $schema->validate('audience_outcomes', [
            'title' => 'T',
            'audience_items' => ['A'],
        ]));

        // Present but not an object.
        $this->assertNotSame([], $schema->validate('audience_outcomes', [
            'audience_items' => ['A'],
            'result' => 'texto suelto',
        ]));

        // Well formed.
        $this->assertSame([], $schema->validate('audience_outcomes', [
            'audience_items' => ['A'],
            'result' => ['items' => ['R']],
        ]));
    }

    public function test_the_compiler_always_emits_the_result_object(): void
    {
        // Even when the owner fills nothing inside it: `result` is REQUIRED by
        // the schema, so omitting it would make an otherwise-valid save fail.
        $payload = $this->save('inversionistas', 'audience_outcomes', [
            'title' => 'Para quién',
            'audience_items' => [['item' => 'Inversionistas']],
        ]);

        $this->assertArrayHasKey('result', $payload);
        $this->assertSame([], $payload['result']['items']);
    }

    // ------------------------------------------------------------ TB2A-6 ----

    #[DataProvider('ctaSections')]
    public function test_the_cta_guidance_reacts_in_every_cta_section(string $pageKey, string $sectionKey): void
    {
        $editor = $this->editor($pageKey, $sectionKey);

        foreach (['route', 'url', 'whatsapp', 'phone', 'email'] as $type) {
            $editor->setTableActionData([
                'payload' => ['primary_cta' => ['label' => 'Ir', 'type' => $type, 'target' => '']],
            ]);

            $editor->assertSee(CtaFields::guidance($type));
        }
    }

    public static function ctaSections(): array
    {
        // The five canonical `cta` sections of the registry.
        return [
            'home/investors_block' => ['home', 'investors_block'],
            'home/final_cta' => ['home', 'final_cta'],
            'nosotros/final_cta' => ['nosotros', 'final_cta'],
            'servicios/final_cta' => ['servicios', 'final_cta'],
            'inversionistas/final_cta' => ['inversionistas', 'final_cta'],
        ];
    }

    // ------------------------------------------------------------ TB2A-7 ----

    #[DataProvider('unsafeTargets')]
    public function test_an_unsafe_cta_target_is_rejected_at_save(string $type, string $target): void
    {
        $this->editor('home', 'final_cta')
            ->setTableActionData(['payload' => [
                'title' => 'Contacto',
                'primary_cta' => ['label' => 'Ir', 'type' => $type, 'target' => $target],
            ]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();

        $this->assertNull($this->section('home', 'final_cta')->fresh()->payload);
    }

    public static function unsafeTargets(): array
    {
        return [
            'javascript:' => ['url', 'javascript:alert(1)'],
            'data:' => ['url', 'data:text/html,<script>alert(1)</script>'],
            'ruta inexistente' => ['route', 'ruta.que.no.existe'],
            'teléfono con letras' => ['phone', 'llamar-ya'],
            'correo inválido' => ['email', 'no-es-un-correo'],
        ];
    }

    // ------------------------------------------------------------ TB2A-8 ----

    #[DataProvider('publicRoutes')]
    public function test_the_public_render_is_unchanged(string $path, string $h1): void
    {
        // The lote touches the EDITOR, not the render. Nothing published changes,
        // so the five pages must answer exactly as before.
        $response = $this->get($path);

        $response->assertOk();
        $this->assertStringContainsString($h1, $response->getContent());
    }

    public static function publicRoutes(): array
    {
        return [
            '/' => ['/', 'Construimos patrimonio'],
            '/nosotros' => ['/nosotros', 'Construimos patrimonio que trasciende'],
            '/servicios' => ['/servicios', 'Del terreno a la entrega'],
            '/inversionistas' => ['/inversionistas', 'De la oportunidad al desarrollo'],
            '/contacto' => ['/contacto', 'Estamos para asesorarte'],
        ];
    }
}

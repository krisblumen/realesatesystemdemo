<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Épica 12.2, Lote B — TB2B-1…TB2B-8: `team` and `feature_sequence`.
 *
 * These two carry images, so the interesting assertions are NOT about text: they
 * are about the media pipeline approved in 12.1-A holding for a second and third
 * consumer. Nothing here re-implements it — the point of the lote is that there
 * is still ONE pipeline.
 */
class FrontendMediaSectionEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function page(string $key): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function section(string $pageKey, string $sectionKey): FrontendSection
    {
        return $this->page($pageKey)->sections()->where('section_key', $sectionKey)->firstOrFail();
    }

    private function team(): FrontendSection
    {
        return $this->section('nosotros', 'team');
    }

    private function sequence(): FrontendSection
    {
        return $this->section('inversionistas', 'investment_path');
    }

    private function editor(FrontendSection $section): Testable
    {
        return Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());
    }

    private function image(string $name = 'foto.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1600, 900);
    }

    /**
     * Saves `payload` through the real form and returns what landed in the row.
     *
     * Los repeaters se declaran aparte porque hay que VACIARLOS primero: uno con
     * `minItems` se hidrata con una fila de relleno, y `setTableActionData` mezcla
     * en vez de reemplazar, así que esa fila vacía sobreviviría y rompería la
     * validación — el test estaría probando algo distinto de lo que dice.
     *
     * El vaciado va por `set()` y las filas por `setTableActionData` porque un
     * `UploadedFile` no sobrevive el snapshot de Livewire.
     *
     * @param  array<string, list<array<string, mixed>>>  $repeaters
     */
    private function save(FrontendSection $section, array $payload, array $repeaters = []): ?array
    {
        $editor = $this->editor($section);

        foreach (array_keys($repeaters) as $key) {
            $editor->set("mountedTableActionsData.0.payload.{$key}", []);
        }

        $editor->setTableActionData(['payload' => $payload + $repeaters])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        return $section->fresh()->payload;
    }

    /** Un archivo real en el disco privado, para los casos que necesitan su ruta. */
    private function onPrivateDisk(string $name): string
    {
        $path = 'section-uploads/'.$name;
        Storage::disk('frontend-private')->put($path, $this->image($name)->get());

        return $path;
    }

    // ------------------------------------------------------------ TB2B-1 ----

    public function test_both_types_show_fields_instead_of_json(): void
    {
        $this->editor($this->team())
            ->assertSee('Integrantes')
            ->assertDontSee('Contenido (JSON)');

        // «Disposición» vive DENTRO de un paso, así que no aparece con el
        // repeater vacío; el test de la allowlist lo cubre con una fila real.
        $this->editor($this->sequence())
            ->assertSee('Pasos')
            ->assertDontSee('Contenido (JSON)');
    }

    #[DataProvider('memberCounts')]
    public function test_a_team_hydrates_with_zero_one_and_the_maximum_members(int $count): void
    {
        $members = [];

        for ($i = 1; $i <= $count; $i++) {
            $members[] = ['name' => "Integrante {$i}", 'role' => 'Asesor'];
        }

        $payload = $this->save($this->team(), ['title' => 'Nuestro equipo'], ['members' => $members]);

        $this->assertCount($count, $payload['members']);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('team', $payload));

        // And the saved draft hydrates back into the form with the same count.
        $this->assertCount($count, $this->editor($this->team())->get('mountedTableActionsData')[0]['payload']['members'] ?? []);
    }

    public static function memberCounts(): array
    {
        return ['ninguno' => [0], 'uno' => [1], 'el máximo' => [24]];
    }

    public function test_a_sequence_hydrates_with_one_and_the_maximum_panels(): void
    {
        // `feature_sequence` has no zero case: its SPECS demands at least one
        // panel, which TB2B-6 covers from the rejection side.
        foreach ([1, 8] as $count) {
            $section = $this->sequence();
            $panels = [];

            for ($i = 1; $i <= $count; $i++) {
                $panels[] = [
                    'title' => "Paso {$i}",
                    'layout' => 'split_media_end',
                    'alt' => "Ilustración del paso {$i}",
                    'upload' => $this->fakeUpload("paso-{$i}.png"),
                ];
            }

            $payload = $this->save($section, ['title' => 'Cómo trabajamos'], ['items' => $panels]);

            $this->assertCount($count, $payload['items']);
            $this->assertSame([], app(FrontendSectionSchema::class)->validate('feature_sequence', $payload));
        }
    }

    /**
     * The state a `FileUpload` holds: a LIST with the file, not a path. Filament
     * stores it on the private disk while resolving the form state, exactly as a
     * real upload from the browser would.
     *
     * @return list<UploadedFile>
     */
    private function fakeUpload(string $name): array
    {
        return [$this->image($name)];
    }

    // ------------------------------------------------------------ TB2B-2 ----

    public function test_replacing_an_image_keeps_the_previous_media_row_and_file(): void
    {
        $section = $this->sequence();

        $first = $this->save($section, [], ['items' => [[
            'title' => 'Paso 1', 'layout' => 'split_media_end', 'alt' => 'Primera',
            'upload' => $this->fakeUpload('primera.png'),
        ]]]);

        $oldUuid = $first['items'][0]['media_id'];
        $oldMedia = Media::query()->where('uuid', $oldUuid)->firstOrFail();
        $oldPath = $oldMedia->getPathRelativeToRoot();

        $second = $this->save($section->fresh(), [], ['items' => [[
            'title' => 'Paso 1', 'layout' => 'split_media_end', 'alt' => 'Segunda',
            'media_id' => $oldUuid,
            'upload' => $this->fakeUpload('segunda.png'),
        ]]]);

        $newUuid = $second['items'][0]['media_id'];

        $this->assertNotSame($oldUuid, $newUuid, 'La imagen nueva no generó un media_id nuevo.');

        // The whole reason the base FileUpload is used instead of the Spatie one:
        // a published revision may still point at the old file.
        $this->assertNotNull(Media::query()->where('uuid', $oldUuid)->first(), 'Se borró la fila de la media anterior.');
        $this->assertTrue(Storage::disk('frontend-private')->exists($oldPath), 'Se borró el archivo de la media anterior.');
    }

    // ------------------------------------------------------------ TB2B-3 ----

    public function test_removing_or_reordering_an_item_never_deletes_media(): void
    {
        $section = $this->sequence();

        $payload = $this->save($section, [], ['items' => [
            ['title' => 'Paso 1', 'layout' => 'split_media_end', 'alt' => 'Uno', 'upload' => $this->fakeUpload('uno.png')],
            ['title' => 'Paso 2', 'layout' => 'split_media_end', 'alt' => 'Dos', 'upload' => $this->fakeUpload('dos.png')],
        ]]);

        [$one, $two] = $payload['items'];
        $before = Media::query()->count();

        // Reorder.
        $this->save($section->fresh(), [], ['items' => [
            ['title' => 'Paso 2', 'layout' => 'split_media_end', 'alt' => 'Dos', 'media_id' => $two['media_id']],
            ['title' => 'Paso 1', 'layout' => 'split_media_end', 'alt' => 'Uno', 'media_id' => $one['media_id']],
        ]]);

        $this->assertSame($before, Media::query()->count(), 'Reordenar borró media.');

        // Remove.
        $this->save($section->fresh(), [], ['items' => [
            ['title' => 'Paso 2', 'layout' => 'split_media_end', 'alt' => 'Dos', 'media_id' => $two['media_id']],
        ]]);

        $this->assertSame($before, Media::query()->count(), 'Quitar un paso borró media.');
        $this->assertNotNull(Media::query()->where('uuid', $one['media_id'])->first());
    }

    // ------------------------------------------------------------ TB2B-4 ----

    public function test_a_media_id_from_another_section_is_rejected(): void
    {
        // A uuid that belongs to a DIFFERENT section (and page) must not be
        // usable here: otherwise a payload could point at an image the owner of
        // this section never uploaded.
        $other = $this->section('home', 'hero');
        $foreign = $other->addMediaFromDisk($this->onPrivateDisk('ajena.png'), 'frontend-private')
            ->toMediaCollection('images');

        $this->editor($this->sequence())
            ->setTableActionData(['payload' => ['items' => [[
                'title' => 'Paso 1', 'layout' => 'split_media_end', 'alt' => 'Ajena',
                'media_id' => (string) $foreign->uuid,
            ]]]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();

        $this->assertNull($this->sequence()->fresh()->payload);
    }

    public function test_an_invented_media_id_is_rejected_without_a_database_error(): void
    {
        // The column is a native PostgreSQL uuid: a non-uuid string would blow up
        // with SQLSTATE 22P02 instead of a validation message if the guard at the
        // frontier were missing.
        $this->editor($this->team())
            ->setTableActionData(['payload' => ['members' => [[
                'name' => 'Alguien', 'alt' => 'Retrato', 'media_id' => 'no-soy-un-uuid',
            ]]]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();
    }

    // ------------------------------------------------------------ TB2B-5 ----

    public function test_a_team_member_without_a_photo_is_valid(): void
    {
        $payload = $this->save($this->team(), [], ['members' => [['name' => 'Ana', 'role' => 'Directora']]]);

        $this->assertArrayNotHasKey('media_id', $payload['members'][0]);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('team', $payload));
    }

    public function test_a_team_member_with_a_photo_and_no_alt_is_rejected(): void
    {
        $this->editor($this->team())
            ->setTableActionData(['payload' => ['members' => [[
                'name' => 'Ana', 'alt' => '', 'upload' => $this->fakeUpload('ana.png'),
            ]]]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();

        $this->assertNull($this->team()->fresh()->payload);
    }

    // ------------------------------------------------------------ TB2B-6 ----

    public function test_a_sequence_panel_without_an_image_is_rejected(): void
    {
        $this->editor($this->sequence())
            ->setTableActionData(['payload' => ['items' => [[
                'title' => 'Paso sin foto', 'layout' => 'split_media_end', 'alt' => 'Nada',
            ]]]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();

        $this->assertNull($this->sequence()->fresh()->payload);
    }

    public function test_a_sequence_with_an_unlisted_layout_or_no_panels_is_rejected(): void
    {
        $schema = app(FrontendSectionSchema::class);
        $uuid = '11111111-2222-4333-8444-555555555555';

        $panel = fn (string $layout): array => [
            'title' => 'Paso', 'media_id' => $uuid, 'alt' => 'Foto', 'layout' => $layout,
        ];

        foreach (config('frontend-sections.feature_sequence_layouts') as $layout) {
            $this->assertSame(
                [],
                $schema->validate('feature_sequence', ['items' => [$panel($layout)]]),
                "El layout permitido «{$layout}» fue rechazado.",
            );
        }

        $this->assertNotSame([], $schema->validate('feature_sequence', ['items' => [$panel('inventado')]]));
        $this->assertNotSame([], $schema->validate('feature_sequence', ['items' => []]));
    }

    public function test_the_form_only_offers_allowlisted_layouts(): void
    {
        // A select offering a fourth option that the schema then rejects would be
        // a trap: the allowlist is one, and it lives in config.
        $options = array_keys($this->layoutSelect()->getOptions());

        $this->assertSame(config('frontend-sections.feature_sequence_layouts'), $options);
    }

    private function layoutSelect(): Select
    {
        $found = null;

        $walk = function ($components) use (&$walk, &$found): void {
            foreach ($components as $child) {
                if ($child instanceof Select && $child->getName() === 'layout') {
                    $found = $child;
                }

                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $editor = $this->editor($this->sequence())
            ->setTableActionData(['payload' => ['items' => [['title' => 'Paso 1', 'layout' => 'split_media_end']]]]);

        $walk($editor->instance()->getMountedTableActionForm()->getComponents());

        $this->assertNotNull($found, 'No se encontró el select de disposición.');

        return $found;
    }

    // ----------------------------------------- mínimos por consumidor -----

    public function test_a_square_portrait_is_accepted_for_a_team_member(): void
    {
        // El mínimo del hero (1200×675, o sea 16:9) se había heredado tal cual
        // para `team`, y eso rechazaba el retrato de una persona — que nadie sube
        // apaisado. Una foto cuadrada razonable tiene que entrar.
        $this->editor($this->team())
            ->setTableActionData(['payload' => ['members' => [[
                'name' => 'Ana', 'alt' => 'Retrato de Ana',
                'upload' => [UploadedFile::fake()->image('ana.png', 800, 800)],
            ]]]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertNotEmpty($this->team()->fresh()->payload['members'][0]['media_id'] ?? null);
    }

    public function test_a_photo_below_the_portrait_minimum_is_still_rejected(): void
    {
        // Bajar el mínimo no es quitarlo: una miniatura sigue sin servir.
        $this->editor($this->team())
            ->setTableActionData(['payload' => ['members' => [[
                'name' => 'Ana', 'alt' => 'Retrato de Ana',
                'upload' => [UploadedFile::fake()->image('chica.png', 300, 300)],
            ]]]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();
    }

    public function test_a_square_image_is_rejected_for_a_sequence_panel(): void
    {
        // Los paneles SÍ son apaisados: el mínimo por consumidor no es un
        // relajamiento general.
        $this->editor($this->sequence())
            ->setTableActionData(['payload' => ['items' => [[
                'title' => 'Paso 1', 'layout' => 'split_media_end', 'alt' => 'Cuadrada',
                'upload' => [UploadedFile::fake()->image('cuadrada.png', 800, 800)],
            ]]]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();
    }

    // ------------------------------------------------------------ TB2B-7 ----

    public function test_the_media_of_these_types_lands_on_the_private_disk(): void
    {
        $payload = $this->save($this->team(), [], ['members' => [[
            'name' => 'Ana', 'alt' => 'Retrato de Ana', 'upload' => $this->fakeUpload('ana.png'),
        ]]]);

        $media = Media::query()->where('uuid', $payload['members'][0]['media_id'])->firstOrFail();

        $this->assertSame('frontend-private', $media->disk);
        $this->assertSame('images', $media->collection_name);

        // Nothing is public until a publish promotes it.
        $this->assertNotSame('promoted', $media->getCustomProperty('promotion_state'));
        $this->assertFalse(Storage::disk('public')->exists($media->getPathRelativeToRoot()));
    }

    // ------------------------------------------------------------ TB2B-8 ----

    public function test_the_draft_preview_uses_the_owner_only_route(): void
    {
        $payload = $this->save($this->team(), [], ['members' => [[
            'name' => 'Ana', 'alt' => 'Retrato de Ana', 'upload' => $this->fakeUpload('ana.png'),
        ]]]);

        $uuid = $payload['members'][0]['media_id'];
        $section = $this->team();

        $html = $this->editor($section)->html();

        $this->assertStringContainsString(
            route('frontend.sections.media', ['section' => $section->getKey(), 'uuid' => $uuid], absolute: false),
            $html,
        );
        $this->assertStringNotContainsString('/storage/', $html);

        // And that route really serves it to the owner.
        $this->get(route('frontend.sections.media', ['section' => $section->getKey(), 'uuid' => $uuid]))->assertOk();
    }
}

<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Feature\Frontend\Concerns\MountsSectionEditor;
use Tests\TestCase;

/**
 * Épica 12.1, Lote B — M-B-1/TB-3: the image rules, exercised through the REAL
 * form.
 *
 * Declaring `acceptedFileTypes`, `maxSize` and `dimensions` on the component is
 * not evidence that anything enforces them: the first version of these tests
 * attached media directly with `addMedia()` and never touched the upload path,
 * so a regression that dropped a rule would have gone unnoticed. These go
 * through Livewire, which is where Laravel actually validates.
 *
 * The other half of the contract is just as important: a rejected file must not
 * end up attached or referenced.
 */
class FrontendHeroUploadValidationTest extends TestCase
{
    use MountsSectionEditor;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function page(): FrontendPage
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail();
    }

    private function hero(): FrontendSection
    {
        return $this->page()->sections()->where('section_key', 'hero')->firstOrFail();
    }

    private function editor(): Testable
    {
        return $this->sectionEditor($this->hero())->mountTableAction('edit', $this->hero()->getKey());
    }

    /** @return array<string, mixed> */
    private function heroData(UploadedFile $file): array
    {
        return [
            'payload' => [
                'title' => 'Hero de prueba',
                'text_align' => 'left',
                'logo_enabled' => false,
                'logo_size' => 'md',
                'slides' => [
                    ['media_id' => null, 'upload' => [$file], 'decorative' => true, 'alt' => null],
                ],
            ],
        ];
    }

    private function assertRejected(UploadedFile $file, string $why): void
    {
        $before = Media::query()->count();

        $this->editor()
            ->setTableActionData($this->heroData($file))
            ->callMountedTableAction()
            ->assertHasTableActionErrors();

        // The second half of the rule: a rejected file must not be attached, and
        // the payload must not reference it.
        $this->assertSame($before, Media::query()->count(), "Se adjuntó media pese a rechazar: {$why}");
        $this->assertEmpty($this->hero()->fresh()->payload['slides'] ?? [], "El payload referenció media rechazada: {$why}");
    }

    public function test_a_valid_image_is_accepted_and_referenced_by_uuid(): void
    {
        $this->editor()
            ->setTableActionData($this->heroData(UploadedFile::fake()->image('ok.png', 1600, 900)))
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $slides = $this->hero()->fresh()->payload['slides'] ?? [];

        $this->assertCount(1, $slides);
        $this->assertNotEmpty($slides[0]['media_id']);
        $this->assertSame('frontend-private', Media::query()->where('uuid', $slides[0]['media_id'])->firstOrFail()->disk);
    }

    public function test_an_svg_is_rejected(): void
    {
        // SVG is forbidden in v1 (§16.4): it is a document that can carry script,
        // and the project deliberately did not take the sanitiser dependency.
        $this->assertRejected(
            UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'),
            'SVG'
        );
    }

    public function test_a_disallowed_mime_is_rejected(): void
    {
        $this->assertRejected(UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'), 'PDF');
        $this->assertRejected(UploadedFile::fake()->create('anim.gif', 100, 'image/gif'), 'GIF');
    }

    public function test_a_camera_sized_file_is_accepted_and_optimized(): void
    {
        // El límite subió de 3 a 12 MB porque la foto se procesa al guardarla:
        // se ajusta a 1920×1080 y se convierte a WebP, así que lo que pesa al
        // subir dejó de determinar lo que pesa en el sitio. Antes el owner tenía
        // que achicarla a mano, que es pedirle el trabajo de la máquina.
        $this->editor()
            ->setTableActionData($this->heroData(UploadedFile::fake()->image('camara.png', 3000, 1800)->size(8192)))
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertNotEmpty($this->hero()->fresh()->payload['slides'][0]['media_id'] ?? null);
    }

    public function test_the_field_limit_never_exceeds_livewire_own_ceiling(): void
    {
        // POR QUÉ 12 MB y no 20. Livewire valida la subida temporal ANTES que el
        // campo, con su propio techo de 12 MB. Un `maxSize` mayor sería una
        // promesa que el campo no puede cumplir: el owner subiría 20 MB y
        // recibiría un error críptico de la capa de transporte en vez del
        // mensaje del formulario.
        //
        // Este test no comprueba el rechazo por comportamiento a propósito: con
        // los dos límites en el mismo valor, un archivo mayor muere en Livewire y
        // nunca llega a la validación que se querría observar.
        $limiteLivewire = 12288;
        $campo = null;

        $walk = function ($components) use (&$walk, &$campo): void {
            foreach ($components as $child) {
                if ($child instanceof FileUpload && $child->getName() === 'upload') {
                    $campo = $child;
                }
                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $walk($this->editor()->instance()->getMountedTableActionForm()->getComponents());

        $this->assertNotNull($campo);
        $this->assertLessThanOrEqual($limiteLivewire, $campo->getMaxSize());
    }

    public function test_an_image_below_the_minimum_dimensions_is_rejected(): void
    {
        // Below 1200×675 the file cannot cover a full-bleed hero without visible
        // upscaling.
        $this->assertRejected(UploadedFile::fake()->image('small.png', 800, 400), '<1200×675');
    }

    public function test_an_alt_longer_than_the_limit_is_rejected(): void
    {
        $data = $this->heroData(UploadedFile::fake()->image('ok.png', 1600, 900));
        $data['payload']['slides'][0]['decorative'] = false;
        $data['payload']['slides'][0]['alt'] = str_repeat('a', 151);

        $this->editor()
            ->setTableActionData($data)
            ->callMountedTableAction()
            ->assertHasTableActionErrors();
    }

    public function test_a_meaningful_slide_without_alt_is_rejected(): void
    {
        $data = $this->heroData(UploadedFile::fake()->image('ok.png', 1600, 900));
        $data['payload']['slides'][0]['decorative'] = false;
        $data['payload']['slides'][0]['alt'] = '';

        $this->editor()
            ->setTableActionData($data)
            ->callMountedTableAction()
            ->assertHasTableActionErrors();
    }
}

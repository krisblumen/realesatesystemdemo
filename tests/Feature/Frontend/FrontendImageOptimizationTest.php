<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\Media\OptimizeSectionImage;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Feature\Frontend\Concerns\MountsSectionEditor;
use Tests\TestCase;

/**
 * Las fotos se optimizan al subirlas.
 *
 * El owner puede subir lo que le da la cámara —varios megas, 4000 px de ancho—
 * y el sitio guarda una versión de hasta 1920×1080 en WebP. Antes tenía que
 * achicarla a mano para entrar en 3 MB, que es pedirle que haga el trabajo de la
 * máquina.
 *
 * Lo que estos tests protegen no es sólo que pese menos: es que la optimización
 * **no introduzca conversiones**. El pipeline de promoción mueve UN archivo por
 * media; una familia de derivados dejaría el original público y las miniaturas
 * privadas, con la URL a medias.
 */
class FrontendImageOptimizationTest extends TestCase
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

    private function hero(): FrontendSection
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'hero')->firstOrFail();
    }

    /** Deja un archivo en el disco privado como lo haría una subida real. */
    private function upload(int $width, int $height, string $name = 'foto.jpg'): string
    {
        $path = 'section-uploads/'.$name;
        Storage::disk('frontend-private')->put($path, UploadedFile::fake()->image($name, $width, $height)->get());

        return $path;
    }

    private function dimensions(string $path): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'nh_test_');
        file_put_contents($tmp, Storage::disk('frontend-private')->get($path));
        [$w, $h] = getimagesize($tmp);
        @unlink($tmp);

        return [$w, $h];
    }

    // ------------------------------------------------------ el procesador --

    public function test_a_huge_image_is_scaled_down_to_the_maximum(): void
    {
        $path = $this->upload(4000, 3000, 'enorme.jpg');

        $optimizada = app(OptimizeSectionImage::class)($path);
        [$w, $h] = $this->dimensions($optimizada);

        $this->assertLessThanOrEqual(OptimizeSectionImage::MAX_WIDTH, $w);
        $this->assertLessThanOrEqual(OptimizeSectionImage::MAX_HEIGHT, $h);
    }

    public function test_the_aspect_ratio_is_preserved_and_nothing_is_cropped(): void
    {
        // Recortar decidiría por el owner qué parte de su foto importa.
        $path = $this->upload(4000, 2000, 'panoramica.jpg');

        [$w, $h] = $this->dimensions(app(OptimizeSectionImage::class)($path));

        $this->assertEqualsWithDelta(2.0, $w / $h, 0.02, 'Se alteró la proporción de la imagen.');
    }

    public function test_a_small_image_is_not_enlarged(): void
    {
        // Agrandar sumaría peso sin sumar un solo píxel de detalle.
        $path = $this->upload(1280, 720, 'chica.jpg');

        [$w, $h] = $this->dimensions(app(OptimizeSectionImage::class)($path));

        $this->assertSame(1280, $w);
        $this->assertSame(720, $h);
    }

    public function test_the_result_is_webp(): void
    {
        $optimizada = app(OptimizeSectionImage::class)($this->upload(2400, 1600, 'origen.jpg'));

        $this->assertStringEndsWith('.webp', $optimizada);

        $tmp = tempnam(sys_get_temp_dir(), 'nh_fmt_');
        file_put_contents($tmp, Storage::disk('frontend-private')->get($optimizada));
        $this->assertSame('image/webp', mime_content_type($tmp));
        @unlink($tmp);
    }

    public function test_a_missing_file_is_returned_untouched(): void
    {
        $this->assertSame('no/existe.jpg', app(OptimizeSectionImage::class)('no/existe.jpg'));
    }

    public function test_a_file_the_driver_cannot_read_never_breaks_the_save(): void
    {
        // Una foto pesada es un problema menor que un guardado caído: ante un
        // archivo ilegible se conserva el original y se sigue.
        Storage::disk('frontend-private')->put('section-uploads/roto.jpg', 'esto no es una imagen');

        $this->assertSame(
            'section-uploads/roto.jpg',
            app(OptimizeSectionImage::class)('section-uploads/roto.jpg'),
        );
    }

    // ------------------------------------------------ integrado al guardado --

    public function test_a_saved_hero_image_is_stored_already_optimized(): void
    {
        $hero = $this->hero();

        $this->sectionEditor($hero)
            ->mountTableAction('edit', $hero->getKey())
            ->setTableActionData(['payload' => [
                'title' => 'Con foto grande',
                'text_align' => 'left', 'logo_enabled' => false, 'logo_size' => 'md',
                'slides' => [[
                    'media_id' => null,
                    'upload' => [UploadedFile::fake()->image('camara.jpg', 4000, 2250)],
                    'decorative' => true, 'alt' => null,
                ]],
            ]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $uuid = $hero->fresh()->payload['slides'][0]['media_id'] ?? null;
        $this->assertNotNull($uuid);

        $media = Media::query()->where('uuid', $uuid)->firstOrFail();
        [$w, $h] = $this->dimensions($media->getPathRelativeToRoot());

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertLessThanOrEqual(OptimizeSectionImage::MAX_WIDTH, $w);
        $this->assertLessThanOrEqual(OptimizeSectionImage::MAX_HEIGHT, $h);
    }

    public function test_the_optimized_media_has_no_conversions(): void
    {
        // LO CENTRAL. El pipeline de promoción mueve un solo archivo por media:
        // una familia de derivados dejaría el original público y las miniaturas
        // privadas, con la URL pública a medias. Por eso se optimiza el ORIGINAL
        // en vez de declarar conversiones.
        $hero = $this->hero();

        $this->sectionEditor($hero)
            ->mountTableAction('edit', $hero->getKey())
            ->setTableActionData(['payload' => [
                'title' => 'Sin conversiones',
                'text_align' => 'left', 'logo_enabled' => false, 'logo_size' => 'md',
                'slides' => [[
                    'media_id' => null,
                    'upload' => [UploadedFile::fake()->image('x.jpg', 2400, 1350)],
                    'decorative' => true, 'alt' => null,
                ]],
            ]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $media = Media::query()->where('uuid', $hero->fresh()->payload['slides'][0]['media_id'])->firstOrFail();

        $this->assertSame([], $media->generated_conversions ?? []);
        $this->assertSame([], $media->responsive_images ?? []);
    }

    public function test_the_upload_limit_allows_a_camera_sized_file(): void
    {
        // 12 MB: el owner sube lo que le da la cámara y la máquina se encarga.
        $componente = null;

        $walk = function ($components) use (&$walk, &$componente): void {
            foreach ($components as $child) {
                if ($child instanceof FileUpload && $child->getName() === 'upload') {
                    $componente = $child;
                }
                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $hero = $this->hero();
        $editor = $this->sectionEditor($hero)->mountTableAction('edit', $hero->getKey());

        $walk($editor->instance()->getMountedTableActionForm()->getComponents());

        $this->assertNotNull($componente);
        $this->assertSame(12288, $componente->getMaxSize());
    }
}

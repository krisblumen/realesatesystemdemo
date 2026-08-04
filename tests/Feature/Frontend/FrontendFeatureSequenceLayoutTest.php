<?php

namespace Tests\Feature\Frontend;

use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendPagePublisher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las tres disposiciones de `feature_sequence` se DIBUJAN distinto.
 *
 * Que las tres pasaran la validación del schema ya estaba probado, y no
 * alcanzaba: `full_overlay` —«Imagen de fondo» en el formulario— estuvo en la
 * allowlist desde el principio SIN una rama que la dibujara, así que el owner
 * la elegía, se guardaba bien, validaba bien, y el sitio la mostraba como si
 * fuera «imagen a la derecha». Un valor válido que el render ignoraba en
 * silencio.
 *
 * Por eso estas pruebas miran el HTML servido y no el payload: el defecto vivía
 * exactamente en el tramo que el schema no cubre.
 */
class FrontendFeatureSequenceLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        Queue::fake();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
    }

    /** Publica Inversionistas con UN panel en la disposición pedida. */
    private function publicarCon(string $layout): string
    {
        $page = FrontendPage::query()->where('key', 'inversionistas')->firstOrFail();
        $seccion = $page->sections()->where('type', 'feature_sequence')->firstOrFail();

        // La imagen se crea de verdad y se PROMUEVE: el schema exige `media_id`
        // en cada panel, y sin una imagen resuelta `full_overlay` no tendría
        // fondo que mostrar — la prueba pasaría sin probar lo que dice probar.
        $media = $seccion->addMedia(UploadedFile::fake()->image('paso.png', 1600, 900))
            ->toMediaCollection('images');

        $seccion->forceFill(['payload' => [
            'title' => 'Cómo trabajamos',
            'items' => [[
                'title' => 'Paso único',
                'body' => 'Texto del paso.',
                'media_id' => (string) $media->uuid,
                'alt' => 'Diagrama del paso',
                'layout' => $layout,
            ]],
        ]])->saveQuietly();

        $page->refresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
        app(FrontendCacheGeneration::class)->bump();

        return $this->get('/inversionistas')->assertOk()->getContent();
    }

    public function test_the_background_layout_puts_the_image_behind_the_text(): void
    {
        $html = $this->publicarCon('full_overlay');

        // El degradado que protege la lectura: de transparente arriba al color
        // principal abajo.
        $this->assertStringContainsString('bg-gradient-to-b from-transparent via-brand-primary/[0.65] to-brand-primary/[0.92]', $html);
        // Y el texto encima, abajo y centrado.
        $this->assertStringContainsString('lg:absolute lg:inset-x-0 lg:bottom-0', $html);
        $this->assertStringContainsString('mx-auto max-w-[820px] text-center', $html);
    }

    #[DataProvider('disposicionesPartidas')]
    public function test_a_split_layout_never_renders_as_a_background(string $layout): void
    {
        $html = $this->publicarCon($layout);

        $this->assertStringNotContainsString(
            'lg:absolute lg:inset-x-0 lg:bottom-0',
            $html,
            "«{$layout}» no debe dibujarse como imagen de fondo.",
        );
        // Sigue siendo la grilla de dos columnas de siempre.
        $this->assertStringContainsString('grid items-center gap-12 lg:grid-cols-2', $html);
    }

    /** @return array<string, array{0: string}> */
    public static function disposicionesPartidas(): array
    {
        return [
            'imagen a la derecha' => ['split_media_end'],
            'imagen a la izquierda' => ['split_media_start'],
        ];
    }

    public function test_the_image_side_flips_between_the_two_split_layouts(): void
    {
        // `lg:order-1` sobre la imagen es lo único que la manda a la izquierda:
        // sin él las dos disposiciones partidas se verían iguales, que es la
        // versión partida del mismo defecto que tenía `full_overlay`.
        $this->assertStringContainsString('lg:order-1', $this->publicarCon('split_media_start'));
        $this->assertStringNotContainsString('lg:order-1', $this->publicarCon('split_media_end'));
    }

    public function test_every_allowlisted_layout_renders_something_of_its_own(): void
    {
        // El guardián de la clase entera: si mañana se agrega una disposición a
        // la allowlist y nadie le escribe su rama, acá se nota — en vez de
        // salir dibujada como otra.
        $vistas = [];

        foreach ((array) config('frontend-sections.feature_sequence_layouts') as $layout) {
            $html = $this->publicarCon($layout);
            $start = strpos($html, 'Paso único');
            $vistas[$layout] = substr($html, max(0, $start - 1200), 1200);
        }

        $this->assertCount(
            count($vistas),
            array_unique($vistas),
            'Hay dos disposiciones que se dibujan igual: alguna no tiene rama propia en la vista.',
        );
    }
}

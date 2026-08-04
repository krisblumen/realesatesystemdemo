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
 * `rich_text` deja elegir la alineación del texto y dónde va la foto.
 *
 * Las dos opciones reusan lo que otras secciones ya tenían: la alineación es el
 * mismo token de tres que el hero y `capability_cards`, y la disposición de la
 * imagen es la misma allowlist que los pasos de `feature_sequence`. Es la misma
 * decisión sobre lo mismo, así que compartir vocabulario evita que el owner
 * tenga que aprenderla dos veces.
 *
 * SIN LAS CLAVES nada se mueve: texto a la izquierda y foto a la derecha, que es
 * como se ven los snapshots publicados hasta hoy (§16.7).
 */
class FrontendRichTextLayoutTest extends TestCase
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

    /**
     * Publica la entrada de Contacto con este payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function publicar(array $payload, bool $conFoto = false): string
    {
        $page = FrontendPage::query()->where('key', 'contacto')->firstOrFail();
        $seccion = $page->sections()->where('type', 'rich_text')->firstOrFail();

        $media = null;
        if ($conFoto) {
            $media = $seccion->addMedia(UploadedFile::fake()->image('foto.png', 1200, 800))
                ->toMediaCollection('images');
            $payload['media_id'] = (string) $media->uuid;
            $payload['alt'] = 'Nuestra oficina';
        }

        $seccion->forceFill(['payload' => $payload + ['body' => 'Texto de la sección.']])->saveQuietly();

        $page->refresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        if ($media !== null) {
            app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
            app(FrontendCacheGeneration::class)->bump();
        }

        return $this->get('/contacto')->assertOk()->getContent();
    }

    // ------------------------------------------------ alineación del texto ----

    #[DataProvider('alineaciones')]
    public function test_the_text_wears_the_chosen_alignment(string $clave, string $clase): void
    {
        $html = $this->publicar(['title' => 'Escribinos', 'text_align' => $clave]);

        $this->assertStringContainsString($clase, $html);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function alineaciones(): array
    {
        return [
            'izquierda' => ['left', 'text-left'],
            'centro' => ['center', 'text-center'],
            'derecha' => ['right', 'text-right'],
        ];
    }

    public function test_without_the_key_the_text_stays_to_the_left(): void
    {
        // LO QUE NO DEBE ROMPERSE: los snapshots ya publicados, que no traen la
        // clave y hoy se ven alineados a la izquierda.
        $this->assertStringContainsString('text-left', $this->publicar(['title' => 'Escribinos']));
    }

    // -------------------------------------------------- dónde va la foto ----

    public function test_the_photo_sits_on_the_right_by_default(): void
    {
        $html = $this->publicar(['title' => 'Escribinos'], conFoto: true);

        // Sin `lg:order-1` la imagen queda después del texto, que es la derecha.
        $this->assertStringNotContainsString('lg:order-1', $html);
        $this->assertStringNotContainsString('lg:absolute lg:inset-x-0 lg:bottom-0', $html);
    }

    public function test_the_photo_can_go_to_the_left(): void
    {
        $html = $this->publicar(['title' => 'Escribinos', 'layout' => 'split_media_start'], conFoto: true);

        $this->assertStringContainsString('lg:order-1', $html);
        // El TEXTO se manda a la derecha en vez de reordenar el HTML: en lectura
        // lineal y para un lector de pantalla sigue viniendo primero.
        $this->assertStringContainsString('lg:order-2', $html);
    }

    public function test_the_photo_can_go_behind_the_text(): void
    {
        $html = $this->publicar(['title' => 'Escribinos', 'layout' => 'full_overlay'], conFoto: true);

        $this->assertStringContainsString('bg-gradient-to-b from-transparent via-brand-primary/[0.65]', $html);
        $this->assertStringContainsString('lg:absolute lg:inset-x-0 lg:bottom-0', $html);
    }

    public function test_without_a_photo_the_layout_is_never_stored(): void
    {
        // Una clave que no hace nada invita a creer que sí. El compilador la
        // omite, y el render se queda con el texto centrado de siempre.
        $html = $this->publicar(['title' => 'Escribinos', 'layout' => 'full_overlay']);

        $this->assertStringNotContainsString('lg:absolute lg:inset-x-0 lg:bottom-0', $html);
        $this->assertStringContainsString('mx-auto max-w-[720px]', $html);
    }
}

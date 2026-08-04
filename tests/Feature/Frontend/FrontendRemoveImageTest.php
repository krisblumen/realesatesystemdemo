<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Una imagen subida se puede QUITAR.
 *
 * Antes no: el `media_id` vive en un campo oculto que un upload vacío no toca
 * —«vacío conserva la actual», decía su propia ayuda—, así que la única salida
 * era borrar la fila entera del repeater. Y en las cuatro ranuras sueltas, todas
 * rotuladas «(opcional)», no había fila que borrar: el logo del hero, la foto de
 * la sección de texto, el destacado del equipo y el autor de los proyectos.
 * Opcional que no se puede desactivar no es opcional.
 *
 * QUITAR NO BORRA EL ARCHIVO, y eso es a propósito (§18.18): una revisión ya
 * publicada puede seguir apuntando a esa media, y borrarla dejaría el sitio con
 * un hueco. Sale del payload; el archivo lo levanta el reporte de huérfanas.
 */
class FrontendRemoveImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        Queue::fake();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function seccion(string $pagina, string $tipo): FrontendSection
    {
        return FrontendPage::query()->where('key', $pagina)->firstOrFail()
            ->sections()->where('type', $tipo)->firstOrFail();
    }

    /** @param array<string, mixed> $estado */
    private function compilar(FrontendSection $seccion, array $estado): array
    {
        return app(SectionPayloadCompiler::class)->compile($seccion, $estado) ?? [];
    }

    private function unaImagen(FrontendSection $seccion): string
    {
        return (string) $seccion->addMedia(UploadedFile::fake()->image('foto.png', 1200, 800))
            ->toMediaCollection('images')->uuid;
    }

    public function test_the_photo_of_a_text_section_can_be_removed(): void
    {
        $seccion = $this->seccion('contacto', 'rich_text');
        $mediaId = $this->unaImagen($seccion);

        // Primero se confirma que SIN pedir nada la imagen se conserva: si no,
        // la prueba de abajo pasaría aunque el compilador la perdiera sola.
        $conservada = $this->compilar($seccion, [
            'body' => 'Texto.', 'media_id' => $mediaId, 'alt' => 'Nuestra oficina',
        ]);
        $this->assertSame($mediaId, $conservada['media_id'] ?? null);

        $quitada = $this->compilar($seccion, [
            'body' => 'Texto.', 'media_id' => $mediaId, 'alt' => 'Nuestra oficina',
            'remove_media' => true,
        ]);

        $this->assertArrayNotHasKey('media_id', $quitada);
    }

    public function test_removing_the_image_also_drops_its_description(): void
    {
        // El `alt` describe una foto que ya no está. Dejarlo sería texto
        // huérfano en el payload, y encima uno que el schema exige que
        // acompañe a una imagen.
        $seccion = $this->seccion('contacto', 'rich_text');
        $mediaId = $this->unaImagen($seccion);

        $payload = $this->compilar($seccion, [
            'body' => 'Texto.', 'media_id' => $mediaId, 'alt' => 'Nuestra oficina',
            'remove_media' => true,
        ]);

        $this->assertArrayNotHasKey('alt', $payload);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('rich_text', $payload));
    }

    public function test_removing_wins_over_a_file_chosen_in_the_same_save(): void
    {
        // Si el owner marcó quitar Y eligió un archivo, lo último que pidió fue
        // que no haya imagen. Quedarse con la nueva sería contradecirlo.
        $seccion = $this->seccion('contacto', 'rich_text');
        $mediaId = $this->unaImagen($seccion);

        $payload = $this->compilar($seccion, [
            'body' => 'Texto.',
            'media_id' => $mediaId,
            'alt' => 'Nuestra oficina',
            'upload' => [UploadedFile::fake()->image('otra.png', 1200, 800)->store('', 'frontend-private')],
            'remove_media' => true,
        ]);

        $this->assertArrayNotHasKey('media_id', $payload);
    }

    public function test_removing_never_deletes_the_file(): void
    {
        // §18.18: una revisión publicada puede seguir apuntando a esa media.
        $seccion = $this->seccion('contacto', 'rich_text');
        $mediaId = $this->unaImagen($seccion);

        $this->compilar($seccion, [
            'body' => 'Texto.', 'media_id' => $mediaId, 'remove_media' => true,
        ]);

        $this->assertTrue(
            $seccion->media()->where('uuid', $mediaId)->exists(),
            'Quitar la imagen del payload no debe borrar el archivo.',
        );
    }

    public function test_the_flag_never_reaches_the_payload(): void
    {
        // `remove_media` es una instrucción del formulario, no contenido. Si se
        // colara, el schema —que es cerrado— rechazaría el guardado entero.
        $seccion = $this->seccion('contacto', 'rich_text');

        $payload = $this->compilar($seccion, ['body' => 'Texto.', 'remove_media' => false]);

        $this->assertArrayNotHasKey('remove_media', $payload);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('rich_text', $payload));
    }

    public function test_the_form_offers_the_switch_only_once_there_is_an_image(): void
    {
        // Se mira el HTML SERVIDO y no el árbol de componentes: la visibilidad
        // depende de `$get('media_id')`, y una caminata del árbol fuera de un
        // render real no resuelve esos cierres — ya nos pasó con otro campo.
        $seccion = $this->seccion('contacto', 'rich_text');

        $sinFoto = $this->editorHtml($seccion);
        $this->assertStringNotContainsString('Quitar la imagen', $sinFoto, 'Sin foto no hay nada que quitar.');

        $seccion->forceFill(['payload' => [
            'body' => 'Texto.', 'media_id' => $this->unaImagen($seccion), 'alt' => 'Nuestra oficina',
        ]])->saveQuietly();

        $this->assertStringContainsString('Quitar la imagen', $this->editorHtml($seccion->fresh()));
    }

    private function editorHtml(FrontendSection $seccion): string
    {
        return Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $seccion->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $seccion->getKey())->html();
    }

    public function test_the_team_spotlight_logo_can_also_be_removed(): void
    {
        // La misma ranura suelta, en otra sección: el arreglo vive en el
        // componente compartido, así que las cuatro quedan cubiertas.
        $seccion = $this->seccion('nosotros', 'team');
        $mediaId = $this->unaImagen($seccion);

        $conLogo = $this->compilar($seccion, [
            'title' => 'Equipo',
            'spotlight' => ['title' => 'A-74', 'media_id' => $mediaId, 'alt' => 'Logo'],
        ]);
        $this->assertSame($mediaId, $conLogo['spotlight']['media_id'] ?? null);

        $sinLogo = $this->compilar($seccion, [
            'title' => 'Equipo',
            'spotlight' => ['title' => 'A-74', 'media_id' => $mediaId, 'alt' => 'Logo', 'remove_media' => true],
        ]);

        $this->assertArrayNotHasKey('media_id', $sinLogo['spotlight'] ?? []);
    }
}

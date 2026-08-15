<?php

namespace Tests\Feature\Filament;

use App\Enums\UserStatus;
use App\Filament\Resources\PropertyResource;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El editor de imagen se abre solo, y sus botones dicen lo que hacen.
 *
 * EL PROBLEMA. El recorte vivía detrás de un lápiz chiquito que sólo aparece
 * SOBRE la miniatura, o sea después de subir. Quien nunca lo vio no sabe que
 * existe: sube la foto, la ve mal encuadrada y se resigna.
 */
class EditorDeImagenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()->setLocale('es');
    }

    private function owner(): User
    {
        $usuario = User::create([
            'name' => 'Quien carga fotos',
            'email' => 'fotos@landra.test',
            'password' => 'una-contrasena-larga',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $usuario->assignRole('owner');

        return $usuario;
    }

    public function test_renaming_two_labels_does_not_break_the_other_thirty(): void
    {
        // EL DEFECTO QUE ME COMÍ ESCRIBIENDO ESTO, y que no avisa.
        //
        // `Lang::addLines()` MARCA EL GRUPO COMO CARGADO. Si se llama antes de
        // que Laravel lea el archivo de Filament, ese archivo no se lee nunca
        // más: quedan las dos claves nuevas y las otras treinta salen como la
        // clave cruda —«filament-forms::components.file_upload...»— en la
        // pantalla del usuario.
        //
        // Por eso `AppServiceProvider` hace `Lang::load()` primero. Sin esa
        // línea, este test cae.
        $this->assertSame(
            'Guardar sin recortar',
            __('filament-forms::components.file_upload.editor.actions.cancel.label'),
        );
        $this->assertSame(
            'Recortar y guardar',
            __('filament-forms::components.file_upload.editor.actions.save.label'),
        );

        foreach (['reset', 'drag_crop', 'drag_move', 'rotate_left'] as $otra) {
            $clave = "filament-forms::components.file_upload.editor.actions.{$otra}.label";

            $this->assertNotSame(
                $clave,
                __($clave),
                "«{$otra}» quedó sin traducir: renombrar dos etiquetas se llevó puesto el resto del archivo.",
            );
        }
    }

    public function test_the_labels_say_what_the_buttons_do(): void
    {
        // «Cancelar» no descarta nada: cierra el modal y deja la imagen tal como
        // se subió —su `oncancel` sólo llama a `closeEditor()`—. Pero quien lo
        // lee entiende «cancelar la subida» y no se anima a tocarlo. Con el
        // editor abriéndose solo, ese malentendido pasa a ser el camino normal.
        $textos = [
            __('filament-forms::components.file_upload.editor.actions.cancel.label'),
            __('filament-forms::components.file_upload.editor.actions.save.label'),
        ];

        foreach ($textos as $texto) {
            $this->assertStringContainsString('uardar', $texto, "«{$texto}» no dice que guarda.");
        }
    }

    public function test_the_form_page_carries_the_script_that_opens_the_editor(): void
    {
        // Comprobación de cableado: que el render hook siga enganchado al panel.
        // Que el editor ABRA es cosa del navegador y se verificó ahí; lo que esto
        // cuida es que nadie saque la línea del proveedor sin darse cuenta.
        $this->actingAs($this->owner());

        $this->get(PropertyResource::getUrl('create'))
            ->assertOk()
            ->assertSee('imageEditInstantEdit', escape: false);
    }
}

<?php

namespace Tests\Feature\Filament;

use App\Enums\UserStatus;
use App\Filament\Widgets\MarcaDelInquilinoWidget;
use App\Models\FrontendSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El escritorio muestra la marca DEL INQUILINO.
 *
 * El panel lleva la marca de Landra —es el producto que se está mostrando— y eso
 * deja al cliente sin ver la suya en ninguna parte mientras trabaja. Este widget
 * es ese lugar.
 *
 * LA TRAMPA QUE ESTE TEST CUIDA: cuando el inquilino no subió su logo, el
 * sistema devuelve el de Landra como respaldo. Un widget que lo dibujara sin más
 * diría «tu marca» señalando un logo ajeno — y peor: haría creer que ya está
 * configurado, así que nadie lo subiría nunca.
 */
class MarcaDelInquilinoWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    private function usuario(string $rol): User
    {
        $usuario = User::create([
            'name' => 'Quien opera',
            'email' => $rol.'@landra.test',
            'password' => 'una-contrasena-larga',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }

    public function test_without_an_own_logo_it_invites_to_upload_one_instead_of_showing_landras(): void
    {
        $this->actingAs($this->usuario('owner'));

        Livewire::test(MarcaDelInquilinoWidget::class)
            ->assertSee('Todavía no subiste tu logo')
            ->assertDontSee('logo-on-light.svg');
    }

    public function test_with_an_own_logo_it_shows_it(): void
    {
        $this->actingAs($this->usuario('owner'));

        $setting = FrontendSetting::current();
        $medio = $setting->addMedia(UploadedFile::fake()->image('mi-logo.png'))
            ->preservingOriginal()->toMediaCollection('logo-light');
        $setting->update(['logo_light_media_id' => $medio->uuid]);

        Livewire::test(MarcaDelInquilinoWidget::class)
            ->assertSee($medio->getUrl(), escape: false)
            ->assertDontSee('Todavía no subiste tu logo');
    }

    public function test_the_logo_is_drawn_inside_a_box_that_actually_exists(): void
    {
        // ESTE TEST NACIÓ VERDE Y NO PROTEGÍA.
        //
        // La primera versión escribía `max-h-14 max-w-[220px] object-contain` en
        // el Blade y verificaba que estuvieran en el HTML. Estaban — y no hacían
        // NADA: el panel de Filament carga su propio bundle de CSS, y esas
        // utilidades no existen ahí. El logo se dibujaba a tamaño natural y
        // tapaba media pantalla, con el test en verde.
        //
        // Por eso ahora se comprueban las dos mitades: que la imagen lleve la
        // clase, y que el tema del panel la DEFINA acotando las dos dimensiones.
        // Una sola de las dos vuelve a mentir.
        $this->actingAs($this->usuario('owner'));

        $setting = FrontendSetting::current();
        $medio = $setting->addMedia(UploadedFile::fake()->image('mi-logo.png'))
            ->preservingOriginal()->toMediaCollection('logo-light');
        $setting->update(['logo_light_media_id' => $medio->uuid]);

        $html = Livewire::test(MarcaDelInquilinoWidget::class)->html();

        $this->assertMatchesRegularExpression(
            '/<img[^>]*class="[^"]*\blandra-marca-logo\b/',
            $html,
            'La imagen tiene que llevar la clase de la caja.',
        );

        $tema = (string) file_get_contents(public_path('css/filament/admin/theme.css'));

        preg_match('/\.landra-marca-logo\{([^}]*)\}/', $tema, $regla);

        $this->assertNotEmpty($regla, 'El tema del panel tiene que definir `.landra-marca-logo`, o la clase no hace nada.');

        foreach (['max-height', 'max-width', 'object-fit:contain'] as $propiedad) {
            $this->assertStringContainsString(
                $propiedad,
                $regla[1],
                "Sin «{$propiedad}» la caja no acota: un logo vertical se estira o uno ancho empuja el botón.",
            );
        }
    }

    public function test_an_agent_does_not_see_it(): void
    {
        // El widget termina en «subí tu logo», y un agente no puede subirlo:
        // ofrecerle una puerta cerrada es peor que no ofrecerle ninguna. Se usa
        // la misma condición que la página de configuración, no una copia — dos
        // reglas separadas divergen en el primer cambio.
        $this->actingAs($this->usuario('agente'));

        $this->assertFalse(MarcaDelInquilinoWidget::canView());
    }

    public function test_the_owner_does_see_it(): void
    {
        $this->actingAs($this->usuario('owner'));

        $this->assertTrue(MarcaDelInquilinoWidget::canView());
    }
}

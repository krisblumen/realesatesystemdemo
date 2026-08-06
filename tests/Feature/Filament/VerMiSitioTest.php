<?php

namespace Tests\Feature\Filament;

use App\Enums\UserStatus;
use App\Filament\Pages\CompartirMiSitio;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El atajo para ver el sitio publicado.
 *
 * POR QUÉ HACE FALTA. La vista previa del CMS muestra el BORRADOR y sólo las
 * secciones editables: los bloques con datos —inmuebles destacados,
 * oportunidades, proyectos— los arma `HomeController`, por donde la vista previa
 * no pasa. Así que quien edita nunca ve su sitio entero desde el panel.
 *
 * Y no es que no pueda: el entorno cerrado exige SESIÓN, y quien está en el
 * panel la tiene. Recorre su sitio completo, con el mismo render y el mismo
 * caché que vería un visitante. Lo que faltaba era el enlace — había que saber
 * que se podía y escribir la dirección a mano.
 *
 * Las dos cosas conviven a propósito: la vista previa sirve MIENTRAS editás,
 * antes de publicar; esto muestra lo que ya está publicado. Fusionarlas perdería
 * la primera.
 */
class VerMiSitioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
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

    private function item(): ?NavigationItem
    {
        foreach (Filament::getPanel('admin')->getNavigationItems() as $item) {
            if ($item->getLabel() === 'Ver mi sitio demo') {
                return $item;
            }
        }

        return null;
    }

    public function test_the_owner_gets_a_shortcut_to_the_published_site(): void
    {
        $this->actingAs($this->usuario('owner'));

        $item = $this->item();

        $this->assertNotNull($item, 'Sin el atajo hay que saber la dirección y escribirla a mano.');
        $this->assertTrue($item->isVisible());
    }

    public function test_it_points_at_the_root_of_the_site_and_not_at_the_panel(): void
    {
        $this->actingAs($this->usuario('owner'));

        $this->assertSame(url('/'), $this->item()?->getUrl());
    }

    public function test_it_opens_in_another_tab(): void
    {
        // Si reemplazara la pestaña, quien está editando pierde el formulario a
        // medio llenar para mirar cómo quedó. Es el momento exacto en que uno
        // quiere comparar, no abandonar.
        $this->actingAs($this->usuario('owner'));

        $this->assertTrue($this->item()?->shouldOpenUrlInNewTab());
    }

    public function test_both_shortcuts_to_the_site_sit_together_and_first(): void
    {
        // Los dos SALEN del panel hacia el sitio; el resto del grupo administra
        // contenido puertas adentro. Van juntos y arriba porque son la pregunta
        // que uno se hace primero: «¿cómo quedó?».
        //
        // Negativos porque ningún otro ítem del grupo declara orden: con valores
        // positivos quedarían al final, separados por los que Filament ordena
        // solo.
        $this->actingAs($this->usuario('owner'));

        $this->assertSame(-2, $this->item()?->getSort());
        $this->assertSame(-1, CompartirMiSitio::getNavigationSort());
    }

    public function test_they_are_told_apart_by_colour_in_the_panel_theme(): void
    {
        // La misma trampa del logo del escritorio: una clase de Tailwind escrita
        // en una vista del panel no existe, porque el panel carga su propio
        // bundle. Acá la regla vive en el tema, y esto verifica que exista —si no,
        // el color sería una intención sin efecto.
        $tema = (string) file_get_contents(public_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString('/frontend/compartir"] .fi-sidebar-item-label', $tema);
        $this->assertStringContainsString('[target="_blank"] .fi-sidebar-item-label', $tema);
    }

    public function test_an_agent_does_not_see_it(): void
    {
        // Va en el grupo «Frontend», que es del owner: un agente vería un grupo
        // con un solo ítem suelto y sin contexto.
        $this->actingAs($this->usuario('agente'));

        $this->assertFalse($this->item()?->isVisible() ?? false);
    }
}

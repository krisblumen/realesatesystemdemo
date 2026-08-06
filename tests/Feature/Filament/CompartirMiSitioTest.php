<?php

namespace Tests\Feature\Filament;

use App\Enums\UserStatus;
use App\Filament\Pages\CompartirMiSitio;
use App\Models\User;
use App\Tenancy\CompartirElSitio;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla desde la que el inquilino comparte su sitio.
 *
 * El enlace se muestra UNA sola vez porque de él sólo se guarda el SHA-256.
 * Guardarlo en claro para poder volver a mostrarlo convertiría una filtración de
 * la base en una filtración de todos los sitios.
 */
class CompartirMiSitioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('enlaces_de_muestra')->delete();
        $this->seed(PermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        DB::table('enlaces_de_muestra')->delete();
        User::query()->where('email', 'like', '%@compartir.test')->forceDelete();

        parent::tearDown();
    }

    private function usuario(string $rol): User
    {
        $usuario = User::create([
            'name' => 'Quien opera',
            'email' => $rol.'@compartir.test',
            'password' => 'una-contrasena-larga',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }

    public function test_an_agent_cannot_reach_it(): void
    {
        // Compartir el sitio es una decisión de quien es dueño del contenido.
        $this->actingAs($this->usuario('agente'));

        $this->assertFalse(CompartirMiSitio::canAccess());
    }

    public function test_generating_shows_the_link_once_and_leaves_one_active(): void
    {
        $this->actingAs($this->usuario('owner'));

        $componente = Livewire::test(CompartirMiSitio::class)
            ->callAction('generar');

        $enlace = $componente->get('enlace');

        $this->assertIsString($enlace);
        $this->assertStringContainsString('/muestra/', $enlace);
        $this->assertSame(1, DB::table('enlaces_de_muestra')->whereNull('revocado_en')->count());
    }

    public function test_the_shown_link_is_not_the_one_stored(): void
    {
        // Si lo que se muestra estuviera guardado tal cual, quien lea la base
        // entra a todos los sitios.
        $this->actingAs($this->usuario('owner'));

        $enlace = Livewire::test(CompartirMiSitio::class)
            ->callAction('generar')
            ->get('enlace');

        $token = basename((string) $enlace);

        $this->assertFalse(DB::table('enlaces_de_muestra')->where('token_hash', $token)->exists());
        $this->assertTrue(DB::table('enlaces_de_muestra')->where('token_hash', hash('sha256', $token))->exists());
    }

    public function test_the_first_link_is_not_confirmed_but_replacing_one_is(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE, visto en pantalla y no en un test.
        //
        // El título y el texto del modal estaban puestos fijos, así que la acción
        // TENÍA modal aunque `requiresConfirmation` fuera falso: al generar el
        // primer enlace, el sistema advertía sobre un enlace anterior que no
        // existía. Una advertencia que miente es peor que ninguna — enseña a
        // apretar «Enviar» sin leer, justo antes de la vez que sí importa.
        $this->actingAs($this->usuario('owner'));

        // Se mira la DESCRIPCIÓN y no el título: `getModalHeading()` cae al
        // label de la acción cuando no hay título propio, así que nunca es nula
        // y no distingue nada. La descripción es además el aviso que se lee.
        $this->assertNull(
            Livewire::test(CompartirMiSitio::class)->instance()->getAction('generar')->getModalDescription(),
            'Sin enlace previo no hay nada que romper: no se confirma.',
        );

        app(CompartirElSitio::class)->generar();

        $this->assertNotNull(
            Livewire::test(CompartirMiSitio::class)->instance()->getAction('generar')->getModalDescription(),
            'Con uno activo, generar otro lo rompe: eso sí se avisa.',
        );
    }

    public function test_revoking_leaves_none_active(): void
    {
        $this->actingAs($this->usuario('owner'));

        app(CompartirElSitio::class)->generar();

        Livewire::test(CompartirMiSitio::class)->callAction('revocar');

        $this->assertSame(0, DB::table('enlaces_de_muestra')->whereNull('revocado_en')->count());
    }

    public function test_revoking_is_offered_only_when_there_is_something_to_revoke(): void
    {
        $this->actingAs($this->usuario('owner'));

        Livewire::test(CompartirMiSitio::class)->assertActionHidden('revocar');

        app(CompartirElSitio::class)->generar();

        Livewire::test(CompartirMiSitio::class)->assertActionVisible('revocar');
    }
}

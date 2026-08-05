<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * Operar el padrón: saber de quién es un inquilino y devolverle el acceso.
 *
 * Las dos cosas salieron del primer despliegue real. Alguien no podía entrar,
 * y para averiguar con qué correo se entraba y volver a darle una contraseña
 * hubo que abrir `tinker` y escribir consultas a mano contra la base de su
 * inquilino. Eso no es operar: es meter la mano adentro y esperar no romper
 * nada.
 */
class OperacionDelPadronTest extends TestCase
{
    use UsaBaseCentral;

    private const CORREO = 'operacion-padron@ejemplo.com';

    protected function tearDown(): void
    {
        // La conexión por defecto NO se envuelve en transacción —sólo la
        // central—, así que lo que este test escriba en `users` queda. Se limpia
        // a mano o el próximo test hereda basura.
        User::query()->where('email', self::CORREO)->forceDelete();

        parent::tearDown();
    }

    private function inquilino(
        string $slug,
        TenantEstado $estado = TenantEstado::Activo,
        ?string $base = null,
    ): Tenant {
        return Tenant::create([
            'slug' => $slug,
            // Por defecto apunta a la base de pruebas, que ya tiene la tabla
            // `users`: al comando le alcanza con una base que la tenga, y así
            // no hay que construir una plantilla entera para probar esto.
            'database' => $base ?? config('database.connections.pgsql.database'),
            'email' => self::CORREO,
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => $estado,
        ]);
    }

    private function owner(string $password): User
    {
        return User::create([
            'name' => 'Owner',
            'email' => self::CORREO,
            'password' => $password,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
    }

    public function test_the_registry_hides_the_email_unless_it_is_asked_for(): void
    {
        // El correo es el ÚNICO dato personal del padrón, y el padrón es lo que
        // uno muestra en una pantalla compartida o pega en un chat. Por eso sale
        // sólo cuando alguien lo pide.
        $this->inquilino('aaaabbbbcccc');

        $this->artisan('demo:padron')
            ->doesntExpectOutputToContain(self::CORREO)
            ->assertSuccessful();
    }

    public function test_the_registry_shows_the_email_when_asked(): void
    {
        // Y TIENE que poder pedirse. La decisión original era no mostrarlo nunca
        // —«no hace falta para operar»— y el primer despliegue la desmintió: sin
        // el correo no se sabe de quién es un inquilino ni con qué usuario se
        // entra, y la salida era abrir `tinker`.
        $this->inquilino('aaaabbbbcccc');

        $this->artisan('demo:padron', ['--correos' => true])
            ->expectsOutputToContain(self::CORREO)
            ->assertSuccessful();
    }

    public function test_reissuing_replaces_the_password_and_the_old_one_stops_working(): void
    {
        $this->inquilino('aaaabbbbcccc');
        $usuario = $this->owner('la-vieja-de-antes');

        $this->artisan('demo:reemitir-acceso', ['slug' => 'aaaabbbbcccc'])
            ->expectsOutputToContain(self::CORREO)
            ->assertSuccessful();

        $this->assertFalse(
            Hash::check('la-vieja-de-antes', (string) $usuario->fresh()?->password),
            'Reemitir tiene que INVALIDAR la anterior, no agregar una segunda.',
        );
    }

    public function test_reissuing_refuses_a_slug_that_does_not_exist(): void
    {
        $this->artisan('demo:reemitir-acceso', ['slug' => 'noexisteaqui'])
            ->assertFailed();
    }

    public function test_reissuing_refuses_a_tenant_that_is_not_active(): void
    {
        // Un inquilino expirado o borrado puede no tener base: el comando moriría
        // por conexión, y el mensaje hablaría de Postgres en vez de decir lo que
        // pasa. Peor todavía sería que funcionara y le devolviera el acceso a
        // alguien cuyo demo ya se cortó.
        foreach ([TenantEstado::Expirado, TenantEstado::Borrado, TenantEstado::Fallido] as $i => $estado) {
            // Base distinta por inquilino: `tenants.database` es único, y estos
            // nunca se abren porque el comando corta antes por el estado.
            $this->inquilino('inactivo'.$i.'aaaa', $estado, 'demo_probe_inactivo_'.$i);

            $this->artisan('demo:reemitir-acceso', ['slug' => 'inactivo'.$i.'aaaa'])
                ->assertFailed();
        }
    }

    public function test_reissuing_says_so_when_the_owner_user_is_missing(): void
    {
        // Pasa de verdad: un alta que murió después de copiar la base deja el
        // inquilino sin owner. Sin este caso, el comando reportaría éxito
        // habiendo cambiado la contraseña de nadie.
        $this->inquilino('aaaabbbbcccc');

        $this->artisan('demo:reemitir-acceso', ['slug' => 'aaaabbbbcccc'])
            ->assertFailed();
    }
}

<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Http\Middleware\CierraElDemo;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * La página de un inquilino no se le sirve jamás a otro.
 *
 * ES EL TEST QUE JUSTIFICA LA ÉPICA. Y se corre contra un almacén de caché
 * COMPARTIDO a propósito: hoy el caché usa la conexión por defecto, así que su
 * tabla ya vive en la base del inquilino y el aislamiento saldría gratis. Con un
 * almacén compartido eso desaparece, y lo único que queda en pie es el prefijo —
 * que es exactamente lo que hay que probar.
 *
 * El día que alguien mueva el caché a Redis por rendimiento —una decisión
 * razonable y probable— el aislamiento por base se evapora de golpe. Si este
 * test pasa con almacén compartido, ese día no pasa nada.
 */
class AislamientoDeCacheTest extends TestCase
{
    use UsaBaseCentral;

    private const BASE_A = 'demo_probe_cache_a';

    private const BASE_B = 'demo_probe_cache_b';

    private string $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = config('database.connections.pgsql.database');
        config(['tenancy.dominio_base' => 'demo.test']);

        // Almacén COMPARTIDO entre inquilinos Y PERSISTENTE entre peticiones.
        //
        // Es el escenario que importa: «alguien movió el caché a un almacén
        // común por rendimiento». Con `array` no serviría —se descarta en cada
        // petición, así que ni el propio inquilino leería lo que escribió— y el
        // test daría verde sin haber probado nada.
        // `phpunit.xml` fija CACHE_STORE=array, así que hay que cambiar TAMBIÉN
        // el almacén por defecto: configurar la conexión del almacén `database`
        // sin eso no tenía ningún efecto, y el test daba un falso rojo.
        config(['database.connections.cache_compartido' => array_merge(
            config('database.connections.pgsql'),
            ['database' => $this->original],
        )]);
        config([
            'cache.default' => 'database',
            'cache.stores.database.connection' => 'cache_compartido',
        ]);

        // El almacén compartido NO está dentro de la transacción del test: lo
        // que escribe un caso sobrevive al siguiente. Sin vaciarlo, el segundo
        // test leería un valor del primero y creería que hay una fuga.
        DB::connection('cache_compartido')->table('cache')->truncate();

        foreach ([self::BASE_A, self::BASE_B] as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
            DB::connection('maintenance')->statement('CREATE DATABASE "'.$base.'"');
        }

        $this->inquilino('cachealphaaaa', self::BASE_A);
        $this->inquilino('cachebetabbbb', self::BASE_B);

        $this->withoutMiddleware(CierraElDemo::class);

        Route::middleware('web')->get('/_cache/{valor?}', function (?string $valor = null) {
            if ($valor !== null) {
                Cache::put('frontend:g1:page:home:v2', $valor, 60);
            }

            return response()->json(['leido' => Cache::get('frontend:g1:page:home:v2')]);
        });
    }

    protected function tearDown(): void
    {
        config(['database.connections.pgsql.database' => $this->original]);
        DB::purge('pgsql');

        foreach ([self::BASE_A, self::BASE_B] as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
        }

        parent::tearDown();
    }

    private function inquilino(string $slug, string $base): void
    {
        Tenant::create([
            'slug' => $slug,
            'database' => $base,
            'email' => $slug.'@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => TenantEstado::Activo,
        ]);
    }

    public function test_two_tenants_writing_the_same_key_do_not_see_each_other(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE: las claves del frontend son
        // idénticas entre inquilinos —`frontend:g1:page:home:v2`— así que con un
        // almacén compartido el primero que publica le sirve su home al
        // segundo. Y nadie lo nota hasta que un prospecto ve el sitio de otro.
        $this->get('http://cachealphaaaa.demo.test/_cache/HOME-DE-A')->assertOk();
        $this->get('http://cachebetabbbb.demo.test/_cache/HOME-DE-B')->assertOk();

        $a = $this->get('http://cachealphaaaa.demo.test/_cache')->json('leido');
        $b = $this->get('http://cachebetabbbb.demo.test/_cache')->json('leido');

        $this->assertSame('HOME-DE-A', $a);
        $this->assertSame('HOME-DE-B', $b);
    }

    public function test_a_tenant_never_reads_a_key_it_did_not_write(): void
    {
        $this->get('http://cachealphaaaa.demo.test/_cache/SOLO-DE-A')->assertOk();

        $b = $this->get('http://cachebetabbbb.demo.test/_cache')->json('leido');

        $this->assertNull($b, 'Un inquilino que nunca escribió esa clave tiene que leer vacío.');
    }

    public function test_outside_a_tenant_the_cache_keeps_its_own_prefix(): void
    {
        // El host central y las peticiones ajenas al demo no llevan prefijo de
        // inquilino: no hay inquilino que prefijar, y ponerle uno inventado
        // partiría el caché de la aplicación en dos.
        $antes = config('cache.prefix');

        $this->get('http://localhost/_cache/AJENO')->assertOk();

        $this->assertSame($antes, config('cache.prefix'));
    }
}

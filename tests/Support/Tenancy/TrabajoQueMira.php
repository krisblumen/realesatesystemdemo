<?php

namespace Tests\Support\Tenancy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

/**
 * Un trabajo que sólo anota contra qué base lo hicieron correr.
 *
 * Sirve para observar el mecanismo desde adentro, que es la única forma de
 * distinguir «la conexión quedó bien al final» de «la conexión estuvo bien
 * MIENTRAS el trabajo corría».
 */
class TrabajoQueMira implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public static ?string $baseQueVio = null;

    /**
     * Lo que cada corrida LEYÓ DE LA BASE, en orden.
     *
     * Se guarda el dato leído y no sólo el nombre configurado porque son cosas
     * distintas: la configuración puede estar bien y la conexión viva seguir
     * apuntando a la base del trabajo anterior. Eso no lanza excepción — el
     * trabajo lee, y lee bien, del inquilino equivocado.
     *
     * @var list<string>
     */
    public static array $folios = [];

    /**
     * Con qué host armaría una dirección: `route()` y `url()` cuelgan de acá.
     */
    public static ?string $raizQueVio = null;

    public static function olvidar(): void
    {
        self::$baseQueVio = null;
        self::$raizQueVio = null;
        self::$folios = [];
    }

    public function handle(): void
    {
        self::$baseQueVio = Config::get('database.connections.pgsql.database');
        self::$raizQueVio = URL::to('/');

        // Sólo si hay algo que leer: este trabajo también se usa para casos sin
        // inquilino, donde la tabla no existe. Un trabajo que corriera contra la
        // base equivocada no anotaría nada, y la lista esperada tampoco daría.
        if (Schema::hasTable('probe_contratos')) {
            self::$folios[] = (string) DB::table('probe_contratos')->value('folio');
        }
    }
}

<?php

namespace Tests\Support\Tenancy;

use App\Support\Frontend\RutaDeMediosPorInquilino;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

    /**
     * Dónde buscaría en el DISCO el archivo de un inquilino.
     *
     * Es la cuarta superficie del aislamiento, y la que no depende de la base:
     * `RutaDeMediosPorInquilino` arma la ruta con el slug del inquilino actual.
     */
    public static ?string $rutaQueVio = null;

    public static function olvidar(): void
    {
        self::$baseQueVio = null;
        self::$raizQueVio = null;
        self::$rutaQueVio = null;
        self::$folios = [];
    }

    public function handle(): void
    {
        self::$baseQueVio = Config::get('database.connections.pgsql.database');
        self::$raizQueVio = URL::to('/');

        // Un medio sin guardar alcanza: la ruta se deriva del id y del inquilino.
        $medio = new Media;
        $medio->id = 7;
        self::$rutaQueVio = (new RutaDeMediosPorInquilino)->getPath($medio);

        // Sólo si hay algo que leer: este trabajo también se usa para casos sin
        // inquilino, donde la tabla no existe. Un trabajo que corriera contra la
        // base equivocada no anotaría nada, y la lista esperada tampoco daría.
        if (Schema::hasTable('probe_contratos')) {
            self::$folios[] = (string) DB::table('probe_contratos')->value('folio');
        }
    }
}

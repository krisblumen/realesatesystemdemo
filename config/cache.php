<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Almacén del limitador de peticiones
    |--------------------------------------------------------------------------
    |
    | SE SEPARA DEL POR DEFECTO, Y NO ES ORGANIZACIÓN. El limitador es un
    | SINGLETON que Laravel construye la primera vez que alguien lo toca, y en
    | esta aplicación eso pasa en `AppServiceProvider::boot()` —donde se
    | declaran los límites de los contratos públicos—, o sea ANTES de que corra
    | un solo middleware.
    |
    | Su almacén se queda con la conexión que hubiera en ese momento, que es la
    | por defecto sin inquilino resuelto: el centinela. `ResolveTenant` la
    | reapunta después, pero el limitador ya guardó la suya y no se entera
    | nunca. El resultado era un 500 en TODA ruta con `throttle`.
    |
    | Se apunta a la central porque es la única base que existe y es correcta en
    | el arranque, cuando todavía no hay petición. Y encaja con lo que un
    | limitador debe contar: los intentos son por dirección IP, no por
    | inquilino — un mismo atacante contra tres demos es un solo balde, no tres.
    |
    */

    'limiter' => env('CACHE_LIMITER', 'limitador'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "storage", "octane",
    |                    "session", "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        // El almacén del limitador: mismo driver y misma tabla que el de arriba,
        // pero con la conexión DECLARADA en vez de heredada. Ver la nota de
        // `limiter`.
        'limitador' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION', 'central'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_QUEUE_CONNECTION', 'central'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        /*
         * Los cerrojos del programador de tareas.
         *
         * `withoutOverlapping()` y `onOneServer()` guardan su cerrojo en el
         * caché, y el almacén `database` de arriba resuelve la conexión POR
         * DEFECTO — que desde consola es el centinela, porque un comando no
         * tiene subdominio del cual resolver un inquilino.
         *
         * Sin este almacén, `demo:borrar` falla antes de mirar un solo
         * inquilino. La central es el lugar correcto: el programa de tareas es
         * de la instalación entera, no de ningún inquilino.
         *
         * NO se cambia el almacén por defecto a este: el caché de cada inquilino
         * vive en SU base, y moverlo acá dejaría todo junto, separado apenas por
         * un prefijo.
         */
        'central' => [
            'driver' => 'database',
            'connection' => 'central',
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => 'central',
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'storage' => [
            'driver' => 'storage',
            'disk' => env('CACHE_STORAGE_DISK'),
            'path' => env('CACHE_STORAGE_PATH', 'framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This value determines the classes that can be unserialized from cache
    | storage. By default, no PHP classes will be unserialized from your
    | cache to prevent gadget chain attacks if your APP_KEY is leaked.
    |
    */

    'serializable_classes' => false,

];

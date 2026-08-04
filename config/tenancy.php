<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Nombres de bases de datos
    |---------------------------------------------------------------------------
    |
    | Los prefijos NO son cosmética: el nombre de la base de un inquilino se
    | interpola en `CREATE DATABASE`, porque Postgres no acepta parámetros
    | enlazados para identificadores. El prefijo fijo más un slug validado es
    | parte de la defensa (RFC-05).
    |
    | El prefijo de pruebas es distinto del de producción a propósito: el
    | comando que barre bases de prueba jamás debe poder rozar una real.
    |
    */

    'prefijo_inquilino' => env('TENANCY_PREFIJO_INQUILINO', 'demo_t_'),
    'prefijo_plantilla' => env('TENANCY_PREFIJO_PLANTILLA', 'demo_template'),
    'prefijo_pruebas' => env('TENANCY_PREFIJO_PRUEBAS', 'demo_probe_t_'),

    /*
    |---------------------------------------------------------------------------
    | La plantilla vigente
    |---------------------------------------------------------------------------
    |
    | Se versiona en vez de migrarse en su lugar. Construir la siguiente no
    | interrumpe las altas, y volver atrás es cambiar este valor: Postgres
    | rechaza copiar una plantilla que tenga cualquier conexión encima, así que
    | migrar la vigente sería una carrera contra cada alta.
    |
    */

    'plantilla_vigente' => env('TENANCY_PLANTILLA', 'demo_template'),

    /*
    |---------------------------------------------------------------------------
    | Centinela de conexión sin resolver
    |---------------------------------------------------------------------------
    |
    | El valor al que apunta la conexión del inquilino cuando todavía no se
    | resolvió. NO puede ser vacío ni nulo: Postgres, con nombre de base vacío,
    | conecta a una base con el nombre del usuario. Una consulta anterior a la
    | resolución no fallaría — escribiría en otro lado, en silencio.
    |
    | Apuntando a un nombre que no existe, esa consulta muere con «database does
    | not exist», que es exactamente lo que uno quiere leer.
    |
    */

    'centinela' => env('TENANCY_CENTINELA', 'demo_sin_resolver'),

    /*
    |---------------------------------------------------------------------------
    | Cerrojo de aprovisionamiento
    |---------------------------------------------------------------------------
    |
    | Clave fija de `pg_advisory_lock`. No se deriva del slug: lo que se
    | serializa es el acceso a LA PLANTILLA, que es una sola, no el alta de un
    | inquilino en particular.
    |
    */

    'cerrojo' => [
        'clave' => (int) env('TENANCY_CERROJO_CLAVE', 728_401),
        'intentos' => (int) env('TENANCY_CERROJO_INTENTOS', 10),
        'espera_ms' => (int) env('TENANCY_CERROJO_ESPERA_MS', 300),
    ],

    /*
    |---------------------------------------------------------------------------
    | Ciclo de vida
    |---------------------------------------------------------------------------
    */

    'dias_de_vida' => (int) env('TENANCY_DIAS_DE_VIDA', 30),

];

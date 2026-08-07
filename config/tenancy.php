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
    | Sitio promocional
    |---------------------------------------------------------------------------
    |
    | A dónde mandar a quien llega al dominio base sin subdominio. Vacío mientras
    | no exista: el host central sirve su propia página mínima, que no consulta
    | ninguna tabla.
    |
    | Ese orden es deliberado. Redirigir siempre ataría el host central a que
    | exista otro sitio, y mientras ese sitio no esté listo cambiaríamos un 500
    | por el 500 del otro dominio. Con la página propia como piso, la redirección
    | es una mejora y no un requisito.
    |
    */

    'sitio_promocional' => env('TENANCY_SITIO_PROMOCIONAL'),

    /*
    |---------------------------------------------------------------------------
    | Duración del enlace para mostrar el sitio
    |---------------------------------------------------------------------------
    |
    | Siete días. Más que eso deja de ser «se lo muestro a mi socio» y pasa a ser
    | un sitio público con pasos extra — que es justo lo que el entorno cerrado
    | existe para evitar.
    |
    */

    'dias_de_enlace' => (int) env('TENANCY_DIAS_DE_ENLACE', 7),

    /*
    |---------------------------------------------------------------------------
    | Bajo qué nombre escribe sus archivos la plantilla
    |---------------------------------------------------------------------------
    |
    | La librería de medios deriva la ruta del identificador de la fila, y esos
    | identificadores ARRANCAN EN 1 EN CADA BASE. El prefijo por inquilino sólo
    | aplica cuando hay uno resuelto — y construir una plantilla es un proceso de
    | consola, sin inquilino.
    |
    | Sin esto, la plantilla escribe en `1/` igual que cualquier otro proceso sin
    | inquilino, y se pisan. Se comprobó en disco: `casa-jardin.jpg` de una
    | plantilla y el logo de otra base, en la misma carpeta.
    |
    | Lo pone `demo:plantilla:construir` en el proceso hijo que siembra. Vacío en
    | cualquier otro contexto.
    |
    */

    'medios_de_plantilla' => env('TENANCY_MEDIOS_DE_PLANTILLA'),

    /*
    |---------------------------------------------------------------------------
    | Límites de alta (RFC-10)
    |---------------------------------------------------------------------------
    |
    | EL NÚMERO NO SE ELIGE, SE DERIVA:
    |
    |     inquilinos simultáneos ≈ registros por día × días de vida
    |
    | Si la instancia sostiene 200 bases y llegan 50 registros diarios, el plazo
    | no puede pasar de cuatro días. No es una decisión de producto: es una
    | división. Elegir el plazo primero y descubrir el techo después es cómo se
    | llena un disco un domingo.
    |
    | El techo real de este VPS NO es el disco (18 MB por inquilino, ~3.000 por
    | espacio) sino las 100 conexiones de Postgres, compartidas con la producción
    | de New Hauz y con el correo. Ver `docs/deployment/DEMO-MULTI-INQUILINO.md`.
    |
    | Están en configuración para poder bajarlos sin desplegar.
    |
    */

    'limites' => [

        /*
         * La sal del hash de origen. FIJA Y CON DUEÑO: si rota, los límites se
         * pierden en silencio —nadie se entera— que es peor que no tenerlos.
         *
         * Sin ella el sistema NO hashea con cadena vacía: se niega. Un límite
         * que parece funcionar y no protege es peor que ninguno.
         */
        'sal' => env('TENANCY_SAL_DE_ORIGEN'),

        /*
         * El tope duro. Cuenta las bases VIVAS —todo lo que no está borrado—
         * porque un inquilino expirado sigue ocupando disco y conexiones hasta
         * que el barrido pase. Existe siempre, aunque el plazo sea corto: es la
         * última red antes de quedarnos sin conexiones.
         *
         * Conservador a propósito hasta medir el comportamiento real con gente
         * adentro.
         */
        'tope_ocupados' => (int) env('TENANCY_TOPE_OCUPADOS', 20),

        /* Altas por origen dentro de la ventana. */
        'por_origen' => (int) env('TENANCY_ALTAS_POR_ORIGEN', 3),
        'ventana_horas' => (int) env('TENANCY_VENTANA_HORAS', 24),
    ],

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

    /*
    |---------------------------------------------------------------------------
    | Dominio base
    |---------------------------------------------------------------------------
    |
    | Cada inquilino vive en `{slug}.{dominio_base}`. La resolución por
    | subdominio llega con el lote D; acá se usa para armar la dirección que
    | imprime la invitación.
    |
    */

    'dominio_base' => env('TENANCY_DOMINIO_BASE', 'demo.localhost'),

    /*
    |---------------------------------------------------------------------------
    | Rol dueño de las bases de inquilino
    |---------------------------------------------------------------------------
    |
    | Quien CREA las bases es el rol de aprovisionamiento —el único con
    | CREATEDB— pero quien tiene que poder USARLAS es el rol con el que la
    | aplicación atiende peticiones. Sin declarar el dueño, la base queda del
    | creador y el primer request del inquilino falla por permisos.
    |
    | Requiere que el rol de aprovisionamiento sea MIEMBRO de este:
    |   GRANT demo_app TO demo_provisioner;
    |
    | Vacío en desarrollo, donde todo corre con el mismo usuario.
    |
    */

    'rol_aplicacion' => env('TENANCY_ROL_APLICACION'),

];

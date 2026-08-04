# Auditoría de diseño — DEMO multi-inquilino

**Proyecto:** realestatesystemDemo  
**Fecha:** 2026-08-03  
**Auditor:** Codex  
**Documentos auditados:** `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md`, `docs/epicas/epica-demo-multi-inquilino.md`, lotes A–F, `docs/rfcdemo/`, `docs/deployment/DEMO-MULTI-INQUILINO.md`  
**Alcance:** diseño y premisas técnicas. Se leyó código real y `vendor/`. No se ejecutó la suite porque `.env.testing` todavía apunta a `inmo_test`.

## Evidencia verificada en código real

- `.env` apunta a desarrollo correcto: `DB_DATABASE=demo_db` (`.env:36-40`).
- `phpunit.xml` fuerza `DB_DATABASE=demo_test` para `artisan test` (`phpunit.xml:29-30`), pero `.env.testing` todavía apunta a `DB_DATABASE=inmo_test` (`.env.testing:36-40`).
- Los 28 modelos actuales no declaran `$connection`; usan la conexión por defecto. Ejemplos: `app/Models/FrontendPage.php:15`, `app/Models/Property.php:29`, `app/Models/User.php:37`. El conteo local de `app/Models/*.php` dio 28.
- Sesión y caché usan `database` por defecto y conexión nula: `config/session.php:21,76`; `config/cache.php:18,42-46`.
- Las claves actuales del frontend no llevan inquilino: `app/Services/Frontend/FrontendPageContentService.php:46`, `FrontendSettingsService.php:54`, `FrontendServicesService.php:51`, `FrontendThemeService.php:48`, `FrontendNavigationService.php:51`.
- Laravel permite acotar las transacciones de `RefreshDatabase`: `vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php:174-178`.
- El glob de migraciones no es recursivo: `vendor/laravel/framework/src/Illuminate/Database/Migrations/Migrator.php:578-582` usa `$path.'/*_*.php'`.
- Filament expone `Panel::domain()`: `vendor/filament/filament/src/Panel/Concerns/HasRoutes.php:50-64`.
- El orden de middleware puede ajustarse con prioridad: `vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:425-429`; esta app ya usa `prependToPriorityList` en `bootstrap/app.php:29-32`.
- Media Library no tiene un `route_generator` con ese nombre; sí tiene `path_generator`, `custom_path_generators` y `url_generator`: `config/media-library.php:144,154,164`; `vendor/spatie/laravel-medialibrary/src/Support/UrlGenerator/UrlGeneratorFactory.php:12-24`.
- La app confía en todos los proxies: `bootstrap/app.php:24-27`; Laravel incluye `HEADER_X_FORWARDED_HOST` entre headers confiados: `vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:22-27`.
- Medición local segura contra `demo_template` / bases temporales `demo_audit_*`:
  - PostgreSQL local: `PostgreSQL 18.3`.
  - `demo_template`: `18 MB`; `demo_test`: `24 MB`; `demo_db`: `18 MB`.
  - `CREATE DATABASE demo_audit_copy_* TEMPLATE demo_template`: `0.306 s`.
  - Copia trae `postgis=3.6.4`, `cms_pages=6`, `gist_indexes=2`.
  - En `demo_template`: `tables=48`, `users=0`, páginas `contacto,home,inversionistas,nosotros,proyectos,servicios`.
  - `CREATE DATABASE` dentro de `BEGIN`: `ERROR: CREATE DATABASE cannot run inside a transaction block`.
  - Copiar `demo_template` con una conexión activa: `ERROR: source database "demo_template" is being accessed by other users`.
  - PDO con el DSN de Laravel `dbname=''` conectó a `current_database() = postgres` para usuario `postgres`.

## Veredicto

❌ **No está listo para implementar sin corregir los hallazgos críticos.**

La dirección arquitectónica es buena: base por inquilino, plantilla versionada, resolución antes de sesión, cola anclada a central, caché prefijada aunque hoy sea redundante, y validación pegada al DDL. ESO está bien pensado.

Pero hay contratos que se contradicen justo donde más duele: base de tests peligrosa, estado `fallido` que a veces tiene base y a veces “no hay base”, borrado con dos órdenes incompatibles, y archivos publicados que pueden saltarse el cierre por sesión. Son fallas implementables, no gustos de estilo.

---

## Afirmaciones verificadas

| # | Afirmación | Veredicto | Evidencia |
|---|---|---|---|
| 1 | Copiar plantilla con `CREATE DATABASE ... TEMPLATE` cuesta ~0.2 s y trae PostGIS, GIST y 6 páginas | **Parcial** | Local: 0.306 s, PostGIS 3.6.4, 2 GIST, 6 páginas. Pero dio 48 tablas, no 50, y no fue el VPS. |
| 2 | Postgres rechaza copiar plantilla con conexiones encima, en 16 y 18 | **Parcial** | Verificado local en 18.3 con error exacto. 16.14 está documentado en `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:181`, pero no se verificó contra un servidor 16 en esta corrida. |
| 3 | `CREATE DATABASE` no puede correr dentro de transacción | **Verificada** | Consola: `ERROR: CREATE DATABASE cannot run inside a transaction block`. |
| 4 | Apuntar la conexión por defecto deja intactos los 28 modelos existentes | **Verificada con condición** | Los 28 modelos actuales no fijan `$connection`; un futuro modelo central sí debe declararla explícita como diseña `docs/epicas/epica-demo-lote-a-diseno.md:132-139`. |
| 5 | DB name vacío no falla: conecta a base del usuario | **Verificada localmente** | PDO con `dbname=''` conectó a `postgres` usando usuario `postgres`; Laravel arma ese DSN en `PostgresConnector.php:71-74`. |
| 6 | Sesión y caché siguen la conexión por defecto | **Parcial** | Sesión DB y caché DB con conexión nula sí (`config/session.php:76`, `config/cache.php:42-46`). Redis/file/array no quedan aislados por DB. |
| 7 | `RefreshDatabase` se puede acotar a una conexión | **Verificada** | `connectionsToTransact()` lee `$connectionsToTransact` si existe (`RefreshDatabase.php:174-178`). |
| 8 | Glob de migraciones no es recursivo | **Verificada** | `Migrator::getMigrationFiles()` usa `$path.'/*_*.php'` (`Migrator.php:578-582`). |
| 9 | Filament expone `Panel::domain()` | **Verificada** | `HasRoutes.php:50-64`. Hay contradicción documental sobre si conviene usarlo. |
| 10 | Un middleware puede garantizarse antes de `StartSession` | **Verificada como mecanismo** | `prependToPriorityList()` existe y ya se usa (`bootstrap/app.php:29-32`). Falta el middleware real. |
| 11 | El generador de rutas de Media Library es reemplazable por config | **Parcial / término impreciso** | Reemplazables: `path_generator` y `url_generator`; no hay `route_generator` en config. |
| 12 | Las claves de caché del frontend hoy no llevan inquilino | **Verificada** | Cinco servicios arman claves `frontend:g...` sin slug. |
| 13 | La app confía todos los proxies e incluye `X-Forwarded-Host` | **Verificada** | `bootstrap/app.php:27`; `TrustProxies.php:22-27`. |
| 14 | Inquilino recién creado pesa ~18 MB y VPS tiene 55 GB libres | **Parcial** | Local: `demo_template` y `demo_db` pesan 18 MB. El valor 55 GB sólo está en docs (`docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:177`); no se verificó el VPS por límite de herramienta. |

---

## Hallazgos críticos

### C-1 — `.env.testing` todavía apunta a `inmo_test`, una base prohibida para este repo

**Qué está mal:** la regla operativa dice que tests deben usar `demo_test` y nunca `inmo_test`. `phpunit.xml` está bien, pero `.env.testing` no.

**Evidencia:**

- Regla del usuario y del repo: `docs/epicas/epica-demo-prompts.md:42-44` exige `demo_test` y prohíbe `inmo_test`.
- `phpunit.xml:29-30` fija `DB_DATABASE=demo_test`.
- `.env.testing:36-40` fija `DB_DATABASE=inmo_test`.
- `composer test` limpia config y corre `artisan test` (`composer.json:56-58`), pero un comando directo con `--env=testing` puede leer `.env.testing`.

**Escenario concreto de falla:** alguien prepara el entorno con `php artisan migrate:fresh --env=testing --force` o corre un test artesanal que carga `.env.testing` fuera de `phpunit.xml`. Laravel usa `DB_DATABASE=inmo_test` y ejecuta DDL contra la base de otro proyecto. No es teórico: el archivo contiene literalmente `inmo_test`.

**Corrección segura:** cambiar `.env.testing` a `demo_test` y agregar una guarda de arranque/console que aborte comandos destructivos si `DB_DATABASE` empieza con `inmo_`.

### C-2 — `fallido` tiene dos contratos incompatibles: “sin base” y “base a medias”

**Qué está mal:** el diseño conceptual define `fallido` como terminal y sin base que borrar, pero el diseño de detalle dice que fallas posteriores a `CREATE DATABASE` dejan una base a medias que la limpieza borra.

**Evidencia:**

- `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:90-96`: `fallido` = “el alta no terminó; no hay base que borrar”, terminal.
- `docs/epicas/epica-demo-lote-a-diseno.md:145-150`: `fallido` terminal.
- `docs/epicas/epica-demo-lotes-b-c-diseno.md:173-180`: si falla crear owner o activar, queda `fallido` **con base a medias** y “hay que mirar si la base existe”.

**Escenario concreto de falla:** `CREATE DATABASE demo_t_acme1234 TEMPLATE demo_template` termina bien; después falla crear el owner por falta de permisos o rol inexistente. El tenant queda `fallido`. Un implementador que siguió el RFC principal trata `fallido` como terminal y “sin base”; la base `demo_t_acme1234` queda viva, ocupando conexiones/disco y con datos semilla accesibles si alguien conoce el nombre.

**Corrección segura:** separar estados o contratos: `fallido_sin_base` / `fallido_con_base_pendiente_borrado`, o mantener un único `fallido` pero documentar que no es terminal para limpieza y que siempre se verifica existencia física de la base.

### C-3 — El borrado documenta dos órdenes distintos; uno permite reconexión antes del `DROP`

**Qué está mal:** el documento consolidado viejo dice “terminar sesiones y luego `DROP`”; RFC-09 dice revocar `CONNECT` antes; Lote F dice `CONNECTION LIMIT 0` antes. No son equivalentes.

**Evidencia:**

- `docs/epicas/epica-demo-multi-inquilino.md:287-295`: primero `pg_terminate_backend`, luego `DROP DATABASE`.
- `docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:36-46`: primero revocar `CONNECT`, luego terminar sesiones.
- `docs/epicas/epica-demo-lotes-d-e-f-diseno.md:166-179`: primero `ALTER DATABASE ... CONNECTION LIMIT 0`, luego terminar sesiones.

**Escenario concreto de falla:** un inquilino tiene una pestaña abierta. El job mata sesiones siguiendo el consolidado, pero antes del `DROP` el navegador reintenta y abre una sesión nueva. `DROP DATABASE` falla con “database is being accessed by other users”. El inquilino queda `expirado` pero no borrado, y el job entra en reintentos innecesarios.

**Corrección segura:** declarar un único contrato vigente. El más sólido de los docs actuales es Lote F: cerrar la puerta (`CONNECTION LIMIT 0` o revocar `CONNECT` con rol definido), terminar sesiones, borrar DB, borrar archivos, marcar `borrado`.

### C-4 — El entorno cerrado no cierra los archivos estáticos publicados

**Qué está mal:** RFC-14 cierra rutas públicas con middleware, pero RFC-08 asume que una ruta de media adivinada no se puede leer por “autorización existente”. Eso no cubre archivos servidos por `/storage` desde el webserver.

**Evidencia:**

- RFC-14 cierra rutas Laravel públicas por middleware: `docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:32-40`.
- RFC-08 afirma autorización existente sobre controladores de medios: `docs/rfcdemo/RFC-08-AISLAMIENTO-DE-ARCHIVOS.md:58-64`.
- El disco `public` sirve por URL `/storage`: `config/filesystems.php:41-49`.
- Media Library default es `public`: `config/media-library.php:32-37`.
- `Property` y `Project` no fuerzan disco privado en sus colecciones: `app/Models/Property.php:292-299`, `app/Models/Project.php:54-60`.
- Sí existen controladores privados para borradores frontend (`app/Http/Controllers/FrontendSectionMediaController.php:12-15`, `FrontendServiceMediaController.php:15-18`), lo que demuestra que el proyecto conoce la diferencia entre ruta Laravel y archivo estático.

**Escenario concreto de falla:** un usuario autenticado de tenant A copia la URL de una imagen publicada `/storage/tenants/a123.../1/foto.webp` desde el HTML y la manda a alguien. Esa persona, sin sesión, abre la URL directa. Nginx/Apache sirve el archivo desde `storage/app/public` sin pasar por `ResolveTenant`, `auth` ni el middleware de entorno cerrado. El sitio está cerrado, pero su media publicada no.

**Corrección segura:** decidir explícitamente una de dos políticas: (1) en demo cerrado, toda media de inquilino se sirve por controlador autorizado; o (2) aceptar por escrito que la media publicada es pública aunque el HTML esté cerrado. Hoy el diseño promete lo primero y el código base se comporta como lo segundo.

---

## Hallazgos medios

### M-1 — Falta contrato de ownership/grants entre `demo_app` y `demo_provisioner`

**Qué está mal:** el diseño separa roles —bien—, pero no define quién queda como owner de la base clonada ni qué grants permiten que `demo_app` use tablas sin poder crear o borrar bases.

**Evidencia:**

- Roles separados: `docs/deployment/DEMO-MULTI-INQUILINO.md:88-92`.
- El rol de peticiones no puede ejecutar `CREATE DATABASE` ni `DROP DATABASE`: `docs/rfcdemo/RFC-05-ALTA-DE-INQUILINO.md:47-49`.
- El DDL usa `maintenance`/aprovisionamiento: `docs/epicas/epica-demo-lotes-b-c-diseno.md:163-164`.
- El checklist de despliegue no incluye owner/grants/schema privileges: `docs/deployment/DEMO-MULTI-INQUILINO.md:123-131`.

**Escenario concreto de falla:** `demo_provisioner` ejecuta `CREATE DATABASE demo_t_x TEMPLATE demo_template`; la base queda operable para el rol creador, pero la app abre peticiones con `demo_app`. Al primer request del inquilino, `demo_app` intenta `select * from users` y falla por permisos insuficientes; si se resuelve dándole `CREATEDB` a `demo_app`, se rompe la separación de privilegios que protege el DDL.

### M-2 — Nombres de bases del diseño chocan con la regla operativa actual

**Qué está mal:** los documentos usan `demo_central`, `demo_template_vN` y `demo_t_{slug}`; la regla operativa actual del repo dice desarrollo `demo_db`, tests `demo_test`, plantilla `demo_template`.

**Evidencia:**

- Regla operativa: `docs/epicas/epica-demo-prompts.md:42`.
- Diseño conceptual: `docs/epicas/epica-demo-multi-inquilino.md:87-93` usa `demo_central`, `demo_template_vN`, `demo_t_{slug}`.
- Lote A: `docs/epicas/epica-demo-lote-a-diseno.md:42-46` fija central en `demo_central`.
- `.env` actual usa `demo_db`: `.env:36-40`.

**Escenario concreto de falla:** un implementador crea la conexión `central` contra `demo_central`, pero el entorno local real está en `demo_db`. El comando de invitación escribe tenants en `demo_central`; la app web sigue leyendo configuración/base desde `demo_db` o viceversa. El alta “funciona” en una base y el resolver busca en otra.

### M-3 — “Sin infraestructura de cola” en fase 1 contradice cron/worker requeridos para expiración y borrado

**Qué está mal:** RFC-13 dice que invitación no necesita infraestructura de cola, pero otros documentos mantienen trabajos de fondo y despliegue exige cron/worker.

**Evidencia:**

- `docs/rfcdemo/RFC-13-INVITACION.md:5-6`: “sin registro público ni infraestructura de cola”.
- `docs/rfcdemo/RFC-13-INVITACION.md:82-84`: RFC-03 se mantiene porque ya hay trabajos en segundo plano.
- `docs/deployment/DEMO-MULTI-INQUILINO.md:16`: proceso largo de cola + cron es requisito.
- `docs/deployment/DEMO-MULTI-INQUILINO.md:130`: checklist exige cola corriendo y cron activo.

**Escenario concreto de falla:** se implementa fase 1 como comando síncrono y no se levanta worker/cron en el VPS porque “no hay cola”. Los inquilinos vencen por fecha, pero nadie ejecuta la tarea que marca `expirado` ni el job que borra bases/archivos. El demo acumula inquilinos eternos.

### M-4 — La invitación como límite depende de disciplina humana si no se mantiene el tope duro

**Qué está mal:** el diseño acierta al sacar límites por origen en fase 1, pero no puede sacar el tope duro de activos. RFC-10 dice que existe siempre; RFC-13 justifica recortar límites por “invita una persona”.

**Evidencia:**

- Fase 1: invitación es el límite (`docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:19-23`).
- RFC-13 descarta límites de abuso y asume que dos invitaciones simultáneas no ocurren (`docs/rfcdemo/RFC-13-INVITACION.md:23-29`).
- RFC-10 define tope duro siempre activo (`docs/rfcdemo/RFC-10-LIMITES-DE-ABUSO-Y-PLAZO-DE-VIDA.md:46-56`).

**Escenario concreto de falla:** el operador importa 80 correos con un shell loop para invitar rápido. No hay ataque público, pero sí una operación humana real. Sin tope duro de activos, el comando crea más bases de las que el servidor tolera o consume conexiones hasta afectar vecinos.

### M-5 — “Reimprimir” contraseña contradice “se imprime una sola vez” y el hashing normal

**Qué está mal:** Lote C dice que si falla imprimir acceso, se puede reimprimir; el mismo documento y RFC-13 dicen que se imprime una sola vez.

**Evidencia:**

- `docs/epicas/epica-demo-lotes-b-c-diseno.md:177`: si falla imprimir acceso, “se puede reimprimir”.
- `docs/epicas/epica-demo-lotes-b-c-diseno.md:198`: imprime contraseña “una sola vez”.
- `docs/rfcdemo/RFC-13-INVITACION.md:105-107`: imprime contraseña una sola vez.

**Escenario concreto de falla:** el usuario owner ya fue creado y la contraseña se guardó hasheada. Si el proceso pierde stdout o la terminal se cierra, reimprimir la contraseña original sólo es posible si se guardó en claro en algún lado. Si no se guardó, “reimprimir” no existe; la operación real es resetear/regenerar.

### M-6 — Contradicción sobre declarar dominio en Filament

**Qué está mal:** el consolidado propone `->domain()` en el panel; RFC-06 dice explícitamente que no hace falta y que agrega superficie de error.

**Evidencia:**

- Consolidado: `docs/epicas/epica-demo-multi-inquilino.md:125-128` propone `Route::domain()` y `->domain()`.
- RFC-06: `docs/rfcdemo/RFC-06-RESOLUCION-DE-INQUILINO-POR-SUBDOMINIO.md:38-43` dice no declararlo en el panel.
- Filament sí soporta `domain()`: `vendor/filament/filament/src/Panel/Concerns/HasRoutes.php:50-64`.

**Escenario concreto de falla:** alguien sigue el consolidado y agrega `->domain('{tenant}.demo...')` al panel. Filament empieza a generar URLs que necesitan `{tenant}` como parámetro/default. El resolver ya usaba `Host`; se duplicó una fuente de verdad y aparecen URLs incompletas o helpers que requieren pasar tenant manualmente.

### M-7 — El término “route generator” de Media Library puede llevar a tocar la pieza equivocada

**Qué está mal:** el diseño habla de generador de rutas; en Spatie la pieza que evita colisión de disco es `path_generator`, no `url_generator` ni una ruta Laravel.

**Evidencia:**

- `config/media-library.php:144`: `path_generator`.
- `config/media-library.php:164`: `url_generator`.
- `vendor/spatie/laravel-medialibrary/src/Support/UrlGenerator/UrlGeneratorFactory.php:21-24` compone URL usando un path generator.

**Escenario concreto de falla:** se reemplaza sólo `url_generator` para devolver URLs con tenant, pero el archivo físico sigue guardándose bajo el path default por `media_id`. Tenant A y B siguen escribiendo en `1/`; las URLs se ven distintas, pero el disco colisiona igual.

---

## Hallazgos menores

### Mn-1 — RFC-06 todavía dice que el host central sirve registro en fase 1

**Evidencia:**

- `docs/rfcdemo/RFC-06-RESOLUCION-DE-INQUILINO-POR-SUBDOMINIO.md:77-80`: host central sirve registro y padrón.
- `docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:71-75`: en fase 1 no hay registro público y el host central no tiene página anónima.
- `docs/rfcdemo/README.md:71-76`: RFC-11 sale de fase 1.

**Escenario concreto de falla:** se implementa Lote D desde RFC-06 y se dejan rutas anónimas de registro en el host central durante fase 1, contradiciendo el entorno cerrado.

### Mn-2 — La medición de tablas está desactualizada o fue tomada sobre otra plantilla

**Evidencia:**

- `docs/epicas/epica-demo-multi-inquilino.md:46-48` afirma 50 tablas.
- Consulta local contra `demo_template`: `tables=48`.

**Escenario concreto de falla:** el test de verificación espera 50 tablas y falla aunque la plantilla esté funcional, o peor: alguien usa el número como señal de completitud y no verifica lo que importa —PostGIS, páginas, permisos, usuarios vacíos.

---

## Sobreingeniería detectada

- **No es sobreingeniería** usar base por inquilino. El código actual tiene 28 modelos sin `$connection`, singletons CMS por `key` y caché sin tenant; base por inquilino elimina una clase entera de errores por scopes olvidados.
- **No es sobreingeniería** prefijar claves de caché aunque hoy la tabla cache viva en la base del inquilino. Es una defensa barata contra Redis futuro.
- **Riesgo de sobreingeniería:** usar `Panel::domain()` cuando RFC-06 ya resolvió por `Host`. Ahí se duplica frontera sin beneficio claro.
- **Riesgo de ceremonia falsa:** hablar de “barrido de cerrojos huérfanos” no aplica si el proceso muere; Postgres suelta el lock de sesión. El diseño actual lo entiende bien.

## Riesgos de implementación

1. Ejecutar comandos directos con `--env=testing` puede tocar `inmo_test` hasta corregir `.env.testing`.
2. Si no se fija un contrato único de borrado, distintos lotes pueden implementar órdenes incompatibles.
3. Si no se diseña ownership/grants, el primer request de un inquilino puede fallar por permisos aunque el alta haya creado la base.
4. Si media publicada sigue en `public`, el entorno “cerrado” sólo cierra HTML/controladores, no bytes estáticos.
5. Si el tope duro se interpreta como fase 2, una operación humana masiva puede llenar la instancia igual que un registro público.

## Riesgos de seguridad

1. `trustProxies(at: '*')` + `X-Forwarded-Host` está correctamente detectado por el diseño como bloqueo de despliegue antes de invitar a nadie.
2. El DDL con nombres de base interpolados está bien tratado: slug generado, formato cerrado, prefijo fijo y validación pegada al uso.
3. Media pública directa es la mayor fuga restante frente al objetivo de entorno cerrado.
4. Separar `demo_app` y `demo_provisioner` es correcto, pero incompleto sin grants explícitos.
5. La regla de no tocar `inmo_*` necesita protección técnica, no sólo instrucción humana.

## Bien resuelto

- La tesis principal —aislamiento por base, no por rol/scope— está bien fundamentada en `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:107-110`.
- La resolución antes de sesión está bien identificada y debe probarse por orden, no sólo por comportamiento (`docs/rfcdemo/RFC-06-RESOLUCION-DE-INQUILINO-POR-SUBDOMINIO.md:57-75`).
- Plantilla versionada en vez de migración in-place evita la carrera real de `CREATE DATABASE ... TEMPLATE` (`docs/rfcdemo/RFC-04-PLANTILLA-VERSIONADA.md:24-35`).
- El diseño detecta bien que `trustProxies(at: '*')` convierte `Host` en frontera blanda (`docs/epicas/epica-demo-lotes-d-e-f-diseno.md:26-49`).
- Los servicios frontend ya tienen generación durable y shape-version; es buena base para prefijar por tenant sin rehacer invalidación.
- Las colecciones sensibles de contratos ya usan disco privado en el código actual (`app/Models/ContratoIntermediacion.php:191-201`).

## Preguntas pendientes

Estas no son hallazgos porque falta evidencia o decisión explícita:

1. ¿El VPS real sigue teniendo 55 GB libres hoy, o esa medición quedó vieja?
2. ¿El owner final de cada base clonada será `demo_app`, `demo_provisioner` u otro rol?
3. ¿La media publicada del sitio demo debe ser privada por sesión o se acepta como pública si alguien conoce la URL?
4. ¿Cuál es el número exacto de reintentos/backoff antes de mostrar un borrado fallido al operador?
5. ¿`demo_central` es un nombre conceptual o reemplaza a `demo_db` en este repo?

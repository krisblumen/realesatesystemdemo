# Landra

Plataforma de gestión inmobiliaria: panel de administración, sitio público
administrable y captación de clientes, con una capa multi-inquilino que permite
mostrarla como demo cerrado.

Cada persona invitada recibe **su propio sistema** —su base de datos, su
contenido, sus archivos— por una dirección propia, durante un plazo acotado.

---

## Qué resuelve

| Módulo | Qué hace |
|---|---|
| Inmuebles | Alta, publicación, galería, características, ficha pública |
| Zonas | Áreas comerciales con polígonos reales sobre PostGIS |
| Clientes y agentes | Padrón de propietarios, asignación por agente y por zona |
| Leads | Captación desde el sitio y asignación automática |
| Contratos | Intermediación con firma electrónica y sello digital |
| CMS del frontend | Seis páginas administrables, con borrador y publicación |
| Multi-inquilino | Aislamiento por base de datos, invitación y expiración |

---

## La decisión que define la arquitectura

El aislamiento entre inquilinos es **por base de datos**, no por una columna
`tenant_id` ni por un rol.

El motivo es concreto: un usuario de demo necesita permisos de `owner` para
poder probar el sistema completo, y el propio código muestra que ese rol lo ve
todo.

```php
// app/Models/Property.php — scopeVisibleTo
if ($user->hasAnyRole(['owner', 'admin'])) {
    return $query;   // ve todo
}
```

Los roles contestan *qué puede hacer* alguien; el aislamiento contesta *qué
datos existen para él*. Son ejes perpendiculares, y confundirlos es entregar la
base entera.

Con una base por inquilino, una consulta cruda mal escrita o un scope olvidado
no pueden alcanzar a otro inquilino: no están en la misma base.

Eso deja tres superficies que la base de datos **no** cubre, y que el diseño
cierra una por una:

- **Caché** — las claves se prefijan con el inquilino.
- **Archivos** — la librería de medios numera desde 1 en cada base, así que las
  rutas llevan el inquilino adelante.
- **Sesión** — el inquilino se resuelve del `Host` antes de que arranque la
  sesión, porque la sesión vive en la base que hay que elegir.

---

## Stack

| Componente | Tecnología |
|---|---|
| Backend | Laravel 13 · PHP 8.3 |
| Panel | Filament 3 |
| Interactividad | Livewire 3 |
| Frontend | Blade · Tailwind CSS 4 · Vite 8 |
| Base de datos | PostgreSQL 16 + PostGIS |
| Geometría | `clickbar/laravel-magellan` |
| Roles y medios | Spatie Permission · Spatie Media Library |

---

## Puesta en marcha local

Requiere PHP 8.3+, Composer 2, Node 22+ y **PostgreSQL con PostGIS habilitado**.
Las funciones espaciales no corren sobre SQLite.

```bash
composer setup
```

Bases que usa el proyecto:

| Base | Para qué |
|---|---|
| `demo_db` | Desarrollo |
| `demo_test` | Suite de pruebas (`phpunit.xml` la fuerza) |
| `demo_central` | Padrón de inquilinos, cola y sesiones del host central |
| `demo_template` | Plantilla desde la que nace cada inquilino |
| `demo_t_{slug}` | Un inquilino |

```bash
composer dev     # servidores de desarrollo
composer test    # suite completa
./vendor/bin/pint
```

---

## Desplegar

Tres cosas **no viajan en git** y hay que generarlas en el servidor:

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci && npm run build
```

```bash
php artisan filament:assets
```

Las tres comparten el mismo modo de falla, y por eso cuestan de encontrar: **no
rompen nada al desplegar**. El sitio arranca, las páginas se dibujan, y sólo
falla cuando alguien intenta usarlo.

Los de Livewire **sí viajan en git**, a propósito: viven en `public/vendor/livewire/`
y se regeneran con `php artisan livewire:publish --assets` cada vez que Livewire
cambia de versión. Están publicados y no servidos por PHP porque, cuando los
sirve la aplicación, cuelgan de una URL terminada en `.js` que los servidores web
suelen atender por extensión sin llegar nunca a PHP — y entonces el script no
carga, Livewire no arranca, el formulario de acceso se envía de forma nativa a
una ruta que sólo acepta GET, y responde 405. La pantalla se ve perfecta hasta
que alguien intenta entrar. Un test fija que lo publicado coincida con lo
instalado, porque Livewire avisa del desajuste sólo por la consola del navegador.

Después, la base central y la plantilla:

```bash
php artisan migrate --database=central --path=database/migrations/central --force
```

```bash
php artisan demo:plantilla:construir demo_template_v1
```

El resto —roles de PostgreSQL, PostGIS en `template1`, DNS y certificado
comodín, proxy de confianza, cola y cron— está en
`docs/deployment/DEMO-MULTI-INQUILINO.md`, con el checklist previo al primer
inquilino.

---

## Operar el demo

```bash
php artisan demo:plantilla:construir demo_template_v2   # construye una plantilla nueva
php artisan demo:invitar persona@ejemplo.com --dias=15  # crea un inquilino e imprime su acceso
php artisan demo:padron                                 # qué inquilinos hay y qué les pasó
php artisan demo:expirar --slug=abcdefgh                # vencer hoy, sin esperar la fecha
php artisan demo:borrar                                 # borra bases y archivos de los vencidos
php artisan demo:abortar-borrado --slug=abcdefgh        # reabre un borrado que quedó a medias
```

El acceso sale por consola y no por correo, a propósito: un mensaje que cae en
spam es una persona que quería probar el producto y no pudo, con un inquilino
aprovisionado ocupando lugar.

---

## Entorno cerrado, y su límite

El sitio de un inquilino exige su sesión. Lo recorre completo —con el mismo
render y el mismo caché que vería un visitante— y nadie de afuera puede
navegarlo. La firma de contratos queda abierta a propósito: ahí el control es el
token del enlace, y es una de las funciones que el demo necesita mostrar.

**El cierre alcanza al HTML, no a los bytes.** Las imágenes publicadas las sirve
el servidor web sin pasar por la aplicación: quien tenga la URL las abre. Es un
límite conocido y aceptado, y el comando de invitación lo advierte a quien va a
subir archivos.

---

## Documentación

```
docs/
├── rfcdemo/      RFC de la épica multi-inquilino (índice en su README)
├── epicas/       Diseño técnico consolidado y diseño de detalle por lote
├── audits/       Auditorías de diseño y de implementación
├── deployment/   Requisitos de infraestructura y checklist previo al despliegue
└── rfc/          RFC heredados de la plataforma base
```

Antes de desplegar, `docs/deployment/DEMO-MULTI-INQUILINO.md`. De todo ese
checklist, dos puntos no dan síntoma si salen mal:

- El rol de la aplicación **no puede ser superusuario**, o los topes de conexión
  que protegen a los vecinos de la instancia no protegen nada.
- El proxy de confianza debe estar acotado a su dirección: con `'*'`, el `Host`
  lo elige el cliente y la frontera entre inquilinos deja de ser una frontera.

---

## Estado

Fase 1 —por invitación y cerrada— implementada y auditada. Fase 2 —registro
público y límites de abuso— diseñada y en pausa.

Pendiente: la copia todavía arrastra la identidad visual y parte de la
documentación de la plataforma de la que se derivó. Des-marcarla es trabajo
previo a mostrar esto fuera del equipo.

---

## Convenciones

Ramas `main` / `develop`, con `feature/` y `fix/` por trabajo.
Commits convencionales (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`,
`chore:`). PHP en PSR-12 con Pint.

---

Proyecto privado. Todos los derechos reservados.

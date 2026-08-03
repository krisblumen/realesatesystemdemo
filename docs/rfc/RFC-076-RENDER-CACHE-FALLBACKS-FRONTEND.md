# RFC-076 Render Público, Caché y Fallbacks del Frontend

> **⚠️ Enmienda normativa (P3 + correcciones posteriores a P3R, 2026-07-20).** Fuente única: **§16** de la épica; donde difiera, **prevalece §16**. Overrides: quinta tabla `frontend_cache_generation`, migración con fila `id=1,generation=1`, bumps por `UPDATE ... + 1 RETURNING`, claves completas `frontend:g{N}:*` y servicios separados por `frontend:g{N}:services:{location}`. El bump global post-commit es el único mecanismo de invalidación; no hay clears/`forget` dirigidos. TTL corto es solo red de seguridad. Incluye invalidación por `ServiceType`/`Media` y tests separados de inicialización, dos bumps concurrentes, aislamiento por location y refill con `CACHE_STORE=database`. El kernel entra en Lote A; RFC-076 integra/endurece.

## Objetivo

Centralizar la entrega de configuración, tema, navegación, servicios y contenido editable al frontend público, con caché segura, invalidación clara y fallbacks obligatorios para que el sitio nunca quede roto por contenido incompleto.

Este RFC conecta RFC-071 a RFC-075 en una capa de lectura estable para Blade. Su valor es evitar lógica duplicada en vistas y garantizar performance, consistencia y degradación segura.

## Épica

Épica 12 — Administrador de Contenidos del Frontend

## Responsable

Por asignar

## Estado

🟡 Correcciones documentales aplicadas; reauditoría independiente pendiente. **Implementación bloqueada** hasta gate `APROBADO`.

---

## Contexto

Los RFCs previos introducen varias fuentes configurables:

- RFC-071: perfil público, logos, contacto, SEO defaults y CTAs base.
- RFC-072: tema visual con variables CSS.
- RFC-073: navegación, footer y CTAs globales.
- RFC-074: servicios ofrecidos y disponibilidad.
- RFC-075: contenido editable de páginas institucionales.

Sin una capa central, cada vista Blade podría terminar consultando modelos directamente, duplicando fallbacks y generando inconsistencias. Este RFC define el contrato único de render público.

---

## Alcance

### Incluye

- Servicio central para leer configuración pública.
- View composer o mecanismo equivalente para compartir datos globales al layout.
- Servicio para contenido por página.
- Servicio para servicios ofrecidos.
- Fallbacks obligatorios por área.
- Cache de configuración/contenido público.
- Invalidación de cache al guardar cambios en CMS.
- Manejo seguro de contenido faltante o inválido.
- Tests de cache, invalidación, fallbacks y render.

### No incluye

- Crear nuevos campos editoriales.
- Definir nuevos tipos de sección.
- Preview/publicación avanzada — ver RFC-077.
- CDN o edge cache.
- Optimización avanzada de imágenes.
- Multitenancy completo.

---

## Principio central

Las vistas Blade no deben conocer detalles internos de los modelos de CMS.

Deben recibir estructuras simples, ya normalizadas, por ejemplo:

- `$siteSettings`.
- `$siteTheme`.
- `$siteNavigation`.
- `$siteFooter`.
- `$pageContent`.
- `$frontendServices`.

La normalización, fallbacks y cache viven en servicios de frontend.

---

## Servicios propuestos

### `FrontendSettingsService`

Responsable de:

- Cargar perfil público activo.
- Resolver logos/favicons/OG image.
- Resolver contacto y WhatsApp.
- Aplicar fallbacks de RFC-071.
- Entregar datos globales al layout.

### `FrontendThemeService`

Responsable de:

- Cargar tokens visuales.
- Validar/normalizar valores antes de render.
- Entregar variables CSS seguras.
- Aplicar fallbacks de RFC-072.

### `FrontendNavigationService`

Responsable de:

- Cargar navegación/header/footer.
- Resolver rutas desde allowlist.
- Ordenar links.
- Excluir links inválidos.
- Aplicar fallbacks de RFC-073.

### `FrontendServicesService`

Responsable de:

- Cargar servicios vinculados a `ServiceType`.
- Filtrar por `ServiceType.active`.
- Filtrar `services(location)` únicamente por la allowlist canónica `home | servicios`; el formulario de leads no es una ubicación de render y consulta la elegibilidad fail-closed de RFC-074.
- Resolver imágenes y CTAs.
- Aplicar fallbacks controlados de RFC-074.

### `FrontendPageContentService`

Responsable de:

- Cargar página por key.
- Cargar secciones habilitadas.
- Validar payloads por tipo.
- Resolver media.
- Integrar servicios/proyectos/inmuebles cuando el tipo lo requiera.
- Aplicar fallbacks de RFC-075.

---

## Contrato de datos para Blade

Las vistas deben consumir arreglos/DTOs simples, no modelos Eloquent crudos.

Ejemplo conceptual:

```php
[
    'site' => [
        'name' => 'New Hauz',
        'tagline' => '...',
        'logo_light_url' => '...',
        'logo_dark_url' => '...',
        'contact' => [...],
        'seo' => [...],
    ],
    'theme' => [
        'primary' => '#091a5b',
        'on_primary' => '#ffffff',
        'accent' => '#f6a300',
        'on_accent' => '#111111',
        'background' => '#f7f7f7',
        'text' => '#111111',
        'heading_font' => 'Montserrat',
        'body_font' => 'Inter',
        'radius' => 'medium',
    ],
    'navigation' => [
        ['label' => 'Inicio', 'url' => '/', 'is_external' => false],
    ],
    'footer' => [
        'columns' => [
            ['title' => '...', 'links' => [
                ['label' => '...', 'url' => '...', 'enabled' => true],
            ]],
        ],
        'legal_text' => '...',
        'social' => [...],
    ],
]
```

Para páginas:

```php
[
    'page' => [
        'key' => 'home',
        'title' => 'Inicio',
        'meta_title' => '...',
        'meta_description' => '...',
    ],
    'sections' => [
        ['type' => 'hero', 'data' => [...]],
        ['type' => 'service_list', 'data' => [...]],
    ],
]
```

---

## Caché

### Tabla y keys normativas

`frontend_cache_generation` es la quinta tabla nueva. Tiene una sola fila (`id=1`, `generation bigint NOT NULL DEFAULT 1`, `CHECK(id=1)`) creada e inicializada por migración. Cada invalidación ejecuta una sola sentencia `UPDATE frontend_cache_generation SET generation = generation + 1 WHERE id = 1 RETURNING generation`; no usa `Cache::increment()` ni read-modify-write en PHP.

Las keys completas incluyen la generación actual: `frontend:g{N}:settings`, `frontend:g{N}:theme`, `frontend:g{N}:navigation`, `frontend:g{N}:services:{location}` y `frontend:g{N}:page:{key}`. Para servicios, `{location}` es obligatoriamente `home` o `servicios`; ambas listas nunca comparten key. Un refill viejo solo puede escribir en `g{N-1}` y nunca vuelve a leerse después del bump.

### TTL

Todas las entradas usan TTL corto documentado de `300 s`. El TTL limita basura/staleness residual, pero no invalida una publicación: la lectura cambia de namespace exclusivamente por bump de generación post-commit.

### Invalidación

Toda mutación confirmada que altere render en `FrontendSetting`, `FrontendService`, `ServiceType`, `FrontendPage`, `FrontendSection` o media relacionada ejecuta exactamente un bump global `afterCommit` mediante observer/acción explícita. Debe cubrir guardar, publicar y promover media; un rollback no cambia generación.

El bump es la **única invalidación**. Está prohibido combinarlo con `Cache::forget`, `Cache::delete`, `flush`, limpieza de sufijos concretos o listas de keys “afectadas”: esas operaciones reintroducen dos protocolos y no aportan corrección porque todo el namespace previo queda inaccesible al cambiar `N`. La expiración por TTL se ocupa únicamente de recolectar las entradas antiguas.

Tests nominales obligatorios:

1. `FrontendCacheGenerationTest::test_migration_initializes_generation_to_one`.
2. `FrontendCacheGenerationTest::test_two_concurrent_bumps_are_both_persisted` con dos conexiones PostgreSQL: desde `1` termina en `3`.
3. `FrontendCacheInvalidationTest::test_old_refill_is_not_read_after_generation_bump` con `CACHE_STORE=database` y dos conexiones.
4. `FrontendServicesCacheTest::test_home_and_services_locations_use_distinct_generation_keys` prueba `frontend:g{N}:services:home` y `frontend:g{N}:services:servicios` sin colisión.

---

## Fallbacks obligatorios

Los fallbacks no son opcionales. Son parte del contrato de producción.

| Área | Fallback mínimo |
| --- | --- |
| Perfil | Datos actuales de New Hauz. |
| Logos | Assets actuales en `public/images/brand`. |
| Favicon | Favicon actual. |
| Tema | Tokens actuales de `resources/css/app.css`. |
| Navegación | Links públicos actuales válidos. |
| Footer | Datos actuales sin links `#` inválidos. |
| Servicios | Servicios actuales reconciliados con RFC-074. |
| Páginas | Contenido equivalente al Blade actual. |
| SEO | Título/descripción default de RFC-071. |

Si un campo existe pero es inválido, el servicio debe preferir fallback antes que romper render.

---

## Manejo de errores

El frontend público no debe lanzar errores visibles por contenido faltante.

Reglas:

- Si falta una sección, omitirla o usar fallback.
- Si falta una imagen, usar fallback o no renderizar la imagen.
- Si CTA es inválido, ocultar CTA o usar CTA global válido.
- Si payload JSON es inválido, ignorar sección y registrar warning.
- Si cache falla, leer directo de BD/fallback.
- Nunca exponer stack traces ni detalles internos al visitante.

---

## Observabilidad mínima

Registrar warnings no invasivos cuando:

- Falta configuración esperada.
- Payload de sección es inválido.
- CTA fue descartado por URL inválida.
- Servicio activo no tiene contenido frontend.
- Media configurada no existe.

El log debe ayudar a depurar sin llenar producción de ruido.

---

## Integración con Blade

### Layout público

`resources/views/components/layouts/public.blade.php` debe consumir datos globales centralizados:

- Site profile.
- Theme variables.
- Header navigation.
- Header CTA.
- Footer.
- SEO defaults.

### Páginas públicas

Cada página debe pedir su contenido por key:

- `home`.
- `nosotros`.
- `servicios`.
- `inversionistas`.
- `contacto`.

Las vistas siguen controlando markup; el servicio controla datos.

---

## Performance

- Evitar N+1 al resolver media, secciones y servicios.
- Cachear estructuras ya normalizadas.
- No consultar configuración global repetidamente por sección.
- No recalcular contraste/theme en cada vista si ya está normalizado.
- Mantener payloads pequeños.

---

## Seguridad

- La capa de render debe recibir datos ya validados, pero igualmente escapar salida en Blade.
- No renderizar HTML libre desde BD.
- No renderizar CSS libre desde BD.
- No confiar en payload JSON sin normalización.
- No exponer rutas internas ni errores.
- URLs deben provenir de resolutores validados de RFC-073.

---

## Archivos esperados

```text
app/
  Models/
    FrontendCacheGeneration.php
  Services/
    Frontend/
      FrontendSettingsService.php
      FrontendThemeService.php
      FrontendNavigationService.php
      FrontendServicesService.php
      FrontendPageContentService.php
      FrontendFallbacks.php
      FrontendCache.php
  Providers/
    AppServiceProvider.php                       (view composers si aplica)
  Observers/
    FrontendSettingObserver.php                  (opcional)
    FrontendServiceObserver.php                  (opcional)
    FrontendPageObserver.php                     (opcional)
    FrontendSectionObserver.php                  (opcional)

database/
  migrations/
    xxxx_create_frontend_cache_generation_table.php  (quinta tabla; inicializa id=1)

resources/
  views/
    components/layouts/public.blade.php
    welcome.blade.php
    site/nosotros.blade.php
    site/servicios.blade.php
    site/inversionistas.blade.php
    leads/create.blade.php

tests/
  Feature/Frontend/
    FrontendRenderFallbackTest.php
    FrontendCacheInvalidationTest.php
    FrontendCacheGenerationTest.php
    FrontendServicesCacheTest.php                 (keys por location)
    FrontendGlobalViewDataTest.php
    FrontendInvalidContentSafetyTest.php
```

---

## Reglas técnicas

- No consultar modelos CMS directamente desde múltiples Blade.
- No duplicar fallback en cada vista.
- No cachear HTML final en este RFC; cachear datos normalizados.
- No depender de TTL como mecanismo principal de actualización.
- Invalidar únicamente mediante bump global post-commit; no borrar keys dirigidas.
- Mantener servicios pequeños y testeables.
- Fallbacks deben vivir en un lugar centralizado.

---

## Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| Fallback duplicado | Inconsistencias difíciles de depurar. | `FrontendFallbacks` central. |
| Cache stale | Owner guarda y no ve cambios. | Bump global post-commit; TTL=300 s solo como red de seguridad. |
| Consultas excesivas | Sitio lento. | Cache de datos normalizados. |
| Blade acoplado a modelos | Mantenimiento difícil. | DTO/arrays simples. |
| Payload inválido rompe página | Error público. | Normalización + omisión/fallback. |

---

## Definition of Done

- Existe una capa central para datos globales del frontend.
- Existe servicio por página o equivalente para contenido institucional.
- Layout público consume configuración desde servicios centralizados.
- Páginas públicas consumen contenido por key desde servicios centralizados.
- Cache se invalida exclusivamente mediante bump global post-commit; no hay clear/`forget` dirigido.
- La quinta tabla de generación se crea con valor `1` y dos bumps concurrentes no pierden incrementos.
- Servicios usa keys distintas `frontend:g{N}:services:home` y `frontend:g{N}:services:servicios`.
- Si no hay configuración/contenido, el sitio renderiza con fallbacks.
- Contenido inválido no rompe página pública.
- Tests cubren fallbacks, inicialización/concurrencia de generación, keys de servicios por location, carrera de refill e invalidación/render seguro.
- `php artisan test` verde sobre PostgreSQL real.
- Pint limpio.
- `npm run build` verde.

---

## Dependencias

- RFC-071 — Perfil público y configuración base.
- RFC-072 — Tema visual configurable.
- RFC-073 — Navegación, footer y CTAs globales.
- RFC-074 — Servicios ofrecidos y disponibilidad.
- RFC-075 — Contenido editable de páginas institucionales.
- Épica 12 documento general: `docs/epicas/epica-12-administrador-contenidos-frontend.md`.

---

## Próximo RFC

RFC-077 — Preview, publicación y QA visual: previsualización segura, flujo de publicación si aplica, checklist visual y pruebas de regresión pública.

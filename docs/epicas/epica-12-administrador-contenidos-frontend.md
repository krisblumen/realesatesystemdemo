# Épica 12 — Administrador de Contenidos del Frontend

**Proyecto:** New Hauz — Plataforma Inmobiliaria (monolito Laravel)
**Épica:** Épica 12 — Administrador de Contenidos del Frontend
**Rama base:** `develop` · **Rama de trabajo:** `feature/epica-12-content-manager`
**Fecha:** 2026-07-20
**Estado:** 🟢 **GATE DE DISEÑO APROBADO** (reauditoría independiente sobre `44f8ec9`, 2026-07-21): **17 de 17 hallazgos resueltos**. **P4-A habilitado — la implementación queda DESBLOQUEADA.** Historia de correcciones en §18.2–§18.17 (§18.6–§18.12 marcadas como históricas: el borrado físico de media quedó fuera de v1 por §18.13).
**Acceso CMS:** exclusivo para usuarios con rol `owner`

---

## 1. Contexto

El frontend público de New Hauz actualmente está construido con vistas Blade, Tailwind v4 y datos mixtos: algunas secciones ya consumen modelos reales (`Property`, `Project`, `ServiceType` para leads), pero gran parte del contenido institucional, marca, navegación, datos de contacto, servicios comerciales, imágenes y llamadas a la acción sigue hardcodeada en vistas.

La Épica 12 crea un área del CMS para que el `owner` pueda personalizar el frontend público sin intervención de desarrollo ni despliegues por cada cambio editorial.

El punto más sensible es la sección de **Servicios**: no todas las inmobiliarias ofrecen lo mismo. Por lo tanto, esta épica no debe limitarse a “editar textos”; debe permitir **habilitar o deshabilitar tipos de servicio** y garantizar que esa disponibilidad afecte de forma coherente al frontend, CTAs y captura de leads.

---

## 2. Evidencia verificada en el frontend actual

| Área | Estado actual verificado | Implicación para Épica 12 |
| --- | --- | --- |
| Layout público | `resources/views/components/layouts/public.blade.php` contiene logo, navegación, metadatos, footer, contacto y WhatsApp hardcodeados. | Debe existir configuración editable de marca, SEO, contacto, navegación básica y CTAs. |
| Tema visual | `resources/css/app.css` define tokens Tailwind v4 de colores, tipografías, radios y sombras. | La personalización debe exponer variables seguras, sin reconstruir Tailwind por cada cambio. |
| Home | `resources/views/welcome.blade.php` mezcla datos dinámicos de inmuebles/proyectos con hero, servicios, inversión, partners y CTAs hardcodeados. | Se requieren bloques editables estructurados, no HTML libre. |
| Servicios | `resources/views/site/servicios.blade.php` declara 4 servicios hardcodeados: Arquitectura, Construcción, Comercialización e Inversión inmobiliaria. | La sección debe salir de un catálogo administrable y ordenable. |
| Leads | `resources/views/livewire/leads/lead-capture-form.blade.php` carga `ServiceType::where('active', true)` y valida contra servicios activos. | Ya existe una base operativa para disponibilidad de servicios; no debe duplicarse sin relación. |
| Catálogo operativo | `app/Models/ServiceType.php` y `app/Filament/Resources/ServiceTypeResource.php` existen; `ServiceTypeSeeder` crea `comercializacion`, `arquitectura`, `construccion`. | Hay desalineación: el frontend muestra “Inversión”, pero el catálogo operativo no la siembra. |
| Páginas institucionales | `nosotros`, `inversionistas`, `contacto` contienen textos, imágenes, estadísticas, equipo y CTAs fijos. | Deben migrarse gradualmente a contenido editable con defaults seguros. |

---

## 3. Problema a resolver

El owner no puede personalizar el sitio público sin tocar código. Esto impide adaptar la marca, servicios, mensajes comerciales y datos de contacto a cada inmobiliaria.

Además, el sistema ya tiene un concepto operativo de servicios activos para leads, pero el frontend comercial mantiene una lista independiente hardcodeada. Esa separación puede causar inconsistencias: mostrar un servicio que no se puede solicitar, o permitir capturar leads de servicios que la inmobiliaria no ofrece.

---

## 4. Objetivos

- Crear un área owner-only en Filament para administrar contenido del frontend.
- Permitir personalizar identidad visual: logotipo, favicon, imagen OG, colores principales y tipografías permitidas.
- Permitir editar datos públicos de la inmobiliaria: nombre comercial, tagline, descripción, teléfono, WhatsApp, correo, dirección y redes sociales.
- Permitir administrar CTAs principales del sitio.
- Permitir administrar servicios como capacidades reales de la inmobiliaria: contenido, orden, visibilidad y estado activo/inactivo.
- Garantizar que un servicio deshabilitado no aparezca como oferta pública ni pueda seleccionarse en formularios de lead.
- Mantener defaults equivalentes al frontend actual para evitar regresiones visuales al desplegar.
- Proteger el área completa para `owner`; ningún `admin`, `agente`, `arquitectura` o `proyectos` debe acceder.

---

## 5. Fuera de alcance inicial

- No se implementa un page builder visual libre.
- No se permite HTML arbitrario editable por usuarios.
- No se crea multitenancy completo ni sucursales/marcas múltiples en fase 1.
- No se modifican los modelos principales de `Property`, `Project`, `User`, `Media` o `Zone` salvo consumo aditivo.
- No se reemplaza el catálogo de inmuebles/proyectos existente.
- No se cambia el diseño visual base del frontend; se parametriza sobre el diseño actual.
- No se habilita acceso a `admin`; esta épica es exclusiva de `owner`.

---

## 6. Reglas de oro

1. **Owner-only real:** la autorización debe aplicarse en policy/gate, rutas y navegación; no basta ocultar menú.
2. **Servicios como contrato funcional:** activar/desactivar un servicio debe afectar contenido público, CTAs y validación de formularios.
3. **Una sola fuente de verdad para servicios:** evitar una lista comercial desconectada de `ServiceType`.
4. **Contenido estructurado:** usar campos y bloques tipados, no HTML libre.
5. **Defaults seguros:** si falta contenido editable, el frontend debe degradar a valores actuales o placeholders controlados.
6. **Sin rebuild por edición:** colores y fuentes deben aplicarse mediante variables/allowlists, no recompilando assets.
7. **Accesibilidad y SEO:** cada imagen configurable requiere alt text; colores deben mantener contraste mínimo.

---

## 7. Alcance funcional propuesto

### 7.1 Identidad de marca

El owner podrá administrar:

- Nombre comercial.
- Tagline / propuesta de valor.
- Logotipo para fondo claro.
- Logotipo para fondo oscuro.
- Favicon.
- Imagen por defecto para Open Graph.
- Texto legal corto para footer.

### 7.2 Tema visual

El owner podrá configurar un set acotado de tokens:

- Color primario.
- Color secundario/acento.
- Color de fondo principal.
- Color de texto principal.
- Tipografía de encabezados desde una allowlist.
- Tipografía de cuerpo desde una allowlist.
- Radio visual base opcional.

La implementación debe validar formato, contraste y valores permitidos.

### 7.3 Contacto y redes

El owner podrá administrar:

- Teléfono principal.
- WhatsApp principal.
- Correo público.
- Dirección pública.
- Horarios de atención.
- Redes sociales.
- CTA global de contacto.

Estos datos deben alimentar header, footer, página de contacto, CTAs y links `wa.me`.

### 7.4 Navegación pública

El owner podrá controlar:

- Etiquetas visibles de navegación.
- Orden básico de navegación.
- Activar/desactivar links institucionales no críticos.
- CTA principal del header.

Las rutas públicas reales siguen definidas por Laravel; el CMS no debe crear rutas arbitrarias en v1.

### 7.5 Servicios ofrecidos

El owner podrá administrar para cada servicio:

- Activo/inactivo.
- Título comercial.
- Descripción corta.
- Descripción larga.
- Bullets o beneficios.
- Imagen principal.
- Ícono o estilo visual.
- Orden.
- Mostrar en home.
- Mostrar en página de servicios.
- Permitir captura de lead.

La decisión técnica preferida es reutilizar `ServiceType` como base operativa y agregar una capa de contenido frontend vinculada al código del servicio. No se debe crear un catálogo paralelo sin relación, porque eso volvería a generar drift.

### 7.6 Contenido de páginas públicas

Se deben considerar como editables graduales:

- Home: hero/slides, servicios, inmuebles destacados, oportunidades, proyectos destacados, bloque de inversionistas, partners y CTA final; el buscador permanece kernel-only.
- Nosotros: hero, estadísticas, historia, valores, equipo (incluido spotlight A-74) y CTA final.
- Servicios: hero, lista/contenido por servicio y CTA final.
- Inversionistas: hero, recorrido editorial de tres paneles, alcance del servicio, audiencia/resultados y CTA final; no existe una región `metrics` independiente.
- Contacto: hero y texto introductorio; el formulario y canales operativos permanecen en kernel, alimentados por `LeadCaptureForm`/`FrontendSetting`.

El contenido debe modelarse como secciones estructuradas por página, con límites claros para evitar un page builder prematuro.

---

## 8. Modelo conceptual inicial

> Los nombres finales se definirán en cada RFC. Este modelo orienta la épica general.

- `SiteProfile` o `FrontendSetting`: configuración singleton del sitio público.
- `FrontendTheme`: tokens visuales validados, posiblemente embebidos como JSON controlado en el perfil.
- `FrontendPage`: página pública editable (`home`, `nosotros`, `servicios`, `inversionistas`, `contacto`).
- `FrontendSection`: bloques estructurados por página.
- `FrontendService`: contenido comercial vinculado a `service_types.code`.
- Media Library: logos, favicons, OG image, imágenes de secciones y servicios.

---

## 9. Autorización

La nueva área del CMS debe ser exclusiva para `owner`.

Recomendación:

- Crear permiso `frontend.manage` asignado únicamente a `owner`.
- Las policies/gates deben exigir rol `owner` o permiso owner-only, según el patrón que se cierre en el RFC correspondiente.
- Validar acceso real con pruebas HTTP/Filament:
  - `owner` accede.
  - `admin` recibe 403.
  - `agente`, `arquitectura`, `proyectos` reciben 403.

---

## 10. Riesgos principales

| Riesgo | Impacto | Mitigación esperada |
| --- | --- | --- |
| Drift entre servicios públicos y `ServiceType` | Leads inconsistentes y mala experiencia comercial. | Vincular contenido frontend con `service_types.code`. |
| Colores configurables rompen contraste | Sitio ilegible o inaccesible. | Validaciones de contraste y presets seguros. |
| HTML libre en contenido | XSS o ruptura visual. | Campos estructurados + Markdown/HTML sanitizado solo si se justifica. |
| Cambios sin preview | Owner publica errores visibles en producción. | Preview o estado borrador/publicado en RFC posterior. |
| Cache stale | El owner guarda cambios pero el sitio no actualiza. | Invalidación explícita al guardar. |
| Deshabilitar servicios críticos | Formularios o CTAs apuntan a servicios apagados. | Reglas de fallback y validación cruzada. |
| Fuentes arbitrarias externas | Performance, privacidad y CSP. | Allowlist local/remota controlada. |

---

## 11. RFCs de la Épica 12

| RFC | Estado | Archivo | Propósito |
| --- | --- | --- | --- |
| RFC-071 — Perfil público y configuración base | ✅ Documentado | `docs/rfc/RFC-071-PERFIL-PUBLICO-FRONTEND.md` | Base owner-only: perfil del sitio, logos, favicon, OG image, contacto, SEO defaults y permisos. |
| RFC-072 — Tema visual configurable | ✅ Documentado | `docs/rfc/RFC-072-TEMA-VISUAL-FRONTEND.md` | Colores, tipografías, validación de contraste y render con CSS variables sin rebuild de Tailwind. |
| RFC-073 — Navegación, footer y CTAs globales | ✅ Documentado | `docs/rfc/RFC-073-NAVEGACION-FOOTER-CTAS-FRONTEND.md` | Navegación pública permitida, footer, CTAs y URLs seguras. |
| RFC-074 — Servicios ofrecidos y disponibilidad | ✅ Documentado | `docs/rfc/RFC-074-SERVICIOS-OFRECIDOS-FRONTEND.md` | Unificar contenido público de servicios con `ServiceType`, disponibilidad y validación de leads. |
| RFC-075 — Contenido editable de páginas institucionales | ✅ Documentado | `docs/rfc/RFC-075-CONTENIDO-PAGINAS-INSTITUCIONALES-FRONTEND.md` | Home, Nosotros, Servicios, Inversionistas y Contacto mediante secciones estructuradas. |
| RFC-076 — Render público, caché y fallbacks | ✅ Documentado | `docs/rfc/RFC-076-RENDER-CACHE-FALLBACKS-FRONTEND.md` | Capa centralizada de render, cache, invalidación y fallbacks. |
| RFC-077 — Preview, publicación y QA visual | ✅ Documentado | `docs/rfc/RFC-077-PREVIEW-PUBLICACION-QA-FRONTEND.md` | Preview owner-only, publicación, preflight validation y QA visual. |

---

## 12. Decisiones iniciales propuestas

| ID | Decisión | Estado |
| --- | --- | --- |
| D-1 | El área de administración del frontend será exclusiva de `owner`. | Cerrada por requerimiento del producto. |
| D-2 | Servicios deben manejar contenido y disponibilidad, no solo texto. | Cerrada por requerimiento del producto. |
| D-3 | `ServiceType` debe ser la base operativa para servicios seleccionables en leads. | Cerrada en RFC-074 como decisión recomendada. |
| D-4 | No habrá page builder libre en v1; se usarán secciones estructuradas. | Cerrada en RFC-075 como restricción de diseño. |
| D-5 | La personalización visual usará tokens/variables, no rebuild de Tailwind. | Cerrada en RFC-072. |
| D-6 | El servicio “Inversión inmobiliaria” debe reconciliarse con el catálogo operativo. | Documentada en RFC-074 como decisión obligatoria de implementación. |

---

## 12.1 Contratos que el diseño técnico debe cerrar

La revisión pre-diseño detectó contratos transversales que no pueden quedar “a criterio de implementación”:

| ID | Decisión requerida en P1 | Estado |
| --- | --- | --- |
| PD-1 | Estrategia draft/publicado por entidad y publicación transaccional. | 🟢 Cerrada → §16.9 / §16.12 (B-2) |
| PD-2 | Garantía de unicidad física para el singleton de configuración. | 🟢 Cerrada → §16.1 / §16.12 (B-3) |
| PD-3 | Autoridad de `owner` y `admin` sobre `ServiceType.active` sin romper permisos previos. | 🟢 Cerrada → §16.6 / §16.12 (B-4) |
| PD-4 | Backfill idempotente y estado inicial de Inversión inmobiliaria. | 🟢 Cerrada → §16.6 / §16.12 (B-5) |
| PD-5 | Diferencia entre contenido no inicializado, deshabilitado y fallback. | 🟢 Cerrada → §16.7 |
| PD-6 | Política de media, SVG, alt text y aislamiento de archivos draft. | 🟢 Cerrada → §16.4 |
| PD-7 | RFC-073 como fuente única de navegación, footer y CTAs. | 🟢 Cerrada → §16.3 |
| PD-8 | Allowlist real de fuentes: retirar Poppins o incorporarla al build. | 🟢 Cerrada → §16.5 |
| PD-9 | Backfill/cutover seguro desde contenido hardcodeado. | 🟢 Cerrada → §16.7 / §16.10 (Lote F) |
| PD-10 | Kernel mínimo de render, publicación, fallback y caché disponible desde el lote A. | 🟢 Cerrada → §16.8 / §16.12 (B-6) |

Fuente: `docs/audits/epica-12-revision-pre-diseno.md`. El cierre de cada contrato está en la sección 16. La implementación no puede iniciar hasta que P2/P3/P3R verifiquen estos cierres y Codex emita `GATE DE DISEÑO: APROBADO`.

---

## 13. Tests esperados por la épica

- Autorización owner-only para cada recurso/página nueva.
- Admin y demás roles reciben 403.
- Render público usa configuración guardada.
- Render público usa fallbacks si falta configuración.
- Servicios inactivos no aparecen en home, servicios ni lead form.
- Servicio inactivo no puede enviarse por POST aunque se manipule el formulario.
- Colores inválidos o de bajo contraste se rechazan.
- Media configurable requiere tipo/tamaño permitido.
- Cache se invalida al guardar cambios.
- No hay regresión en páginas públicas actuales, búsqueda, propiedades, proyectos ni captura de leads.

---

## 14. Checklist de cierre de la épica

- [x] Documento general aprobado.
- [x] RFC-071→077 documentados.
- [x] Revisión integral pre-diseño completada en `docs/audits/epica-12-revision-pre-diseno.md`.
- [x] Diseño técnico consolidado. → §16
- [ ] Auditoría de diseño completada.
- [ ] Reauditoría de diseño con gate `APROBADO`.
- [x] Lote A implementado y auditado con gate `APROBADO`.
- [x] Lote B implementado y auditado con gate `APROBADO`.
- [x] Lote C implementado y auditado con gate `APROBADO`.
- [x] Lote D implementado y auditado con gate `APROBADO`.
- [ ] Lote E implementado y auditado con gate `APROBADO`. → implementado; auditoría Codex pendiente.
- [ ] Lote F implementado y auditado con gate `APROBADO`.
- [ ] Lote G implementado y auditado con gate `APROBADO`.
- [ ] Auditoría integral de integración con gate `APROBADO`.
- [ ] Suite completa verde sobre PostgreSQL real.
- [ ] `npm run build` verde.
- [ ] Pint limpio.
- [ ] Verificación visual del frontend público.
- [ ] Owner puede personalizar marca/contenido sin deploy.
- [ ] Admin y otros roles no acceden al administrador de frontend.

### Regla de gate por fase

Cada lote se implementa, audita y —si corresponde— corrige y reaudita antes de habilitar el siguiente. No se permite acumular A→G para una auditoría general al final. Un lote sólo abre el siguiente cuando su documento de auditoría contiene veredicto `APROBADO`; `APROBADO CON OBSERVACIONES` no es suficiente.

La auditoría integral final valida la interacción entre lotes, pero no sustituye las siete auditorías de implementación. El flujo normativo está definido en `docs/prompts/EPICA-12-PROMPTS-MULTIMODELO.md`.

---

## 15. Nota de producto

Esta épica convierte el frontend público en una capa configurable, pero no debe convertir el CMS en un constructor genérico de sitios. La prioridad es permitir personalización real y segura de la inmobiliaria: marca, mensajes, contacto, servicios ofrecidos y contenido institucional, manteniendo el diseño New Hauz, la estabilidad del frontend y la coherencia operativa con leads.

---

## 16. Diseño técnico consolidado

> Esta sección cierra los contratos transversales (B-2→B-6 y PD-1→PD-10) con decisiones ancladas en el código real. **Cada decisión cita la evidencia verificada.** No reabre las decisiones ya cerradas (D-1→D-6).

### 16.0 Evidencia de código que ancla el diseño

| Contrato | Evidencia verificada en código | Consecuencia de diseño |
| --- | --- | --- |
| Elegibilidad de lead | `app/Livewire/Leads/LeadCaptureForm.php:109` → `Rule::exists('service_types','code')->where('active', true)` | `ServiceType.active` es hoy la **única** compuerta server-side de recepción de leads. Todo lo nuevo se cruza con este campo, no lo reemplaza. |
| Autoridad ServiceType | `app/Filament/Resources/ServiceTypeResource.php:100-103` → `canViewAny() = hasAnyRole(['owner','admin'])` | `admin` ya edita `ServiceType.active`. El área frontend owner-only NO puede quitarle ese permiso (regla aditiva). |
| Modelo ServiceType | `app/Models/ServiceType.php:10-12` → PK `code` string no incremental | El join `FrontendService → service_types.code` es por `code`, no por `id`. |
| Backfill sin seeders | `database/migrations/2026_06_29_190245_add_service_type_fk_to_leads_table.php:15-33` → `DB::table('service_types')->updateOrInsert()` en `up()` | Patrón oficial del repo: **catálogos productivos se siembran en migración idempotente**, porque `migrate` no corre seeders. |
| Fuentes en build | `vite.config.js:14-19` → `bunny('Montserrat')`, `bunny('Inter')` | El build solo tiene Montserrat + Inter. Poppins NO está disponible. |
| Tokens de tema | `resources/css/app.css:12-52` → bloque `@theme { --color-navy-*, --color-orange-*, --font-*, --radius-*, --shadow-* }` | Tailwind v4 emite estos tokens como custom properties en `:root`; **overridearlos en runtime re-skinea sin recompilar**. |
| Layout fallback-ready | `resources/views/components/layouts/public.blade.php:64` → `@foreach ($navLinks ?? [ … 7 links … ])` | El layout ya acepta `$navLinks` inyectado con fallback al array hardcodeado: el cutover es incremental. |
| Contacto hardcodeado | `public.blade.php:162,179` → `hola@newhauz.com.mx`, `https://wa.me/524422722623` | Valores exactos de fallback de contacto (§16.7). |
| Servicios hardcodeados | `resources/views/site/servicios.blade.php:25-28` → 4 servicios (Arquitectura, Construcción, Comercialización, **Inversión inmobiliaria**) | Fallback exacto de servicios y confirmación del drift de "Inversión". |
| Permisos | `database/seeders/PermissionSeeder.php:16-33,37-65` → `PERMISSIONS` const + `ROLE_PERMISSIONS`; `owner => self::PERMISSIONS` | Agregar `frontend.manage` a `PERMISSIONS` lo otorga a `owner` automáticamente y a nadie más (los otros roles listan subconjuntos explícitos). |
| Cache | `phpunit.xml:26` usa `array`; `config/cache.php` default `database` | Los tests de invalidación deben forzar `CACHE_STORE=database`; el store `array` no prueba la invalidación real. |
| Media disk | `config/media-library.php:36` → disco `public` por defecto; `/storage` público (`config/filesystems.php:41-49`) | La media de borrador **no puede vivir en disco público**: va a disco `frontend-private` con lectura owner-only, promovida al publicar (§16.4, C-3). |

### 16.1 Modelo de datos final

**Cinco** tablas nuevas, todas **aditivas**: `frontend_settings`, `frontend_services`, `frontend_pages`, `frontend_sections` y `frontend_cache_generation`. Ninguna migración altera el esquema de `users`, `properties`, `projects`, `media`, `zones` ni `service_types`; la reconciliación de datos de §16.6 es idempotente y no destructiva.

**`frontend_settings`** — singleton del sitio (RFC-071 + RFC-072 + RFC-073).

- `id`.
- `singleton_key` `string(20)`, **valor constante `'default'` forzado por `CHECK (singleton_key = 'default')` + `UNIQUE`** (garantía física del singleton, cierra C-4/B-3). Ya no basta un UNIQUE sobre un default: el CHECK impide que un import o bug inserte `'secondary'` y cree una segunda config válida.
- Identidad: `site_name` (req, ≤120), `tagline` (≤180), `short_description` (≤300), `legal_name`.
- Contacto: `public_phone`, `whatsapp_phone`, `public_email`, `public_address`, `business_hours` (JSON).
- SEO default: `default_meta_title`, `default_meta_description`, `default_og_title`, `default_og_description`.
- CTAs globales (**autoridad RFC-073**, §16.3): `primary_cta` (JSON `{label, type, target}`), `secondary_cta` (JSON `{label, type, target}`) — value object tipado, ya no dos columnas `*_route` (cierra M-6).
- Navegación: `navigation` (JSON) — **schema normativo único, el de RFC-073 (autoridad declarada), no `route_name`** (cierra C-1 P5): lista de `{key, label, enabled, sort_order, open_in_new_tab}`.
  - `key` ∈ allowlist de rutas internas. **La URL se resuelve desde `key`**, nunca desde texto libre; el owner cambia `label`, no la ruta. **La key inicial es `home`, no `inicio`** (cierra C-1 P6): `routes/web.php:13` la nombra `home` y RFC-073:104 usa `home`; `inicio` era una tercera variante inventada y queda eliminada.
  - **Mapa normativo `key` → nombre de ruta Laravel** (verificado en `routes/web.php:13-26`). La key **no** es el nombre de ruta: dos difieren y hardcodear `route($key)` rompería:

    | `key` | Nombre de ruta real | URL | Label default |
    | --- | --- | --- | --- |
    | `home` | `home` | `/` | Inicio |
    | `nosotros` | `nosotros` | `/nosotros` | Nosotros |
    | `servicios` | `servicios` | `/servicios` | Servicios |
    | `proyectos` | `proyectos` | `/proyectos` | Proyectos |
    | `inmuebles` | **`inmuebles.index`** | `/inmuebles` | Inmobiliaria |
    | `inversionistas` | `inversionistas` | `/inversionistas` | Inversionistas |
    | `contacto` | **`leads.create`** | `/contacto` | Contacto |

    El resolver usa esta tabla; una `key` fuera de ella se rechaza al guardar. `label` es lo único editable por el owner.
  - `sort_order` (int ≥ 0) es la única fuente de orden; el array persistido no depende de su índice.
  - `open_in_new_tab` (bool) existe en el schema pero en **v1 debe ser `false`**: solo aplicaría a URLs externas y v1 no habilita links externos en navegación. Un `true` persistido se normaliza a `false` al render.
  - **Regla de no-vaciado (RFC-073:124):** si el guardado dejaría todos los links `enabled=false`, se **bloquea el guardado** con error de validación. No se usa el fallback silencioso: apagar todo es un error del owner, no una intención editorial.
  - `route_name` queda **eliminado del diseño**; era un segundo schema competidor.
- Footer: `footer` (JSON tipado: `{ columns:[{title, links:[{label,type,target,enabled}]}], legal_text }`) (cierra M-6). `enabled` es booleano requerido; un link deshabilitado se conserva para edición pero no se renderiza y no activa fallback.
- Tema (RFC-072, embebido): `theme` (JSON validado — schema autoritativo en §16.5).
- Redes: `social_links` (JSON, allowlist de proveedores).
- **Referencias de marca por UUID explícito** (obligatorias, §16.4): `logo_light_media_id`, `logo_dark_media_id`, `favicon_media_id`, `og_image_media_id` — `uuid` **nullable**, FK → `media.uuid`. Son la **única** fuente de verdad de qué archivo está vigente. Validación al guardar: el UUID debe existir, pertenecer a `FrontendSetting` y a la colección correspondiente; si no, se rechaza. `null` o UUID inválido → **fallback de marca** (§16.7).
- Media collections (Spatie): `logo-light`, `logo-dark`, `favicon`, `default-og-image`. **Son solo almacenamiento**: como en v1 nada se borra, una colección acumula versiones y `getFirstMedia()` no es determinista. **El render nunca usa `getFirstMedia()`**; resuelve el `*_media_id` guardado. Ninguna de estas colecciones declara `singleFile()` ni `onlyKeepLatest()` (§16.4).
- timestamps. **Se elimina `is_active`** (Mn-3): el singleton no eliminable no tiene un estado "inactivo" con semántica definida; su ausencia se cubre con fallbacks (§16.7).
- **Publicación: estrategia A (inmediata).** Validación dura al guardar + normalización defensiva en el boundary de render (§16.5). No lleva columnas draft.

**`frontend_services`** — capa editorial de servicios (RFC-074).

- `id`.
- `service_type_code` **`string('service_type_code', 30)`** — coincide con el tipo real `service_types.code string(30)` (`create_service_types_table.php:14-20`); FK → `service_types.code`. Relación 1:1. **Su unicidad es un ÚNICO índice parcial** (DDL en §16.1.2); **no lleva `->unique()` global**, que sería un segundo constraint y además impediría recrear el servicio de un `code` borrado en soft.
- **Disponibilidad (estrategia A, efecto inmediato — crítico para leads):** `show_in_home` (bool), `show_in_services` (bool), `allow_leads` (bool), `sort_order` (int).
- **Contenido editorial (estrategia A — enmienda C-G-1, §16.9):** columnas de contenido directo `title`, `short_description`, `long_description`, `bullets[]` (JSON), `icon`, `image_media_id` (UUID), `image_alt`. **Guardar = publicar** con validación dura al guardar y bump `afterCommit`. **RETIRADO por C-G-1:** `draft_payload`/`published_payload`/`draft_revision`/`published_at`/`published_by`, el lock optimista y `expected_draft_revision_service` — un servicio (fila 1:1, sin composición) no justifica un flujo draft→publicado; ver la justificación en la enmienda §16.9.
- Media collection: `image` (validada por existencia/owner/colección al guardar; sin borrado físico, §16.4).
- timestamps + **`SoftDeletes`** (`deleted_at`): igual que `frontend_sections`, protege la media referenciada por `published_payload` (§16.4). `forceDelete` prohibido por policy. Su único UNIQUE es el parcial declarado arriba.

> **Justificación del split A/B en servicios:** apagar `allow_leads`/`show_in_*` debe surtir efecto **al instante** (regla de oro 2). Pulir la copia larga se beneficia de preview antes de publicar. Por eso disponibilidad = inmediata y contenido = draft/publicado.

**`frontend_pages`** — página institucional editable (RFC-075). Snapshot publicable completo (cierra C-2).

- `id`, `key` `string(30)` **UNIQUE** ∈ allowlist `{home, nosotros, servicios, inversionistas, contacto}`.
- **Estado de trabajo (draft):** `is_enabled` (bool), `seo` (JSON `{meta_title, meta_description, og_title, og_description}`). **`canonical` NO es editable** (cierra C-1.4): siempre lo deriva el render de la URL de la ruta nombrada de la página (§16.9). Evita dos autoridades y canonicals inválidos.
- **Revisión publicada (atómica):** `published_revision` (JSON) que **captura la página completa publicable**: `{ is_enabled, seo:{...}, sections:[{ section_key, type, sort_order, is_enabled, payload_con_media_ids }], generated_from_ids:[...] }`; `published_at`; **`published_by`** (FK → users); `revision` (bigint, contador de publicaciones).
- **Versión del estado de trabajo:** `draft_revision` (bigint, `NOT NULL`, default `1`). Toda mutación draft de la página o de cualquiera de sus secciones la incrementa atómicamente bajo el lock de la página. La pantalla de publicación conserva la revisión que leyó y la envía como `expected_draft_revision`; una revisión obsoleta se rechaza antes de construir el snapshot.
- timestamps.
- **El render público lee SOLO `published_revision`.** SEO, `is_enabled`, orden, secciones y referencias media viajan **dentro** del snapshot; nunca se leen columnas draft en producción (cierra C-2: no se filtra SEO/estado/media de borrador).

**`frontend_sections`** — bloques tipados por página, estado **de trabajo** (RFC-075, D-4: sin page builder). El snapshot vive en `frontend_pages.published_revision`.

- `id`, `frontend_page_id` FK, `section_key` `string(40)` (clave estable canónica, §16.1.1), `type` `string(30)` ∈ allowlist (§16.1.1), `sort_order` (int ≥ 0), `is_enabled` (bool).
- `payload` (JSON según schema del `type`, con `media_id` UUID + `alt`/`decorative` para cada imagen).
- **`SoftDeletes` obligatorio + `deleted_at`** (cierra C-3 P5): `delete` solo owner y siempre **soft**. Esto es lo que impide que Spatie borre la media referenciada por la revisión publicada viva (`InteractsWithMedia.php:56-60`, §16.4). `forceDelete` retorna `false` en la policy — no existe camino desde la UI.
- **TODOS los UNIQUE son parciales** (cierra C-3 P6): **ningún UNIQUE global sobrevive en tablas con `SoftDeletes`** — uno global sobre `sort_order` dejaría esa posición ocupada para siempre por una fila borrada, haciendo imposible reordenar o recrear la sección. Es la regla, no una excepción de `section_key`. El DDL ejecutable está en §16.1.2.
- Crear, editar, reordenar o borrar una sección toma primero el lock de su `FrontendPage` e incrementa `frontend_pages.draft_revision` en la misma transacción; la sección no mantiene una versión competidora.

**`frontend_cache_generation`** — generación durable de caché (RFC-076).

- Una sola fila física: `id=1`, `generation bigint NOT NULL DEFAULT 1` y `CHECK (id = 1)`.
- La migración crea e inicializa la fila con generación `1`; el runtime no depende de seeders.
- Cada bump usa una sola sentencia `UPDATE ... SET generation = generation + 1 WHERE id = 1 RETURNING generation`; no hay read-modify-write en PHP.
#### 16.1.1 Registry canónico de secciones (cierra M-1)

Un registry estático `config/frontend-sections.php` (o clase) declara, por cada `type`: `renderer` (componente Blade), `schema` (campos + tipos + reglas), `multiplicity` (single|multi por página), `media` (colecciones y cardinalidad) y `fallback_adapter` (cómo derivar el contenido hardcodeado actual). Mapeo explícito del frontend real:

| Página | `section_key` | `type` | Multiplicidad | Origen actual del fallback |
| --- | --- | --- | --- | --- |
| home | `hero` | `hero` | single | `welcome.blade.php` hero |
| home | `featured_properties` | `featured_properties` | single | `HomeController.php:16-27` (dinámico: Property) |
| home | `opportunity_properties` | `opportunity_properties` | single | `HomeController.php:19-20` + `welcome.blade.php:207-242` (dinámico: `Property::opportunity()`) |
| home | `featured_projects` | `featured_projects` | single | `HomeController.php:29-36` (dinámico: Project) |
| home | `services_home` | `service_list` | single | §16.6 (services) |
| home | `investors_block` | `cta` | single | `welcome.blade.php` inversión |
| home | `partners` | `partners` | single | `welcome.blade.php` partners |
| home | `final_cta` | `cta` | single | `welcome.blade.php` CTA final |
| nosotros | `hero` | `hero` | single | `site/nosotros.blade.php:2-20` |
| nosotros | `metrics` | `metrics` | single | `site/nosotros.blade.php:22-37` |
| nosotros | `story` | `rich_text` | single | `site/nosotros.blade.php:39-57` |
| nosotros | `values` | `values` | single | `site/nosotros.blade.php:59-83` |
| nosotros | `team` | `team` | single | `site/nosotros.blade.php:85-123`; schema incluye spotlight A-74 + miembros |
| nosotros | `final_cta` | `cta` | single | `site/nosotros.blade.php:125-134` |
| servicios | `hero` | `hero` | single | `site/servicios.blade.php:2-20` |
| servicios | `services_list` | `service_list` | single | `site/servicios.blade.php:22-52` + §16.6 |
| servicios | `final_cta` | `cta` | single | `site/servicios.blade.php:54-61` |
| inversionistas | `hero` | `hero` | single | `site/inversionistas.blade.php` hero |
| inversionistas | `investment_path` | `feature_sequence` | single | tres paneles de `site/inversionistas.blade.php:22-74` |
| inversionistas | `service_scope` | `values` | single | “¿Qué incluye?” de `site/inversionistas.blade.php:76-116` |
| inversionistas | `audience_outcomes` | `audience_outcomes` | single | audiencia + resultado de `site/inversionistas.blade.php:118-161` |
| inversionistas | `final_cta` | `cta` | single | `site/inversionistas.blade.php:163-172` |
| contacto | `hero` | `hero` | single | `leads/create.blade.php:2-17` |
| contacto | `contact_intro` | `rich_text` | single | título/copia de `leads/create.blade.php:23-27` |

**Tipos permitidos (allowlist final, ver enmienda §18.19):** `hero`, `rich_text`, `metrics`, `values`, `team`, `feature_sequence`, `audience_outcomes`, `cta`, `service_list`, `featured_properties`, `opportunity_properties`, `featured_projects`, `partners`. `feature_sequence` ejecuta un renderer de paneles ordenados con media y variante allowlisted (`split_media_end`, `split_media_start`, `full_overlay`); `audience_outcomes` ejecuta la composición tipada de lista de audiencia + tarjeta de resultados. No son aliases descriptivos ni permiten HTML/layout arbitrario.

Schemas mínimos de los tipos nuevos: `feature_sequence = {items:[{eyebrow,title,body,media_id,alt,layout}]}` con `layout` en la allowlist anterior; `audience_outcomes = {eyebrow,title,audience_items:[string],result:{eyebrow,title,items:[string],quote?:string}}`. Cada renderer escapa texto, resuelve `media_id` por el boundary de media y rechaza campos/variantes desconocidos.

**Schema autoritativo de `hero`, con slides (cierra M-1 P5 y la contradicción RFC-075).** El Blade real del home NO tiene un hero de imagen única: `welcome.blade.php:3-10` define **cuatro slides** de fondo que rotan por animación CSS (`:13-28`), bajo un overlay navy que sostiene la legibilidad del texto (`:31`). El schema anterior (solo `eyebrow/title/subtitle/primary_cta`) no puede representarlos. Schema único:

```json
{
  "eyebrow": "string|null",
  "title": "string (requerido)",
  "subtitle": "string|null",
  "primary_cta": { "label": "…", "type": "route|url", "target": "…" },
  "secondary_cta": { "label": "…", "type": "route|url", "target": "…" },
  "slides": [ { "media_id": "uuid", "alt": "string|null", "decorative": true, "sort_order": 0 } ]
}
```

- **`secondary_cta` es un CTA real, no `null` fijo (cierra M-1 P6).** El hero del home tiene **dos** CTAs visibles hoy: `welcome.blade.php:46` → "Ver Propiedades" (`route('inmuebles.index')`) y `:47-49` → "Conocer Proyectos" (`route('proyectos')`). Fijarlo en `null`, como decía el diseño anterior, **habría borrado un CTA visible en el cutover**. Es nullable (una página puede tener un solo CTA), pero su **fallback en el home son esos dos exactos**, en ese orden. Ambos usan el mismo value object `{label,type,target}` de RFC-073 y pasan por el `CtaResolver`.

- **Cardinalidad y orden:** `slides` es `0..6`. `sort_order` (int ≥ 0) es la única fuente de orden y define el `animation-delay` derivado (`i * (ciclo / n)`), no un valor editable. Cero slides = hero sin slideshow (fondo sólido), no un error.
- **Accesibilidad:** los slides son **fondo decorativo detrás de un overlay**; el contenido informativo es el texto del hero. Por eso `decorative: true` es el **default** y en ese caso `alt` debe ser `null`. ~~(Se emiten como `background-image`, sin `<img>` ni rol.)~~ ⚠️ **La TÉCNICA quedó superada por §18.18 punto (6):** se emiten como **`<img aria-hidden="true" alt="">`** dentro de la capa oculta, porque `background-image` exigía un atributo `style` inline que ninguna CSP admite sin `unsafe-inline`. **La regla de accesibilidad no cambia**: siguen sin exponerse a tecnología asistiva. Si el owner marca `decorative: false`, `alt` pasa a ser **obligatorio** — misma regla que §16.4, sin excepción.
- **Fallback exacto (§16.7) — POR PÁGINA (enmendado, §18.18).** Sin slides publicados, el render emite **el fondo hardcodeado actual de ESA página**, no el del home: `home` → las **cuatro URLs Unsplash** de `welcome.blade.php:12-16` (arquitectura, construcción, comercialización, inversión); `nosotros` → `images/nosotros/header_nosotros.png`; `servicios` → `images/servicios/header_servicios.png`; `inversionistas` → `images/inversionistas/header_inversionistas.png`; `contacto` → **sin imagen de fondo** (hoy `/contacto` no tiene). Es la aplicación literal del principio de §16.7 («no inicializado → valor hardcodeado **actual**»): el `hero` es un tipo compartido, pero su fallback **no** puede ser el contenido de una sola página. Son placeholders; el cutover del Lote F **no los borra**: siguen siendo el fallback hasta que el owner publique slides propios. Un hero publicado con `slides: []` NO revive el fallback (§16.7: apagar deliberadamente ≠ no inicializado); para volver a los placeholders el owner deshabilita la sección. Matriz completa por página y estado: `docs/epicas/epica-12-1-mejora-ux-hero.md` §8.
- **Media:** los slides usan la colección `images` de `frontend_sections` con las reglas de §16.4 (raster, ≤3 MB, sin SVG, draft en disco privado, promoción post-commit por `media_id`). Ninguna URL externa es editable por el owner: subir un archivo sí, apuntar a un host arbitrario no.
- `hero` es **single** por página y el mismo `type` sirve a home, nosotros, servicios, inversionistas y contacto; solo el home usa `slides` en v1, pero el schema es único (no hay `hero_home` aparte).

Los tres tipos de catálogo son independientes: `featured_properties` lee `Property::featured()`, `opportunity_properties` lee `Property::opportunity()` y `featured_projects` lee `Project` con `is_featured=true`. Su payload solo lleva parámetros allowlisted (p. ej. límite), nunca items ni consultas arbitrarias. Esto conserva los tres bloques dinámicos reales durante el cutover (cierra M-1/PD-9).

**No existe `inversionistas.metrics` en el Blade verificado.** El bloque visual “Resultado esperado” es parte de `audience_outcomes`; inventar una sección `metrics` produciría un fallback que hoy no existe.

**Regiones kernel-fed sin contenido libre (cierra M-1, explícito):**

| Página | Región | Autoridad | Límite CMS |
| --- | --- | --- | --- |
| home | Buscador de inmuebles (`welcome.blade.php:56-149`) | `$typeOptions`, `$searchZones`, `route('inmuebles.index')` | Bloque funcional sin `section_key` ni payload; permanece siempre en kernel. |
| contacto | Formulario (`leads/create.blade.php:26`) | `LeadCaptureForm` + elegibilidad/locks de §16.6 | No editable, reemplazable ni ocultable desde secciones. |
| contacto | Canales (`leads/create.blade.php:30-66`) | contacto/horarios/WhatsApp de `FrontendSetting` | No se duplican en `FrontendSection`; el kernel compone estos datos alrededor de `contact_intro`. |

Oportunidades (`opportunity_properties`) y proyectos (`featured_projects`) sí tienen sección registrable para presencia/orden/parámetros allowlisted, pero el kernel obtiene sus items desde modelos. Header, navegación, footer y WhatsApp flotante son layout/kernel bajo RFC-071/073, no secciones de página.

Ninguno introduce HTML/CSS/JS editable por el usuario (regla de oro). El cutover del Lote F preserva buscador, inmuebles destacados, oportunidades y proyectos destacados sin intercambiar sus fuentes de datos.

#### 16.1.2 DDL ejecutable de los índices únicos parciales (cierra C-3 P7)

`UNIQUE (...) WHERE ...` es descripción conceptual, **no DDL válido**: PostgreSQL no acepta un constraint `UNIQUE` con predicado. La unicidad parcial se implementa con **`CREATE UNIQUE INDEX ... WHERE ...`**, exactamente como ya lo hace este repositorio en `database/migrations/2026_07_13_000001_create_lona_requests_table.php:31-35` (RFC-062). Se sigue ese patrón, con **nombre explícito** por índice para que el rollback y los asserts de esquema puedan referenciarlo:

```php
// Dentro del up() de cada migración, después del Schema::create() de su tabla.
DB::statement(<<<'SQL'
    CREATE UNIQUE INDEX frontend_services_service_type_code_active_unique
    ON frontend_services (service_type_code)
    WHERE deleted_at IS NULL
SQL);

DB::statement(<<<'SQL'
    CREATE UNIQUE INDEX frontend_sections_page_section_key_active_unique
    ON frontend_sections (frontend_page_id, section_key)
    WHERE deleted_at IS NULL
SQL);

DB::statement(<<<'SQL'
    CREATE UNIQUE INDEX frontend_sections_page_sort_order_active_unique
    ON frontend_sections (frontend_page_id, sort_order)
    WHERE deleted_at IS NULL
SQL);
```

- **Ningún `->unique()` de Blueprint** sobre estas columnas: generaría un constraint global adicional que anularía el propósito del índice parcial.
- **Rollback:** los tres índices se crean en la misma migración que crea su tabla, así que `Schema::dropIfExists('frontend_sections' | 'frontend_services')` en el `down()` los elimina con ella — igual que en `lona_requests`. No hace falta `DROP INDEX` explícito y no queda índice huérfano. Si alguna vez se agregaran a una tabla preexistente, el `down()` debe hacer `DROP INDEX IF EXISTS <nombre>`.
- **Verificación (T-9e):** el test consulta `pg_indexes` y afirma que existen los tres índices **con esos nombres**, que su definición contiene `WHERE (deleted_at IS NULL)`, y que **no** existe ningún índice único global sobre esas mismas columnas.

#### 16.1.3 Inicialización productiva de las páginas canónicas (cierra N-1, CRÍTICO)

El deploy corre `php artisan migrate --force` **sin seeders** (`docs/deployment/CI-CD-PIPELINE.md:46-58`). Por eso un `FrontendPageSeeder` **no puede ser la fuente productiva** de las cinco páginas: en una instalación limpia el sitio quedaría en fallback y el owner **no vería ninguna entidad editable** — el objetivo del módulo quedaría incompleto en producción. Mismo error que ya se corrigió para el permiso `frontend.manage` (§16.2) y para `inversion` (§16.6); se aplica el mismo patrón.

- **Acción idempotente invocable `SeedFrontendPages`**, con lógica **insert-if-missing** (`firstOrCreate` por `key`), que crea las cinco filas de `frontend_pages`: `home`, `nosotros`, `servicios`, `inversionistas`, `contacto`.
- **La migración aditiva la invoca**; el seeder queda solo para dev/test. Producción obtiene las páginas con el `migrate` normal.
- **Estado inicial `is_enabled = false`.** Es deliberado y respeta la semántica tri-estado de §16.7: el owner recibe las cinco entidades editables en el CMS, y el sitio público **sigue renderizando exactamente los fallbacks hardcodeados actuales** hasta que decida habilitar cada página. Una instalación limpia no cambia ni un pixel del frontend (regla de oro: los fallbacks se conservan).
- **No crea secciones.** Las `FrontendSection` se materializan cuando el owner las edita, a partir de los `section_key` que el registry (§16.1.1) declara válidos para esa página. Sembrar payloads default duplicaría los fallbacks en dos lugares y crearía drift.
- **No destructiva:** nunca toca filas existentes. Correrla dos veces sobre una instalación con páginas ya habilitadas y personalizadas no cambia `is_enabled`, `seo`, `published_revision` ni `draft_revision`.
- **Pruebas (T-15/T-15b):** tras `migrate` **sin seeders** existen las cinco páginas y el owner las ve en el CMS; el frontend público sigue idéntico al fallback. Segunda ejecución sobre filas personalizadas → sin cambios.

### 16.2 Policies / permisos owner-only (cierra C-5)

- **Doble compuerta rol + permiso:** todas las policies/gates exigen **`hasRole('owner') && can('frontend.manage')`**, siguiendo el patrón estricto ya presente en `app/Policies/ZonePolicy.php:32-37`. Solo el permiso no alcanza (M/C-5: un no-owner que reciba el permiso por error NO debe entrar); solo el rol tampoco (permite auditar/retirar la capacidad). Ambas condiciones.
- **Permiso nuevo:** `frontend.manage`, agregado a `PermissionSeeder::PERMISSIONS` (owner lo obtiene por `owner => self::PERMISSIONS`; nadie más).
- **Creación productiva garantizada (crítico):** el deploy recomendado corre `migrate --force` **sin seeders** (`docs/deployment/CI-CD-PIPELINE.md:46-58`). Por eso el permiso NO puede depender solo del seeder. Se crea con una **migración idempotente aditiva** que hace `Permission::firstOrCreate(['name'=>'frontend.manage','guard_name'=>'web'])` y lo asigna al rol `owner` (`firstOrCreate` del rol + `givePermissionTo` idempotente). Así producción obtiene el permiso con el `migrate` normal. El seeder se actualiza en paralelo para dev/test.
- **Registro de policies:** se registran explícitamente en `app/Providers/AppServiceProvider.php` (patrón del proyecto, `AppServiceProvider.php:72-81`): `Gate::policy(FrontendSetting::class, FrontendSettingPolicy::class)`, etc. `delete`/`forceDelete` de `FrontendSetting` retornan `false` siempre (C-4/B-3).
- **Filament:** cada Resource/Page implementa `canViewAny()/canAccess()` → `auth()->user()?->hasRole('owner') && auth()->user()->can('frontend.manage')`. `navigationGroup` propio `Frontend`, fuera de los grupos operativos.
- **Rutas de preview** (§16.9): panel `admin` + gate `frontend.manage` + rol owner. `admin` y demás roles → **403 real**.
- **Tests:** `PermissionSeederTest` (hoy fija 14 permisos, `tests/Feature/Auth/PermissionSeederTest.php:15-51`) se actualiza a 15 e incluye `frontend.manage` solo en owner. Test específico: tras `migrate` sin seeders, `frontend.manage` existe y owner lo tiene (deploy productivo). Autorización por recurso/página/preview: `owner` 200; `admin`/`agente`/`arquitectura`/`proyectos` 403.

### 16.3 Servicios de frontend y contratos de datos para Blade

Fuente única de render (RFC-076): las vistas **nunca** consultan modelos directamente; consumen structs normalizados del kernel (§16.8). Contratos (arrays/DTO de solo lectura):

- `settings()` → `{ site_name, tagline, contact:{phone, whatsapp, whatsapp_href, email, address, hours}, seo:{...}, social:[...], brand:{logo_light_url, logo_dark_url, favicon_url, og_image_url} }`.
- `theme()` → `{ primary, on_primary, accent, on_accent, background, text, heading_font, body_font, radius }` (ya validado y normalizado; §16.5).
- `navigation()` → `{ links:[{ key, label, url, active_pattern, sort_order }], ctas:{ primary:{label,url}, secondary:{label,url} } }`. Los links llegan **ya ordenados por `sort_order` y ya filtrados** (`enabled=false` se omite; el DTO no expone `enabled` porque el Blade no debe decidir visibilidad). `url` y `active_pattern` se **derivan de `key`** vía la allowlist de rutas, nunca del payload persistido (§16.1).
- **`footer()`** → `{ columns:[{ title, links:[{label,url,enabled}] }], legal_text, social:[...] }` (cierra M-6: el footer tipado **sí** se expone al Blade y conserva el estado explícito). Los links se resuelven con `CtaResolver`; el renderer omite `enabled=false` sin revivirlos mediante fallback. **RFC-073 es la única autoridad de navegación, footer y CTAs.**
- `services(location)` → lista filtrada por §16.6 para `home` | `servicios`, cada item `{ code, title, short, long, bullets, image_url, image_alt, allow_leads, cta }`. El **`cta` por servicio es DERIVADO, no editable** (cierra C-1.3/M-6): si `allow_leads=true` → `{ label:"Solicitar información", url: route('leads.create', ['service'=>code]) }`; si no, `null`. No hay CTA por servicio almacenado en v1; se enmienda RFC-074.
- `page(key)` → `{ enabled, seo:{...}, canonical, sections:[{ section_key, type, payload }] }` resuelto desde `published_revision`. `canonical` derivado de la ruta (§16.9).

**Binding server-side del CTA de servicio al formulario (cierra M-6):** `GET /contacto?service=<code>` se atiende mediante un controller invocable, no mediante `Route::view`. El controller acepta solo un string de código con longitud máxima 30 y consulta la misma regla fail-closed de §16.6 (`ServiceType.active=true` **y** `FrontendService.allow_leads=true`). Solo un código elegible se pasa a Blade como `$preselectedServiceType` y de ahí a `<livewire:leads.lead-capture-form :service-type="$preselectedServiceType" ...>`; `locked=false`, porque es preselección, no imposición. Código ausente, malformado, desconocido o inelegible se **ignora de manera uniforme**: HTTP 200, formulario sin selección y sin revelar cuál condición falló. El POST vuelve a validar y bloquear ambas autoridades; nunca confía en la query ni en el estado montado.

Regla de invocación en Blade: el layout inyecta `settings()`, `theme()`, `navigation()`; cada vista de página pide `page(key)` y, cuando corresponda, `services(location)` con `location=home|servicios`. El `navLinks` inyectado reemplaza gradualmente el array hardcodeado de `public.blade.php:64` (cutover, §16.10 Lote F).

### 16.4 Estrategia de Media Library (cierra PD-6, C-3, Mn-4)

- **Colecciones:** `frontend_settings` → `logo-light`, `logo-dark`, `favicon`, `default-og-image`. `frontend_services` → `image`. `frontend_sections` → `images`.
- **MIME/tamaño:** raster `image/png,image/jpeg,image/webp`, ≤ 3 MB, dimensiones mínimas por colección (og ≥ 1200×630). `favicon`: `image/png` o `image/x-icon`.
- **SVG PROHIBIDO en v1** (Mn-4, recomendación de auditoría): los logos exigen PNG/WebP raster. Se evita la dependencia `enshrined/svg-sanitize` (no instalada), reduce superficie de ataque y matriz de pruebas. Incorporar SVG sanitizado queda como trabajo posterior con dependencia declarada y tests de contenido malicioso real.
- **`alt_text` obligatorio** para toda imagen visible (regla de oro 7). Cada referencia en el payload lleva `media_id` (UUID) + `alt` o `decorative:true`. Falta de `alt` en imagen no decorativa → rechazo al guardar.
- **Media draft en disco PRIVADO** (cierra C-3, patrón `ContratoIntermediacion.php:191-201`): la media de contenido en borrador se sube al disco `frontend-private` (no accesible por URL). Se lee en el CMS/preview con un **controlador owner-only** (`frontend.manage` + rol owner; 403/404 uniforme para anónimos).
- **Promoción rollback-safe, DESPUÉS del commit** (corrige el error del diseño P3): el filesystem **no participa del rollback de PostgreSQL** — copiar al disco público dentro de la transacción deja un archivo público huérfano si la transacción falla. Por eso:
  - El `published_revision` referencia media **solo por `media_id` (UUID)** — **una sola representación** (se descarta guardar también URL, cierra la ambigüedad de C-3). El render resuelve `media_id`→URL pública.
  - La transacción de publicación NO copia archivos; solo escribe el snapshot con los `media_id`.
  - En **`DB::afterCommit`** se despacha `PromoteFrontendMedia` por cada `media_id` publicado. El job es **idempotente**: toma los locks en el orden global **`page → sections(id ASC) → media(uuid ASC)`** (§18.18 punto 4 — el lock de página hace atómica la revalidación de referencia), termina sin copiar si ya está `promoted` o si la media dejó de estar referenciada por la revisión vigente, copia a un nombre determinista, verifica existencia/integridad y solo entonces marca `promoted`; sus reintentos no crean duplicados.
  - **Durabilidad del enqueue:** antes del commit, cada media referenciada queda marcada `pending_promotion` en `media.custom_properties` dentro de la misma transacción que el snapshot (sin alterar el schema de `media`). Si hay rollback, ni el snapshot ni ese estado sobreviven y no se despacha el job. Si `afterCommit` no logra encolar o el proceso muere antes del callback, el comando idempotente `ReconcileFrontendMediaPromotions` recorre `media_id` referenciados por revisiones publicadas/payloads publicados que no estén `promoted` y vuelve a despachar el mismo job. Se ejecuta programado y puede correrse manualmente; no requiere una sexta tabla ni asume que el callback en memoria es durable.
  - El fallo de dispatch se registra con `media_id` y revisión, pero no revierte una publicación ya confirmada. La reconciliación es el mecanismo de recuperación obligatorio, no una intervención manual informal.
  - **El render solo emite media `promoted`** — nunca una URL privada ni un archivo a medias. ~~Mientras una imagen recién publicada no termina de promoverse, el render usa la versión pública anterior de esa media o placeholder.~~ ⚠️ **SUPERADO por §18.18 (Épica 12.1).** La «versión pública anterior» **no tiene representación en el snapshot** —solo existe `media_id`, una sola referencia— y por lo tanto no era implementable. **Conducta normativa vigente:** una media referenciada pero **no `promoted` se OMITE** del render; si tras la omisión no queda ninguna imagen renderizable, la sección se muestra **sin imagen** (el fallback de §16.7 aplica solo a «no inicializado», no a una publicación con intención de imagen). **No existen** «versión anterior» ni placeholder.
- **Retención: la revisión publicada NUNCA pierde su media (cierra C-3 P5).** El snapshot referencia `media_id`, así que cualquier borrado del modelo draft rompería la página pública viva. Spatie borra **toda** la media del modelo en el evento `deleting` (`vendor/spatie/laravel-medialibrary/src/InteractsWithMedia.php:51-63`), salvo dos escapes que este diseño usa deliberadamente:
  - **`FrontendSection` y `FrontendService` usan `SoftDeletes`.** El vendor retorna temprano cuando el modelo usa `SoftDeletes` y no está en `forceDeleting` (`InteractsWithMedia.php:56-60`), así que el borrado desde la UI **conserva los archivos**. Borrar una sección la saca del estado de trabajo, no de la revisión publicada: hasta la próxima publicación, la página pública sigue renderizando su snapshot intacto.
  - ⚠️ **Conservar los bytes NO alcanza (precisado por §18.18, Épica 12.1).** El render resuelve la URL de una media a través de su **fila propietaria** (`model_type` + `model_id` + colección). Si esa resolución usa la relación por defecto, el `SoftDeletingScope` **excluye la sección borrada** y la media referenciada por el snapshot deja de resolverse: la página publicada **perdería la imagen** sin que nadie publique nada — exactamente lo contrario de esta garantía. **Norma:** toda resolución de propietario que sirva a una **revisión publicada** debe usar **`withTrashed()`** (renderer y `PublishedMediaReference::owningPage()`). El propietario se usa solo para **acotar** la consulta de media, no como prueba de vigencia editorial: la vigencia la da el snapshot. Contrato y pruebas: `docs/epicas/epica-12-1-mejora-ux-hero.md` §7.11.
  - **`forceDelete` está prohibido por policy** para ambos modelos (retorna `false` siempre, igual que `FrontendSetting` en C-4). No hay camino desde la UI hacia `deleteAllMedia()`.
- **Las DOS rutas automáticas de borrado del stack quedan neutralizadas (cierra C-3 P14).** `SoftDeletes` protege el borrado del **modelo propietario**, pero **no** intercepta un `Media::delete()` directo. Hay dos caminos que borran media sin que nadie lo pida, y ambos se anulan explícitamente:
  1. **El uploader de Filament.** `SpatieMediaLibraryFileUpload::setUp()` registra `saveRelationshipsUsing(fn => $component->deleteAbandonedFiles(); ...)` (`vendor/filament/spatie-laravel-media-library-plugin/src/Forms/Components/SpatieMediaLibraryFileUpload.php:125-128`), y `deleteAbandonedFiles()` ejecuta **`$media->delete()` sobre todo UUID que no esté en el estado del formulario** (`:247-257`); el observer de Spatie borra entonces los archivos (`MediaObserver.php:55-64`). Es decir: **retirar una imagen del formulario y guardar destruye fila y archivos**, aunque `published_revision` todavía los referencie. **Corrección:** todo campo de formulario **con relación Spatie** en Épica 12 usa **`NonDestructiveMediaUpload`**, subclase de `SpatieMediaLibraryFileUpload` cuyo `deleteAbandonedFiles(): void` es un **no-op documentado**. Quitar una imagen del formulario **solo quita la referencia editorial del payload**; el archivo permanece.

**Precisión de alcance (enmienda §18.18).** El mandato existe para neutralizar **un comportamiento específico de `SpatieMediaLibraryFileUpload`** (`saveRelationshipsUsing` → `deleteAbandonedFiles()`). Un campo que **no** establece relación Spatie —el `FileUpload` base de Filament, usado para el estado de **lista** de `hero.slides` dentro de un `Repeater`, donde el estado canónico es el array de `media_id` del payload y no una columna única— **no posee esa ruta de borrado en absoluto** y, por lo tanto, no puede usar la subclase (su hidratación es single-UUID por columna del modelo). Para ese caso rige la **misma garantía contractual**, verificada por pruebas equivalentes: quitar o reordenar una slide **solo** reescribe el payload, **nunca** invoca `Media::delete()`; siguen prohibidos `singleFile()`, `onlyKeepLatest()`, `forceDelete` y todo borrado físico. Queda **prohibido** usar `SpatieMediaLibraryFileUpload` directamente en cualquier caso. Contrato detallado: `docs/epicas/epica-12-1-mejora-ux-hero.md` §7.
  2. **El límite de tamaño de colección.** Si una colección se registra con `singleFile()` u `onlyKeepLatest(n)`, agregar media dispara `clearMediaCollectionExcept()` y **borra el excedente** (`MediaCollections/FileAdder.php:645-651`; `MediaCollection.php:90,98-106`). **Corrección:** **ninguna** colección de Épica 12 usa `singleFile()` ni `onlyKeepLatest()` en `registerMediaCollections()`. Queda prohibido explícitamente.
- **Consecuencia de diseño: la pertenencia a la colección NO es la fuente de verdad.** Como nada se borra, una colección puede acumular varias imágenes. Por eso **toda** referencia de media en Épica 12 es por **`media_id` explícito** —incluida la marca: `frontend_settings` guarda `logo_light_media_id`, `logo_dark_media_id`, `favicon_media_id` y `og_image_media_id`—. **El render nunca usa `getFirstMedia()`**: resuelve el `media_id` guardado. Así "reemplazar" es cambiar un UUID, no destruir un archivo.
- **Reemplazo de imagen en draft:** subir una imagen nueva **no borra la anterior**. La media previa permanece mientras siga referenciada por `published_revision` o `published_payload`. El draft apunta al `media_id` nuevo; el público sigue viendo el viejo hasta que se publique.
- **SIN borrado físico de media en v1 (decisión de alcance, cierra C-3/M-7).** La media que deja de estar referenciada **no se elimina**: queda en disco, marcada como no referenciada para inventario y observabilidad. **No existe** prune, purga física, lease, advisory lock, path generator propio ni barrido de huérfanos.

  **Por qué.** Recuperar espacio en disco es una **optimización**, no un requisito de la épica. El requisito real de C-3 era de **seguridad** —que la media en borrador no fuera accesible públicamente— y está cerrado por el disco privado, el controlador owner-only y la promoción post-commit. Borrar archivos, en cambio, obligaba a coordinar tres sistemas que **no comparten transacción** (PostgreSQL, filesystem y cola) y arrastraba: una tabla de lease, una conexión de BD dedicada, `pcntl` como requisito de producción, la invariante `0 < job_timeout < lease_ttl < retry_after`, dos subclases de job de Spatie, un path generator con scope, un barrido de huérfanos y ~30 pruebas de concurrencia. Todo eso para un CMS de una inmobiliaria cuyo volumen de imágenes huérfanas crece en **megabytes por mes**.

  **Tradeoff aceptado y medido.** El disco crece con media reemplazada o de secciones borradas. Con el límite de 3 MB por archivo (§16.4) y el volumen editorial esperado, es costo despreciable frente al riesgo de borrar por error una imagen que una revisión publicada todavía referencia.

  **Lo que SÍ queda garantizado en v1:**
  - La media en borrador **nunca es pública** (disco privado + controlador owner-only).
  - Una revisión publicada **nunca pierde su media**: `SoftDeletes` + `forceDelete` prohibido, **`NonDestructiveMediaUpload`** (anula `deleteAbandonedFiles()`) y **prohibición de `singleFile()`/`onlyKeepLatest()`**. Las tres rutas de borrado —modelo propietario, uploader y límite de colección— quedan cerradas.
  - Reemplazar una imagen **no destruye** la anterior: cambia el `media_id` referenciado.
  - La promoción draft→pública es idempotente y recuperable (`PromoteFrontendMedia` + `ReconcileFrontendMediaPromotions`).

  **Marcado, no borrado.** Un comando de solo lectura `frontend:media:report-unreferenced` lista la media editorial sin referencias en ningún JSON draft ni publicado, con antigüedad y tamaño. **No borra nada**: sirve para dimensionar el problema y decidir, con datos reales, si la épica de borrado vale la pena.

  **Diferido a épica propia.** El borrado físico se difiere a una épica futura, con su propio diseño y su propio gate. Si se retoma, el trabajo de análisis de las rondas P8→P13 queda registrado en §18.6–§18.12 y en Engram: la frontera BD↔filesystem, el remover que silencia fallos, la familia responsive del original, los jobs en cola y la vida de sesión de un advisory lock. **No se pierde: se archiva.**

### 16.5 Estrategia de tema visual runtime (cierra PD-8, D-5, M-4)

**Schema autoritativo de `frontend_settings.theme`** (único; RFC-072 se enmienda a este):

| Token | Tipo/regla | `--nh-*` | Fallback (`app.css`) |
| --- | --- | --- | --- |
| `primary` | hex `#rrggbb` | `--nh-primary` | `#091a5b` |
| `on_primary` | hex, contraste ≥4.5:1 vs `primary` | `--nh-on-primary` | `#ffffff` |
| `accent` | hex | `--nh-accent` | `#f6a300` |
| `on_accent` | hex, contraste ≥4.5:1 vs `accent` | `--nh-on-accent` | `#111111` |
| `background` | hex | `--nh-bg` | `#f7f7f7` |
| `text` | hex, contraste ≥4.5:1 vs `background` | `--nh-text` | `#111111` |
| `heading_font` | enum `{Montserrat, Inter}` | `--nh-font-heading` | `Montserrat` |
| `body_font` | enum `{Montserrat, Inter}` | `--nh-font-body` | `Inter` |
| `radius` | enum `{soft, medium, rounded}` | expansión server-side a `--nh-radius-md`, `--nh-radius-lg`, `--nh-radius-xl` | `12px`, `16px`, `24px` (`medium`) |

- **Cada par de contraste está declarado** (M-4): se agregan `on_primary`/`on_accent` para que el CTA tenga color de texto validable; el diseño anterior prometía contraste de CTA sin ese color.
- **Expansión exacta de radio (M-4):** el valor almacenado nunca se emite como variable singular. El servidor aplica esta tabla cerrada antes de construir el `<style>`:

  | Preset almacenado | `--nh-radius-md` | `--nh-radius-lg` | `--nh-radius-xl` |
  | --- | --- | --- | --- |
  | `soft` | `8px` | `12px` | `16px` |
  | `medium` | `12px` | `16px` | `24px` |
  | `rounded` | `16px` | `24px` | `32px` |

  `medium` reproduce los tokens actuales de `resources/css/app.css:58-62` y es el fallback. No existe `--nh-radius` singular en el contrato emitido.
- **Validación al guardar Y normalización al render (doble boundary, M-4):** al guardar, Filament valida hex estricto, enum de fuentes/radio y los tres pares de contraste WCAG AA (4.5:1). **Además**, el kernel `FrontendThemeService` **re-normaliza al render**: cualquier valor persistido que no matchee `^#[0-9a-fA-F]{6}$` o no esté en el enum se descarta y cae al fallback. Esto blinda contra datos legacy/importados fuera de Filament (p. ej. un valor malicioso que intente cerrar `<style>` con `}</style><script>`): la regex de color y el enum lo hacen imposible de emitir.
- **Tokens semánticos runtime, sin prometer un re-skin global (cierra M-4):** declarar `--nh-*` no cambia consumidores existentes. `app.css` debe exponer nombres semánticos con `@theme inline` para que Tailwind genere utilities cuyo valor se resuelve en runtime:

  ```css
  @theme inline {
    --color-brand-primary: var(--nh-primary, #091a5b);
    --color-on-brand-primary: var(--nh-on-primary, #ffffff);
    --color-brand-accent: var(--nh-accent, #f6a300);
    --color-on-brand-accent: var(--nh-on-accent, #111111);
    --color-site-background: var(--nh-bg, #f7f7f7);
    --color-site-text: var(--nh-text, #111111);
    --font-brand-heading: var(--nh-font-heading, 'Montserrat'), ui-sans-serif, system-ui, sans-serif;
    --font-brand-body: var(--nh-font-body, 'Inter'), ui-sans-serif, system-ui, sans-serif;
    --radius-brand-md: var(--nh-radius-md, 12px);
    --radius-brand-lg: var(--nh-radius-lg, 16px);
    --radius-brand-xl: var(--nh-radius-xl, 24px);
  }
  ```

  El layout inyecta los `--nh-*` validados en `:root`; el preset `radius` se expande server-side con la tabla anterior para evitar aritmética CSS dependiente del navegador. Los componentes públicos **brand-critical deben migrar** los roles de marca a utilities semánticas: `bg-brand-primary`, `text-on-brand-primary`, `bg-brand-accent`, `text-on-brand-accent`, `bg-site-background`, `text-site-text`, `font-brand-heading`, `font-brand-body` y `rounded-brand-{md,lg,xl}`. Esto incluye como mínimo botones/CTAs compartidos, header/nav, drawer, footer, shells de página y tarjetas públicas principales; `text-white` sobre CTA y `bg-navy-900` usado como superficie de marca no pueden permanecer en esos roles.
- **Alcance explícito:** v1 tematiza `primary`, `accent`, sus dos colores `on_*`, `background`, `text`, radios y las dos fuentes en roles semánticos. Tonos decorativos fijos (`navy-50/700/900`, `orange-50/100/600`, gradientes, sombras, bordes neutros y colores de WhatsApp/estado) pueden permanecer cuando su función sea decorativa o de estado y no representen un rol de marca configurable. No se recalculan escalas cromáticas ni se promete que cada shade fijo cambie. La revisión de Blade debe distinguir roles brand-critical de decoración; esa frontera queda cubierta por tests de clases emitidas y de variables runtime.
- **Fuentes (B-8):** allowlist = **Montserrat + Inter** (únicas en `vite.config.js:14-19`). **Poppins retirado** (se enmienda RFC-072). El toggle runtime nunca introduce una fuente no compilada; agregarla es cambio de `vite.config.js` + build en lote dedicado.
- **Test de seguridad (T-8b):** persistir directamente en BD un `theme.primary` malicioso (`#000}</style><script>alert(1)</script>`) y verificar que el render lo descarta y NO aparece en el HTML.

### 16.6 Estrategia ServiceType + FrontendService (cierra B-4, B-5, PD-3, PD-4)

**Regla única de elegibilidad** (unifica render y lead; RFC-074 + evidencia `LeadCaptureForm.php:109`):

```text
Servicio visible en ubicación L  ⇔  ServiceType.active = true  AND  FrontendService.show_in_L = true
Servicio aceptado en leads       ⇔  ServiceType.active = true  AND  FrontendService.allow_leads = true
```

`ServiceType.active = false` gana siempre: el servicio no se muestra ni acepta leads aunque `FrontendService` diga lo contrario.

**Autoridad (B-4, sin romper permisos previos):**

- `ServiceType.active` sigue siendo **operativo** y editable por `owner` **y** `admin` desde el `ServiceTypeResource` existente (grupo Configuración). **No se cambia** ese permiso.
- El área frontend **owner-only** edita `FrontendService` (contenido + `show_in_*` + `allow_leads`). No expone `ServiceType.active` como campo editorial: lo **lee** para calcular elegibilidad. Así admin conserva el control operativo y owner gana el editorial, sobre la misma verdad (`ServiceType.active`), sin drift ni cambio silencioso de permisos.

**Elegibilidad FAIL-CLOSED (cierra M-2):** tras el backfill, **la ausencia de `FrontendService` = servicio NO elegible** (no visible, no acepta leads). Se descarta el fallback `allow_leads=true` del diseño anterior (fallaba abierto). Consecuencia: si `admin` crea un `ServiceType` activo nuevo (puede hacerlo, `ServiceTypeResource:28-60`), **no recibe leads ni se muestra hasta que el `owner` cree su `FrontendService`** con `allow_leads=true`. Esto respeta la aprobación editorial owner-only sin quitarle a admin el control operativo.

**Cambio aditivo en `LeadCaptureForm` (mismo lote que `inversion`):**

- La regla `service_type` valida contra **join `service_types.active=true` INNER JOIN `frontend_services.allow_leads=true`** (sin fila `frontend_services` ⇒ rechazado).
- **Validación + `Lead::create()` atómicos (cierra M-2 carrera):** hoy validación y creación no son atómicas (`LeadCaptureForm.php:76-93`). Se envuelven en `DB::transaction` con **re-verificación de elegibilidad bajo `lockForUpdate()`** antes del insert. Como la elegibilidad depende de **DOS autoridades** (`service_types.active` + `frontend_services.allow_leads`), NO basta bloquear una fila: se bloquean **ambas en orden determinista** (`service_types` por su `code`, luego `frontend_services` por su `id`) para evitar deadlocks y para que una desactivación concurrente de cualquiera de las dos no admita un lead posterior a la validación.
- **Mismo protocolo para las mutaciones de las autoridades (cierra M-2):** quien cambie `ServiceType.active` (admin, desde `ServiceTypeResource`) o `FrontendService.allow_leads`/`show_*` (owner) debe hacerlo en `DB::transaction` tomando el mismo par de locks en el mismo orden. Así lectura de elegibilidad y escritura de cualquiera de las dos autoridades quedan serializadas sobre el mismo orden de bloqueo; no hay ventana donde el lead form vea un estado mixto.

**Inversión inmobiliaria — backfill NO destructivo (cierra B-5, PD-4, M-5):**

- **Operación idempotente invocable y testeable** (no `updateOrInsert`, que sobrescribe): una acción `SeedInversionService` con lógica **insert-if-missing** (`insertOrIgnore` / `firstOrCreate`) para `service_types` code `inversion` y para las filas `frontend_services` de los 4 codes. **No toca filas existentes** → si una instalación ya tiene `inversion` personalizado o inactivo, **no lo reactiva ni sobrescribe**.
- La migración aditiva **invoca esa acción**; la acción también se puede llamar dos veces en un test contra filas previamente personalizadas y verificar que no cambia estado ni contenido (T-12 real, ya no depende de "migrate no reejecuta").
- Estado inicial en instalación limpia: `inversion` → `active=true`, `show_in_home/services=true`, **`allow_leads=false`** (hoy se muestra hardcodeado pero el lead form no lo ofrece; `false` preserva la recepción de leads). Los 3 existentes → `allow_leads=true`, `show_in_*=true` (preservan el form actual). Contenido editorial inicial desde `servicios.blade.php:25-28`.
- `ServiceTypeSeeder` suma `inversion` (dev/test); la **fuente productiva es la migración/acción**.

### 16.7 Fallbacks exactos (cierra PD-5, PD-9 parte 1)

**Semántica tri-estado (PD-5), precisada por entidad — un fallback nunca revive lo apagado:**

- **No inicializado** (fila/tabla ausente o campo null) → **fallback** (valor hardcodeado actual).
- **Inicializado y activo** → valor guardado.
- **Deshabilitado deliberadamente** → depende de la entidad (M-6):
  - **Sección** (`is_enabled=false`), **servicio** (`show_in_*/allow_leads=false`), **link de nav/footer** (`enabled=false`) → **se omite del render**; no hay fallback.
  - **Página** (`FrontendPage.is_enabled=false`) → **NO es 404/410**: las rutas institucionales son incondicionales (`routes/web.php:19-26`) y deben seguir alcanzables por SEO/links existentes. `is_enabled=false` significa "esta página **no la gestiona el CMS**": se renderiza el **fallback hardcodeado** con HTTP 200 e indexable. Ocultar una página del todo queda fuera de alcance v1 (las rutas son fijas). Precedencia de render: `published_revision` (si enabled) → fallback hardcodeado.

**Valores de fallback (exactos, verificados en código):**

| Dominio | Fallback exacto | Fuente |
| --- | --- | --- |
| Nav | `[Inicio /, Nosotros, Servicios, Proyectos, Inmobiliaria, Inversionistas, Contacto]` | `public.blade.php:64` |
| Contacto email | `hola@newhauz.com.mx` | `public.blade.php:162` |
| WhatsApp | `524422722623` → `https://wa.me/524422722623` | `public.blade.php:179` |
| Marca | logos `newhauz-on-light.svg` / `newhauz-on-dark.svg`, favicon `newhauz_monogram.ico`, OG `meta_image_newhauz.jpg` | `public.blade.php:24,48,60,136` |
| Servicios | 4 items con títulos/bullets/imágenes de `servicios.blade.php` | `servicios.blade.php:25-28` |
| Tema | primary `#091a5b`, accent `#f6a300`, fonts Montserrat/Inter, radios actuales | `app.css:12-52` |
| CTA header | **"Agenda una cita"** → `route('leads.create')` (corrige Mn-1; el valor real, no "Contacto") | `public.blade.php:80-84` |
| Hero `home` — slides | **4 URLs Unsplash** en orden arquitectura, construcción, comercialización, inversión, con la animación y el overlay navy actuales | `welcome.blade.php:12-16,20-28,31` |
| Hero `nosotros` — slides (enmienda §18.18) | `images/nosotros/header_nosotros.png` (imagen única, sin rotación) | `site/nosotros.blade.php:11` |
| Hero `servicios` — slides (enmienda §18.18) | `images/servicios/header_servicios.png` (imagen única, sin rotación) | `site/servicios.blade.php:11` |
| Hero `inversionistas` — slides (enmienda §18.18) | `images/inversionistas/header_inversionistas.png` (imagen única, sin rotación) | `site/inversionistas.blade.php:11` |
| Hero `contacto` — slides (enmienda §18.18) | **Sin imagen de fondo** (superficie de marca plana) | `/contacto` = `LeadCaptureController` |
| Hero home — CTAs | **primario** "Ver Propiedades" → `route('inmuebles.index')`; **secundario** "Conocer Proyectos" → `route('proyectos')` (ambos, en ese orden) | `welcome.blade.php:46,47-49` |

### 16.8 Caché e invalidación (cierra B-6, PD-10)

- **Kernel desde el Lote A** (B-6): interfaz `FrontendContent` (contrato de lectura por dominio con fallback) + `FrontendPublisher` (publicar/invalidar). Los servicios concretos (`FrontendSettingsService`, `FrontendThemeService`, `FrontendNavigationService`, `FrontendServicesService`, `FrontendPageContentService` de RFC-076) implementan el contrato incrementalmente. RFC-076 deja de ser "capa que aparece en F": F es **integración/endurecimiento**, no primera aparición.
- **Claves por generación (cierra M-3, vence la carrera de refill):** las keys completas incluyen un contador global de revisión — `frontend:g{N}:settings`, `frontend:g{N}:theme`, `frontend:g{N}:navigation`, `frontend:g{N}:services:{location}` y `frontend:g{N}:page:{key}`. `{location}` es obligatoriamente `home` o `servicios`, por lo que ambas listas no colisionan. `{N}` = generación actual. Al mutar, `N` sube y las lecturas pasan a la key nueva; **un refill concurrente que escribió bajo la key vieja `g{N-1}` nunca se vuelve a leer**.
- **Almacenamiento durable con incremento atómico (cierra M-3):** `N` NO usa `Cache::increment()` — en el store `database` real `increment()` devuelve `false` si la clave aún no existe (`vendor/.../Cache/DatabaseStore.php:273-286`), así que una inicialización ingenua o concurrente pierde bumps. En su lugar, `N` vive en una **columna de una tabla propia** `frontend_cache_generation` (una fila, `id=1`, `generation bigint not null default 1`), **sembrada por la migración** con valor inicial `1` (idempotente, `firstOrCreate`). El bump es un **`UPDATE ... SET generation = generation + 1 RETURNING generation`** atómico a nivel BD (una sola sentencia, sin read-modify-write en PHP): dos mutaciones concurrentes producen dos incrementos distintos, nunca uno perdido. El valor leído se cachea en memoria de request y se relee tras cada bump.
- **TTL corto como red de seguridad:** todas las entradas usan `300 s`; expira basura/staleness residual, pero no sustituye la invalidación.
- **Invalidación única para TODAS las mutaciones que alteran render (M-3):** incrementan la generación los observers de `FrontendSetting`, `FrontendService`, `FrontendPage`, `FrontendSection`, **`ServiceType`** (su `active` afecta elegibilidad) y **`Media`** (alta/baja de imágenes de marca/servicio/sección). El bump se hace **dentro de `DB::afterCommit`** y es el único protocolo. Quedan prohibidos `Cache::forget`, `delete`, `flush` o clears por recurso/key; el namespace anterior se vuelve inaccesible y el TTL recolecta sus entradas.
- **Tests obligatorios (M-3):** (1) migrar desde cero crea exactamente la fila `id=1, generation=1`; (2) con **dos conexiones PostgreSQL independientes**, dos bumps concurrentes terminan en `generation=3`, sin incremento perdido; (3) con `CACHE_STORE=database`, conexión A entra en refill (lee key vieja), conexión B publica (bump), A escribe bajo la key vieja y la siguiente lectura devuelve el dato **nuevo** de `g{N}`, no el repoblado; (4) `services:home` y `services:servicios` usan keys distintas bajo la misma generación.

### 16.9 Preview / publicación (cierra B-2, PD-1)

**Estrategia por entidad (explícita):**

| Entidad | Estrategia | Publicación |
| --- | --- | --- |
| `FrontendSetting` (identidad, contacto, SEO, CTAs, nav, tema, social) | **A — inmediata** | Guardar = publicar. Validación dura + invalidación `afterCommit`. Su "preview" es el sitio en vivo. |
| `FrontendService` — disponibilidad (`show_*`, `allow_leads`) | **A — inmediata** | Efecto al instante (lead-safety). |
| `FrontendService` — contenido editorial | **A — inmediata** (enmienda C-G-1) | Guardar = publicar con validación dura + bump `afterCommit`. Ver enmienda abajo. |
| `FrontendPage` (+ sus `FrontendSection`) | **B — snapshot de revisión completo** | Ver abajo. |

> **⚠️ Enmienda normativa C-G-1 (2026-07-24, reconciliación tras la auditoría del Lote G).** El **contenido editorial de `FrontendService`** pasa de estrategia **B** a estrategia **A — inmediata (guardar = publicar)**, alineando el contrato con lo implementado y aprobado en los Lotes D/E/G. **Justificación:** un servicio es una fila 1:1 con `service_types.code`, editable solo por el owner, sin composición multi-sección; el valor de un flujo draft→publicado (revisión, preview, snapshot) no compensa su costo frente a la disponibilidad (`show_*`/`allow_leads`) que **ya** es inmediata por lead-safety — separar contenido y disponibilidad en dos estrategias sobre la misma fila añade complejidad sin beneficio de producto. En consecuencia quedan **retirados del contrato vigente**: las columnas `draft_payload`/`published_payload`/`draft_revision`/`published_by`/`published_at` de `FrontendService`, el `FrontendServicePublisher`, `expected_draft_revision_service`, el preview editorial de servicios y el test obligatorio **T-11s** (`FrontendServicePublishConcurrencyTest`). La validación dura de RFC-074 (elegibilidad fail-closed, CTA derivado) se ejecuta **al guardar**. Las páginas institucionales (`FrontendPage`) siguen siendo la **única** entidad de estrategia B con preview owner-only. Esta enmienda es fuente única; donde RFC-074 o RFC-077 aún describan el flujo B de servicios, prevalece esta tabla.

**Publicación de página como revisión completa y atómica (cierra C-2):**

**Protocolo de concurrencia compartido (cierra C-2) — la página es el punto de serialización:**

- **Toda escritura de trabajo** sobre una página (editar/crear/reordenar/borrar una `FrontendSection`, o editar SEO/`is_enabled` de la página) se hace **dentro de `DB::transaction` tomando primero `FrontendPage::lockForUpdate()` de su fila** y las secciones afectadas por `id ASC`. Si el JSON final contiene UUIDs, **`FrontendMediaReference`** **valida** cada uno (existencia, owner y colección) antes de escribir. **No necesita bloquear `media`**: sin borrado físico en v1 (§16.4) ninguna fila `media` desaparece, así que un UUID validado no puede quedar colgante. *(Esto sigue siendo correcto: el lock de `media` que introduce §18.18 aplica **solo** a la publicación con promoción y al job, no a las mutaciones draft.)* Solo entonces aplica la mutación e incrementa `draft_revision` en la misma transacción. No basta bloquear al publicar: el contador debe cambiar en **cada** mutación draft confirmada para invalidar pantallas abiertas previamente.
- Publicar toma **el mismo lock de página**, además `sections()->lockForUpdate()->orderBy('id')`. Como todos —editores de sección y publicador— respetan el mismo orden, el snapshot se arma sobre estado consistente. ~~**No se bloquea `media`**~~ ⚠️ **PRECISADO por §18.18 (Épica 12.1):** la afirmación sigue vigente **para las mutaciones draft** (§16.3: editar/crear/reordenar/borrar sección no bloquea `media`, porque en v1 nada la borra y un `media_id` validado no queda colgante). **Pero la PUBLICACIÓN con promoción de media SÍ bloquea `media`**: al marcar `pending_promotion` y decidir la promoción, publisher y job toman el orden global **`page → sections(id ASC) → media(uuid ASC)`**. Ese lock no protege contra borrado (no existe), sino contra la **carrera de referencia**: sin él, un job podría promover una media que una publicación concurrente acaba de retirar. Un solo orden para todos los actores ⇒ sin ciclos. Contrato completo: `docs/epicas/epica-12-1-mejora-ux-hero.md` §7.9.
- `draft_revision` da control **optimista real**: al abrir la pantalla, la UI conserva `expected_draft_revision`. El publicador la recibe como argumento obligatorio y, **después de adquirir los locks pero antes de construir el snapshot**, compara con la revisión actual. Si difiere, aborta la transacción con conflicto de estado (HTTP/acción equivalente a 409), no publica nada y muestra "hubo cambios, recargá". `revision` queda reservado como número de publicación y no sustituye a `draft_revision`.

Pasos de la publicación:

1. `DB::transaction`; `FrontendPage::lockForUpdate()` + `FrontendSection::where(page)->lockForUpdate()->orderBy('id')`.
2. Comparar `expected_draft_revision` con `draft_revision`; si no coincide, rechazar como stale sin efectos.
3. Se **arma el snapshot completo** desde el estado de trabajo bloqueado: `{ is_enabled, seo, sections ordenadas [{section_key, type, sort_order, is_enabled, payload_con_media_ids}] }`. **`media` viaja como `media_id` (UUID) únicamente**, no URL (cierra C-3 ambigüedad); el render resuelve `id`→URL pública.
4. Se extraen todos los UUID del snapshot y **`FrontendMediaReference`** **valida** existencia, owner y colección. Cualquier UUID faltante o inelegible aborta todo el publish. ~~No hace falta bloquear `media`~~ ⚠️ **PRECISADO por §18.18 punto (4):** la validación en sí no necesita lock (en v1 nada borra media), pero **la publicación con promoción SÍ bloquea `media`** en el paso 4-bis, por un motivo distinto: la **carrera de referencia**.
4-bis. Se leen los UUID de la revisión **anterior** (bajo el lock ya tomado), se calculan `added`/`removed`, y se toman `lockForUpdate()` sobre esas filas `media` **ordenadas por `uuid ASC`**, haciendo **merge** de `custom_properties` (nunca sobrescritura). Secuencia completa: `docs/epicas/epica-12-1-mejora-ux-hero.md` §7.12.
5. Se escribe `published_revision`, `published_by`, `published_at`, `revision = revision + 1` y se marcan las media referenciadas `pending_promotion` en la transacción. Publicar no incrementa `draft_revision` porque no muta el estado de trabajo.
6. **`DB::afterCommit`** → (a) dispatch de `PromoteFrontendMedia` (§16.4, fuera de la transacción BD) y (b) bump de generación de caché (§16.8). `ReconcileFrontendMediaPromotions` recupera callbacks/dispatch perdidos.

- **T-11 usa dos conexiones PostgreSQL reales:** la UI A lee `draft_revision=N`; la conexión B confirma una mutación draft e incrementa a `N+1`; A intenta publicar con `expected_draft_revision=N` y debe ser rechazada sin cambiar `published_revision`. Otro caso mantiene la edición concurrente durante publicación para probar que los locks deterministas impiden snapshot mixto y registran `published_by`/`revision`.
- ~~**Publicación editorial de servicio**~~ — **RETIRADO por la enmienda C-G-1 (§16.9):** el contenido editorial de servicios es estrategia A (guardar=publicar), sin publisher, `draft_payload`/`published_payload`, `expected_draft_revision_service` ni preview propio. La validación dura (elegibilidad, `media_id`, CTA derivado) corre **al guardar**, con bump `afterCommit`.
- **Preview owner-only** (RFC-077): ruta `/admin/frontend/preview/{pageKey}`; `pageKey` **validado contra enum/allowlist server-side** → `pageKey` inválido devuelve **404 uniforme** (no filtra existencia). Panel + gate `frontend.manage` + rol owner; renderiza el layout público con estado **de trabajo**; `noindex,nofollow`; sesión (sin token reutilizable); banner "PREVIEW — no es producción".
- **Sin historial avanzado en v1:** una sola `published_revision` por página + su actor; `revision` incremental deja la puerta a versionar sin rediseño.

**Contratos de CTA, footer y SEO (cierra M-6):**

- **CTA value object tipado** `{ label, type, target }`, `type ∈ {route, url, whatsapp, tel, mailto}`. Un **resolver central** valida por tipo: `route` ∈ allowlist de rutas nombradas; `url` HTTPS; `whatsapp/tel` normalizados; `mailto` email válido. Prohíbe `javascript:`/protocolos inseguros. Usado por CTAs de `frontend_settings`, footer y secciones `cta`.
- **Footer tipado:** `frontend_settings.footer` (JSON validado, §16.1); sin HTML libre.
- **SEO (precedencia y comportamiento definidos):** por página `published_revision.seo` → si falta, `frontend_settings.default_*` → si falta, fallback hardcodeado. `canonical` = URL de la ruta nombrada. `sitemap.xml` lista solo rutas públicas existentes. `JSON-LD` `Organization` (desde `frontend_settings`) + `WebSite`. Página con `is_enabled=false` → **indexable** (renderiza fallback, §16.7); el **preview** siempre `noindex`.

### 16.10 Lotes de implementación A→G

Orden **kernel-first** (B-6) y auditoría bloqueante por lote (B-1). Cada lote abre el siguiente solo con veredicto `APROBADO`.

| Lote | Alcance | Cierra | RFC |
| --- | --- | --- | --- |
| **A — Kernel + Perfil** | Permiso `frontend.manage` **por migración idempotente** + registro de policies (rol+permiso); `frontend_settings` singleton (`CHECK+UNIQUE`, no delete); tabla `frontend_cache_generation` inicializada; disco `frontend-private`; contrato `FrontendContent`/`FrontendPublisher`; cache por generación + invalidación `afterCommit`; Resource owner-only identidad/contacto/SEO; fallbacks. | B-3, B-6, C-5, D-1 | 071, 076 |
| **B — Tema visual** | Schema `theme` autoritativo (con pares de contraste); validación al guardar + normalización al render; `--nh-*`; allowlist Montserrat/Inter; presets seguros. | B-8, M-4 | 072 |
| **C — Navegación/footer/CTAs** | Nav allowlist de rutas nombradas; **CTA value object tipado + resolver**; footer tipado; fallback Inicio+Contacto; accesibilidad móvil (teclado, foco, Escape, `aria-expanded`, reduce-motion). | PD-7, M-6 | 073 |
| **D — Servicios** | `frontend_services` (1:1 `code`, fail-closed); regla única de elegibilidad; **acción `SeedInversionService` no destructiva**; **`LeadCaptureForm` con join `allow_leads` + validación/creación atómica bajo lock**. | B-4, B-5, M-2, M-5 | 074 |
| **E — Contenido de páginas** | Registry canónico de secciones (§16.1.1); `frontend_pages` con `published_revision` completo + `published_by`; `frontend_sections` de trabajo; schemas por tipo; media draft privada→promoción; publicación por revisión atómica con lock. | B-2, C-2, C-3, M-1 | 075 |
| **F — Render, caché y cutover** | Consolidación del kernel; SEO (precedencia, canonical **derivado de ruta**, sitemap, JSON-LD; **página deshabilitada = 200 indexable con fallback**, §16.7); prueba con `CACHE_STORE=database`; **cutover del Blade hardcodeado** a lecturas del kernel, preservando el sitio actual (PD-9). | PD-9, PD-10 | 076 |
| **G — Preview/publicación/QA** | UX de preview owner-only; preflight validation; publicación transaccional; QA visual y de accesibilidad; documentación en Ayuda (Épica 11). | PD-1 | 077 |

### 16.11 Matriz de tests (ampliada por M-7)

Concurrencia = **dos conexiones PostgreSQL independientes** (no llamadas secuenciales).

| # | Test | Tipo | Lote |
| --- | --- | --- | --- |
| T-1 | Autorización: `owner` (rol+permiso) 200; `admin`/`agente`/`arquitectura`/`proyectos` → 403 en cada Resource/Page/preview y por URL directa | Feature/HTTP | A→G |
| T-1b | **Deploy sin seeder:** tras `migrate --force` (sin `db:seed`), `frontend.manage` existe y owner lo tiene | Feature | A |
| T-1c | `PermissionSeederTest` = 15 permisos; `frontend.manage` solo en owner | Feature | A |
| T-2 | Singleton: inserción concurrente (2 conexiones) crea **una** fila; `CHECK(singleton_key='default')` rechaza otro valor; `delete/forceDelete` prohibidos | Feature | A |
| T-3 | Render público usa la revisión publicada | Feature | A→F |
| T-4 | Fallback exacto cuando falta config (§16.7), incl. "Agenda una cita" | Feature | A→F |
| T-5 | Sección/servicio/link **deshabilitado** se omite; **página** deshabilitada renderiza fallback con 200 indexable | Feature | E, F |
| T-6 | Servicio inactivo o `allow_leads=false` **o sin `FrontendService`** (fail-closed) no aparece ni en el form | Feature | D |
| T-6b | **Admin crea `ServiceType` activo sin `FrontendService`** → no recibe leads ni se muestra | Feature | D |
| T-7 | POST de lead contra servicio inelegible rechazado aunque se manipule el form | Feature | D |
| T-7b | **Desactivación concurrente:** lock/transacción impide crear lead posterior a la validación | Feature | D |
| T-8 | Contraste bajo o fuente fuera de allowlist → rechazo al guardar (los 3 pares) | Unit/Feature | B |
| T-8b | **Theme persistido malicioso** (`}</style><script>`) se descarta en el render y no aparece en el HTML | Feature | B |
| T-8c | **Theme runtime emitido:** primary/accent, ambos `on_*`, background, text y fuentes producen variables `--nh-*`; cada preset `soft|medium|rounded` expande exactamente los tres radios `md/lg/xl`; utilities semánticas se consumen y componentes brand-critical no dependen de `text-white`/shades fijos para esos roles | Feature/DOM + build | B, F |
| T-9 | Media: MIME/tamaño/dimensiones; **SVG rechazado**; falta de `alt` no-decorativo rechazada | Feature | A, D, E |
| T-9b | **Media draft directa:** 403/404 anónimo antes de publicar; pública después | Feature | E, G |
| T-9c | **Promoción durable:** rollback no copia ni encola; fallo de enqueue queda recuperable; reconciliación vuelve a encolar; retry del job es idempotente y no duplica archivo/estado | Feature/Queue | E, G |
| T-10 | Publicar/mutar invalida cache con `CACHE_STORE=database` (Setting, ServiceType, FrontendService, Page, Section, **Media**) | Feature | A, F |
| T-10a | **Tabla de generación:** migración inicializa exactamente `id=1, generation=1`; dos bumps concurrentes con conexiones PostgreSQL terminan en `3` | Feature/DB | A, F |
| T-10b | **Carrera de refill** (2 conexiones): un refill viejo no se lee tras bump de generación | Feature | F |
| T-10c | **Scope de servicios:** `services:home` y `services:servicios` usan `frontend:g{N}:services:{location}` distintas; invalidar solo hace bump, nunca clear dirigido | Feature/Cache | D, F |
| T-11 | **Stale publisher de página:** UI A lee `draft_revision=N`, conexión B muta draft → `N+1`, A publica con `expected=N` y se rechaza sin tocar snapshot; otro caso de 2 conexiones prueba locks deterministas/snapshot completo y `published_by` | Feature/DB | E, G |
| ~~T-11s~~ | ~~**Stale publisher de servicio**~~ — **RETIRADO por la enmienda C-G-1 (§16.9):** el contenido editorial de servicios es estrategia A (guardar=publicar), no existe `FrontendServicePublisher` ni `draft_revision` de servicio. No aplica. | — | — |
| T-11b | Preview muestra estado de trabajo; público muestra revisión publicada | Feature | G |
| T-12 | Acción `SeedInversionService` **idempotente y no destructiva**: correr 2× sobre filas personalizadas no cambia estado ni contenido | Feature | D |
| T-13 | Preview `noindex,nofollow`; requiere owner; **pageKey inválido → 404 uniforme**; sin token reusable | Feature | G |
| T-13b | **Schema por sección:** payload que no matchea el schema del `type` se rechaza | Feature | E |
| T-13c | **CTA tipado:** `type` fuera de allowlist o `javascript:`/no-HTTPS rechazado por el resolver | Feature | C |
| T-13c2 | **Forma CTA:** `hero`/`cta` aceptan solo `{label,type,target}` anidado y rechazan campos planos legacy | Feature | C, E |
| T-13d | **SEO:** precedencia página→settings→fallback; canonical; `sitemap.xml`; JSON-LD Organization | Feature | F |
| T-13e | **Nav móvil DOM:** teclado, foco, `Escape` cierra, `aria-expanded`, `prefers-reduced-motion` | Feature/DOM | C |
| T-13f | **CTA de servicio:** un `?service=<code>` elegible preselecciona `LeadCaptureForm`; código inválido/inelegible se ignora uniformemente y el POST sigue fail-closed | Feature/Livewire | D, F |
| T-13g | **Footer deshabilitado:** links con `enabled=false` no aparecen y no son revividos por fallback | Feature | C, F |
| T-13h | **Schema persistido de navegación (N):** `navigation` guarda exactamente `{key,label,enabled,sort_order,open_in_new_tab}`; `route_name` u otra clave desconocida se rechaza; `url`/`active_pattern` se derivan de `key` y no son persistibles; el orden lo fija `sort_order`, no el índice del array; `open_in_new_tab=true` se normaliza a `false` en v1; guardar con **todos** los links `enabled=false` se bloquea con error de validación | Feature | C |
| T-13h2 | **Key `home`, no `inicio`, y mapa key→ruta:** la allowlist acepta `home` y **rechaza `inicio`**; cada key resuelve al nombre de ruta real de `routes/web.php:13-26` — en particular `inmuebles`→`inmuebles.index` y `contacto`→`leads.create`, que NO coinciden con la key; `route($key)` ingenuo falla el test | Feature | C, F |
| T-14 | Sin regresión: home conserva `Property::featured()`, `Property::opportunity()` y `Project::is_featured`; el registry cubre todos los `<section>` editables de Nosotros/Servicios/Inversionistas/Contacto; no inventa `inversionistas.metrics`; buscador, formulario y canales operativos siguen kernel-only | Feature | E, F, G |
| T-14b | **Hero slides, cutover y fallback:** sin slides publicados el home emite **las 4 URLs de `welcome.blade.php:12-16` en orden** con su animación/overlay (**fallback home-scoped**; las demás páginas caen a su propio fondo — §18.18); las cinco páginas se prueban contra la matriz de `epica-12-1-mejora-ux-hero.md` §8; publicar 1–6 slides las reemplaza respetando `sort_order`; `slides: []` publicado NO revive el fallback; `decorative:true` no emite `alt` ni `<img>`; `decorative:false` sin `alt` se rechaza al guardar; más de 6 slides se rechaza | Feature/DOM | E, F |
| T-14c | **Ambos CTAs del hero sobreviven al cutover:** el home renderiza el primario "Ver Propiedades"→`inmuebles.index` **y** el secundario "Conocer Proyectos"→`proyectos` (`welcome.blade.php:46-49`), en ese orden, tanto por fallback como publicados; `secondary_cta` acepta un value object válido y no está fijado a `null` | Feature/DOM | C, E, F |
| T-9d | **Ninguna ruta del sistema borra media (las tres cerradas):** (a) guardar un formulario real de `NonDestructiveMediaUpload` **retirando** una imagen del estado no borra fila, original, conversiones ni responsive images, y la `published_revision` sigue resolviendo su `media_id`; se prueba en `FrontendSetting` (marca), `FrontendService` y `FrontendSection`; (b) **reemplazar** una imagen conserva la anterior y solo cambia el `media_id` referenciado; (c) borrar (soft) una `FrontendSection` conserva sus archivos y `forceDelete` retorna 403/false por policy; (d) **ninguna colección de Épica 12 declara `singleFile()` ni `onlyKeepLatest()`** (assert sobre `registerMediaCollections()`), así que `clearMediaCollectionExcept()` nunca se dispara (`FileAdder.php:645-651`); (e) tras toda la secuencia el **conteo de archivos en disco nunca decrece** | Feature/Filament/Filesystem | A, D, E, G |
| T-9o | **Reporte read-only de media no referenciada:** `frontend:media:report-unreferenced` lista media sin referencias en ningún JSON draft, publicado ni de sección soft-deleted, **ni en los cuatro `*_media_id` de marca de `frontend_settings`**, con antigüedad y tamaño; **no modifica ninguna fila ni ningún archivo** (se comparan conteos y checksums antes/después); no incluye media referenciada ni colecciones fuera de alcance | Feature/Console | E, G |
| T-9e | **Índices parciales vs. SoftDeletes:** tras borrar (soft) una sección se puede **recrear la misma `section_key` y reutilizar el mismo `sort_order`** en esa página; tras borrar un `FrontendService` se puede recrear el mismo `service_type_code`; ningún UNIQUE global sobrevive en estas tablas (assert sobre el esquema real de PostgreSQL) | Feature/DB | D, E |
| T-9f | **Reconciliación programada:** el scheduler registra `frontend:media:reconcile` con `withoutOverlapping()->onOneServer()`; el comando reencola la promoción de media publicada que quedó sin promover y es idempotente. Un caso afirma que **no existe ningún comando programado que borre archivos** (§16.4) | Feature/Schedule | E, G |
| T-15 | **Instalación limpia sin seeders (N-1):** tras `migrate --force` existen las cinco `frontend_pages` (`home, nosotros, servicios, inversionistas, contacto`) con `is_enabled=false`, el owner las ve en el CMS y **el frontend público es byte-idéntico al fallback actual** | Feature/DB | A, E |
| T-15b | **`SeedFrontendPages` no destructiva:** segunda ejecución sobre páginas habilitadas/personalizadas no cambia `is_enabled`, `seo`, `published_revision` ni `draft_revision` | Feature | A, E |

**Casos nominales obligatorios para los seis parciales:**

- `FrontendPublishConcurrencyTest::test_stale_publisher_is_rejected_after_draft_mutation`.
- ~~`FrontendServicePublishConcurrencyTest::test_stale_service_publisher_is_rejected_after_draft_payload_mutation`~~ — **RETIRADO por C-G-1 (§16.9):** servicios son estrategia A; no hay publisher de servicio.
- `FrontendPageSectionRegistryTest::test_registry_covers_current_institutional_blade_regions`.
- `FrontendPageSectionRegistryTest::test_investor_registry_does_not_invent_metrics_section`.
- `FrontendContactKernelRegionsTest::test_form_and_contact_channels_are_not_editable_sections`.
- `FrontendHomeSectionsTest::test_opportunity_properties_are_independent_from_featured_properties_and_projects`.
- `FrontendCacheGenerationTest::test_migration_initializes_generation_to_one`.
- `FrontendCacheGenerationTest::test_two_concurrent_bumps_are_both_persisted`.
- `FrontendServicesCacheTest::test_home_and_services_locations_use_distinct_generation_keys`.
- `FrontendMediaPromotionTest::test_rollback_does_not_copy_or_enqueue_media`.
- `FrontendMediaPromotionTest::test_enqueue_failure_is_recovered_by_reconciliation`.
- `FrontendMediaPromotionTest::test_promotion_retry_is_idempotent`.
- `FrontendServiceCtaTest::test_valid_service_query_preselects_livewire_service_type`.
- `FrontendServiceCtaTest::test_invalid_or_ineligible_service_query_is_ignored`.
- `FrontendFooterRenderTest::test_disabled_footer_links_are_not_rendered_or_replaced_by_fallback`.
- `FrontendThemeRuntimeTest::test_theme_emits_all_semantic_runtime_variables`.
- `FrontendThemeRuntimeTest::test_each_radius_preset_expands_to_exact_md_lg_xl_values`.
- `FrontendThemeRuntimeTest::test_brand_critical_blade_components_use_semantic_theme_utilities`.
- `FrontendSectionValidationTest::test_cta_payload_requires_nested_typed_value_object`.

**Casos nominales de los cierres P5–P7** (completan T-13h2, T-9e, T-9f y T-14c):

- `FrontendNavigationSchemaTest::test_navigation_persists_only_key_label_enabled_sort_order_and_new_tab` (T-13h).
- `FrontendNavigationSchemaTest::test_saving_all_links_disabled_is_rejected` (T-13h).
- `FrontendNavigationSchemaTest::test_allowlist_accepts_home_key_and_rejects_inicio` (T-13h2).
- `FrontendNavigationSchemaTest::test_each_key_resolves_to_its_real_laravel_route_name` (T-13h2 — cubre `inmuebles`→`inmuebles.index` y `contacto`→`leads.create`).
- `FrontendMediaRetentionTest::test_soft_deleted_section_keeps_media_referenced_by_published_revision` (T-9d).
- `FrontendMediaRetentionTest::test_force_delete_is_denied_by_policy` (T-9d).
- `FrontendMediaRetentionTest::test_removing_an_image_from_the_filament_form_keeps_row_and_all_files` (T-9d; ejercita el guardado real del componente).
- `FrontendMediaRetentionTest::test_replacing_an_image_keeps_the_previous_one_and_only_changes_the_referenced_uuid` (T-9d).
- `FrontendMediaRetentionTest::test_published_revision_still_resolves_its_media_id_after_editorial_removal` (T-9d).
- `FrontendMediaRetentionTest::test_no_epic12_collection_declares_single_file_or_only_keep_latest` (T-9d).
- `FrontendMediaRetentionTest::test_file_count_on_disk_never_decreases_through_the_full_sequence` (T-9d).
- `FrontendMediaMaintenanceScheduleTest::test_reconcile_is_scheduled_with_overlap_and_single_server_protection` (T-9f).
- `FrontendMediaMaintenanceScheduleTest::test_no_destructive_command_is_scheduled` (T-9f).
- `FrontendUnreferencedMediaReportTest::test_report_lists_unreferenced_editorial_media_with_age_and_size` (T-9o).
- `FrontendUnreferencedMediaReportTest::test_report_ignores_media_referenced_by_draft_published_or_soft_deleted_sections` (T-9o).
- `FrontendUnreferencedMediaReportTest::test_report_changes_no_row_and_no_file` (T-9o).
- `FrontendUnreferencedMediaReportTest::test_brand_media_referenced_by_settings_uuid_is_never_reported_as_unreferenced` (T-9o/B-1).
- `FrontendPartialIndexTest::test_section_key_and_sort_order_are_reusable_after_soft_delete` (T-9e).
- `FrontendPartialIndexTest::test_service_type_code_is_reusable_after_soft_delete` (T-9e).
- `FrontendPartialIndexTest::test_named_partial_unique_indexes_exist_and_no_global_unique_survives` (T-9e — consulta `pg_indexes`).
- `FrontendHeroSlidesTest::test_fallback_emits_the_four_current_slides_in_order` (T-14b).
- `FrontendHeroSlidesTest::test_decorative_slide_emits_no_alt_and_non_decorative_requires_alt` (T-14b).
- `FrontendHeroSlidesTest::test_both_hero_ctas_survive_cutover` (T-14c).
- `FrontendCanonicalPagesInstallTest::test_migrate_without_seeders_creates_five_disabled_pages` (T-15).
- `FrontendCanonicalPagesInstallTest::test_second_run_does_not_modify_customized_pages` (T-15b).

### 16.12 Cierre explícito de bloqueantes

| ID | Estado | Cierre |
| --- | --- | --- |
| **B-1** | 🟢 | Siete ciclos implementación→auditoría→corrección→reauditoría con gate `APROBADO` por lote (§16.10 + §14 regla de gate) + auditoría integral final. |
| **B-2** | 🟢 | Estrategia por entidad definida (§16.9): A inmediata para `FrontendSetting` y disponibilidad de servicios; B draft/publicado transaccional para contenido de servicios y páginas. |
| **B-3** | 🟢 | `singleton_key` constante con **`CHECK(singleton_key='default') + UNIQUE`** (no basta el UNIQUE sobre default) + `firstOrCreate` + `delete/forceDelete` prohibidos + test de concurrencia con 2 conexiones T-2 (§16.1). |
| **B-4** | 🟢 | `ServiceType.active` operativo (owner+admin, sin cambios); frontend owner-only edita `FrontendService` y solo **lee** `active`. Regla única de elegibilidad (§16.6). |
| **B-5** | 🟢 | Migración idempotente siembra `inversion` (active) + backfill de `frontend_services` con `inversion.allow_leads=false`; cambio aditivo de `LeadCaptureForm` en el mismo lote (§16.6, Lote D). |
| **B-6** | 🟢 | Contrato `FrontendContent`/`FrontendPublisher` + cache keys + invalidación `afterCommit` desde el Lote A; RFC-076 (F) queda como integración/endurecimiento (§16.8). |

### 16.13 Archivos a crear / modificar

**Crear:**

- `database/migrations/*_add_frontend_manage_permission.php` — **crea `frontend.manage` y lo asigna a owner** (idempotente; garantiza el permiso en producción sin seeders, C-5).
- `database/migrations/*_seed_inversion_and_backfill_frontend_services.php` — invoca `SeedInversionService`.
- **`database/migrations/*_seed_frontend_canonical_pages.php` — invoca `SeedFrontendPages` (cierra N-1).** Crea las cinco páginas canónicas en producción con el `migrate`; NO depende de seeders.
- `config/filesystems.php` → disco `frontend-private` (C-3); `config/frontend-sections.php` → registry canónico (§16.1.1).
- `app/Models/FrontendSetting.php`, `FrontendService.php`, `FrontendPage.php`, `FrontendSection.php`, `FrontendCacheGeneration.php`.
- `app/Policies/FrontendSettingPolicy.php` (+ Service/Page/Section) — todas exigen `owner` + `frontend.manage`.
- `app/Services/Frontend/Contracts/FrontendContent.php`, `FrontendPublisher.php`; implementaciones `FrontendSettingsService.php`, `FrontendThemeService.php` (normaliza al render), `FrontendNavigationService.php`, `FrontendServicesService.php` (servicios = estrategia A, guardar=publicar), **`FrontendPageContentService.php`** (writer draft de páginas), **`FrontendPagePublisher.php`** (publisher de páginas con revisión esperada) y **`FrontendPreflightValidator.php`** (Lote G). **RETIRADO por C-G-1 (§16.9):** `FrontendServiceContentService.php` y `FrontendServicePublisher.php` — servicios no tienen flujo draft/publicado.
- **`app/Forms/Components/NonDestructiveMediaUpload.php`** (subclase de `SpatieMediaLibraryFileUpload` con `deleteAbandonedFiles()` **no-op**; obligatoria en TODA colección de Épica 12 — §16.4).
- **`app/Console/Commands/ReportUnreferencedFrontendMedia.php`** (`frontend:media:report-unreferenced`, **solo lectura**: lista media editorial sin referencias con antigüedad y tamaño; no borra ni modifica nada — §16.4).
- **`app/Services/Frontend/FrontendMediaReference.php`** *(nombre real de la clase; los inventarios anteriores decían `FrontendMediaReferenceService`)* — valida existencia, owner y colección antes de escribir, y **rechaza uuid malformado antes de consultar** (§18.18). La **validación** no toma locks sobre `media` (en v1 no hay borrado físico). El lock de `media` aparece **solo** en la publicación con promoción y en el job, por la carrera de referencia (§18.18 punto 4).
- **`app/Services/Frontend/PublishedMediaReference.php`** — frontera única del predicado «¿este uuid está referenciado por la `published_revision` vigente?»: `mediaIdsOf()`, `isReferencedByPublishedRevision()`, `owningPage()` (con `withTrashed()`), `danglingPending()` (acotado a `FrontendSection`/`images`).
- **`app/Exceptions/FrontendMediaReferenceUnavailable.php`** — aborto/rollback nominal para UUID faltante o inelegible.
- `app/Actions/Frontend/SeedInversionService.php` (idempotente no destructiva, testeable — M-5).
- **`app/Actions/Frontend/SeedFrontendPages.php`** (insert-if-missing de las 5 páginas con `is_enabled=false`, idempotente no destructiva — N-1, §16.1.3).
- `app/Support/Frontend/ThemeContract.php` (schema + contraste WCAG + normalización), `CtaResolver.php` (M-6).
- `app/Http/Controllers/Frontend/DraftMediaController.php` (lectura owner-only de media privada, C-3).
- `app/Http/Controllers/LeadCaptureController.php` (binding server-side fail-closed de `?service=` a `serviceType`, M-6).
- `app/Jobs/PromoteFrontendMedia.php` (promoción idempotente post-commit) y `app/Console/Commands/ReconcileFrontendMediaPromotions.php` (recupera enqueue/callback perdido, C-3).
- `app/Filament/Resources/Frontend/*` + `app/Filament/Pages/Frontend/Preview*`.
- Tests nombrados en `tests/Feature/Frontend/*`: `FrontendPublishConcurrencyTest`, `FrontendServicePublishConcurrencyTest`, `FrontendHomeSectionsTest`, `FrontendPageSectionRegistryTest`, `FrontendContactKernelRegionsTest`, `FrontendCacheGenerationTest`, `FrontendServicesCacheTest`, `FrontendMediaPromotionTest`, `FrontendServiceCtaTest`, `FrontendFooterRenderTest`, `FrontendThemeRuntimeTest`, `FrontendSectionValidationTest`, **`FrontendNavigationSchemaTest`** (T-13h/T-13h2), **`FrontendHeroSlidesTest`** (T-14b/T-14c), **`FrontendMediaRetentionTest`** (T-9d), **`FrontendPartialIndexTest`** (T-9e), **`FrontendMediaMaintenanceScheduleTest`** (T-9f), **`FrontendCanonicalPagesInstallTest`** (T-15/T-15b), además de la matriz §16.11. Las carreras usan `tests/Support/PostgresTwoConnectionHarness.php` con dos procesos/conexiones y barreras explícitas, nunca temporización por sleeps.

**Modificar (aditivo):**

- `database/seeders/PermissionSeeder.php` → agregar `frontend.manage` a `PERMISSIONS` (dev/test; producción por migración).
- **`tests/Feature/Auth/PermissionSeederTest.php`** → 14 → 15 permisos, `frontend.manage` solo owner (C-5).
- `app/Providers/AppServiceProvider.php` → **registrar las 4 policies** (C-5, patrón existente).
- `database/seeders/ServiceTypeSeeder.php` → agregar `inversion`.
- `app/Livewire/Leads/LeadCaptureForm.php` → regla `service_type` con join `allow_leads` + validación/creación atómica bajo lock (M-2, Lote D).
- `app/Filament/Resources/ServiceTypeResource.php` → mutaciones de `active` usan el protocolo compartido y el mismo orden de locks `service_types`→`frontend_services` (§16.6).
- `app/Providers/Filament/AdminPanelProvider.php` → `navigationGroups` suma `Frontend`.
- **`routes/web.php`** → ruta de preview `/admin/frontend/preview/{pageKey}` (allowlist server-side) y `/contacto` mediante `LeadCaptureController`.
- **`routes/console.php`** → programar `frontend:media:reconcile` con `withoutOverlapping()->onOneServer()`. **No se programa ningún comando que borre archivos** (§16.4).
- `resources/views/components/button.blade.php`, `badge.blade.php`, `property-card.blade.php`, `components/layouts/public.blade.php` y `welcome`/`site/*`/`leads/create` → cutover a lecturas del kernel y migración de roles brand-critical a utilities semánticas; `leads/create` pasa `serviceType` validado.
- `resources/css/app.css` → declarar `--nh-*`, mapping `@theme inline` semántico para colores/on-colors/background/text/radius/fonts y conservar shades decorativos fuera de alcance (Lote B/F).
- **`app/Filament/Pages/Ayuda.php`** → registrar la sección de ayuda del nuevo módulo Frontend en el registry (Lote G, Épica 11).
- `vite.config.js` → **sin cambios en v1** (Montserrat/Inter).

### 16.14 Matriz de riesgos (actualizada)

| Riesgo | Prob. | Impacto | Mitigación de diseño | Test |
| --- | --- | --- | --- | --- |
| Acceso no-owner por permiso asignado fuera del seeder | Media | **Crítico** | Doble compuerta `owner` + `frontend.manage` en cada policy/gate/URL (§16.2). | T-1 |
| Owner bloqueado por deploy sin seeder | Media | Alto | Permiso creado por **migración** idempotente (§16.2). | T-1b |
| Exposición directa de media draft | Media | **Crítico** | Disco privado + controlador owner-only + promoción al publicar (§16.4). | T-9b |
| CSS persistido malicioso rompe `<style>` | Media | Alto | Validación al guardar **+ normalización al render** (regex color/enum) (§16.5). | T-8b |
| CTA con destino inseguro (`javascript:`) | Media | Alto | Value object tipado + resolver con allowlist/HTTPS (§16.9). | T-13c |
| Lead contra servicio sin config / deshabilitado | Media | Alto | **Fail-closed** + validación/creación atómica bajo lock (§16.6). | T-6/T-6b/T-7/T-7b |
| Dos configs válidas (singleton) | Baja | Alto | `CHECK(singleton_key='default')+UNIQUE` + no delete (§16.1). | T-2 |
| Backfill sobreescribe `inversion` personalizado | Media | Medio | Acción **no destructiva** insert-if-missing, testeable 2× (§16.6). | T-12 |
| Refill de caché repuebla dato viejo | Media | Medio | Claves por **generación** + invalidación de ServiceType/Media (§16.8). | T-10/T-10b |
| Publicación mezcla revisiones | Baja | Medio | Snapshot completo con `lockForUpdate` + `revision` (§16.9). | T-11 |
| Cutover rompe el frontend actual | Media | Alto | Fallbacks exactos + cutover incremental con no-regresión (§16.7). | T-4/T-14 |
| Preview de `pageKey` arbitrario | Media | Medio | Allowlist server-side, 404 uniforme (§16.9). | T-13 |
| Admin pierde control operativo de servicios | Baja | Medio | `ServiceType.active` intacto para owner+admin; frontend solo lo lee (§16.6). | T-6b |

**Nota de seguridad:** Markdown/HTML libre queda **prohibido** en v1. Si se incorporara Markdown después, `Str::markdown()` debe usar `html_input=escape` y `allow_unsafe_links=false` (patrón ya aplicado en Ayuda, Épica 11).

### 16.15 Conflictos entre RFCs detectados y resolución

1. **CTAs duplicados (RFC-071 vs RFC-073).** **Resolución:** CTAs como **value object tipado** `{label,type,target}` en `frontend_settings` (§16.1), con **RFC-073 como única autoridad** de semántica, validación (resolver §16.9) y render. RFC-071 solo los aloja. (Cierra hallazgo alto 1 / PD-7 / M-6.)
2. **Tema: tabla propia vs campos en Setting (RFC-072).** RFC-072 dejaba la decisión abierta. **Resolución:** embebido como `frontend_settings.theme` (JSON) en v1, para no sobrefragmentar; se puede extraer a tabla propia si crece. (§16.1/§16.5.)
3. **Poppins (RFC-072) vs build real.** RFC-072 listaba Poppins; el build no la tiene. **Resolución:** retirar Poppins de la allowlist v1 (§16.5, B-8).
4. **Momento del kernel (RFC-076 "lote F" vs B-6).** **Resolución:** el contrato del kernel entra en el Lote A; F pasa a integración/endurecimiento (§16.8/§16.10).

---

## 17. Definition of Done del diseño

- [x] Documento actualizado con diseño técnico consolidado (§16).
- [x] **Auditoría de diseño (P2) reconciliada** — C-1→C-5, M-1→M-7, Mn-1→Mn-4 y riesgos de seguridad resueltos o diferidos con justificación (§18).
- [x] **RFC-071→077 reconciliados** con §16 vía enmienda normativa por RFC (cierra C-1); §16 es la fuente única.
- [x] B-2→B-6 cerrados con evidencia de código (§16.12).
- [x] PD-1→PD-10 mapeados a su cierre (§12.1 + §16).
- [x] Lista de archivos a crear/modificar (§16.13).
- [x] Matriz de riesgos (§16.14) y de tests ampliada (§16.11).
- [x] Seis correcciones documentales de P3R y cinco bloqueantes confirmados por revisión fresca aplicados (§18.2–§18.3), sin código de producción.
- [ ] Reauditoría independiente posterior a estas correcciones → gate `APROBADO` **(pendiente)**.

> Este documento cierra el diseño; **no incluye código de producción**. La implementación permanece **BLOQUEADA** hasta que Codex emita `GATE DE DISEÑO: APROBADO` en P3R.

---

## 18. Cambios aplicados desde la auditoría de diseño (P3)

Reconciliación de `docs/audits/epica-12-auditoria-diseno.md` (veredicto RECHAZADO). Cada hallazgo → **RESUELTO** (con la corrección) o **DIFERIDO** (con tradeoff). Ningún crítico ignorado.

### Críticos

| ID | Hallazgo | Estado | Corrección aplicada |
| --- | --- | --- | --- |
| **C-1** | RFCs contradicen la épica; no hay spec única | ✅ RESUELTO | §16 declarado fuente normativa única; **enmienda normativa por RFC** (071→077, ver §18.1) que sobreescribe los ítems obsoletos. Un solo nombre por campo/código/colección/estrategia/tipo/cache key. |
| **C-2** | Publicación sin snapshot completo ni seguro ante concurrencia | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Páginas: snapshot completo + `draft_revision`/`expected_draft_revision`. Servicios: `draft_revision`/`expected_draft_revision_service`. Ambos publishers rechazan stale bajo lock y tienen tests con 2 conexiones (§16.1, §16.9, T-11/T-11s). |
| **C-3** | Media draft sigue pública | ✅ RESUELTO EN P3R; ENTREGA EXPLICITADA | Disco privado + `PromoteFrontendMedia` post-commit + reconciliación de enqueue perdido; payload por `media_id`; T-9b/T-9c (§16.4). |
| **C-4** | Singleton no garantizado físicamente | ✅ RESUELTO | `CHECK(singleton_key='default') + UNIQUE`; T-2 con 2 conexiones (§16.1). |
| **C-5** | Owner-only no garantizado ni desplegable | ✅ RESUELTO | Policies exigen `owner` **+** `frontend.manage`; registro en `AppServiceProvider`; permiso creado por **migración** (deploy sin seeders); `PermissionSeederTest` 14→15; T-1/T-1b/T-1c (§16.2). |

### Medios

| ID | Hallazgo | Estado | Corrección aplicada |
| --- | --- | --- | --- |
| **M-1** | Registro de secciones no representa el frontend | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Registry canónico cubre cada región editable verificada de Home/Nosotros/Servicios/Inversionistas/Contacto, separa los tres catálogos, no inventa `inversionistas.metrics` y deja form/canales operativos kernel-only (§16.1.1). |
| **M-2** | Elegibilidad falla abierta | ✅ RESUELTO | **Fail-closed**: ausencia de `FrontendService` = inelegible; validación/creación de lead atómica bajo lock; T-6b/T-7b (§16.6). |
| **M-3** | Caché permite staleness y omite mutaciones | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Quinta tabla + bump atómico único; claves completas por generación; servicios usan `frontend:g{N}:services:{location}`; no hay clear/forget dirigido y TTL=300 s solo recolecta entradas antiguas (§16.1, §16.8). |
| **M-4** | Tema/CSS sin contrato único | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | `radius ∈ {soft,medium,rounded}` se expande a valores exactos `--nh-radius-md/lg/xl`; utilities semánticas cubren colores/on-colors/fondo/texto/radios/fuentes; shades decorativos quedan fuera de alcance (§16.5). |
| **M-5** | Backfill puede sobrescribir estado | ✅ RESUELTO | Acción `SeedInversionService` **no destructiva** (insert-if-missing), testeable 2×; T-12 (§16.6). |
| **M-6** | Footer/CTAs/SEO sin contrato | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Binding server-side de `?service=`, footer con `enabled` y CTA exclusivamente anidado `{label,type,target}`; se eliminaron campos planos legacy en RFC-071/075 (§16.1, §16.3). |
| **M-7** | Matriz de tests insuficiente | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Matriz e inventario nombran registry/cutover, radius exacto, cache por location, stale publisher de servicio, CTA anidado y promoción media durable (§16.11, §16.13). |

### Menores y seguridad

| ID | Hallazgo | Estado | Corrección |
| --- | --- | --- | --- |
| **Mn-1** | Fallback CTA incorrecto | ✅ RESUELTO | "Agenda una cita" (verificado `public.blade.php:80-84`) (§16.7). |
| **Mn-2** | Tipo FK y unique redundante | ✅ RESUELTO — ⚠️ **corrección SUPERSEDIDA por §18.6** | Se resolvió el tipo: `string('service_type_code', 30)` acorde a `service_types.code`. **La parte `->unique()` global de esta corrección queda ANULADA:** con `SoftDeletes` (§16.4) un UNIQUE global impide recrear el servicio de un `code` borrado. El contrato vigente es el **índice único parcial nombrado** de §16.1.2. Esta fila se conserva como registro histórico; **no debe implementarse tal como está escrita**. |
| **Mn-3** | `is_active` sin semántica | ✅ RESUELTO | Eliminado (§16.1). |
| **Mn-4** | SVG decisión abierta | ✅ RESUELTO | **SVG prohibido en v1** (§16.4). |
| Seg. | 6 riesgos de seguridad de la auditoría | ✅ RESUELTO | Integrados a la matriz §16.14 con su control y test. |
| Mant. | Registry de Ayuda debe actualizarse | ✅ RESUELTO | `app/Filament/Pages/Ayuda.php` agregado a archivos esperados (Lote G, §16.13). |

**Diferido (con tradeoff documentado):** ninguno de los críticos/medios se difiere. Quedan como trabajo posterior explícito, fuera de v1: SVG sanitizado (Mn-4), disco privado adicional para media inmediata, historial de publicaciones versionado, CSP con nonce para el `<style>` runtime (recomendación opcional de la auditoría §10).

### 18.1 Enmienda normativa por RFC (cierra C-1)

Se agrega al encabezado de cada RFC un bloque **"Enmienda normativa (auditoría de diseño P3)"** que declara: *"§16 de `docs/epicas/epica-12-administrador-contenidos-frontend.md` es la fuente normativa única. Donde este RFC difiera, prevalece §16."* más los overrides específicos:

| RFC | Overrides declarados |
| --- | --- |
| 071 | Singleton `CHECK+UNIQUE`; owner+permiso; permiso por migración; media draft privada; CTAs anidados `{label,type,target}` delegados a RFC-073; sin campos CTA planos ni `is_active`. |
| 072 | Schema `theme` único; `radius ∈ {soft,medium,rounded}` con expansión exacta a tres variables; utilities semánticas runtime para colores/on-colors/fondo/texto/radios/fuentes; migración de consumers brand-critical; shades decorativos fuera de alcance; **Poppins retirada**. |
| 073 | CTA value object `{label,type,target}` + resolver; footer `{label,type,target,enabled}` y DTO con `enabled`; autoridad única de nav/footer/CTAs. |
| 074 | Code `inversion`; `show_in_*`, colección `image`; fail-closed; locks compartidos; CTA derivado; binding server-side; contenido editorial con `draft_revision`/`expected_draft_revision_service`. |
| 075 | Registry completo con section keys estables y tipos ejecutables; tres catálogos independientes; sin `inversionistas.metrics`; form/canales kernel-only; CTA anidado; stale publisher de página; job/reconciliación de media. |
| 076 | Quinta tabla; bump atómico como invalidación única; keys completas y `services:{location}`; sin clear/forget dirigido; TTL=300 s; tests de init, concurrencia, location y refill. |
| 077 | Snapshot completo + revisiones esperadas de página y servicio; locks deterministas; `PromoteFrontendMedia` + reconciliación; bump único de cache; `pageKey` inválido → 404; preview solo para estrategia B. |

### 18.2 Correcciones posteriores a la reauditoría P3R

La reauditoría `docs/audits/epica-12-reauditoria-diseno.md` mantiene el gate **RECHAZADO** y dejó seis correcciones documentales pendientes. Esta revisión aplica las seis sin código de producción. **"Corrección aplicada" no equivale a hallazgo aprobado:** todos permanecen pendientes de una reauditoría independiente.

| ID P3R | Pendiente concreto señalado | Estado documental | Corrección aplicada |
| --- | --- | --- | --- |
| **C-2** | Una pantalla de publicación abierta antes de una mutación draft podía publicar estado nuevo con una revisión esperada obsoleta. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | `draft_revision` aumenta atómicamente en toda mutación de página/sección; el publisher exige `expected_draft_revision` y rechaza stale después de locks deterministas. Test nominal con dos conexiones (§16.1, §16.9, T-11). |
| **M-1** | Oportunidades se atribuían a `featured_projects`; `Property::featured`, `Property::opportunity` y Project destacados no estaban separados; inversionistas usaba `type=varios`. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Registry separa `featured_properties`, `opportunity_properties` y `featured_projects`, y expande cada `section_key` de inversionistas a un tipo allowlisted ejecutable (§16.1.1, T-14). |
| **M-3** | `frontend_cache_generation` contradecía el conteo de tablas, faltaba su migración y no tenía tests de inicialización/bump concurrente. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Modelo declara cinco tablas; inventario suma la migración; T-10a prueba fila inicial y dos bumps PostgreSQL concurrentes (§16.1, §16.8, §16.13). |
| **M-4** | La promesa de re-skin global era falsa: radios, `on_*` y shades fijos no seguían el tema. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Contrato usa utilities semánticas runtime para colores, `on_*`, fondo/texto, radios y fuentes; obliga a migrar roles brand-critical y deja shades decorativos explícitamente fuera de alcance (§16.5, T-8c). |
| **M-6** | `?service=` no llegaba a `LeadCaptureForm`; footer omitía `enabled` en schema/DTO. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Controller valida elegibilidad fail-closed y pasa `serviceType` solo para códigos válidos; inválidos se ignoran uniformemente. Footer persiste y expone `enabled`, y el renderer omite links apagados (§16.1, §16.3, T-13f/T-13g). |
| **M-7** | Faltaban pruebas nominales de los contratos anteriores y de promoción media durable. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | §16.11/§16.13 nombran stale publisher, tres fuentes home independientes, cache init/concurrencia, rollback/enqueue/reconciliación/idempotencia de media, CTA preseleccionado, footer deshabilitado y mappings runtime del tema. `PromoteFrontendMedia` + `ReconcileFrontendMediaPromotions` cierran la entrega pendiente de C-3. |

Los hallazgos que la reauditoría ya marcó como resueltos no se reabren. C-3 conserva su solución post-commit y ahora explicita job, retry, recuperación de dispatch perdido e idempotencia para que la implementación no dependa de una promesa sin archivo ni prueba.

### 18.3 Bloqueantes confirmados por revisión fresca

La revisión fresca posterior a §18.2 confirmó que cinco contradicciones seguían abiertas. Esta pasada las corrige en la épica y RFCs afectados; el estado continúa siendo documental, no una aprobación independiente.

| ID fresco | Bloqueante confirmado | Estado documental | Reconciliación aplicada |
| --- | --- | --- | --- |
| **FR-1 Registry/cutover** | Faltaban hero/CTA y regiones reales; Inversionistas inventaba `metrics`; Contacto mezclaba CMS con operación. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Registry verificado contra los cinco Blade cubre cada región editable con tipo ejecutable; `feature_sequence`/`audience_outcomes` tienen renderer/schema; no existe `inversionistas.metrics`; buscador, form y canales quedan kernel-only (§16.1.1, T-14). |
| **FR-2 Cache** | `services(location)` podía colisionar y RFC-076 mezclaba bump con limpieza dirigida. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Key única `frontend:g{N}:services:{location}`; bump global post-commit como única invalidación; TTL=300 s como recolección; prohibidos clear/forget; test de locations (§16.8, T-10c). |
| **FR-3 Radius** | Schema singular y mapping plural sin valores implementables. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | Enum almacenado `soft|medium|rounded` con tabla exacta para `--nh-radius-md/lg/xl`; no se emite `--nh-radius`; test por preset (§16.5, T-8c). |
| **FR-4 Servicio stale** | `FrontendService` estrategia B no protegía contra publisher obsoleto. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | `frontend_services.draft_revision`, incremento por toda mutación de `draft_payload`, `expected_draft_revision_service`, lock y test con dos conexiones (§16.1, §16.9, T-11s). |
| **FR-5 CTA legacy** | RFC-071/075 conservaban campos CTA planos incompatibles con RFC-073. | 🟡 CORRECCIÓN APLICADA; REAUDITORÍA PENDIENTE | CTAs globales y de sección usan exclusivamente `{label,type,target}` anidado; fields legacy se rechazan y tienen prueba de schema (T-13c2). |

**Estado del gate:** correcciones documentales de §18.2–§18.3 aplicadas; **reauditoría independiente pendiente**. La implementación **permanece BLOQUEADA** hasta que esa reauditoría emita `GATE DE DISEÑO: APROBADO`.

### 18.4 Correcciones de la reauditoría P5 (2026-07-21)

La reauditoría del 2026-07-21 (`docs/audits/epica-12-reauditoria-diseno.md`) mantiene **RECHAZADO** con balance 12 resueltos / 4 parciales, más un hallazgo nuevo **N-1 CRÍTICO**. Esta pasada corrige los cinco bloqueantes de su §6. Cada afirmación se verificó contra el código real antes de corregir; **las cuatro observaciones eran correctas**.

| ID | Bloqueante confirmado | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-1** | Dos schemas de navegación competidores. | `épica:341` = `{route_name,label,enabled}` vs `RFC-073:110-116` = `{key,label,enabled,sort_order,open_in_new_tab}`, con RFC-073 declarado autoridad única. | Schema **único, el de RFC-073**. `route_name` eliminado del diseño. `url`/`active_pattern` derivados de `key`; `sort_order` única fuente de orden; `open_in_new_tab=false` en v1; no-vaciado **bloquea el guardado**. DTO `navigation()` entrega links ya ordenados y filtrados (§16.1, §16.3, RFC-073, T-13h). |
| **M-1** | El schema `hero` no puede representar los slides reales. | `welcome.blade.php:12-16` define **4** URLs de fondo rotativas (`:20-28`) bajo overlay navy (`:31`); `RFC-075:244-256` solo modelaba `eyebrow/title/subtitle/primary_cta`. | `hero` incorpora **`slides`** con cardinalidad `0..6`, `sort_order`, `decorative`/`alt` (default decorativo, `alt` obligatorio si no lo es), fallback exacto de las 4 URLs en orden ⚠️ **(registro histórico P5: ese fallback quedó luego acotado a `home` por §18.18 — el fallback es POR PÁGINA)**, `slides:[]` publicado no revive el fallback, y media solo por upload (§16.1.1, §16.7, RFC-075, T-14b). |
| **C-3** | Borrar una sección destruye la media de la revisión publicada viva. | `InteractsWithMedia.php:51-63`: `deleting` ejecuta `deleteAllMedia()`, con escape temprano si el modelo usa `SoftDeletes` y no es `forceDeleting` (`:56-60`). | `FrontendSection` y `FrontendService` usan **`SoftDeletes`** (UNIQUE parcial `WHERE deleted_at IS NULL`) y `forceDelete` retorna `false` por policy. Reemplazar imagen en draft no borra la publicada. ⚠️ **SUPERSEDIDO por §18.13** (sin borrado físico en v1): recolección diferida con `PruneFrontendMedia` fuera de línea y con ventana de retención (§16.1, §16.4, T-9d). |
| **N-1** | **CRÍTICO:** las 5 páginas canónicas dependían de un seeder que producción no ejecuta. | `RFC-075:405` solo inventariaba `FrontendPageSeeder`; el pipeline corre `migrate --force` sin seeders (`CI-CD-PIPELINE.md:46-58`). Mismo error ya corregido para `frontend.manage` e `inversion`. | Acción idempotente **`SeedFrontendPages`** invocada por **migración aditiva**; crea las 5 páginas con `is_enabled=false`, así el owner recibe las entidades editables y el sitio público queda **byte-idéntico al fallback**. No crea secciones (evita duplicar fallbacks). No destructiva (§16.1.3, RFC-075, T-15/T-15b). |
| **M-7** | Faltaban las pruebas nominales de lo anterior. | §6.5 de la reauditoría. | Añadidas **T-13h** (schema persistido de navegación), **T-14b** (slides: fallback, orden, cardinalidad, accesibilidad, cutover), **T-9d** (retención de media vs. revisión publicada), **T-15/T-15b** (instalación limpia sin seeders e idempotencia), con sus clases nombradas en §16.13. |

**Estado del gate:** correcciones documentales aplicadas; **ninguna es una aprobación**. La implementación **permanece BLOQUEADA** hasta que una reauditoría independiente emita `GATE DE DISEÑO: APROBADO`.

### 18.5 Correcciones de la reauditoría P6 (commit `0c03fdd`)

Balance de la reauditoría: **12 resueltos + N-1 resuelto, 4 parciales**. Los cuatro bloqueantes restantes eran correctos y **tres nacieron de esta corrección, no del diseño original** — se documentan como tales.

| ID | Bloqueante | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-1** | Tercera variante de key: `inicio`. | `routes/web.php:13` nombra la ruta raíz **`home`**; `RFC-073:104` usa `home`. `inicio` lo introdujo la corrección P5. | Key inicial unificada en **`home`**. Además se agrega el **mapa normativo `key` → nombre de ruta**, porque dos keys NO coinciden con su ruta: `inmuebles`→`inmuebles.index` y `contacto`→`leads.create` (`routes/web.php:16,26`). Un `route($key)` ingenuo rompería (§16.1, T-13h2). |
| **C-3** | UNIQUE globales incompatibles con `SoftDeletes`; prune "programado" sin contrato. | La corrección P5 dejó `->unique()` global **y** parcial en `frontend_services`, y en `frontend_sections` volvió parcial solo `section_key`, dejando `(page, sort_order)` global: una fila soft-deleted ocupaba ese `sort_order` para siempre. | **Todos** los UNIQUE de tablas con `SoftDeletes` son parciales (`WHERE deleted_at IS NULL`): `service_type_code`, `(page, section_key)` y `(page, sort_order)`; se elimina el `->unique()` global. `deleted_at` incorporado a los campos normativos de RFC-074/075. ⚠️ **La parte de scheduler de esta corrección queda SUPERSEDIDA por §18.6 (C-3.1):** programar prune y reconciliación como **dos eventos separados** no da exclusión ni orden, porque el mutex se deriva del comando (`Event.php:878-879`). El contrato vigente es el orquestador único `frontend:maintain-media` (§16.4). |
| **M-1** | El hero pierde su segundo CTA. | `welcome.blade.php:46` "Ver Propiedades" → `inmuebles.index`; `:47-49` "Conocer Proyectos" → `proyectos`. El schema P5 fijaba `secondary_cta: null`. | `secondary_cta` pasa a ser un CTA real y nullable, con **fallback = ese par exacto en ese orden**. RFC-075 lo define en su schema. Se descarta la eliminación: el default debe conservar el frontend actual (§16.1.1, §16.7, RFC-075, T-14c). |
| **M-7** | Faltaban las pruebas de lo anterior. | §5.5 de la reauditoría. | **T-13h2** (`home` vs `inicio` + mapa key→ruta), **T-9e** (reutilización de `section_key`/`sort_order`/`service_type_code` tras soft delete, sin UNIQUE globales en el esquema real), **T-9f** (schedule registrado y no solapado), **T-14c** (ambos CTAs sobreviven al cutover). |

**Nota de proceso:** tres de los cuatro bloqueantes fueron **regresiones introducidas al corregir**, no deuda del diseño original. Es el argumento a favor de que cada corrección vuelva a auditarse contra el código en lugar de darse por buena.

**Estado del gate:** **BLOQUEADA**. Requiere nueva reauditoría independiente.

### 18.6 Correcciones de la reauditoría P7 (commit `66beb31`)

> **⚠️ PARCIALMENTE SUPERSEDIDA POR §18.13.** El **DDL de los índices únicos parciales (C-3.2) sigue VIGENTE** — §16.1.2. La parte de **orquestador de mantenimiento (C-3.1)** queda supersedida: sin borrado físico, el scheduler solo programa `frontend:media:reconcile`.

Balance: **13 resueltos + N-1 resuelto, 3 parciales**. Los cuatro bloqueantes eran correctos y verificados contra el vendor y el propio repositorio.

| ID | Bloqueante | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-3.1** | Dos eventos programados no dan exclusión ni orden. | `Event::mutexName()` devuelve `'framework/schedule-'.sha1($expression . $command)` (`Console/Scheduling/Event.php:878-879`): **el mutex depende del comando**, así que dos comandos distintos tienen mutexes distintos. `withoutOverlapping()` protege cada evento de sí mismo, nunca del otro, y coincidir en horario no crea precedencia. | **Un único evento programado**, `frontend:maintain-media`, que ejecuta reconciliación y **solo si tuvo éxito** el prune, secuencialmente en el mismo proceso. Los dos comandos individuales siguen existiendo para operación manual pero **no se programan** (§16.4, §16.13, T-9f). ⚠️ La afirmación histórica "un mutex ⇒ exclusión real" queda **SUPERSEDIDA en alcance por §18.7**: solo evita duplicar el evento programado; no protege frente a writers ni prune manual. |
| **C-3.2** | `UNIQUE (...) WHERE ...` no es DDL ejecutable. | PostgreSQL no admite predicado en un constraint `UNIQUE`; la unicidad parcial es `CREATE UNIQUE INDEX ... WHERE ...`. El repo ya usa ese patrón en `database/migrations/2026_07_13_000001_create_lona_requests_table.php:31-35` (RFC-062). | Nueva **§16.1.2** con el DDL exacto vía `DB::statement`, **nombre explícito** para los tres índices (`frontend_services_service_type_code_active_unique`, `frontend_sections_page_section_key_active_unique`, `frontend_sections_page_sort_order_active_unique`), prohibición de `->unique()` de Blueprint y rollback (los índices caen con `dropIfExists` de su tabla, igual que en `lona_requests`). |
| **M-7** | T-9f afirmaba algo indemostrable; faltaban nombres `Clase::método`. | §5.3 de la reauditoría. | T-9f reescrito para probar la orquestación real (un solo evento agendado, prune no corre si falla la reconciliación). Se agregan los contratos nominales de T-13h/T-13h2/T-9d/T-9e/T-9f/T-14b/T-14c/T-15/T-15b, con `FrontendPartialIndexTest` y `FrontendMediaMaintenanceScheduleTest` sumadas al inventario (§16.11, §16.13). |
| **Mn-2** | La fila histórica seguía ordenando `->unique()` global. | `épica:958` contradecía el contrato vigente. | La fila se marca **SUPERSEDIDA**: conserva el cierre del tipo `string(30)` y anula explícitamente la parte `->unique()`, remitiendo al índice parcial de §16.1.2. Se mantiene como registro histórico con la advertencia de no implementarla tal cual. |

**Nota de proceso:** los dos hallazgos técnicos de esta vuelta (mutex por comando, DDL de índice parcial) solo se detectan **leyendo el vendor y las migraciones reales del repo**. Ninguno es deducible del documento. Refuerza la regla: toda afirmación sobre el comportamiento del framework se verifica en el código antes de escribirla.

**Estado del gate:** **BLOQUEADA**. Requiere nueva reauditoría independiente.

### 18.7 Correcciones de la reauditoría P8 (commit `1db3318`)

> **⚠️ SECCIÓN HISTÓRICA — SUPERSEDIDA POR §18.13.** Todo lo referido a **borrado físico de media** (prune, purga, intent, lease, advisory lock, jobs guardados, path generator con scope, barrido de huérfanos y sus tablas/comandos/tests) **quedó FUERA DE ALCANCE de v1** por decisión de §18.13. Se conserva como registro del análisis, **no como instrucción de implementación**. Lo que sigue vigente de esta sección se indica en §18.13.

La reauditoría confirmó que C-3 y M-7 seguían parciales: el orquestador único ordenaba mantenimiento programado, pero no serializaba referencias UUID en JSON contra prune ni cubría ejecución manual. Esta corrección no borra los registros P5–P7; limita explícitamente sus afirmaciones supersedidas.

| ID | Bloqueante | Corrección aplicada |
| --- | --- | --- |
| **C-3** | Candidate discovery y ventana de 30 días dejaban TOCTOU; writers/publishers no bloqueaban `media`, y el mutex no cubría HTTP/Filament ni prune manual. | §16.4.1 define protocolo compartido: cada transacción fuerza `READ COMMITTED` con `SET TRANSACTION ...` como primera sentencia; locks de entidad existentes → `media.uuid ASC FOR UPDATE` → validar → escribir JSON. Prune fuerza el mismo aislamiento, bloquea una media primero, reconsulta las cuatro fuentes JSON y solo borra si sigue sin referencias y cumple retención. Manual y orquestador llaman el mismo `FrontendMediaPruner`; el mutex no es safety boundary. |
| **M-7** | No había pruebas reales edit/publish/manual-prune versus prune. | T-9g y sus métodos nominales usan dos conexiones PostgreSQL y barreras sin sleeps para ambos interleavings, más tests del scope que excluyen las colecciones inmediatas de `FrontendSetting`. |

**Reconciliación de interleavings:** writer primero ⇒ prune espera y ve la referencia confirmada; prune primero ⇒ el lock del writer devuelve UUID faltante y `FrontendMediaReferenceUnavailable` revierte sin JSON colgante.

**Estado del gate:** corrección documental aplicada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.8 Correcciones de la reauditoría P9

> **⚠️ SECCIÓN HISTÓRICA — SUPERSEDIDA POR §18.13.** Todo lo referido a **borrado físico de media** (prune, purga, intent, lease, advisory lock, jobs guardados, path generator con scope, barrido de huérfanos y sus tablas/comandos/tests) **quedó FUERA DE ALCANCE de v1** por decisión de §18.13. Se conserva como registro del análisis, **no como instrucción de implementación**. Lo que sigue vigente de esta sección se indica en §18.13.

Balance de la reauditoría: **15 de 17 hallazgos cerrados**. C-3 y M-7 quedaron parciales por **una sola frontera**: el protocolo trataba el borrado de una `Media` como si base de datos y filesystem compartieran rollback. La observación es correcta y verificada en el vendor.

| ID | Bloqueante | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-3** | La transacción borraba la fila `media`; un rollback dejaba la fila apuntando a archivos inexistentes. | Spatie registra el observer de `Media` (`MediaLibraryServiceProvider.php:32-37`) y su `deleted()` llama **inmediatamente** `$filesystem->removeAllFiles($media)` (`MediaObserver.php:55-65`). Eloquent dispara `deleted` justo después del `DELETE`, dentro de la transacción, sin frontera `afterCommit`; la transacción de Laravel no vuelve transaccional el filesystem (`ManagesTransactions.php:26-74`). | Nueva **§16.4.2**. La transacción protegida **solo escribe un intent durable** (`custom_properties.frontend_purge`, mismo mecanismo que `pending_promotion`, sin sexta tabla; marcar no dispara nada porque `MediaObserver::updated()` solo actúa si cambió `manipulations`, `:42-52`). La purga física corre **post-commit, fuera de toda transacción**, en `PurgeFrontendMediaFiles`: **archivos primero** (original, conversiones, responsive images) y **la fila después**, con `Media::withoutEvents()`. Un rollback conserva fila y archivos; la marca nunca existió. |
| **C-3 (idempotencia)** | Un fallo post-commit debía poder reintentarse sin revivir la fila ni fallar por archivos ausentes. | — | El orden **archivos→fila** es la garantía: los archivos solo se borran mientras la fila —única portadora de sus rutas— existe. Muerte tras borrar archivos ⇒ el retry repite sin efecto y completa la fila; muerte tras borrar la fila ⇒ el retry termina en el primer paso. Archivo ya ausente cuenta como éxito. El orden inverso perdería el puntero a los archivos y se descarta explícitamente. Dispatch perdido se recupera con el barrido de `ReconcileFrontendMediaPromotions`. |
| **C-3 (fail-closed)** | — | — | Una fila marcada `frontend_purge` es **inelegible** en `lockAndValidateForWrite`: un writer que la referencie recibe `FrontendMediaReferenceUnavailable` y revierte. No hay ventana para adoptar media condenada. |
| **M-7** | Faltaban pruebas PostgreSQL + filesystem de esa frontera. | §6.2 de la reauditoría. | **T-9h** con siete métodos nominales en `FrontendMediaPurgeAtomicityTest`: rollback conserva fila y los tres tipos de archivo en disco real; commit dispara purga posterior; retry tras fallo de archivos completa sin revivir la fila; retry tras borrar la fila es no-op; media marcada bloquea a writers; dispatch perdido se recupera; manual y programado usan el mismo pruner y el mismo job. |

**Nota de proceso:** el hallazgo no era deducible del documento — exigía leer el observer de Spatie y saber que Eloquent dispara `deleted` dentro de la transacción. Es el tercer hallazgo consecutivo que solo aparece leyendo el vendor (mutex del scheduler, DDL de índice parcial, y ahora la frontera BD↔filesystem). Confirma la regla: **toda afirmación sobre atomicidad se verifica en el código del framework antes de escribirla.**

**Estado del gate:** corrección documental aplicada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.9 Correcciones de la reauditoría P10

> **⚠️ SECCIÓN HISTÓRICA — SUPERSEDIDA POR §18.13.** Todo lo referido a **borrado físico de media** (prune, purga, intent, lease, advisory lock, jobs guardados, path generator con scope, barrido de huérfanos y sus tablas/comandos/tests) **quedó FUERA DE ALCANCE de v1** por decisión de §18.13. Se conserva como registro del análisis, **no como instrucción de implementación**. Lo que sigue vigente de esta sección se indica en §18.13.

Balance: **15 de 17 cerrados**. C-3 y M-7 seguían parciales por una única suposición no verificada: que `Filesystem::removeAllFiles()` informa los fallos. **No lo hace**, y la observación es correcta.

| ID | Bloqueante | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-3** | La purga borraba la fila confiando en un método que silencia fallos reales. | El remover activo es `DefaultFileRemover` (`config/media-library.php:149`). Su `removeAllFiles()` es **`void`** (`:17`); el retorno de `delete()` se **ignora** en las tres rutas (`:44,72,113`); y cada bloque está envuelto en `try { … } catch (Exception $e) { report($e); }` (`:50-52,78-80,119-121`). Los discos declaran `throw => false` y `report => false` (`config/filesystems.php:37,49`), así que un borrado fallido devuelve `false` **sin lanzar y sin reportar**. Conclusión: **que `removeAllFiles()` termine no prueba nada**; una purga parcial podía borrar la fila y perder el único puntero a los archivos residuales. | §16.4.2 reescrita: (1) **inventario explícito** de original, conversiones y responsive images —en `disk` y `conversions_disk`— computado **con la fila viva** y persistido en `frontend_purge.paths`; (2) **borrado estricto** ruta por ruta evaluando el retorno de `delete()`, sin capturar excepciones y **sin usar `removeAllFiles()`**; (3) **verificación de ausencia** como gate real, con `exists()`/`allFiles()` — se verifica ausencia, no el reporte del adapter, porque es la única comprobación independiente de la semántica `throw`/`report`; (4) la fila se borra **solo si todas las rutas están ausentes**. |
| **C-3 (preservar el puntero)** | Un fallo silencioso no debía permitir borrar la fila. | — | Cualquier `delete() === false`, excepción o residuo detectado en la verificación **conserva la fila y `frontend_purge`**, incrementa `attempts`, registra `last_error` y falla el job para reintento con backoff. Tras N intentos el intent queda `failed`, se alerta y el candidato sale de las corridas automáticas hasta intervención. **Nunca se borra la fila para destrabar la limpieza:** una fila de más cuesta almacenamiento; una fila de menos deja un archivo huérfano permanente. |
| **M-7** | Las pruebas no simulaban los fallos reales del adapter. | §6.2 de la reauditoría. | **T-9i** en `FrontendMediaPurgeStrictnessTest`, con disco falso que reproduce los tres modos reales: `delete()` devuelve `false`; el adapter lanza una excepción del tipo que `DefaultFileRemover` absorbería; y eliminación parcial entre original/conversiones/responsive. En los tres, fila y marca sobreviven y el retry completa la purga al recuperarse el disco. Un caso extra afirma que el diseño **no** invoca `removeAllFiles()` como prueba de éxito. |
| **Coherencia RFC** | Vocabulario e inventarios obsoletos. | §5 de la reauditoría. | `lock/recheck/delete` → **`lock/recheck/intent`** en RFC-074 y RFC-075; `PurgeFrontendMediaFiles` incorporado a los inventarios de RFC-075 y RFC-077; `FrontendMediaPurgeStrictnessTest` sumado a la matriz y al inventario de la épica. |

**Nota de proceso:** es el **cuarto** hallazgo consecutivo que solo aparece leyendo el vendor —mutex del scheduler, DDL de índice parcial, observer síncrono de Media, y ahora un remover que silencia fallos—. El patrón común: **una API que "parece" reportar errores y no lo hace.** La regla que queda: cuando una garantía depende de un efecto externo (filesystem, cola, red), el diseño no puede confiar en el valor de retorno de la librería; tiene que **verificar el estado resultante**.

**Estado del gate:** corrección documental aplicada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.10 Correcciones de la reauditoría P11

> **⚠️ SECCIÓN HISTÓRICA — SUPERSEDIDA POR §18.13.** Todo lo referido a **borrado físico de media** (prune, purga, intent, lease, advisory lock, jobs guardados, path generator con scope, barrido de huérfanos y sus tablas/comandos/tests) **quedó FUERA DE ALCANCE de v1** por decisión de §18.13. Se conserva como registro del análisis, **no como instrucción de implementación**. Lo que sigue vigente de esta sección se indica en §18.13.

Balance: **15 de 17 cerrados**. C-3 y M-7 seguían parciales por dos huecos concretos en la purga. Ambos verificados en el vendor y **ambos correctos**.

| ID | Bloqueante | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-3.a** | El inventario omitía las responsive images **del original**. | `Media::responsiveImages()` tiene `$conversionName = ''` por default (`MediaCollections/Models/Media.php:507-509`) y `RegisteredResponsiveImages` traduce la cadena vacía a la clave **`media_library_original`** (`ResponsiveImages/RegisteredResponsiveImages.php:14-18`). Spatie genera responsive del original bajo esa clave más un tiny-JPG (`ResponsiveImageGenerator.php:45,50`), las reconoce por el prefijo `___media_library_original_` (`DefaultFileRemover.php:108`) y su propio `CleanCommand` la agrega a la lista de conversiones con responsive (`CleanCommand.php:172`). Iterar solo `getMediaConversionNames()` dejaba **toda esa familia** fuera del inventario y, por lo tanto, fuera del gate de ausencia. | El inventario usa la **unión** de `array_keys($media->responsive_images)`, `getMediaConversionNames()` y **`media_library_original`**, incluye el patrón por prefijo, y agrega un **barrido de directorios** con `allFiles()` para capturar derivados con nombres inesperados (§16.4.2, T-9j). |
| **C-3.b** | Un job de derivados **en vuelo** podía escribir después del gate y del borrado de la fila. | Conversiones y responsive corren en cola (`config/media-library.php:92`, `queue_conversions_by_default = true`). `PerformConversionsJob::handle()` (`:27-36`) y `GenerateResponsiveImagesJob::handle()` (`:20-28`) escriben derivados **sin consultar el intent ni tomar lock**. `deleteWhenMissingModels = true` (`PerformConversionsJob.php:19`) solo descarta el job **al deserializar**: no protege a uno ya iniciado. | Cuatro capas: (1) **jobs sustituidos** por `GuardedPerformConversionsJob`/`GuardedGenerateResponsiveImagesJob` vía `config/media-library.php:271-274` —el vendor los resuelve en `FileManipulator.php:137-138` y `FileAdder.php:630`— que **abortan en el punto de escritura** si hay intent activo o la fila no existe, cubriendo jobs ya iniciados; (2) **claim antes del inventario**; (3) **ventana de asentamiento con doble verificación**: si un derivado reaparece, la fila **no se borra**; (4) retención de 30 días como defensa en profundidad. |
| **C-3.c** | El caso residual no puede eliminarse por completo. | Filesystem y cola **no comparten transacción** con PostgreSQL. | Se agrega `PruneFrontendMediaOrphans`: barrido de **detección y reparación** que elimina derivados cuya fila `media` ya no existe, con el mismo scope editorial estricto. Se declara explícitamente como control compensatorio: el diseño **no afirma una atomicidad que el stack no ofrece**; cierra el caso residual en lugar de negarlo. |
| **M-7** | Faltaban pruebas de ambos huecos y los inventarios RFC estaban desalineados. | §6.3 de la reauditoría. | **T-9j** (familia responsive del original: inventariada, eliminada y verificada; una sola superviviente impide borrar la fila) y **T-9k** (interleaving de jobs en vuelo, ventana de asentamiento, barrido de huérfanos y config apuntando a las clases guardadas), con clases nominales. RFC-075 corrige "solo borra" → `lock→recheck→intent`, y los inventarios de tests de RFC-075/077 suman `FrontendMediaPurgeAtomicityTest`, `FrontendMediaPurgeStrictnessTest` y `FrontendMediaDerivativeRaceTest`. |

**Nota de proceso:** quinto hallazgo consecutivo originado en el vendor, y el primero que obliga a admitir un límite real del stack. La respuesta correcta no fue reforzar la promesa de atomicidad, sino **acotarla y agregar detección/reparación**. Un diseño que declara sus límites es auditable; uno que promete garantías imposibles solo posterga el bug.

**Estado del gate:** corrección documental aplicada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.11 Correcciones de la reauditoría P12

> **⚠️ SECCIÓN HISTÓRICA — SUPERSEDIDA POR §18.13.** Todo lo referido a **borrado físico de media** (prune, purga, intent, lease, advisory lock, jobs guardados, path generator con scope, barrido de huérfanos y sus tablas/comandos/tests) **quedó FUERA DE ALCANCE de v1** por decisión de §18.13. Se conserva como registro del análisis, **no como instrucción de implementación**. Lo que sigue vigente de esta sección se indica en §18.13.

Balance: **15 de 17 cerrados**. C-3 y M-7 seguían parciales por dos defectos que introdujo la corrección P11. Ambos verificados en el vendor; **ambos correctos**.

| ID | Bloqueante | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-3.a** | El guard del job era un chequeo puntual, no exclusión: TOCTOU entre el chequeo y las escrituras. | Tras entrar a `handle()`, Spatie escribe **varias veces**: un `copyToMediaLibrary` por cada ancho responsive (`ResponsiveImageGenerator.php:44-46,111`), el tiny-JPG (`:50`) y la copia de conversiones (`Conversions/Actions/PerformConversionAction.php:51`). Un job puede superar el guard y quedar suspendido antes de cualquiera de ellas; una espera acotada no es un lock. | **Advisory lock de PostgreSQL** por `media_id`: el job guardado lo toma **antes de su primera escritura y lo libera al terminar**, cubriendo todas; el pruner exige el mismo lock con `pg_try_advisory_lock` **antes del inventario** y lo **conserva** durante verificación, ventana de asentamiento y borrado de la fila. Sin lock → `skipped_busy`, sin tocar nada. Una sola clave por media, tomada primero por ambos lados: no hay deadlock. El chequeo bajo lock queda como corto-circuito; **la garantía la da el lock**. |
| **C-3.b** | El barrido de huérfanos era **inimplementable**: prometía "scope editorial, nunca `FrontendSetting`" sin poder determinarlo. | `DefaultPathGenerator::getBasePath()` devuelve **solo `$media->getKey()`** más un prefijo global (`Support/PathGenerator/DefaultPathGenerator.php:36-45`): la ruta no codifica owner, morph ni colección. Borrada la fila, un directorio residual no dice de qué era. | Se registra **`FrontendMediaPathGenerator`** en `custom_path_generators` (`config/media-library.php:154`) para `FrontendSection` y `FrontendService`: la media editorial vive en `frontend-editorial/{collection}/{media_id}/`. **El scope pasa a ser estructural.** El barrido recorre solo ese subárbol y declara huérfano al directorio cuyo `{media_id}` ya no tiene fila. Sin migración de rutas: esas tablas son nuevas y no tienen media previa. Se elige el path generator sobre un tombstone porque **no agrega estado que pueda desincronizarse**: la ruta es la fuente de verdad de sí misma. |
| **C-3.c** | El reparador estaba inventariado pero **no se ejecutaba**. | `frontend:maintain-media` solo corría reconcile → prune. | El orquestador pasa a correr **reconcile → prune → prune-orphans**, en ese orden y solo si el paso previo terminó sin error. Estar en el inventario no es estar orquestado; T-9f/T-9k lo verifican. |
| **M-7** | Faltaba el interleaving exacto y la prueba del scope sin fila; RFC-077 omitía los tests de purga. | §6.3 de la reauditoría. | **T-9l** (job pausado **después** del guard ⇒ el pruner no obtiene el lock y registra `skipped_busy`; caso simétrico; y el lock se conserva durante todo el ciclo) y **T-9m** (huérfano identificado solo por ruta; directorio con fila viva se omite; el barrido nunca recorre rutas de `FrontendSetting` ni de otros modelos), con clases nominales. El inventario de RFC-077 suma los cinco tests de purga. |

**Nota de proceso:** los dos defectos nacieron de la corrección anterior — un guard que parecía exclusión sin serlo, y un control compensatorio que prometía un scope que el path generator no permite reconstruir. La lección se repite y conviene dejarla escrita: **una barrera no vale por lo que declara sino por el mecanismo que la sostiene**. Un `if` al comienzo de un método no es un lock, y una regla de scope no es aplicable si el dato que la decide ya no existe.

**Estado del gate:** corrección documental aplicada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.12 Correcciones de la reauditoría P13

> **⚠️ SECCIÓN HISTÓRICA — SUPERSEDIDA POR §18.13.** Todo lo referido a **borrado físico de media** (prune, purga, intent, lease, advisory lock, jobs guardados, path generator con scope, barrido de huérfanos y sus tablas/comandos/tests) **quedó FUERA DE ALCANCE de v1** por decisión de §18.13. Se conserva como registro del análisis, **no como instrucción de implementación**. Lo que sigue vigente de esta sección se indica en §18.13.

Balance: **15 de 17 cerrados**. C-3 y M-7 seguían parciales por **un único bloqueante**: el ciclo de vida de la sesión que sostiene el advisory lock. La observación es correcta y verificada en el framework.

| ID | Bloqueante | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-3.a** | Un advisory lock **de sesión** no sobrevive a una reconexión, y el job seguiría escribiendo creyéndose protegido. | `Connection::handleQueryException()` lanza si hay transacción abierta, pero **fuera de transacción** delega en `tryAgainIfCausedByLostConnection()`, que hace `reconnect()` y **reintenta la consulta** (`Database/Connection.php:998-1004,1020-1023`). Como el período protegido es no transaccional (escrituras a filesystem), una conexión perdida se reemplaza en silencio y **la sesión nueva no posee el lock**. | Se separan **correctitud** y **velocidad**. La autoridad pasa a ser un **lease durable**: nueva tabla `frontend_media_activity` (PK `media_id`, `holder_token`, `expires_at`, `ON DELETE CASCADE`). Es **dato, no estado de sesión**, así que sobrevive a la reconexión. `expires_at` **se deriva del `$timeout` del job**: el worker mata al job al vencer, por lo que **un job vivo nunca sobrevive a su lease** — ahí está la garantía demostrable. El advisory lock queda para las secciones **cortas** (adquirir/liberar el lease, decisión del pruner), donde ya no es la autoridad. |
| **C-3.b** | Sin `finally` ni unlock verificado, una excepción retiene el lock en un **worker persistente** y bloquea la purga indefinidamente. | Búsqueda del contrato: el diseño especificaba adquisición y duración, pero no liberación. | Contrato explícito: **conexión dedicada `pgsql_locks`** (aislada de las consultas de la app), `pg_backend_pid()` capturado al adquirir y **comparado antes de liberar** —un PID distinto no asume posesión—, liberación en **`finally`** evaluando el booleano de `pg_advisory_unlock`, y `pg_advisory_unlock_all()` de cierre, seguro precisamente por correr sobre la conexión aislada. |
| **C-3.c** | Faltaba el fail-closed ante pérdida de sesión. | — | Antes de delegar en Spatie, el job revalida en **una** consulta que sigue siendo dueño de un lease vigente; si no lo es, **aborta sin escribir** y se reintenta. La revalidación consulta el **lease durable**, no `pg_locks`, así que no depende de la sesión. Se documenta además por qué se descarta `pg_advisory_xact_lock`: no puede filtrarse, pero exigiría una transacción abierta durante minutos de procesamiento de imágenes, reteniendo snapshot y bloqueando `VACUUM`. |
| **M-7** | Los tests cubrían el lock adquirido, no su ciclo de vida. | §6.3 de la reauditoría. | **T-9n** en `FrontendMediaLockLifecycleTest`: excepción con unlock verificado; pérdida/reconexión de sesión con aborto previo a cualquier escritura; PID distinto que no asume posesión; retry sin duplicar ni escribir fuera del lease; lease vencido que habilita la purga y lease vigente que fuerza `skipped_busy`; y `expires_at > $timeout`. |
| **Editorial** | Tres resúmenes desactualizados (§5). | Épica `:878,1000`; RFC-075 `:438`. | Los tres pasan a describir la cadena completa **reconcile → prune → prune-orphans**. |

**Nota de proceso:** es el segundo ciclo consecutivo en que la barrera propuesta **era correcta en el caso feliz y fallaba en el modo de fallo**. Primero un `if` que no era lock; ahora un lock que no sobrevive a una reconexión. La regla que queda: **al elegir una primitiva de exclusión hay que preguntar de qué depende su vida** —sesión, transacción, proceso, dato— y si ese soporte puede desaparecer mientras el trabajo continúa. Si puede, la correctitud tiene que apoyarse en algo durable.

**Estado del gate:** corrección documental aplicada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.13 Decisión de alcance: el borrado físico de media sale de v1

**Contexto medido.** Las reauditorías P8→P13 mantuvieron el balance en **15 de 17 hallazgos cerrados durante seis rondas consecutivas**, con C-3 y M-7 siempre parciales por el **mismo** subsistema: el ciclo de vida del borrado de media. Trece hallazgos llevan cinco o más rondas cerrados y ninguno se reabrió. No hubo regresión del diseño; hubo **estancamiento en un único componente**.

**Qué exigía ese componente.** Cada ronda agregó una capa para cerrar una carrera real: intent durable, promoción post-commit, borrado estricto con verificación de ausencia, familia responsive del original, jobs guardados, advisory lock, lease durable, path generator con scope, barrido de huérfanos. El costo acumulado: una tabla extra, una conexión de BD dedicada, `pcntl` como requisito productivo, la invariante `0 < job_timeout < lease_ttl < retry_after`, dos subclases de job de Spatie, dos comandos, un generador de rutas y ~30 métodos de prueba de concurrencia.

**Por qué se corta.** Recuperar espacio en disco **no es un requisito de la Épica 12**. El requisito real de C-3 era de seguridad —media en borrador no accesible públicamente— y está cerrado hace rondas por el disco privado, el controlador owner-only y la promoción post-commit. Se estaba pagando el componente más riesgoso del diseño, y bloqueando los otros 15 hallazgos ya cerrados, por una **optimización de almacenamiento** que nadie pidió, en un CMS cuyo volumen de imágenes huérfanas crece en megabytes por mes.

Además, la raíz del problema es estructural y no desaparece endureciendo el protocolo: PostgreSQL, el filesystem y la cola **no comparten transacción**. Cada ronda cerraba una carrera y descubría la siguiente por el mismo motivo.

| Decisión | Detalle |
| --- | --- |
| **Se elimina de v1** | Prune, purga física, intent, lease, advisory lock, conexión `pgsql_locks`, jobs guardados de Spatie, `FrontendMediaPathGenerator`, barrido de huérfanos, tabla `frontend_media_activity` y los tests T-9g→T-9n. El modelo vuelve a **cinco tablas**. |
| **Se conserva** | Media draft en **disco privado** + controlador owner-only; promoción post-commit idempotente con reconciliación; **`SoftDeletes`** en `FrontendSection`/`FrontendService` con `forceDelete` prohibido; índices únicos **parciales** (§16.1.2); reemplazo que no destruye la imagen publicada. |
| **Se agrega** | `frontend:media:report-unreferenced`: comando **de solo lectura** que lista media editorial sin referencias, con antigüedad y tamaño. No borra nada; existe para decidir con datos si la épica de borrado se justifica. |
| **Tradeoff aceptado** | El disco crece con media reemplazada o de secciones borradas. Con el tope de 3 MB por archivo, es costo despreciable frente al riesgo de borrar una imagen que una revisión publicada todavía referencia. |
| **Diferido** | El borrado físico pasa a una **épica propia**, con su diseño y su gate. El análisis de P8→P13 queda archivado en §18.6–§18.12 (marcadas como históricas) y en Engram. |

**Efecto sobre los hallazgos.** **C-3** queda cerrado por lo que exigía —privacidad de la media en borrador y que una revisión publicada nunca pierda su media—; el borrado ya no forma parte del alcance, así que no puede quedar "parcial" por él. **M-7** deja de requerir las pruebas de concurrencia de un subsistema inexistente; la matriz conserva T-9d como garantía de que **ningún camino del sistema borra archivos en v1**.

**Nota de proceso.** La corrección de un diseño no siempre es agregar una capa más. Cuando un componente concentra todos los bloqueantes durante seis rondas, la pregunta correcta no es "¿cómo lo hago seguro?" sino **"¿por qué está en v1?"**. Acá la respuesta era: por nada.

**Estado del gate:** decisión de alcance aplicada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.14 Correcciones de la reauditoría P14

La reauditoría validó el recorte de alcance, pero encontró que **la premisa central era falsa**: el sistema **sí** tenía una ruta automática de borrado. El hallazgo es correcto y verificado en el vendor instalado.

| ID | Bloqueante | Evidencia verificada | Corrección aplicada |
| --- | --- | --- | --- |
| **C-3 / B-1** | El uploader de Filament borra media físicamente en cada guardado. | `SpatieMediaLibraryFileUpload::setUp()` registra `saveRelationshipsUsing(fn => $component->deleteAbandonedFiles(); …)` (`vendor/filament/spatie-laravel-media-library-plugin/.../SpatieMediaLibraryFileUpload.php:125-128`), y `deleteAbandonedFiles()` hace **`$media->delete()` sobre todo UUID ausente del estado del formulario** (`:247-257`); el observer borra los archivos (`MediaObserver.php:55-64`). `SoftDeletes` protege el borrado del **modelo propietario**, no un `Media::delete()` directo. Mi decisión de alcance afirmaba "nada borra media en v1" — **era falso**. | Todas las colecciones de Épica 12 usan **`NonDestructiveMediaUpload`**, subclase con `deleteAbandonedFiles()` **no-op**. Retirar una imagen del formulario **solo quita la referencia del payload**; el archivo permanece. |
| **C-3 / segunda ruta** (no señalada por la auditoría) | El límite de tamaño de colección también borra. | Si una colección declara `singleFile()` u `onlyKeepLatest(n)` (`MediaCollection.php:90,98-106`), agregar media dispara `clearMediaCollectionExcept()` y **elimina el excedente** (`FileAdder.php:645-651`). | **Ninguna** colección de Épica 12 usa `singleFile()` ni `onlyKeepLatest()`. Prohibido explícitamente en `registerMediaCollections()` y afirmado por test. |
| **Consecuencia de diseño** | Sin borrado, la colección acumula y `getFirstMedia()` deja de ser determinista. | — | **Toda** referencia de media es por **`media_id` explícito**, incluida la marca (`logo_light_media_id`, `logo_dark_media_id`, `favicon_media_id`, `og_image_media_id` en `frontend_settings`). El render **nunca** usa `getFirstMedia()`. La pertenencia a la colección es almacenamiento, no fuente de verdad. |
| **C-1 / B-2** | §16 se contradecía: §16.9 seguía ordenando locks sobre `media` y quedaban métodos nominales del orquestador eliminado. | Épica `:706,719,822-824`. | Se quitan los locks de `media` de §16.9 (sin borrado, un UUID validado no puede quedar colgante) y los tres métodos nominales del orquestador. |
| **M-7 / B-3** | T-9d no probaba la ruta real de Filament. | Épica `:783,817-818`. | T-9d reescrito: ejercita el **guardado real del componente** —retiro y reemplazo— en `FrontendSetting`, `FrontendService` y `FrontendSection`; afirma que fila, original, conversiones y responsive images sobreviven, que la revisión publicada sigue resolviendo su `media_id`, que ninguna colección declara `singleFile()`, y que **el conteo de archivos en disco nunca decrece**. |
| **M-7 / B-4** | El comando de reporte no tenía clase inventariada ni prueba. | Épica `:568`, §16.13. | Se inventaría `ReportUnreferencedFrontendMedia` y se agrega **T-9o**: lista con antigüedad y tamaño, ignora media referenciada por draft/publicado/soft-deleted, y **no modifica ninguna fila ni archivo** (conteos y checksums antes/después). |
| **Coherencia RFC / §18.4** | Cuerpos operativos obsoletos y una fila histórica sin marcar. | §6 de la reauditoría; Épica `:1015`. | Cada línea de RFC-074/075/077 que menciona el subsistema eliminado lleva marca inline `HISTÓRICO: fuera de alcance v1, §18.13`; la fila de §18.4 queda anotada como supersedida. |

**Nota de proceso.** El recorte de alcance fue la decisión correcta, pero **quitar una funcionalidad del diseño no la quita del framework**. El stack traía dos rutas de borrado activas **por defecto** —una de Filament y otra de Spatie— y ninguna requería que el diseño las pidiera. La regla que queda: cuando una garantía se enuncia como *"el sistema nunca hace X"*, hay que **buscar quién hace X sin que se lo pidan**, no solo abstenerse de pedirlo.

**Estado del gate:** corrección documental aplicada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.15 Correcciones de la reauditoría P15

La reauditoría confirma que **la elección técnica central ya es viable** (`NonDestructiveMediaUpload` + prohibición de `singleFile()`/`onlyKeepLatest()`) y acota el rechazo a **cuatro inconsistencias documentales**. Las cuatro eran restos de la pasada anterior, no diseño faltante.

| ID | Bloqueante | Corrección aplicada | Verificación |
| --- | --- | --- | --- |
| **B-1** | §16.4 exigía cuatro `*_media_id` de marca, pero ni §16.1 ni RFC-071 los declaraban. Sin ellos no hay fuente de verdad determinista para elegir logo/favicon/OG, y el reporte no puede saber si una media de marca está en uso. | §16.1 y RFC-071 declaran `logo_light_media_id`, `logo_dark_media_id`, `favicon_media_id`, `og_image_media_id` (`uuid` nullable, FK → `media.uuid`), con validación de pertenencia y fallback. Las colecciones quedan como **almacenamiento**; el render **nunca** usa `getFirstMedia()`. T-9o trata esos UUID como referencias vigentes. | `rg 'logo_light_media_id'` → épica **3**, RFC-071 **1**. |
| **B-2** | §16.9 seguía invocando un prune inexistente (`:706`, `:719`), y §18.14 **afirmaba falsamente** haberlo retirado. | Ambas referencias reescritas: se valida existencia/owner/colección **sin lock sobre `media`**, porque en v1 ninguna ruta la borra. | `rg 'prune'` fuera de §18 → **0**. |
| **B-3** | RFC-074/075/077 conservaban instrucciones **activas** de locks sobre `media` y de programar `frontend:maintain-media`. Los comentarios inline no bastaban: había párrafos que mezclaban comportamiento vigente y obsoleto. | Los párrafos mixtos fueron **reescritos** con el contrato v1 (validación sin lock; scheduler solo `frontend:media:reconcile`), en vez de anexar comentarios. La enmienda P3R de RFC-075 queda marcada explícitamente como histórica. | `rg` de locks activos sobre `media` y de `maintain-media` programado → **0** en los tres RFC. |
| **B-4** | T-9f exigía scheduler de reconcile y ausencia de comandos destructivos, pero la lista nominal no tenía métodos de `FrontendMediaMaintenanceScheduleTest`. | Agregados `test_reconcile_is_scheduled_with_overlap_and_single_server_protection` y `test_no_destructive_command_is_scheduled`. | `rg 'FrontendMediaMaintenanceScheduleTest::'` → **2**. |

**Nota de proceso — el error que corrijo en mí mismo.** En §18.14 escribí que las referencias a prune de §16.9 "fueron retiradas". **No lo estaban.** Afirmé una limpieza sin comprobarla, y la auditoría lo encontró. Es el mismo defecto que vengo señalando en el diseño, aplicado a mi propia redacción: **una afirmación sin verificación es una hipótesis con tono de hecho**. Por eso esta reconciliación incluye una columna de verificación con el comando y el resultado: cada fila se puede reproducir, no hay que creerme.

**Estado del gate:** correcciones aplicadas y verificadas, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.16 Correcciones de la reauditoría P16

**Balance de la reauditoría: 16 de 17 hallazgos resueltos.** Solo C-1 quedaba parcial, por **tres líneas** en inventarios de RFC que seguían describiendo el subsistema eliminado.

| ID | Bloqueante | Corrección aplicada |
| --- | --- | --- |
| **B-3.1** | RFC-075 `:429` inventariaba `FrontendPagePublisher.php (página → secciones id ASC → media UUID ASC)`. | Reescrito: `(pagina -> secciones id ASC; media solo se VALIDA, sin lock)`. |
| **B-3.2** | RFC-075 `:431` y RFC-077 `:303` inventariaban `FrontendMediaReferenceService.php (locks/validación y recheck JSON)`. | Reescritos: `(valida existencia/owner/coleccion; SIN lock ni recheck: en v1 nada borra media)`. |
| **B-3.3** | RFC-077 `:361` conservaba la regla de purga **sin** marca histórica local, a diferencia de sus vecinas. | Marcada como histórica. **Causa raíz:** el script de marcado buscaba `purge` y la línea decía `purga`. |

**Verificación reproducible (lo que el auditor pidió).** Comando ejecutado desde la raíz del repo:

```bash
rg -n 'prune|purga|pruner|advisory|lease|huérfan|huerfan|orphan|maintain-media' \
  docs/epicas/epica-12-administrador-contenidos-frontend.md docs/rfc/RFC-07*.md \
  | rg -v 'HISTÓRIC' | rg -v ':[0-9]+:>' \
  | rg -v 'epica-12-administrador-contenidos-frontend\.md:1[0-9]{3}:' \
  | rg -v 'fuera de alcance|supersedid|no existe|NO existe|sale de v1'
```

**Control previo obligatorio:** correr el mismo patrón **sin los filtros** y comprobar que el resultado es **mayor que cero** en la épica y en RFC-074/075/077. Eso prueba que el patrón y las rutas funcionan — **un cero solo es informativo si el control da distinto de cero**. **No se fijan cifras a propósito:** esta misma sección menciona los términos buscados, así que cualquier número escrito acá queda obsoleto en cuanto se edita el documento (la reauditoría P17 detectó exactamente eso: los conteos habían pasado de 35/12/21/8/1 a 44/14/23/8/1 por autorreferencia). El criterio es el signo, no la magnitud.

**Resultado filtrado: 5 líneas, ninguna es instrucción activa.** Revisadas una por una: `:500` habla de un **índice** huérfano en el `down()` de una migración; `:546` justifica la promoción post-commit (que se conserva) mencionando un archivo público huérfano ante rollback; `:561`, `:563` y `:575` **son la propia declaración de alcance** de §18.13. Las menciones restantes en RFC son locks de **entidad** (`FrontendPage`, `FrontendSection`, `FrontendService`, `service_types`), que el diseño **conserva**, o la subcadena `block` dentro de `investors_block`.

**Nota de proceso — tres ceros falsos antes de este resultado.** Al construir esta verificación obtuve `0` tres veces por motivos equivocados: `rg` fuera del `PATH` del script, una función recursiva que causó segfault, y una variable de rutas expandida como un único nombre de archivo. Los tres imprimían `0` con aspecto de éxito. De ahí la regla incorporada arriba: **toda verificación negativa necesita un control positivo que demuestre que el instrumento mide.** Un cero sin control no prueba ausencia; prueba que no se buscó.

**Estado del gate:** correcciones aplicadas con verificación reproducible y controlada, **implementación BLOQUEADA**. Solo una nueva reauditoría independiente puede emitir `GATE DE DISEÑO: APROBADO`.

### 18.17 Gate aprobado (reauditoría P17)

**Veredicto: `GATE DE DISEÑO: APROBADO` sobre el commit `44f8ec9`. Balance final: 17 de 17 hallazgos resueltos. P4-A habilitado.**

Cierre del último bloqueante (C-1): los inventarios activos de RFC-075 `:429-431` y RFC-077 `:303` prescriben validación de media **sin lock ni recheck**, y la regla residual de purga de RFC-077 `:361` lleva marca histórica local.

**Observación no bloqueante atendida.** La reauditoría señaló que los conteos de control de §18.16 habían quedado obsoletos —de 35/12/21/8/1 a 44/14/23/8/1— **por autorreferencia**: la propia sección que documenta la búsqueda menciona los términos buscados, así que engorda el corpus que cuenta. Un reconteo posterior con `rg -c` (líneas, no ocurrencias) arroja cifras distintas otra vez. Corrección aplicada: **el control ya no fija cifras, exige que el resultado sin filtros sea mayor que cero**. El criterio es el signo, no la magnitud — una métrica que se invalida al escribirla no sirve como control.

**Recorrido del gate.** Diecisiete rondas de auditoría independiente, sin una línea de código de producción:

| Ronda | Balance |
| --- | --- |
| P3R | 8 / 16 |
| P4R | 12 / 16 |
| P5–P6 | 12–13 / 17 |
| P7–P13 | 15 / 17 (seis rondas estancadas en el borrado de media) |
| P14 (recorte de alcance §18.13) | 14 / 17 |
| P15–P16 | 15–16 / 17 |
| **P17** | **17 / 17 — APROBADO** |

Lo que el gate evitó, cazado leyendo código del framework y no el documento: snapshots mixtos por concurrencia, generación de caché perdida por `Cache::increment()` sobre store `database`, un mutex de scheduler que no serializaba comandos distintos, archivos huérfanos por borrado dentro de transacción, un remover que silencia fallos, un advisory lock que no sobrevive a una reconexión, y **dos rutas del stack que borraban media sin que nadie las invocara**.

**Estado:** implementación **DESBLOQUEADA**. El alcance de v1 y los contratos vigentes son §16; §18.6–§18.12 quedan como registro histórico del borrado físico diferido a épica propia.

---

## 19. Notas de implementación por lote

Decisiones menores tomadas al implementar, que no estaban resueltas en el diseño y que la auditoría de lote debe conocer. No alteran contratos de §16: los precisan.

### 19.1 Lote A — Kernel + Perfil

**A-1. `NonDestructiveMediaUpload` también debe reemplazar la carga de estado, no solo el borrado.** El diseño (§16.4) exigía anular `deleteAbandonedFiles()`. Al implementar apareció una segunda ruta que contradice "la colección es almacenamiento, no fuente de verdad": el componente de Filament rellena su estado con `loadStateFromRelationshipsUsing`, que lee **la colección** y toma `take(1)` (`SpatieMediaLibraryFileUpload.php:55-74`). Como en v1 nada se borra, la colección acumula versiones y esa lectura no es determinista; peor aún, resucita en el formulario una referencia que el owner acababa de quitar. El componente propio agrega `->uuidColumn('<columna>')` y carga el estado **desde la columna `*_media_id`**, la misma que resuelve el render. Es coherente con §16.4 y lo hace ejecutable.

**A-2. El intent del formulario se captura ANTES de `getState()`.** `ComponentContainer::getState()` ejecuta `saveRelationships()` y **acto seguido** `loadStateFromRelationships(andHydrate: true)` (`filament/forms/src/Concerns/HasState.php:244-245`), de modo que el estado posterior ya no refleja lo que el owner envió, sino lo releído del registro. `FrontendSettingsPage::save()` captura el estado crudo de los campos de marca antes de validar y usa esa captura para decidir el valor de las columnas. Sin esto, quitar una imagen del formulario no persistía (se releía la anterior).

**A-3. Los tests de conexiones reales exigen `DB::purge()`, no solo `disconnect()`.** §16.11 pide pruebas de concurrencia con dos conexiones PostgreSQL reales. Implementadas de forma ingenua, rompieron **24 tests ajenos** de la suite con errores del tipo `relation "users" does not exist`. Causa: `disconnect()` cierra el PDO pero **deja la conexión registrada** en el `DatabaseManager`; la sesión remanente sostiene locks que hacen fallar el `migrate:fresh` que `RefreshDatabase` dispara más adelante (`DatabaseMigrations` resetea su flag), y el esquema queda a medias. Se extrajo el trait `tests/Support/UsesRealPostgresConnections`, que centraliza la limpieza de datos y el `DB::purge()` de cada conexión. **Los lotes siguientes deben usar ese trait** para toda prueba de concurrencia.

**A-4. Hallazgo preexistente, fuera de alcance del lote: `create_media_table` no tiene `down()`.** `database/migrations/2026_06_16_215849_create_media_table.php` solo define `up()`, así que `migrate:rollback` reporta la migración como revertida sin borrar la tabla `media`. Hoy es inocuo porque `RefreshDatabase` usa `migrate:fresh` (que dropea todo), pero es una bomba latente para cualquier flujo que dependa de `rollback`. **No se corrige en este lote** por ser una migración existente y ajena al alcance (regla de oro: cambios aditivos). Queda registrado para decidirlo aparte.

**A-5. Violaciones de Pint preexistentes, no tocadas.** `database/seeders/ZoneSeeder.php`, `database/seeders/AgentSeeder.php` y `app/Livewire/Leads/LeadCaptureForm.php` fallan `pint --test` también con este lote revertido (verificado con `git stash`). Se dejaron intactas para no mezclar ruido en el diff del lote. `pint --test` está **limpio sobre todos los archivos del lote**.

**A-6. `frontend_settings` se crea de forma perezosa, no por migración.** El diseño no fija el momento de creación de la fila singleton. Se resolvió con `FrontendSetting::current()` (`firstOrCreate`), que es idempotente y seguro ante concurrencia gracias al `UNIQUE` + `CHECK`. No requiere seeder ni migración de datos, y mantiene el frontend en fallback puro hasta que el owner guarde por primera vez.

#### 19.1.1 Correcciones tras la auditoría de implementación del Lote A

La auditoría (`docs/audits/epica-12-lote-a-auditoria-implementacion.md`) rechazó el lote con cinco hallazgos medios propios y dos bloqueantes de línea base. Los cinco propios eran correctos y están corregidos.

| ID | Contrato incumplido | Corrección |
| --- | --- | --- |
| **M-1** | §16.1: el UUID debe existir, **pertenecer al singleton y a la colección**; solo tenía la FK de existencia. | Nuevo `FrontendMediaReference` valida `model_type` + `model_id` + `collection_name` antes de persistir; UUID inelegible lanza `ValidationException` y **aborta el guardado completo** (all-or-nothing). El render usa **el mismo servicio**, eliminando la duplicación que la auditoría marcó como riesgo de drift para los lotes D/E. |
| **M-2** | §16.8 lista `Media` entre las mutaciones que deben hacer bump; no estaba implementado. | `FrontendMediaObserver` con bump `DB::afterCommit`, **acotado a entidades del frontend** (una portada de inmueble no debe invalidar el sitio entero). Un rollback no incrementa. |
| **M-3** | §16.1: "validación dura al guardar **+ normalización defensiva en el boundary de render**". El formulario no es el único escritor. | `normalizedEmail()` / `normalizedWhatsapp()` en `settings()`: un email inválido o un teléfono con menos dígitos que un número internacional plausible caen al **fallback exacto** de §16.7, en vez de publicar `https://wa.me/1`. |
| **M-4** | La caché es optimización con red de seguridad TTL, no dependencia dura. | `catch` **acotado** de fallos del store → lectura directa + `Log::warning`. Deliberadamente no es un catch-all: un `LogicException` de `build()` sigue propagando, con test que lo prueba. |
| **M-5** | §16.11: "Concurrencia = dos conexiones independientes **(no llamadas secuenciales)**". | Reescritos T-2 y T-10a con **solapamiento real**: A retiene la transacción, B se **bloquea** de verdad (probado con `lock_timeout` y `assertTrue($blocked)`, que hace fallar el test si no hay contención), A commitea, B reintenta. |

**Autocrítica registrada (M-5).** Los tests anteriores se llamaban "concurrentes" y ejecutaban A y después B. No probaban ninguna garantía de carrera y **generaban confianza falsa** — el mismo defecto que este documento le viene exigiendo al diseño ("una barrera vale por el mecanismo que la sostiene, no por lo que declara"), cometido en la capa de pruebas. La corrección exige contención observable, no idempotencia secuencial.

**Bloqueantes de línea base (C-1, C-2): resueltos en tarea aparte.** Ambos verificados como preexistentes con `git stash` (fallan igual con el lote revertido), por lo que se corrigieron **fuera del diff del Lote A** para no romper la atribución por lote:

- **C-1** — la migración `2026_07_04_000000` cambió `zones.polygon` de `Polygon` a `MultiPolygon`, pero `ZoneSeeder` seguía emitiendo WKT `POLYGON`, y el cast de Magellan lo rechazaba: `migrate:fresh --seed` moría. Se agrega un helper que envuelve el anillo en un MultiPolygon de una parte (misma geometría, tipo correcto) e ignora literales que ya sean MULTIPOLYGON. Con tests de tipo, validez e idempotencia.
- **C-2** — `pint --test` fallaba por tres archivos ajenos al lote y por un **snippet de documentación no parseable** (`docs/files-login-design/AdminPanelProvider.snippet.php`). Se formatean los tres archivos y se agrega `pint.json` que excluye `docs/`: son artefactos de documentación, no código fuente. Pint global queda en **exit 0**, dando la línea base reproducible que pedía la auditoría para atribuir estilo en lotes posteriores.

#### 19.1.2 Cierre de M-4 (reauditoría del Lote A)

La reauditoría cerró M-1, M-2, M-3, M-5, C-1 y C-2, y dejó **M-4 abierto** con evidencia exacta: `CacheManager::resolve()` lanza la clase **global** `\InvalidArgumentException` cuando el store no existe (`vendor/laravel/framework/src/Illuminate/Cache/CacheManager.php:120`). Esa clase extiende `LogicException`, así que **no** es `RuntimeException` **ni** implementa `Psr\SimpleCache\InvalidArgumentException` — las dos ramas que capturaba el servicio. Verificado con `get_parent_class()` e `is_subclass_of()`.

**La corrección no es agregar la clase que faltaba.** Enumerar excepciones es precisamente lo que dejó pasar el fallo: la lista siempre puede estar incompleta, y cada store nuevo trae sus propias clases. El aislamiento pasa a ser **estructural**:

- `build()` se ejecuta **fuera de todo `try` de caché**. Sus errores de dominio o programación no pueden ser absorbidos por el manejo de caché, no porque se excluya una clase sino porque **nunca están dentro del bloque protegido**.
- Con `build()` afuera, las llamadas a caché sí pueden guardarse con `Throwable`, que es lo que un fallo de store realmente necesita: cubre resolución inválida, backend caído y cualquier clase futura.
- La lectura y la escritura se guardan por separado: un fallo al escribir **devuelve igual el valor** ya calculado, porque solo falló la optimización.

Esto satisface la exigencia de la reauditoría ("no convertir el catch en `Throwable` genérico: los errores de `build()` deben seguir propagándose") de forma más fuerte que enumerando clases, y el test `test_a_programming_error_inside_build_is_not_swallowed` lo prueba.

**Tests agregados**, usando el fallo **real** de Laravel y no solo mocks genéricos (recomendación 11.2 de la reauditoría): store inexistente que degrada a lectura directa; store inexistente que además entrega los fallbacks exactos de §16.7; store que lanza al leer; store que lanza al escribir. Total del lote: **542 tests verdes**.

**Lección registrada.** Es el mismo patrón que la Épica documentó en §18.9 al auditar el diseño —"una API que parece reportar errores y no lo hace"— apareciendo ahora en la implementación: la defensa por *lista de clases* es tan frágil como la defensa por *declaración*. Cuando la garantía es "esto nunca debe tumbar el sitio", el aislamiento tiene que ser estructural, no enumerativo.

### 19.2 Lote B — Tema visual runtime

**B-1. El contraste se valida en dos boundaries, con la misma clase.** `ThemeContract` es la única fuente del schema: Filament la usa para rechazar al guardar y `FrontendThemeService` para re-normalizar al render. Se evita así el drift que la auditoría del Lote A marcó cuando la validación de media estaba duplicada.

**B-2. La normalización al render es campo por campo, no todo o nada.** Un color inválido persistido no descarta el resto del tema: solo ese campo cae a su fallback. Descartar el tema entero por un valor malo castigaría al owner por un dato que probablemente no escribió desde el formulario.

**B-3. Un par por debajo de AA se repara al render, no se publica.** Si la base termina con un par ilegible (import, SQL manual, fila legacy), el servicio reemplaza el color de texto por negro o blanco —el que más contraste dé sobre ese fondo— en vez de emitir algo que no se puede leer. La validación al guardar impide llegar ahí desde la UI; esto cubre el resto de los caminos.

**B-4. La regex de color es también la defensa XSS.** `^#[0-9a-fA-F]{6}$` hace imposible emitir un valor que cierre el bloque `<style>`. Por eso el `<style>` del layout no necesita escapado adicional: lo que llega ya pasó por el contrato. T-8b lo prueba de punta a punta contra el HTML renderizado.

**B-5. Deltas visuales asumidos al migrar a utilities semánticas.** §16.5 exige que `text-white` sobre CTA y `bg-navy-900` como superficie de marca no permanezcan en roles brand-critical. Migrarlos cambia levemente el default:

| Rol | Antes | Ahora (fallback) | Motivo |
| --- | --- | --- | --- |
| Texto del `<body>` | `text-graphite` `#2d2d2d` | `text-site-text` → `#111111` | Es el rol "texto sobre el fondo" del contrato; el token declarado en §16.5 es `#111111`. |
| Superficie del footer | `bg-navy-900` `#050f38` | `bg-brand-primary` → `#091a5b` | §16.5 prohíbe explícitamente `bg-navy-900` en un rol de marca. |
| Hover de botones | tonos fijos (`orange-hover`, `navy-700`) | `brightness()` sobre el color del tema | Un hover fijo dejaría de combinar en cuanto el owner cambie la paleta. |

Se documentan porque son visibles y deliberados: sin ellos, el tema no llegaría a esos roles. Los tonos decorativos y de estado (`navy-50` como tinte de nav activo, scrim del drawer, verde de WhatsApp, sombras y bordes neutros) **se conservan fijos**, dentro del alcance explícito de §16.5.

**B-6. Verificación en navegador real.** Además de los tests, se aplicó un tema distintivo (`primary #0f766e`, `accent #be123c`, `radius rounded`) y se comprobó por CSSOM que el CTA resuelve `rgb(190, 18, 60)`, el footer `rgb(15, 118, 110)` y el radio `16px`. Confirma lo que los tests de clases no pueden: que el puente `@theme inline` efectivamente resuelve en el navegador y no solo emite variables ignoradas — que era justo el modo de fallo que M-4 del diseño describía.

**B-7. Hallazgo operativo.** El sitio público consulta `frontend_cache_generation` en cada request, así que la base de desarrollo necesita `php artisan migrate` antes de servir. Es correcto (el deploy migra antes de servir) pero conviene tenerlo presente al levantar entornos nuevos.

#### 19.2.1 Correcciones tras la auditoría de implementación del Lote B

La auditoría rechazó el lote con dos hallazgos medios y uno menor. Los tres eran correctos.

| ID | Contrato incumplido | Corrección |
| --- | --- | --- |
| **M-B1** | `RFC-072:148` exige migrar los roles de marca en shells de página y tarjetas públicas; `welcome`, `site/*` y `leads/create` conservaban paleta fija. | Migrados los siete consumers públicos. Los gradientes de hero pasan a `bg-brand-primary` con degradado de luz/sombra encima: **el color es rol tematizado, la profundidad sigue siendo decoración**. Se conservan explícitamente los grises neutros, el glow de acento, el verde de WhatsApp y los shades con sufijo. |
| **M-B2** | Los tests no detectaban el hueco. | Matriz `FrontendPublicThemeCoverageTest`: por vista exige utilities semánticas **y** ausencia de roles fijos; por ruta pública comprueba que un tema guardado llega **y se consume**. Al escribirla fallaba **15 de 27** — esa es la prueba de que detecta el defecto real, no de que la aserción sea laxa. |
| **Mn-B1** | `RFC-072:137` pide 3:1 en foco/contorno y no había ni estilo de foco ni garantía. | Se emite `--nh-focus`: usa el acento cuando alcanza 3:1 contra el fondo y, si no, degrada al color de texto, que el contrato ya valida a 4.5:1 contra ese mismo fondo. La garantía es **estructural**, no una restricción extra sobre la paleta del owner. Se agrega `focus-visible` en botones y navegación. |

**Tres defectos que encontré mirando el navegador, no los tests.**

**1. Mi propia matriz tenía el mismo hueco que estaba corrigiendo.** Baneaba clases (`bg-navy`) pero no **hex de marca dentro de valores arbitrarios** (`bg-[linear-gradient(...#091A5B...)]`). Los hero shells seguían navy con todas las clases aparentemente migradas. Se agregó `test_public_views_do_not_hardcode_brand_colours_in_arbitrary_values`, que falló en 5 vistas y las obligó a migrar. Es exactamente el defecto de M-B2 cometido dentro de su propia corrección.

**2. Introduje un problema de legibilidad al tematizar superficies.** Los eyebrows de acento sobre el hero eran legibles solo porque la superficie era **siempre navy**. Al hacerla configurable, un acento carmesí sobre un primario teal queda ilegible — visible en captura. El contrato de §16.5 no cubre ese par (valida acento contra su propio texto, no contra el primario). Se agrega `--nh-accent-on-primary`, que conserva el acento cuando es legible sobre la superficie y degrada a `on_primary` en caso contrario.

**3. Un cambio de shape de la caché podía tumbar el sitio en el deploy.** Al agregar una clave al arreglo cacheado, las entradas escritas por la release anterior seguían tibias y su lectura lanzaba `Undefined array key` → 500 en producción. **Ningún test podía verlo**: todos arrancan con caché fresca. La clave ahora lleva versión de shape (`frontend:g{N}:theme:v{S}`), así una estructura distinta falla el cache-miss en vez de explotar. Con test de regresión que simula la entrada vieja.

**Nota de proceso.** Los tres salieron de abrir el navegador con un tema distintivo, no de la suite. La suite verifica lo que uno pensó verificar; el navegador muestra lo que el usuario ve. En un lote cuyo entregable es visual, la verificación visual no es un extra.

#### 19.2.2 Correcciones tras la reauditoría del Lote B

La reauditoría reabrió M-B1 y M-B2, mantuvo Mn-B1 y abrió **C-B1 (crítico)**. Los cuatro eran correctos y verificados contra el código.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **C-B1** | Superficies ya tematizadas usaban foregrounds **no garantizados**: `bg-brand-accent` con `text-brand-primary` (1.15:1 con el tema probado) y `bg-brand-primary` con `text-white` fijo. | Cada superficie usa su `on_*`. Nuevo test que **parsea los atributos `class`** y exige el par correcto, analizando estado base y `hover:` por separado. |
| **M-B1** | Quedaban `bg-navy-900` como superficie en dos CTAs y **los tres `site/partials/*` sin migrar** — el listado y detalle de proyectos solo muestran el hueco cuando existen proyectos. | Migrados partials, CTAs y placeholders. El scrim del drawer pasa a `bg-black/40`: **un atenuador no es rol de marca**, y volverlo primario era conceptualmente incorrecto. |
| **M-B2** | La matriz podía certificar el defecto. | Reescrita con tres correcciones **estructurales**, no cosméticas. |
| **Mn-B1** | `focus:ring-orange` en la galería de proyecto. | `focus:ring-brand-focus`, que ya lleva la garantía 3:1. |

**Por qué mi matriz certificaba el defecto, y qué cambió.**

| Debilidad | Consecuencia | Corrección estructural |
| --- | --- | --- |
| Lista de vistas **escrita a mano** | `site/partials/*` era invisible | Las vistas se **descubren del disco**: un partial nuevo queda cubierto el día que se crea |
| Excluí **en bloque** los shades con sufijo como decorativos | `bg-navy-900` sobrevivió como superficie de CTA, y §16.5 lo prohíbe **por nombre** | Las superficies de marca se prohíben explícitamente, con sufijo o sin él |
| La aserción de ruta se satisfacía con el **layout global** | Una página entera con roles fijos igual pasaba | Las rutas se afirman por **ausencia** de roles fijos en el HTML renderizado, con proyectos sembrados para forzar los partials |
| No existía verificación de par superficie/texto | C-B1 era invisible | Test de pares que parsea `class` por estado |

**Dos refinamientos que el test necesitó para no volverse ruido.** Un test impreciso deja de leerse, y eso es su propio modo de fallo:

- **Solo utilities de color cuentan como foreground.** `text-sm`, `text-center` y `text-[11px]` son tamaño y alineación; marcarlas habría llenado la salida de falsos positivos.
- **Solo una superficie opaca gobierna el contraste.** `bg-brand-accent/10` es un tinte: lo que el lector ve detrás del texto sigue siendo el fondo de página, así que la regla de pares no aplica por debajo del 50 % de opacidad.

**Frontera rol/decoración, ahora explícita.** Se tematizan: superficies primarias y de acento (incluidas las de gradiente), sus textos, tipografías, radios, foco y los estados de navegación del carrusel. **Se conservan fijos**: colores de estado del badge (`success`, `danger`, `warning`), neutros, el verde de WhatsApp, el glow radial de acento, los grises decorativos y el scrim del drawer.

**Verificación con datos reales.** Con dos proyectos sembrados y el tema distintivo, `/proyectos` renderiza **cero** clases de marca fijas en el DOM; las flechas de navegación resuelven `rgb(15, 118, 110)` y el tab activo `rgb(190, 18, 60)`. Es la prueba que la auditoría pedía: el hueco de los partials solo aparece con datos, y un home vacío lo ocultaba.

#### 19.2.3 Correcciones tras la segunda reauditoría del Lote B

La reauditoría cerró **Mn-B1** pero mantuvo **C-B1** y **M-B2** abiertos, con un diagnóstico exacto: la matriz por *source* solo exigía el foreground cuando `bg-brand-*` y `text-*` estaban **en el mismo elemento**. En los CTAs reales la superficie vive en el `<div>` padre y el texto en los hijos, así que la relación peligrosa nunca se evaluaba. Un owner podía guardar un par válido (`primary #fef08a`, `on_primary #111111`, 16.2:1) y el texto seguía blanco e ilegible.

| ID | Defecto que sobrevivió | Corrección |
| --- | --- | --- |
| **C-B1** | Textos de contenido sobre superficie temática seguían con foreground fijo (`text-white`, `text-[#5b3f00]`, `text-brand-primary`/`text-brand-accent`) en **elementos hijos** del que pinta la superficie. | Migrados los 58 foregrounds blancos de los hero/paneles/CTA primarios a `text-on-brand-primary` (con sus opacidades), los CTA sobre acento a `text-on-brand-accent`, y los eyebrows de acento del footer a `text-accent-on-brand-primary`. |
| **M-B2** | La aserción global de presencia/ausencia por *source* no recorría el árbol. | Nuevo `FrontendSurfaceForegroundTest`: **recorre el DOM renderizado** (DOMDocument/DOMXPath). Para cada elemento que pinta una superficie temática opaca (≥50 %, incluidos los stops `from-*`/`via-*` de gradiente), exige que **todo descendiente** que fije color use el token garantizado. |

**Por qué el árbol y no el source.** El color de texto se **hereda hacia abajo**: se declara junto al contenido, no junto al fondo. La única forma de afirmar el contrato real —"lo que se pinta sobre esta superficie es legible"— es mirar la relación ancestro/descendiente en el HTML ya renderizado. El test arrancó en **rojo con 35 violaciones únicas enumeradas**; esa lista fue la guía de migración. No es una aserción laxa que pasa por diseño: es la que detecta el defecto que dos suites "verdes" dejaron pasar.

**Tres decisiones de frontera que el árbol volvió explícitas.**

- **Los stops de gradiente cuentan como superficie.** Los hero lavan `from-brand-primary/[0.92]` sobre una foto: el 92 % de lo que el lector ve detrás del titular **es** la superficie de marca. Tratarlo como "no superficie" es justo como el `text-white` sobrevivió en los hero.
- **`hover:`/`focus:` se juzgan contra la misma superficie que el estado base.** Un color de hover se pinta sobre el mismo fondo, así que hereda la misma garantía.
- **Un descendiente que pinta su propio fondo opaco sale del contrato.** El verde de WhatsApp (`bg-[#25d366]`), el CTA gris neutro (`#2e2e2e`) y el lightbox sobre scrim oscuro **no** son superficie de marca: su blanco es legítimo y el test lo respeta comprobando el fondo real detrás del texto, no solo el temático.

**`accent-on-brand-primary` es foreground garantizado.** El test lo acepta sobre `bg-brand-primary` porque el servicio ya lo degrada a `on_primary` cuando el acento no es legible sobre esa superficie: es un token con garantía, no un acento crudo.

#### 19.2.4 Correcciones tras la tercera reauditoría del Lote B

La tercera reauditoría cerró C-B1, M-B1, M-B2 y Mn-B1, y abrió **C-B2 (crítico)**: `primary` y `accent` se usaban como **color de texto** (`text-brand-primary`/`text-brand-accent`) sobre el fondo base, pero el contrato solo valida esos colores contra su propio `on_*`, no contra `background`. Con un tema válido (`primary #fef08a`, `accent #fde68a`, `background #ffffff`) los títulos, eyebrows y enlaces quedaban en **1.16:1 / 1.25:1** — muy por debajo de AA. Verificado con `ThemeContract::contrastRatio()`.

**El doble rol es la raíz.** `primary`/`accent` sirven a la vez como **superficie** (`bg-brand-*`) y como **foreground** (`text-brand-*`). El contrato solo puede garantizar uno. Un primario claro es **válido como superficie** (con `on_primary` oscuro encima, el hero da 16.2:1); prohibirlo al guardar para proteger el otro uso castigaría una paleta legítima.

**Rechazo la Opción 1 del auditor** (validar `primary/background` y `accent/background` al guardar). Estrecharía la paleta sin necesidad. Aplico la **Opción 2** con el mismo patrón que `accent_on_primary` y `focus`: separar el rol de foreground en un token derivado con garantía.

| Token nuevo | Regla | Uso |
| --- | --- | --- |
| `--nh-primary-ink` → `text-brand-primary-ink` | `primary` si es legible sobre `background`; si no, degrada a `text` (que el contrato ya valida a 4.5:1 contra `background`). | Títulos, nav activo, datos de contacto sobre el fondo base o tintes claros. |
| `--nh-accent-ink` → `text-brand-accent-ink` | Igual, con `accent`. | Eyebrows, enlaces "ver todos". |

`--nh-primary`/`--nh-accent` **siguen siendo superficies** (`bg-brand-*` intactos). Migrados los 54 foregrounds de contenido de `text-brand-*` a `text-brand-*-ink`. La caché sube a `SHAPE=3`.

**Delta visual deliberado por defecto, y por qué es correcto.** Igual que la tabla B-5:

| Rol | Antes (default) | Ahora (default) | Motivo |
| --- | --- | --- | --- |
| Títulos de sección | `text-brand-primary` navy `#091a5b` | `text-brand-primary-ink` → **navy sin cambio** | El primario por defecto da 14.9:1 sobre el fondo; `ink` lo conserva. |
| Eyebrows / enlaces de acento | `text-brand-accent` naranja `#f6a300` | `text-brand-accent-ink` → **texto `#111111`** | El naranja por defecto daba **1.93:1 sobre el fondo base: nunca cumplió AA.** `ink` lo degrada a un color legible. |

El eyebrow naranja no era una decisión de marca sacrificada: era un **defecto de accesibilidad preexistente** que C-B2 destapó. Degradarlo es la corrección, no una regresión. Los enlaces `text-orange-600` fijos (no tematizados) quedan fuera del contrato de tema y fuera del alcance de C-B2.

**Cobertura.** Tres pruebas nuevas, ninguna cosmética:

- `FrontendThemeServiceTest`: con el tema hostil, `primary_ink`/`accent_ink` **degradan** y cumplen AA sobre `background`; con un tema legible, `ink == color de marca` (no se aplana a negro).
- `FrontendPublicThemeCoverageTest`: **prohíbe `text-brand-primary`/`text-brand-accent` crudos** como foreground en cualquier vista (con o sin estado `hover:`); deben ser `-ink` sobre el fondo base u `on-brand-*` sobre la superficie. Cierra el riesgo de mantenimiento #2 del auditor: un consumer nuevo no puede reintroducir el hueco.
- `FrontendThemeRuntimeTest`: extremo a extremo — el HTML servido con el tema hostil emite `--nh-*-ink` que **cumplen AA**, y nunca sirve el color pálido crudo como tinta.

**Verificación viva.** Con el tema hostil (`#fef08a`/`#fde68a`/blanco), en `/inversionistas` el título de sección y el eyebrow sobre el fondo base resuelven `rgb(17,17,17)` (antes 1.2:1, ilegibles); el hero conserva `on-brand-primary`; sin errores de consola. Con el tema por defecto, los headings siguen navy y los eyebrows pasan a texto oscuro legible.

#### 19.2.5 Correcciones tras la cuarta reauditoría del Lote B

La cuarta reauditoría cerró **C-B2** y reabrió **M-B1/M-B2** más un nuevo **M-B3**. Los tres tenían la **misma raíz**: mi matriz "descubre del disco" presumía cubrir *"every public Blade file"*, pero sus raíces eran `site/**` y `components/**` elegidas a mano. Dejó fuera `inmuebles/**` y `livewire/leads/**` — rutas públicas reales (`/inmuebles`, `/contacto`) enlazadas desde el header. Los 636 tests verdes daban una **falsa sensación de cobertura**.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **M-B1** | `inmuebles/{index,show}` conservaban gradiente navy fijo, `text-navy`, `text-orange`, `font-display`. | Migrados ambos consumers y el form Livewire: hero → `bg-brand-primary` con degradado de luz/sombra; texto de contenido → `text-brand-primary-ink`/`text-on-brand-primary`; acento → `text-accent-on-brand-primary`/`on-brand-accent`; fuente → `font-brand-heading`. Scrims sobre foto → `bg-black/*` (**un atenuador sobre imagen no es rol de marca**). |
| **M-B2** | La discovery mentía sobre su alcance. | Raíces ampliadas a `inmuebles` y `livewire/leads`. Y un **test guardián** recorre TODO el árbol, encuentra cada vista que renderiza en `x-layouts.public` y exige que esté cubierta **o** en una lista de exclusión explícita. Un consumer público nuevo **falla** hasta que un humano decide su alcance. |
| **M-B3** | `focus:ring-orange/20` ignoraba `--nh-focus`; el anillo compuesto daba ~1.16:1 sobre blanco (bajo el 3:1 de RFC-072:138). | Sustituido por `focus:ring-brand-focus`/`focus:border-brand-focus` (sin alpha) en el form de leads y los filtros/galería de inmuebles; el radio pasa a `accent-brand-accent`. Nuevo test que prohíbe `ring-orange`/`border-orange`/`accent-orange` en vistas públicas. |

**Exclusiones de alcance, ahora explícitas y asertas.** El tema gobierna el frontend de **marketing**. Quedan fuera, documentado en la constante `EXCLUDED_PUBLIC_VIEWS` y verificado por el guardián:

- `styleguide`: página de referencia dev que **documenta la paleta fija** a propósito (sus muestras SON `bg-navy`, `text-orange`); tematizarla borraría lo que existe para mostrar. No enlazada desde el nav.
- `public/contratos/*` y `contratos/*`: flujo de firma/verificación de contratos, área funcional aparte alcanzada por token — no el frontend que el owner personaliza.

**Por qué una lista a mano volvió a fallar, y qué lo cierra de fondo.** El error no fue la lista: fue **afirmar cobertura total sin un mecanismo que lo probara**. El guardián invierte la carga: en vez de que yo recuerde agregar cada vista, el test recorre el árbol y **obliga** a clasificar cada vista de layout público. La cobertura ya no depende de mi disciplina.

**Verificación con datos reales.** Con una propiedad publicada y el tema hostil, `/inmuebles` renderiza **cero** `ring-orange`/`text-navy`/`font-display`/`bg-navy-900`; el hero resuelve la superficie ámbar con texto oscuro legible; `--nh-focus` resuelve `rgb(17,17,17)` (18.9:1 sobre blanco); sin errores de consola.

#### 19.2.6 Correcciones tras la quinta reauditoría del Lote B

La quinta reauditoría cerró **C-B2/M-B1/M-B2/M-B3** y abrió **M-B4**: cuatro iconos SVG en `site/inversionistas.blade.php` fijaban `stroke="#ffffff"` **dentro** de `bg-brand-accent`. Con un acento claro (`#fde68a`), el stroke blanco daba **1.245:1** — ilegible. Mismo defecto que C-B1 (foreground no garantizado sobre superficie temática) pero por un **vector que el test no miraba**: un atributo `stroke`/`fill` de SVG, no una clase de color.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **M-B4** | `stroke="#ffffff"` en un SVG sobre `bg-brand-accent` (4 iconos), 1.245:1. | `stroke="currentColor"` + `text-on-brand-accent` en el contenedor: el icono hereda el foreground garantizado. De paso, `border-navy` (borde de marca fijo del mismo bloque) → `border-brand-primary/15`. |

**Cerrado el vector, no solo la ocurrencia.** El `violations()` del árbol DOM ahora, además de las clases, recorre los atributos `stroke`/`fill` de cada descendiente de una superficie temática: cualquier valor fijo (hex, `white`, `rgb`) es violación; solo se permiten `currentColor`, `none`, `inherit`, `transparent`, `url(...)` y `var(...)` —los que **difieren** al color heredado, que una clase fija al token garantizado—. Un test de regresión alimenta un snippet malo conocido (`<svg stroke="#ffffff">` sobre `bg-brand-accent`) y **prueba que el detector lo caza**, no solo que las vistas están limpias. La matriz por source suma `border-navy` a los roles fijos prohibidos.

**Caza exhaustiva del vector.** Antes de corregir se barrieron todas las vistas públicas por `stroke`/`fill` fijos, bordes de marca (`border-navy`/`border-orange`) y `style` inline con color: solo aparecieron las dos ocurrencias de este bloque. No quedan otros focos del mismo tipo.

**Verificación con datos reales.** Con el tema hostil, los cuatro iconos resuelven `stroke: rgb(17,17,17)` vía `currentColor` heredando `text-on-brand-accent` (16.2:1 sobre el acento ámbar); antes eran blancos (1.245:1). Cero `stroke="#ffffff"` en el DOM; sin errores de consola.

### 19.3 Lote C — Navegación, footer y CTAs

Alcance §16.10 (RFC-073, cierra PD-7 y M-6): navegación pública configurable owner-only, footer tipado, CTAs como value object, allowlist de rutas nombradas, fallbacks y accesibilidad móvil. Alcance del render confirmado con el usuario: **dominio + render con fallback** (el layout consume `navigation()`/`footer()` conservando el sitio actual si no hay config; F queda como consolidación/SEO).

**C-1. Una sola allowlist, la URL se deriva de la key.** `PublicRoutes` es la fuente única: 7 keys → `{route_name, default_label, active_pattern}`. El owner puede reetiquetar y reordenar, **nunca repuntar**: un `url` persistido o una key fuera de la allowlist se ignoran y la URL siempre sale de `route(routeName(key))`. Un destino de ruta se cambia en código, tras deploy.

**C-2. Un solo resolver para todo destino.** `CtaResolver` es el único lugar donde un target se vuelve URL. Un CTA es `{label, type, target}` con `type ∈ {route, url, whatsapp, tel, mailto}`; devuelve `{label, url, external}` o `null` (que el llamador trata como «descarta este link», nunca como fatal). Las reglas de esquema inseguro viven en un solo sitio: `route` exige key de allowlist, `url` exige HTTPS absoluto —lo que rechaza `javascript:`/`data:`/`file:`/`vbscript:`, `//host` y rutas relativas en un solo check—, `whatsapp`/`tel` exigen dígitos suficientes, `mailto` email válido. Un label con `<`/`>` se rechaza de raíz. El footer usa el mismo resolver que los CTAs: no hay una segunda ruta de validación que olvidar.

**C-3. Doble boundary, igual que tema.** `FrontendNavigationService` re-valida al render (imports, SQL manual, filas legacy pueden tener cualquier cosa), con caché por generación + `SHAPE` y degradación a lectura directa ante caída del store. Fallbacks exactos de §16.7: sin config, la navegación son las 7 páginas actuales y el CTA «Agenda una cita» → Contacto; si todo queda deshabilitado por error, se conservan Inicio+Contacto. El footer omite links deshabilitados (`footer()` los expone con `enabled=false`, Blade los salta) y un link inseguro **nunca** llega a Blade. El fallback del footer usa rutas reales: **cero `href="#"`**.

**C-4. Validación al guardar, no solo al render.** La página owner-only bloquea el guardado si la navegación quedaría vacía (no fallback silencioso), rechaza HTML en labels y rechaza cualquier CTA/link de footer cuyo target no resuelva por `CtaResolver`. El orden del repeater se persiste como `sort_order` explícito. Owner-only real: `canAccess` con rol **y** permiso; un admin recibe 403 al abrir la página y no hay camino para persistir.

**C-5. Una fuente para desktop y móvil.** Header y drawer leen el mismo `navigation()['links']`: un reetiquetado aparece en ambos, no hay segundo array hardcodeado. El menú móvil conserva el toggle por checkbox (funciona sin JS) y un script de progressive-enhancement agrega `aria-expanded`, Escape, teclado (Enter/Space en labels `role=button`), foco al abrir/cerrar y trampa de foco; la animación respeta `prefers-reduced-motion` vía variantes `motion-reduce`. Verificado en vivo (móvil): Enter abre con foco dentro del menú, Escape cierra devolviendo el foco al toggle.

**C-6. Verificación con datos reales.** Sin config, `/nosotros` renderiza las 7 páginas idénticas y el footer con rutas reales (cero `#`); con navegación configurada, el reetiquetado aparece dos veces (header + drawer) y un link deshabilitado desaparece; con footer configurado, un link deshabilitado no se renderiza y uno inseguro nunca aparece en el HTML.

#### 19.3.1 Correcciones tras la auditoría de implementación del Lote C

La auditoría rechazó por **M-C1** (medio, bloqueante) y anotó **Mn-C1** (deuda no bloqueante). Ambos correctos y verificados.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **M-C1** | Un `footer.columns[*].links` persistido como **string** (import/SQL/legacy) caía en `foreach()` sobre un string → **HTTP 500 en `/`**. El boundary defensivo cubría que `footer` fuera array, pero no la estructura anidada. | Normalización de tipos en **cada nivel** vía `asList()`: `columns` no-lista → vacío; columna no-array → se descarta; `links` no-lista → vacío; link no-array → lo rechaza el resolver. Cualquier forma inválida degrada sin excepción. Se blindó también `social_links`. |
| **Mn-C1** | `open_in_new_tab` no se materializaba en el DTO ni se normalizaba; el schema normativo es `{key,label,enabled,sort_order,open_in_new_tab}`. | El DTO ahora emite `open_in_new_tab` **forzado a `false`** (v1 no tiene navegación externa) y la página lo persiste como `false`; un `true` persistido se normaliza al render. |

**El render nunca debe 500 ante datos malformados** — es el mismo principio del boundary defensivo del tema (§16.5): la validación al guardar impide llegar ahí desde la UI, pero el render re-valida porque el formulario no es el único escritor. El defecto real fue que la re-validación era parcial (nivel raíz, no anidada).

**Reproducción y cobertura.** Se reprodujo el 500 con cuatro formas malformadas (`columns` string, columna string, `links` string, link escalar) antes de corregir; ahora un test Feature con **SQL directo** (data provider) exige **HTTP 200** y footer presente para cada forma. Un test de servicio verifica el schema exacto de nav y la normalización `true → false`.

**Verificación en vivo.** Con el payload exacto del auditor (`{columns:[{title:Bad, links:"malformed-links"}]}`) aplicado en PostgreSQL, `GET /?audit=lotec-malformed` responde **200** (antes 500): el footer renderiza, conserva `legal_text`, descarta la columna malformada (0 links, sin crash) y no emite `#`; sin errores de consola.

### 19.4 Lote D — Servicios

Alcance §16.10 (RFC-074, cierra B-4/B-5/M-2/M-5): `frontend_services` 1:1 con `service_types.code`, regla única de elegibilidad fail-closed, `SeedInversionService` no destructiva y `LeadCaptureForm` fail-closed + atómico bajo lock. Alcance confirmado con el usuario: **render con fallback** (como C) y **guardar = publicar** (Strategy A, sin `draft_revision` ni publisher).

**D-1. Regla única de elegibilidad (§16.6).** `FrontendServicesService` es el ÚNICO lugar donde vive la regla, así render y lead capture no pueden divergir: `visible en L ⇔ ServiceType.active AND FrontendService.show_in_L`; `lead-eligible ⇔ ServiceType.active AND FrontendService.allow_leads`. Fail-closed: `active=false` gana siempre y **la ausencia de `FrontendService` no concede nada**. Un mismo `join` sirve a los dos usos. `services()` se cachea por generación; `isLeadEligible()` es query directa —gobierna una escritura y debe leer fresco—.

**D-2. Tabla con índice único parcial (§16.1.2).** `frontend_services` con `SoftDeletes` y un `CREATE UNIQUE INDEX ... WHERE deleted_at IS NULL` (no `->unique()` de Blueprint: con SoftDeletes un unique global impediría recrear el servicio de un `code` borrado; PostgreSQL no admite predicado en un constraint). `forceDelete` prohibido por policy.

**D-3. Backfill no destructivo (B-5/M-5).** La migración crea un `FrontendService` para cada `ServiceType` existente con el contenido actual del sitio (preserva el frontend), `allow_leads=true` para los que ya aceptaban leads. `SeedInversionService` reconcilia inversión (info-only, `allow_leads=false`) con `firstOrCreate` —insert-if-missing, nunca `updateOrInsert`—, así correrla de nuevo desde la migración, un seeder o un test no pisa una fila personalizada. **Sin el backfill, fail-closed rompería el lead capture** de comercialización/arquitectura/construcción.

**D-4. Lead atómico bajo lock (M-2).** La regla `service_type` valida fail-closed vía el servicio de elegibilidad, y **validación + `Lead::create()` son atómicos**: dentro de una transacción se re-verifica la elegibilidad tomando `lockForUpdate` en orden `service_types → frontend_services` —el mismo orden que usan las mutaciones de las autoridades (toggle `active` de admin, toggles del owner)—, así la elegibilidad no puede voltearse entre el check y el insert y los locks no hacen deadlock. El honeypot, el rate limit y las reglas de `comercializacion`/`property_id` se conservan intactos (regresión verde).

**D-5. CTA server-side.** `/contacto` deja de ser `Route::view` y usa `LeadCaptureController`: valida `?service=` con la misma regla fail-closed y sólo preselecciona un código elegible; ausente, malformado, desconocido o inelegible se ignora **de forma uniforme** (200, sin selección), así la URL no filtra qué códigos existen y la preselección nunca concede elegibilidad. El CTA por servicio es **derivado** (`Solicitar información` → `/contacto?service=<code>`), sólo cuando el servicio acepta leads; no es editable.

**D-6. Render con fallback + owner UI.** `welcome` y `site/servicios` consumen `services('home'|'services')`; tri-estado §16.7: tabla no inicializada → fallback actual, inicializada sin elegibles → estado vacío (un fallback nunca revive lo apagado). `FrontendServiceResource` owner-only (policy doble gate; admin conserva `ServiceTypeResource` pero **no** entra a este módulo) edita contenido, toggles y orden; guardar bombea la generación. Verificado en vivo: home renderiza 4 cards ordenadas por `sort_order`, servicios muestra descripción/bullets/imagen y CTA sólo en los lead-eligible; sin errores de consola.

#### 19.4.1 Correcciones tras la auditoría de implementación del Lote D

La auditoría rechazó por **C-D1** (crítico) y **M-D1** (medio). Ambos correctos y reproducidos en vivo.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **C-D1** | `FrontendService` declaraba `singleFile()`, que dispara `onlyKeepLatest(1)` → `clearMediaCollectionExcept` **borra físicamente** la imagen anterior al reemplazar. Viola §18.13 (sin borrado físico de media en v1). | Quitado `singleFile()` de la colección `image`. El render ya resuelve la imagen vigente por `image_media_id` (§16.4), así que las superadas dejan de referenciarse **sin borrarse**. Test de reemplazo que prueba que sobreviven la media y el archivo anteriores. |
| **M-D1** | El form público de leads listaba **todos** los `ServiceType` activos sin filtrar `allow_leads`, así `inversion` (info-only) aparecía como radio seleccionable. El server rechazaba el POST, pero el contrato exige que un no-elegible **no aparezca**. | Nuevo `FrontendServicesService::leadOptions()` con la **misma** regla fail-closed del submit; el blade consume ese método. Un servicio inactivo, info-only o sin fila viva no se ofrece. Test DOM que excluye inversión/inactivos/sin-fila y confirma que un elegible sí aparece. |

**Una sola fuente para la lista visible y la validación.** El defecto de M-D1 era **drift**: la vista consultaba `ServiceType` directo mientras el servicio central ya tenía el join correcto. Ahora las opciones, los CTAs, el render y la validación de escritura salen todos de la misma regla; `isLeadEligible()` queda separado sólo porque gobierna una escritura y lee fresco.

**El render nunca debe borrar media.** C-D1 fue un error mío: el `FrontendSetting` usa `NonDestructiveMediaUpload` sin `singleFile`, y acá lo introduje. Se agrega además un test permanente del índice único parcial + soft-delete para que una migración futura no lo degrade a `UNIQUE` global.

**Verificación en vivo.** Con datos reales, `/contacto` emite **3 radios** (comercialización, arquitectura, construcción) y **cero inversión** (el auditor encontró 4); el reemplazo de imagen conserva ambas medias y archivos; sin errores de consola.

### 19.5 Lote E — Contenido de páginas

Alcance §16.10 (RFC-075, cierra B-2/C-2/C-3/M-1): registry canónico de secciones, `frontend_pages` con `published_revision`+`published_by`, `frontend_sections` de trabajo, schemas por tipo, y publicación por revisión atómica con lock. Alcance confirmado con el usuario: **solo dominio** (el cutover de render de las 5 páginas queda 100% en F) y **media directa validada** (como D; sin pipeline draft-privado→promoción).

**E-1. Registry canónico cerrado (M-1).** `config/frontend-sections.php` declara, por página, sus `section_key → type`, y la allowlist de tipos ejecutables. Un `type` fuera de la lista o un `(page, section_key)` no canónico se rechazan: no es page builder libre. Cubre las 24 regiones editables verificadas contra los Blade actuales; las regiones kernel (buscador, formulario de leads, canales de contacto) **no** son secciones.

**E-2. Draft/publish con revisión atómica (C-2/C-3).** `FrontendPage` guarda el snapshot publicado (`published_revision`), `revision`, `published_by` y un `draft_revision` optimista. `FrontendSection` es el borrador editable (SoftDeletes, dos índices únicos **parciales**: `(page, section_key)` y `(page, sort_order)` sobre filas vivas). Toda mutación draft pasa por `FrontendPageContentService`: transacción con `READ COMMITTED` como primera sentencia, `lockForUpdate` de la página y las secciones por `id ASC` (mismo orden que el publisher, sin deadlock), validación por tipo + media, y bump de `draft_revision` en la misma transacción.

**E-3. Publicación optimista.** `FrontendPagePublisher.publish()` toma el lock, compara el `draft_revision` que envió la UI contra el actual y, si otra conexión confirmó una edición desde que la UI cargó, **termina en conflicto sin tocar el snapshot**. Re-valida cada sección habilitada bajo lock antes de copiarla al `published_revision`. Verificado con **dos conexiones PostgreSQL reales**: una segunda transacción bloquea en el row-lock de la página, y una edición que confirma primero hace conflictar una publicación stale.

**E-4. Validación por tipo (sin HTML libre).** `FrontendSectionSchema` rechaza tipos no allowlisted, cualquier string con `<`/`>` (sin HTML libre), CTAs que no sean el value object anidado `{label,type,target}` de RFC-073 o con destino inseguro, y layouts de `feature_sequence` fuera de la allowlist. Las referencias `media_id` se validan contra `FrontendMediaReference` (existencia/owner/colección `images`, §16.4); sin `singleFile()` (lección de C-D1).

**E-5. Media directa validada.** Las imágenes de sección van a la colección pública `images`, referenciadas por `media_id` validado, sin borrado físico ni disco privado ni job de promoción — consistente con lo aprobado en A/B/D y con §18.13. Un reemplazo deja la imagen anterior sin referenciar, nunca borrada.

**E-6. page(key) con fallback + owner UI.** `page(key)` (cacheado por generación) lee **solo** el snapshot publicado; una página no inicializada, deshabilitada o sin publicar devuelve el marcador de fallback (§16.7) que el cutover de F consume. `FrontendPageResource` owner-only (policy doble gate; admin/otros roles 403) edita las secciones ruteando **todo** por el content service (la UI nunca escribe JSON directo) y publica con una acción que envía `expected_draft_revision`. Las 5 páginas se siembran vía migración que invoca `SeedFrontendPages` (idempotente; producción migra sin seeders).

#### 19.5.1 Correcciones tras la auditoría de implementación del Lote E

La auditoría rechazó por tres críticos, dos medios y dos menores. Los tres críticos eran defectos reales que dejé sin cablear bajo presión de presupuesto. Todos reproducidos en vivo por el auditor.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **C-E1** | `FrontendSectionSchema` no validaba por tipo: aceptaba campos desconocidos (`rogue_field`), tipos incorrectos (`title:123`), 7 slides. | Reescrito con **specs declarativas cerradas por tipo** y un validador recursivo: rechaza claves desconocidas, tipos incorrectos, cardinalidad excedida, `string_list`, `media_id`, layout allowlisted y CTA anidado. Se corre en draft **y** publish. |
| **C-E2** | `isCanonicalSection()` existía pero **nunca se invocaba** — una fila `rogue` insertada por SQL se publicaba. | Cableado en `updateSectionPayload`, `saveSectionDraft` (rechaza editar no canónica) y `publish` (excluye no canónicas del snapshot). `FrontendSectionPolicy::create`/`delete` → `false` (M-E2). |
| **C-E3** | `page(key)` leía `seo`/`is_enabled` de columnas **vivas** — un cambio draft alteraba el render sin publicar. | El snapshot ahora es el **estado publicable completo** `{is_enabled, seo, sections}`; `page(key)` deriva **todos** sus campos del snapshot, con fallback solo si no hay snapshot. |
| **M-E1** | La acción Filament publicaba con el `draft_revision` al hacer clic, no el que la pantalla cargó. | La publicación vive en la Edit page, que conserva `loadedDraftRevision` en `afterFill` y lo envía al publisher; una pantalla stale conflicta. |
| **Mn-E1** | `type string(40)` vs contrato `string(30)`. | Alineado a `string(30)`. |
| **Mn-E2** | Comentarios prometían garantías no implementadas. | Ahora coinciden con la implementación. |

**Recorté el contrato, no lo entregué.** El motor (lock, revisión, seed, índices) estaba bien, pero el **schema por tipo** era un stub y el **registry** era código muerto no invocado — exactamente lo que el Lote F iba a consumir. Se agregan pruebas negativas por los tres críticos: claves/tipos/cardinalidad rechazados, sección no canónica que no puede editarse ni publicarse, y mutación draft de `seo`/`is_enabled` que no cambia `page(key)` tras publicar; más una prueba de la UI Filament con revisión stale.

**Verificación.** Suite completa verde; los tres críticos con prueba de regresión reproducible sobre PostgreSQL real.

#### 19.5.2 Corrección tras la reauditoría del Lote E

La reauditoría cerró C-E2, C-E3, M-E1, M-E2, Mn-E1 y Mn-E2, y reabrió parcialmente C-E1 como **C-E1-R**: el schema por tipo aún aceptaba payloads incompletos y reglas de accesibilidad inválidas. El auditor persistió por el write path un `audience_outcomes` **sin `result`**.

| ID | Residuo | Corrección |
| --- | --- | --- |
| **C-E1-R** | Los specs compuestos (`object`) se trataban como opcionales al faltar; en slides, `media_id`/`sort_order` eran opcionales y faltaba la regla `decorative`/`alt`. | Nuevo spec `object!` (compuesto **requerido**) para `audience_outcomes.result`; slide con `media_id` requerido y `sort_order` `int_min0`; **regla cruzada** `decorative`/`alt` (decorativo ⇒ `alt` vacío; no decorativo ⇒ `alt` obligatorio). Pruebas negativas **por el write path**, no solo `validate()`. |

**Decisión de forma para `metrics`/`values`.** El RFC ejemplifica `metrics` como lista simple; el schema implementado usa el wrapper `{items:[...]}` para que **todo payload sea un objeto** (validación uniforme, claves desconocidas rechazables en el nivel raíz). Es una decisión de consistencia deliberada, no un olvido; el renderer del Lote F consume `payload.items`.

**Verificación.** Suite completa verde; C-E1-R con prueba de regresión que ejerce `updateSectionPayload` (no solo el validador) y confirma que un `audience_outcomes` incompleto ni se persiste ni bumpea `draft_revision`.

#### 19.5.3 Corrección tras la segunda reauditoría del Lote E

La segunda reauditoría cerró los casos específicos de C-E1-R, pero reabrió **C-E1-R2**: el schema cerraba `hero`/`result` pero **no el contrato completo por tipo**. El auditor persistió un `audience_outcomes` con `result` presente pero **sin `items`**, y demostró que `feature_sequence`, `team` y `gallery` aceptaban imágenes sin `alt`.

| ID | Residuo | Corrección |
| --- | --- | --- |
| **C-E1-R2** | Listas/compuestos requeridos tratados como opcionales; media sin `alt` aceptada en los tipos editoriales. | Nuevo spec **`list!`** (lista requerida con mínimo): `feature_sequence.items` (≥1) y `gallery.items` (≥1). `audience_items` y `result.items` pasan a **requeridos**. Y una **regla universal**: todo objeto con `media_id` no vacío debe tener `alt` o `decorative:true`, aplicada a slides, `feature_sequence`, `team` y `gallery` —no solo al hero—. Pruebas negativas por tipo, en schema **y** write path. |

**La lección era el alcance, no la técnica.** Cada ronda cerré los casos que el auditor nombró en vez de cerrar el contrato entero de una. Esta vez el validador aplica las reglas **universalmente** (media+alt en cualquier tipo con imagen; requeridos explícitos por composición), así un tipo editorial nuevo hereda la garantía sin que yo la recuerde. El ejemplo `metrics`/`values` del RFC se alineó al wrapper `{items}` (§19.5.2) para evitar drift.

#### 19.5.4 Corrección tras la tercera reauditoría del Lote E

La tercera reauditoría cerró **todos** los hallazgos previos y encontró dos divergencias contractuales finales.

| ID | Divergencia | Corrección |
| --- | --- | --- |
| **C-E1-R3** | `feature_sequence.items[*].layout` estaba documentado como requerido y allowlisted, pero el schema lo declaraba `?layout` (opcional). | `layout` requerido (`layout`, no `?layout`); prueba de rechazo en schema **y** write path y positivas contra las tres variantes. |
| **M-E3** | El `published_revision` no tenía la forma completa de la épica (:384/:714): faltaban `sections[*].is_enabled` y `generated_from_ids`, y las secciones deshabilitadas se **descartaban**. | El snapshot ahora incluye **todas** las secciones canónicas con su `is_enabled` (una deshabilitada viaja marcada, no ausente) y `generated_from_ids` (inventario de entidades dinámicas resueltas al publicar). Solo se re-valida la sección habilitada. |
| **Mn-E4** | La épica usaba `key` en el ejemplo compacto vs `section_key` operativo. | Alineado a `section_key`. |

**Hueco de robustez cerrado de paso.** Al probar el write path descubrí que un `media_id` malformado (no-uuid) hacía **crashear** el query de elegibilidad al castear a `uuid` (`QueryException`, no validación). El schema ahora valida **formato uuid** de `media_id`, así una referencia malformada se rechaza limpio antes de tocar la BD.

**Verificación.** Suite completa verde; C-E1-R3 y M-E3 con pruebas de forma exacta del snapshot (`is_enabled` por sección, `generated_from_ids`) y de rechazo de `layout` por schema y write path.

#### 19.5.5 Corrección tras la cuarta reauditoría del Lote E

La cuarta reauditoría confirmó C-E1-R3 y M-E3 cerrados, y encontró dos bloqueantes nuevos —ambos del mismo tipo: una frontera que existía en un camino pero no en el otro—.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **C-E4** | El write/publish path corría el query de elegibilidad de media **aunque el schema ya hubiera devuelto errores**; un `media_id` malformado lo hacía crashear con `QueryException` (SQLSTATE 22P02) en vez de una `ValidationException` controlada. Mi validación uuid del schema no cortocircuitaba. | **Short-circuit**: si `schema->validate()` devuelve errores, se lanza `ValidationException` **antes** de cualquier consulta de media, en `updateSectionPayload`, `saveSectionDraft` y `publish`. Un uuid malformado nunca toca la columna uuid. |
| **M-E4** | `generatedFromIds()` recorría **todas** las secciones sin re-aplicar el registry; una fila dinámica no canónica insertada por SQL contaminaba `generated_from_ids` aunque el snapshot de `sections` sí la excluía. | El inventario aplica el **mismo** `isCanonicalSection()` que el snapshot: una fila rogue queda ausente de `sections` **y** de `generated_from_ids`. |

**Fail-closed en todos los caminos, no en uno.** Ambos defectos eran fronteras aplicadas en un lugar pero no en su gemelo: la validación uuid existía pero no cortocircuitaba el query; el registry filtraba `sections` pero no el inventario. Las pruebas nuevas ejercen el write path con `media_id` malformado (exige `ValidationException`, sin crash) y publican una sección dinámica rogue (exige ausencia en ambas estructuras).

### 19.6 Lote F — Render, caché y fallbacks

Alcance §16.10 (RFC-076): cablear el kernel de lectura (settings/theme/navigation/footer/services/page) en las 5 páginas públicas, con **caché por generación** como única invalidación, **SEO** derivado del kernel, y **fallbacks** que preservan el sitio actual hasta que el owner publica. Alcance confirmado con el usuario: **cutover completo con fallback** en las 5 páginas y **SEO completo** (canonical + JSON-LD + sitemap).

**F-1. Caché por generación, invalidación por namespace.** Cada dominio de lectura cachea bajo `frontend:g{N}:{dominio}[:{location}][:v{SHAPE}]`. La **única** invalidación es el bump atómico de `frontend_cache_generation` (`UPDATE … generation = generation + 1 … RETURNING`), nunca `forget`/`flush`; el TTL de 300s es solo red de seguridad. Pruebas: `services('home')` y `services('servicios')` usan claves distintas (un servicio de una ubicación no puede filtrarse a la otra por caché); y un bump desde una **segunda conexión real** hace que una request fresca lea el valor nuevo, con la entrada `g{N}` anterior aún físicamente presente (invalidación por namespace, no por borrado).

**F-2. Presenter defensivo (`FrontendPageRenderer`).** Único lugar donde el snapshot publicado se convierte en un view-model seguro: resuelve CTAs (`primary_cta`/`secondary_cta`) por `CtaResolver` (inválido ⇒ `null`, botón omitido), `media_id` por `FrontendMediaReference` contra la sección dueña (uuid no elegible ⇒ sin imagen, nunca fuga de otro archivo), y los tipos `dynamic` desde las autoridades del kernel (Property/Project/ServiceType), nunca desde ids guardados. Un `media_id` malformado se filtra por formato uuid **antes** de la query (un snapshot corrupto degrada, no crashea el cast). Los Blade por tipo quedan tontos: solo muestran.

**F-3. Cutover con fallback en las 5 páginas.** `home`, `nosotros`, `servicios`, `inversionistas` y `contacto` llaman `render(key)`: si la página no está publicada devuelve el marcador de fallback y se renderiza **el contenido hardcodeado actual sin cambios** (§16.7 REGLA DE ORO); si está publicada, el dispatcher `frontend.render` recorre las secciones habilitadas y las despacha a un partial por tipo (14 tipos del registry). Un tipo desconocido se omite en silencio. En `contacto` el **formulario y los canales de contacto se muestran siempre** (no son contenido del CMS); solo el encabezado entra al cutover.

**F-4. SEO derivado del kernel.** El layout emite un `canonical` derivado de la ruta (sin query string, para no duplicar URLs) y un bloque JSON-LD `Organization` + `WebSite` construido con `json_encode` desde `settings()` (nunca concatenación, para que ningún dato del CMS rompa el `<script>`). `GET /sitemap.xml` lista las páginas institucionales desde la allowlist canónica (`PublicRoutes`) más los detalles publicados de inmuebles y proyectos, sirviendo XML válido.

**F-5. El render nunca lanza.** Ante un snapshot corrupto —tipo desconocido, sección que no es array, `media_id` irresoluble, CTA que no es value object— la ruta degrada a página parcial/vacía con **200, nunca 500**. El presenter y el dispatcher son la frontera defensiva; prueba con snapshot hand-corrupted que exige 200.

**Verificación.** Suite completa verde (781 tests); Pint limpio; `npm run build` verde; verificación en vivo sobre PostgreSQL real: fallback (H1 hardcodeado + canonical + JSON-LD Organization/WebSite), y tras publicar por el motor de E, el render CMS reemplaza el fallback (hero + `service_list` dinámico) — las 5 rutas y `/sitemap.xml` responden 200.

#### 19.6.1 Correcciones tras la auditoría de implementación del Lote F

La auditoría rechazó por Pint sucio + cuatro medios y un menor, todos **confirmados en vivo**. No hubo críticos: cero 500s, cero fugas. Pero recorté el contrato en tres frentes y repetí una lección ya aprendida. Los acepté todos tras verificarlos contra el código real.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **Pint** | Corrí `pint` solo sobre dos archivos de servicio, no sobre tests ni `routes/web.php`; `pint --test` fallaba en tres archivos. | `pint` sobre `app/ tests/ routes/`; `pint --test` limpio. Disciplina: se corre sobre todo el diff, no sobre lo que toqué a mano. |
| **M-F1** | El `seo` publicado vivía en el snapshot pero el renderer no lo propagaba; `<title>`/`description`/OG usaban props estáticos. | `render()` surface `seo` del snapshot publicado; el layout recibe un prop `:seo` y aplica precedencia **página publicada → `settings()['seo']` → título/descr por vista → default**, sin leer columnas draft. Prueba HTTP/DOM: `meta_title`/`meta_description`/`og_title` publicados llegan al head; sin publicar, cae al título por vista. |
| **M-F2** | La location pública era `services` (inglés); la normativa (§16.8/RFC-076) exige `servicios`. Tapé la divergencia del Lote D con un **alias** en el renderer. | Key pública alineada a `servicios` en el service, el renderer (sin alias), las claves de caché y los callers; la columna `show_in_services` (DB) queda intacta. El test ahora prueba **aislamiento real** (un servicio home-only y otro servicios-only, cada location resuelve el suyo) y que el alias inglés ya no es válido. |
| **M-F3** | El layout hardcodeaba logos, WhatsApp y contacto; solo el JSON-LD usaba `settings()`. | El layout resuelve `settings()` **una vez** y lo consume en logos (header/móvil/footer), favicon, OG image, WhatsApp flotante y contacto del footer, **conservando exactamente los fallbacks** cuando el dato falta. Pruebas DOM: con perfil personalizado aparecen dirección/teléfono/WhatsApp; sin configuración sobreviven los fallbacks. |
| **M-F4** | El observer de media solo cubría `FrontendSetting`; `FrontendService` y `FrontendSection` también alimentan el render y no bombeaban. | `FRONTEND_MODELS` incluye las tres entidades `HasMedia` del frontend; el bump `afterCommit` cubre alta/baja de media de servicio y de sección. Pruebas: alta/baja bombean; `Property` sigue fuera. |
| **Mn-F1** | El presenter entregaba modelos Eloquent crudos a los partials dinámicos. | El presenter normaliza Property/Project a **arreglos view-ready**; los partials consumen arreglos, nunca métodos/relaciones de modelo. Prueba de contrato de arreglos. Además el dispatcher es **fail-closed** contra el registry (`config/frontend-sections.php`): un tipo no allowlisted (incl. path-like `../../etc/passwd`) nunca resuelve una vista ajena. |

**La misma clase de defecto que en el Lote E.** M-F4 era una frontera aplicada en el singleton pero no en sus gemelos (`FrontendService`/`FrontendSection`) — idéntico a C-E4/M-E4. M-F1/M-F3 eran "completo a medias": construí el canal (JSON-LD desde `settings`, `seo` en el snapshot) pero no lo cablée hasta la superficie. Y M-F2 fue el peor hábito: **tapar una divergencia con un alias en vez de corregir el contrato**.

**Verificación.** Suite completa verde (**789 tests**, +8 nuevos); `pint --test` limpio; `npm run build` verde; verificación en vivo sobre PostgreSQL real: fallbacks de perfil intactos sin configuración (logo, WhatsApp, contacto) y las 5 rutas + `/sitemap.xml` en 200.

#### 19.6.2 Corrección tras la reauditoría del Lote F

La reauditoría **cerró los cinco hallazgos anteriores** (M-F1 a M-F4, Mn-F1, verificados en vivo) y encontró un crítico nuevo: **C-F1**. Otra vez la misma clase — defendí los ELEMENTOS de `sections` pero no el CONTENEDOR, y validé CTA/media pero no los tipos escalares del SEO. El auditor reprodujo dos **500** reales sobre PostgreSQL.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **C-F1** | (1) `published_revision.sections` escalar/`null`/objeto asociativo → `foreach()` en el renderer revienta con 500. (2) `seo.meta_title` como array → `htmlspecialchars()` en el layout recibe un array → `TypeError` 500. Mi test defensivo cubría una lista con elementos inválidos, no el contenedor inválido ni tipos SEO no escalares. | **Normalización en la frontera de lectura** (`FrontendPageContentService::build`, antes de cachear): un `sections` que no es una **lista** (`array_is_list`) marca el snapshot como estructuralmente corrupto y sirve el **fallback** hardcodeado (§16.7), nunca una página en blanco ni un 500; los campos SEO se reducen a sus **strings escalares** conocidos (`safeSeo`), y un no-string se descarta para que aplique el fallback de `settings()`. Defensa en profundidad extra en el renderer (`is_array` antes del `foreach`). El read model **cacheado** ya es el normalizado. |

**Tests (8, sobre PostgreSQL real):** dataprovider de `sections` escalar/`null`/objeto/entero → 200 con fallback; dataprovider de `seo.meta_title` array/objeto/número/bool → 200 con título estático seguro. Reproducción en vivo: las dos sondas del auditor devuelven 200 y tras revertir el snapshot la ruta sigue en 200.

**Recomendación atendida (no bloqueante):** `og:image:type`/`width`/`height` (JPEG 1200×1200) solo se anuncian para la imagen institucional por defecto, no para una `og:image` de media CMS de formato/tamaño desconocido.

**La lección, otra vez:** la frontera va en el contenedor Y en sus elementos. Un boundary defensivo no está completo hasta que cubre la forma del dato de arriba hacia abajo: el array externo, cada elemento, y cada campo escalar.

**Verificación.** Suite completa verde (**797 tests**, +8 de C-F1); `pint --test` limpio; `npm run build` verde; las dos formas corruptas que daban 500 ahora devuelven 200 en vivo.

### 19.7 Lote G — Preview, publicación y QA

Alcance §16.10 (RFC-077, cierra PD-1): preview owner-only, preflight validation, publicación transaccional, QA visual/accesibilidad y documentación en Ayuda. Alcance confirmado con el usuario: **coherente con D/E** — preview de páginas (estrategia B, único flujo draft→publicado realmente construido) + preflight + observabilidad + QA + Ayuda; **sin retrofit** de servicios (siguen estrategia A, guardar=publicar) ni de media (sigue directa). Todo el bloque de borrado físico de media (prune/purge/lease/orphan) es `HISTÓRICO — fuera de alcance v1` por §18.13. Preview vía **Filament Page** (no ruta suelta).

**G-1. Draft render path.** `FrontendPageRenderer::renderDraft(pageKey)` renderiza el borrador de trabajo (las filas vivas de `FrontendSection`), no el snapshot publicado, reutilizando **el mismo presenter** que el render público (CTAs, media, dinámicos). Un método común `buildSections()` alimenta ambos caminos, así que preview y público no pueden divergir. Una key no canónica devuelve `null` para 404.

**G-2. Preview owner-only (Filament Page + ruta owner-gated).** `FrontendPreview` (Filament Page en el grupo Frontend, `canAccess` owner-only) es la UX: selector de las 5 páginas + iframe al endpoint `frontend.preview`. El `FrontendPreviewController` es la **única frontera**, en orden: owner-gate (403 para anónimo/no-owner, ANTES de mirar la key para no filtrar cuáles existen) → key canónica (404 uniforme) → `renderDraft`. Sin token público reusable; el shell (`preview-shell.blade.php`) rinde el layout público en modo `preview` (banner "no es producción" + `noindex,nofollow`) y nunca está en el sitemap.

**G-3. Preflight de publicación.** `FrontendPreflightValidator` agrega las reglas de composición **page-level** que el schema por sección no puede expresar (§16 "Validaciones pre-publicación / Páginas"): una página habilitada debe publicar con su `hero` activo — el H1 de la página. Corre bajo lock en el publisher, antes de escribir el snapshot; un fallo revierte sin efectos. El schema por tipo (E) sigue validando cada sección.

**G-4. Observabilidad.** El publisher emite logs `frontend.published` (actor + entidad + revisión), `frontend.publish_failed` (actor + razón, nunca el contenido) y `frontend.cache_generation_bumped`, además del `published_by`/`published_at` que E ya registraba.

**G-5. Documentación en Ayuda.** Sección «Sitio público» (owner-only, gate delega en `FrontendSettingsPage::canAccess`) en el registry de Ayuda (Épica 11), con el manual del módulo: dos formas de publicar, vista previa, publicación y notas de media/tema.

**Tests (9):** acceso al preview (owner 200 + noindex + banner; admin/agente 403; anónimo 403; key no canónica 404 uniforme); aislamiento draft/público (un borrador sin publicar se ve en preview pero NO en el sitio público); preflight (página habilitada con hero deshabilitado rechazada; hero activo publica); observabilidad (publish exitoso registra actor/timestamp + log; publish rechazado loguea el fallo).

**QA visual (ejecutado).** Home desktop y móvil, las 5 rutas + `/sitemap.xml` en 200 sin errores de consola; menú móvil y logo/CTAs correctos; preview en vivo como owner mostrando el borrador dentro del layout real con el banner. El sitio degrada a los fallbacks exactos sin configuración.

**Verificación.** Suite completa verde (**806 tests**, +9 de G); `pint --test` limpio; `npm run build` verde; preview verificado en vivo sobre PostgreSQL real.

#### 19.7.1 Correcciones tras la auditoría de implementación del Lote G

La auditoría rechazó por un crítico (deuda de contrato heredada de D), dos medios y dos menores. **Los cinco confirmados contra el código.** El crítico se resolvió por decisión de producto (documental); los demás son fixes reales.

| ID | Defecto | Corrección |
| --- | --- | --- |
| **C-G-1** | El contrato (§16.9, RFC-074, RFC-077) declara el **contenido editorial de servicios** como estrategia B (draft→publicado), pero el código lo implementa como estrategia A (guardar=publicar) desde el Lote D. Contradicción doc↔código; el auditor no acepta resolverla con comentarios. | **Reconciliación documental (opción 2, decidida con el usuario):** enmienda normativa **C-G-1** en §16.9 declarando el contenido editorial de servicios como **estrategia A**, con justificación (fila 1:1 sin composición; disponibilidad ya inmediata por lead-safety). Retirados del contrato vigente: `draft_payload`/`published_payload`/`draft_revision`/`published_by`/`published_at` de servicio, `FrontendServicePublisher`, `expected_draft_revision_service`, preview editorial de servicios y el test **T-11s**. Enmiendas propagadas a §16.9, el schema §16.1.2, el protocolo de publicación, los archivos esperados, la matriz de tests, RFC-074 y RFC-077. `FrontendPage` queda como **única** entidad de estrategia B con preview. El código ya coincidía; no hubo retrofit. |
| **M-G-1** | El toolbar del preview (selector de página) se solapaba con el header sticky del panel en móvil (390px). | Toolbar **mobile-first**: en móvil apila etiqueta + select **full-width** + enlace en filas separadas (`flex-col sm:flex-row`), sin flex horizontal que envuelva bajo el header. Verificado en vivo a 390px: select en `top=208` (header termina en `144`), full-width, usable. |
| **M-G-2** | El preview solo llevaba `sections`, no el SEO ni el `is_enabled` del draft; el owner veía metadatos/estado distintos de lo que publicaría. | `renderDraft()` devuelve el **estado de trabajo completo** (`seo` + `enabled` + `sections`); el controller pasa el SEO draft al shell (misma precedencia del layout público) y una **nota en el banner** cuando la página está deshabilitada. Test: el `meta_title` draft llega al `<title>` del preview y la nota de deshabilitada aparece. |
| **Mn-G-1** | El comentario del controller decía middleware `auth`, pero la ruta solo tiene `web`. | Comentario corregido: el controller **es** la frontera (403 explícito), no hay `auth` porque Filament dueña la ruta de login y no existe ruta `login` nombrada. |
| **Mn-G-2** | El test de acceso solo iteraba `admin`/`agente`. | Ahora cubre `admin`, `agente`, `arquitectura` y `proyectos` (matriz completa de no-owner). |

**Recomendación atendida:** log `frontend.previewed` (actor + entidad, sin contenido).

#### 19.7.2 Recomendación #1 tras la reauditoría aprobada del Lote G

La reauditoría **aprobó** el Lote G sobre `f86e530` sin correcciones obligatorias, con cuatro recomendaciones no bloqueantes. Se atiende la #1 (defecto real de correctitud): el `revision` del log `frontend.published` estaba **desfasado en +1** respecto a la fila persistida, porque `Eloquent::update()` muta el atributo en memoria y el log volvía a leer `$locked->revision + 1` tras la actualización. Corrección: se calcula `$newRevision` **una vez, antes del `update()`**, y se usa tanto en la fila como en el log. La prueba de observabilidad ahora **exige** que el `revision` logueado sea igual a `$page->revision` persistido, de modo que el desfase no puede reaparecer sin romper el test. Verificación: suite completa verde; `pint --test` limpio.

**La decisión de fondo (C-G-1).** El contrato normativo describía un flujo que el equipo nunca construyó y que, revisado, no aporta valor para una fila 1:1 editable solo por el owner. En vez de construir maquinaria por cumplir el papel, se **corrigió el papel** para que coincida con la realidad — con justificación explícita y propagada a todos los documentos, no un comentario suelto.

**Verificación.** Suite completa verde (**806 tests** — la reconciliación no agrega ni quita tests netos: M-G-2 suma uno, T-11s nunca existió como código); `pint --test` limpio; `npm run build` verde; M-G-1 y el preview verificados en vivo a 390px y desktop sobre PostgreSQL real.

---

### 18.19 Retiro del tipo `gallery` (Épica 12.2-D, 2026-07-26)

`gallery` estaba en la allowlist de §16.1.1 y tenía schema ejecutable, pero **ninguna de las cinco páginas del registro lo declaraba**. Al cerrar la Épica 12.2 —que le da formulario propio a cada tipo y retira el editor JSON— quedaba como un tipo ejecutable sin punto de entrada: nadie podía crear una sección de ese tipo, y por lo tanto tampoco podía tener formulario.

**Decisión del owner del proyecto (2026-07-26): se retira.** Sale de `config('frontend-sections.types')` y de `FrontendSectionSchema::SPECS`. Si en el futuro se necesita una galería en una página institucional, se agrega junto con su sección canónica, su formulario y sus pruebas — no antes.

**Aclaración necesaria, porque el nombre se repite en el proyecto:** `gallery` es también el nombre de una colección de media de Spatie en `Property` y `Project`, que es la galería de fotos del inmueble y del proyecto en `/inmuebles/{slug}` y en el detalle de proyecto. Las dos cosas **no tienen ninguna relación**: esas vistas leen `$model->getMedia('gallery')` directo del modelo y nunca pasan por `FrontendSectionSchema` ni por `config('frontend-sections')`. El retiro no las alcanza, y `FrontendSectionEditorClosureTest` lo deja asertado para que nadie tenga que volver a deducirlo.

**Consecuencia sobre la regla universal de accesibilidad (§18.14 / C-E1-R2):** sigue vigente sin cambios; su alcance pasa a ser `slides`, `feature_sequence` y `team`. La regla nunca dependió de la lista de tipos, sino de que el objeto tuviera `media_id`.

### 18.18 Enmiendas de la Épica 12.1 — Mejora UX del Hero (2026-07-25)

Incremento [Épica 12.1](epica-12-1-mejora-ux-hero.md): reemplazo del editor JSON del `hero` por formulario estructurado, más logo/alineación/carousel y cierre de la frontera de privacidad de media. La reauditoría de diseño de 12.1 detectó **dos contradicciones normativas de esta épica** que no podían resolverse enmendando solo RFC-075 (documento subordinado). Se corrigen acá, en la fuente única.

**(1) §16.1.1 y §16.7 — el fallback del `hero` es POR PÁGINA.** El texto anterior definía el fallback de un tipo **compartido** por las cinco páginas usando el contenido de **una sola** (las cuatro URLs del home). Aplicado literalmente, `nosotros`, `servicios` e `inversionistas` habrían perdido su encabezado propio y `contacto` habría ganado un fondo que hoy no tiene — una **regresión visible** en tres páginas públicas. La regla vigente es la aplicación literal del principio ya establecido en §16.7 («no inicializado → **valor hardcodeado actual**»): cada página cae a **su** fondo actual (`home` = 4 URLs Unsplash; `nosotros`/`servicios`/`inversionistas` = su PNG de encabezado; `contacto` = sin imagen). **No cambian** la cardinalidad `0..6`, el orden por `sort_order`, ni la regla de que un `slides: []` publicado no revive el fallback. Se corrige además la referencia `welcome.blade.php:5-10` → `:12-16` (las URLs viven ahí).

**(2) §16.4 — precisión de alcance del uploader no destructivo.** El mandato de `NonDestructiveMediaUpload` se redactó como «toda colección», pero su **propósito** es neutralizar un comportamiento propio de `SpatieMediaLibraryFileUpload` (`deleteAbandonedFiles()`). El estado de **lista** de `hero.slides` vive en el payload (array de `media_id`), no en una columna única, por lo que no puede usar la hidratación single-UUID de esa subclase; usa `FileUpload` base, que **no establece relación Spatie y por lo tanto no tiene ninguna ruta de borrado**. Rige la misma garantía contractual y las mismas pruebas de no borrado. Sigue **prohibido** `SpatieMediaLibraryFileUpload` directo, y siguen prohibidos `singleFile()`, `onlyKeepLatest()`, `forceDelete` y el borrado físico.

**(3) §16.4 — conducta ante media no promovida.** El texto anterior prometía que el render usaría «la versión pública anterior de esa media o placeholder» mientras la promoción no terminara. **No era implementable:** el snapshot referencia **una sola** representación (`media_id`), no guarda ninguna versión previa, y no existe vínculo entre el uuid nuevo y el que reemplazó. Conducta normativa vigente: la media **no `promoted` se omite** del render; si no queda ninguna imagen renderizable, la sección va **sin imagen**. **No existen** versión anterior ni placeholder. Esto **no** relaja la garantía original: el render sigue sin emitir jamás una URL privada ni un archivo a medias.

**Estados de promoción (§16.4, detalle de 12.1).** `draft` (sin flags) → `pending` (`pending_promotion`) → `promoted`. Invariantes: (1) `promoted ⇒ sin pending_promotion`; (2) `pending ⇒ referenciada por la `published_revision` vigente`; (3) `promoted` es **terminal** (perder la referencia no despromueve: los bytes ya son públicos y v1 no borra). La transición `pending → draft` por pérdida de referencia la aplican publisher, job y reconciliación desde **una frontera compartida** (`PublishedMediaReference`), y el job **toma los locks en el orden global `page → sections(id ASC) → media(uuid ASC)`** para que la evaluación de la referencia sea atómica frente a una publicación concurrente. Contrato completo: `docs/epicas/epica-12-1-mejora-ux-hero.md` §7.8.

**(4) §16.3 — precisión del lock de `media`.** «Publicar no bloquea `media`» sigue vigente **para mutaciones draft**, pero **no** para la publicación con promoción: allí publisher y job toman `page → sections(id ASC) → media(uuid ASC)`. El lock no protege contra borrado (no existe en v1) sino contra la **carrera de referencia**.

**(5) §16.4 — resolución de propietario tras soft-delete.** Conservar los archivos no basta: si el propietario se resuelve con la relación por defecto, el `SoftDeletingScope` lo excluye y la revisión publicada **pierde la imagen**. Toda resolución de propietario al servicio de una revisión publicada usa **`withTrashed()`**. Corrige un defecto **preexistente del render** (Lote F) que afecta a **todos** los tipos con media, no solo al hero.

**(6) §16.1.1 — las slides decorativas se emiten como `<img aria-hidden alt="">`, no como `background-image`.** El texto anterior decía «se emiten como `background-image`, sin `<img>` ni rol». Su **intención es de accesibilidad**: que un fondo decorativo no se exponga a tecnología asistiva. Esa intención se conserva íntegra —la capa entera va `aria-hidden` y las imágenes no llevan alt— pero la técnica cambió por una razón de seguridad: `background-image` exige un atributo `style` inline con la URL, y **ninguna directiva de CSP lo admite sin `unsafe-inline`**. Era la última superficie inline del hero. También se añade, como presentación **por página y no editable**, `config('frontend-sections.hero_variants')` (`featured`/`compact`/`standard`): unificar el renderer del hero no debía cambiar el aspecto actual de cada página. Ninguna de las dos toca el schema ni el payload. Detalle: `docs/epicas/epica-12-1-mejora-ux-hero.md` §0.0.

**Alcance de §16.4 cerrado por 12.1:** disco privado, preview owner-only, promoción post-commit idempotente y reconciliación se implementan para **`FrontendSection.images`**. Para **`FrontendService.image`** el mismo tratamiento queda **abierto** como deuda declarada de un incremento posterior; 12.1 **no** clausura §16.4 globalmente.

**Lo que NO cambia:** owner-only por policy, snapshot y versionado optimista con `expected_draft_revision`, prohibición de borrado físico (§18.13), registro canónico cerrado de secciones y el tipo `hero` **único** compartido por las cinco páginas (no se crea `hero_home` aparte: los heroes ya son independientes por fila y payload; lo compartido es la plantilla, no el contenido).

### 18.20 Épica 12.4 — Refinamiento del editor: fiabilidad, medios, tipografía y marca (2026-07-27 a 2026-07-29)

Incremento **as-built** (sin auditoría externa previa, sin gate formal), documentado en detalle en
[`docs/epicas/epica-12-4-refinamiento-editor-secciones.md`](epica-12-4-refinamiento-editor-secciones.md).
44 commits entre `40c0d5c` y `c00640c`, originados en el owner del sitio usando el CMS en producción —
mitad defectos reproducidos y corregidos, mitad capacidades nuevas del editor. Se resume acá lo que tiene
implicación **normativa** sobre §16; el resto (rediseños visuales, ajustes de espaciado, decisiones de
producto sin impacto de contrato) queda sólo en el documento de detalle.

**Registro canónico y schema (§16.1.1).** Doce tipos de sección ganaron `title_bold`/`eyebrow_bold`
(`?bool`, tercer estado ausente = «como la configuración del sitio»): `hero`, `rich_text`, `values`, `team`,
`capability_cards`, `feature_sequence`, `audience_outcomes`, `cta`, `service_list`,
`featured_properties`/`opportunity_properties`/`featured_projects`. `cta` sumó además `title_color` (aplica
sólo al título, nunca al antetítulo ni al cuerpo). `metrics` sumó `background_color`/`value_color`; `values`
y `capability_cards` sumaron `icon_bg_color`/`icon_color`; `team` sumó `background_color`/`title_color` y su
`spotlight` pasó de tres claves planas `spotlight_*` a un objeto anidado con `media_id` — el pipeline de
media (validación, promoción, reporte de huérfanas) depende de recorrer el payload buscando esa clave
exacta, así que la migración de datos reescribió tanto borradores como snapshots ya publicados. Todos los
campos nuevos son opcionales y sin valor por defecto persistido: una sección guardada antes de que un campo
existiera se sigue viendo exactamente igual que antes de la migración.

**Orden de secciones — corrección a lo ya cerrado en Lote B.** El campo numérico de orden (§19, Lote B) se
retiró por completo: se probó en producción que aceptaba negativos sin queja (PostgreSQL `unsignedInteger`
es un `integer` con signo) y colocaba secciones por encima del hero pese al candado de orden fijo del hero,
que sólo vigilaba su propio valor. El orden se mueve ahora por intercambio con el vecino; `saveSectionDraft`
ya no acepta el campo, así que no puede reabrirse por un segundo formulario.

**Paleta de marca (§16.5), de 10 a 18 entradas.** `brand_palette` (antes `CardBorderPalette` en `Media\`,
ahora `BrandPalette` en `Services\Frontend\`) sumó 6 neutros con hexadecimal propio, más `site` (fondo
configurado del sitio) y `navy` (el azulado fijo de los paneles, no derivable del principal). Es una lista
**única** compartida por todos los selectores de color del panel — nace en este incremento la regla de que
un color nuevo se agrega en un solo lugar y lo heredan todos sus consumidores.

**Tipografía (§16.5), catálogo cerrado ampliado.** `ThemeContract::FONTS` pasa de 2 a 8 familias
(Montserrat, Inter, Playfair Display, Lora, Space Grotesk, Caveat, Arial, Georgia), con
`ThemeContract::SYSTEM_FONTS` marcando las dos que no se descargan. El tema ganó `eyebrow_font`,
`heading_bold`, `eyebrow_bold` como configuración global del sitio (`FrontendSettingsPage` /
`FrontendThemeService`), separada de la familia de títulos y de cuerpo ya existentes desde §16.5 original.

**Corrección de un defecto normativo preexistente, no introducido en este incremento:** el layout público
nunca invocaba `Vite::fonts()`, así que ninguna tipografía —incluidas Montserrat e Inter, ya vigentes desde
la fundación del frontend— se cargaba realmente en el navegador; el sitio dibujaba todo con la tipografía
de reserva del sistema operativo sin que la variable CSS ni el nombre de la familia delataran el problema.
Corregido cargando sólo los alias de las familias efectivamente configuradas (nunca las 6 completas del
catálogo).

**Media (§16.4).** Sin cambios de contrato. Se corrigieron dos defectos operativos que rompían el flujo de
guardado sin tocar las garantías: la promoción de media pasó a ejecutarse de forma **síncrona** en sus tres
puntos de invocación (antes asíncrona por cola, sin worker corriendo de forma confiable en este entorno) —
desviación explícita del diseño original de 12.1, con el resto del contrato de esa promoción (copia fuera de
transacción, reconciliación como red de rescate) intacto; y `Property` ganó una ventana de excepción acotada
(`deferCoverGuard()`) al candado de portada mínima, usada únicamente por `EditProperty::save()` para
resolver un desencuentro de orden entre cómo Filament reemplaza archivos (borra, luego guarda) y cómo el
candado contaba reemplazos (mirando la base de datos en el instante intermedio). Fuera de esa ventana el
candado sigue rechazando el borrado de la última portada de un inmueble publicado, sin excepción.

**Lo que NO cambia:** el modelo de datos de §16.1, la estrategia de media de §16.4 fuera de los dos puntos
señalados, la política owner-only, y la prohibición de interpolar payload en clases o `style` (§16.1.1) —
los dos selectores de color del panel (§8–9 del documento de detalle) siguen usando `style` inline
exclusivamente porque el panel de Filament no compila las utilities del sitio, no porque la regla se haya
relajado.

### 18.21 Épica 12.5 — `/proyectos` se administra desde el CMS: sexta página canónica (cms-pagina-proyectos, 2026-07-29 a 2026-07-30)

Cambio planificado y ejecutado con SDD (`openspec/changes/cms-pagina-proyectos/`: proposal, spec, design,
tasks — ahí vive el detalle test-por-test; no se duplica un documento de bitácora aparte porque el rastro
completo ya existe en esos cuatro artefactos y en `sdd/cms-pagina-proyectos/*` de la memoria persistente del
proyecto). Extiende el registro de §16.1.1 de **cinco a seis páginas**: `/proyectos`
(`ProjectController@index`, hoy blade estático con branding hardcodeado de A-74 Arquitectura) pasa a tener
`hero→hero`, `projects_list→featured_projects`, `final_cta→cta` — mismo patrón que las otras cinco, cero
tipos de sección nuevos.

**Alcance — corrección de RFC-075 §«No incluye».** El RFC dice literalmente «no incluye: crear páginas
públicas nuevas desde el CMS». Este cambio **no lo contradice**: la ruta `/proyectos` y su controlador ya
existían: lo que se agrega es que su contenido pase a ser administrable, igual que ya lo es el de las otras
cinco — ninguna ruta ni controlador nuevo. Pero el conteo de «páginas administrables» que aparece en varios
puntos del RFC (`key` único: `home`, `nosotros`, `servicios`, `inversionistas`, `contacto` — línea 138; «5
páginas» en el árbol de archivos) queda **desactualizado** tras este incremento. Nota de alcance espejada en
el propio RFC-075 (bloque de enmiendas del encabezado).

**Precedencia del logo propio vs. el de marca — spec corregido por el design.** El spec
(`specs/hero-logo-propio/spec.md`) proponía inicialmente que un logo propio resuelto ignora `logo_enabled`
siempre. El design detectó que esa regla no deja forma de **apagar** el logo: borrar la imagen hace
desaparecer la clave `logo` del payload, revive el fallback A-74 hardcodeado, y `logo_enabled` no tiene
ningún efecto sobre él. Se resolvió a favor del design — no es un empate de tradeoffs, es un defecto de
capacidad del texto original. Regla vigente: **`logo_enabled` gobierna AMBOS logos** (propio si existe, de
marca si no; apagado, ninguno). El spec quedó enmendado in-place (texto original tachado, conservado por
trazabilidad) en vez de reescrito, mismo criterio que ya usa este documento con RFC-075.

**El chip A-74 dejó de blanquear el logo que sube el owner.** El design había dejado esto como riesgo
abierto y sin resolver («un logo de color subido por el owner saldría como silueta blanca», por el filtro
`brightness-0 invert` del distintivo). Se cerró en la Fase 4 de aplicación: el renderer marca el logo
derivado del **fallback** con `from_fallback: true`, y el partial del hero aplica el filtro sólo cuando esa
marca está presente — el logo que sube el owner conserva su color de marca, el logo A-74 hardcodeado del
blade estático sigue viéndose exactamente igual que siempre (§16.7).

**Autoridad de listado propia (`catalog`).** `featured_projects` en `/proyectos` gana una variante de
presentación por página (`config('frontend-sections.project_list_variants')`, mismo mecanismo que
`hero_variants`) que lista **todos** los `Project` sin filtrar por `is_featured` — a diferencia de `home`,
que sigue mostrando sólo destacados con tope 12. Layout propio: carrusel de a 6 en escritorio con swipe en
móvil, estado vacío propio («Pronto publicaremos nuestros proyectos»), fondo por defecto con el gradiente
literal de 3 paradas que ya tenía la página (no interpolado, §6.1).

**Verificación de cierre.** Suite completa del repo (no sólo Frontend, primera corrida entera de todo el
cambio): **1487/1487 verde**. Subconjunto Frontend en aislamiento: **965/965 verde**. `./vendor/bin/pint
--test` sobre el repo completo: limpio. Sin regresión de comportamiento ni de snapshot en ninguna de las
cinco páginas previas — los dos mecanismos nuevos (`logo` en `hero`, `project_list_variants`) están
gateados por ausencia de clave, no-op para snapshots publicados antes de este cambio.

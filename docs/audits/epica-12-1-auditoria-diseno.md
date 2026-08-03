# Auditoría de diseño — Épica 12.1 Mejora UX del Hero

**Proyecto:** New Hauz — Plataforma inmobiliaria (monolito Laravel)  
**Fecha:** 2026-07-25  
**Auditor:** Codex (auditor independiente)  
**Rama auditada:** `feature/epica-12-content-manager`  
**Documento auditado:** `docs/epicas/epica-12-1-mejora-ux-hero.md`  
**Commit del diseño:** `423ea71` (`docs(epica-12): diseño auditable de la mejora UX del Hero (12.1)`)

---

## 1. Veredicto

### **APROBADO CON CORRECCIONES**

La dirección funcional es válida: reemplazar el JSON crudo del Hero por un formulario estructurado, conservar el flujo draft → publicación, reutilizar el logo configurado y mantener un schema cerrado. También se confirmó que las policies actuales ya son owner-only.

Sin embargo, el diseño todavía no es implementable de forma segura y verificable. Hay tres bloqueantes: el contrato de carga de múltiples imágenes no coincide con el uploader existente, el fallback descrito no coincide con las rutas reales y el carousel no define una interacción accesible completa. No debe iniciar la implementación hasta cerrar C-1, C-2 y C-3.

**Decisión de gate:** `GATE DE DISEÑO 12.1: RECHAZADO` hasta reconciliar los hallazgos críticos.

---

## 2. Evidencia verificada en código real

### Comandos ejecutados

| Comando | Resultado |
| --- | --- |
| `composer validate --strict` | ✅ `./composer.json is valid` |
| `./vendor/bin/pint --test` | ✅ Passed |
| `DB_DATABASE=inmo_test php artisan test` sobre PostgreSQL local | ⚠️ El primer intento fue bloqueado por el sandbox (`Operation not permitted` al conectar a `127.0.0.1:5432`); reejecutado con acceso autorizado. La suite completa posterior no produjo salida durante varios minutos y se canceló para no dejar un proceso colgado. |
| Tests focales FrontendPage/Renderer/Access/Media | ✅ 38 tests, 171 assertions |
| `git diff --name-status 423ea71^..HEAD` | ✅ El commit auditado sólo agrega el documento de diseño; no modifica código productivo. |
| búsqueda de directivas CSP en `app`, `config`, `routes` y vistas | ✅ No se encontró una política CSP declarada en el código revisado. |
| `composer.lock` | ✅ Filament `v3.3.54`; `composer.json` declara Filament `^3.2` y Media Library `^11.23`. |

### Contratos actuales comprobados

- `SectionsRelationManager` todavía usa únicamente `Textarea::make('payload')` para el contenido JSON: `app/Filament/Resources/FrontendPageResource/RelationManagers/SectionsRelationManager.php:31-42`.
- El schema actual de `hero` sólo contiene texto, CTAs y `slides`: `app/Services/Frontend/FrontendSectionSchema.php:31-38`. La regla `decorative/alt` sí se valida: `:101-115`.
- El guardado valida schema, elegibilidad de media y actualiza el draft dentro de transacción: `app/Services/Frontend/FrontendPageContentService.php:143-179,190-229`.
- `FrontendSection` sólo tiene `payload` como estado editorial de slides y una colección `images`; no existen columnas UUID por slide: `app/Models/FrontendSection.php:21-52`.
- `NonDestructiveMediaUpload` carga y persiste el estado desde **una** columna UUID del modelo y desactiva el borrado físico: `app/Forms/Components/NonDestructiveMediaUpload.php:22-28,30-69`.
- El presenter recorre los arrays conservando su orden; no ordena `slides` por `sort_order`: `app/Services/Frontend/FrontendPageRenderer.php:116-160,169-199`.
- El Hero actual muestra sólo la primera slide elegible y no aplica alineación, logo ni carousel: `resources/views/frontend/sections/hero.blade.php:7-36`.
- El fallback home contiene cuatro URLs rotativas en `resources/views/welcome.blade.php:10-36`; las vistas institucionales tienen fallbacks distintos, por ejemplo `resources/views/site/nosotros.blade.php:9-27`.
- La variante de logo oscuro y su fallback ya existen en `FrontendSettingsService::settings()`: `app/Services/Frontend/FrontendSettingsService.php:115-120`; el footer la consume en `resources/views/components/layouts/public.blade.php:239-245`.
- Owner-only está implementado por rol **y** permiso en `FrontendPagePolicy` y `FrontendSectionPolicy`: `app/Policies/FrontendPagePolicy.php:15-48`, `app/Policies/FrontendSectionPolicy.php:15-50`. La suite focal confirmó 403 para no-owner.
- `CtaResolver` existe como autoridad de runtime para `{label,type,target}`, pero no se encontró una función o componente compartido llamado `ctaFields` en `app/Filament`/`resources`: `app/Support/Frontend/CtaResolver.php:20-58`.
- Los carousels existentes usan scripts inline: `resources/views/site/proyectos.blade.php:118-132` y `resources/views/site/partials/carousel-script.blade.php:4-69`. El patrón CSS del home usa también un `<style>` inline: `resources/views/welcome.blade.php:20-27`.

---

## 3. Hallazgos críticos

### C-1 — El contrato de upload del Repeater no es compatible con el uploader existente

**Estado:** CONFIRMADO.  
**Evidencia:** El diseño exige un `Repeater` de hasta seis imágenes y afirma que cada upload produce un UUID para `slides[].media_id`: `docs/epicas/epica-12-1-mejora-ux-hero.md:136-145`. El componente existente `NonDestructiveMediaUpload` sólo hidrata el estado desde una columna UUID única del modelo (`:22-24,50-63`). `FrontendSection` no tiene columnas por slide; sólo tiene `payload` y la colección `images` (`app/Models/FrontendSection.php:26-33,49-52`).

**Impacto:** La implementación no puede rehidratar de forma determinista seis referencias dentro de un Repeater usando el componente existente. Si se sustituye por el uploader stock de Spatie, puede reactivarse `deleteAbandonedFiles()` y borrar físicamente media que todavía utiliza un snapshot publicado. Si se adjuntan archivos antes de guardar el payload y luego falla la validación, el diseño tampoco define qué sucede con las referencias huérfanas.

**Corrección obligatoria:** Definir antes de implementar un contrato explícito para `hero.slides[].media_id`:

1. componente/adaptador exacto para estado `list<uuid>` dentro del Repeater;
2. momento en que el archivo se adjunta a `FrontendSection`/colección `images`;
3. garantía de que nunca se ejecuta borrado físico al quitar o reordenar una slide;
4. comportamiento ante upload exitoso y guardado fallido;
5. mapeo de estado Filament a payload canónico y posterior validación por `saveSectionDraft`;
6. prueba de rehidratación de un Hero existente con 0, 1 y 6 slides, y prueba de UUID de otra sección/owner.

La corrección puede usar un nuevo adaptador no destructivo, pero no puede quedar como “la subida produce el UUID” sin explicar cómo se conserva una lista de UUIDs.

---

### C-2 — El fallback de slides descrito no coincide con las rutas públicas reales

**Estado:** CONFIRMADO.  
**Evidencia:** El diseño afirma que “sin slides publicados” se usan cuatro URLs actuales y que `slides: []` publicado no revive el fallback: `docs/epicas/epica-12-1-mejora-ux-hero.md:31-35,126-132,171-177`. En el código, las cuatro URLs sólo existen en el fallback de home (`resources/views/welcome.blade.php:10-36`). `nosotros`, `servicios`, `inversionistas` y `contacto` tienen fallbacks hardcodeados diferentes. Además, una página con snapshot publicado entra en `frontend.render`; `hero.blade.php` filtra las slides y, si no hay ninguna elegible, no renderiza ningún fondo (`resources/views/frontend/sections/hero.blade.php:7-16`).

**Impacto:** Un snapshot publicado con `slides: []` o con referencias no elegibles puede dejar el Hero sin imagen, aunque el documento prometa conservar fallback. También queda sin definición si las “cuatro URLs actuales” son sólo un fallback de home o una regla común para las cinco páginas canónicas.

**Corrección obligatoria:** Declarar una matriz de fallback por `pageKey` y por estado:

- página sin publicación;
- snapshot publicado sin la clave `slides`;
- snapshot publicado con `slides: []`;
- slides con UUID inválidos o no elegibles;
- una o varias slides válidas.

Elegir explícitamente si el fallback es el de la vista legacy por página, un fallback común configurable o un fondo deliberadamente plano cuando `slides: []` significa “sin imagen”. La decisión debe implementarse en un boundary único (presenter/servicio/configuración), no repetirse en cada Blade, y debe tener pruebas HTTP/DOM para las cinco rutas.

---

### C-3 — El carousel no tiene un contrato de accesibilidad operable

**Estado:** CONFIRMADO.  
**Evidencia:** El diseño sólo especifica slides apiladas, `aria-hidden`, `role="img"`, alt y `prefers-reduced-motion`: `docs/epicas/epica-12-1-mejora-ux-hero.md:126-132,161-167`. No define controles, pausa, estado activo, interacción por teclado, pausa por hover/focus ni cómo se actualizan los atributos ARIA mientras CSS alterna opacidades. El carousel real existente en `resources/views/site/proyectos.blade.php:118-132` usa `setInterval` sin pausa ni controles.

**Impacto:** CSS puede mostrar una slide que continúa marcada como `aria-hidden`; un lector de pantalla puede recibir una imagen distinta de la visible. Un Hero que rota automáticamente durante más de unos segundos sin mecanismo de pausa puede ser una barrera de accesibilidad. `prefers-reduced-motion` sólo cubre una preferencia de movimiento; no resuelve el control del contenido cambiante.

**Corrección obligatoria:** Fijar una de estas dos políticas, no dejarla a criterio del implementador:

- carousel automático con botón visible “Pausar/Reanudar”, pausa al hover y focus, soporte de teclado y actualización de `aria-hidden`/estado activo; o
- sin autoplay por defecto, con navegación manual accesible.

Para `prefers-reduced-motion: reduce`, mostrar una slide estática sin temporizador. Definir el nombre accesible del bloque, qué slide es anunciable, cuándo `role="img"` aplica, cómo se evita duplicar el alt y qué ocurre si todas son decorativas. Añadir prueba de DOM y de comportamiento, no sólo asserts de texto del Blade.

---

## 4. Hallazgos medios

### M-1 — `sort_order` está documentado, pero no existe una frontera que lo normalice

**Estado:** CONFIRMADO.  
**Evidencia:** El diseño exige ordenar por `sort_order`: `docs/epicas/epica-12-1-mejora-ux-hero.md:33-34,126-130`. El presenter conserva el orden recibido (`app/Services/Frontend/FrontendPageRenderer.php:169-199`) y el schema sólo valida que el valor sea un entero no negativo (`app/Services/Frontend/FrontendSectionSchema.php:197-200`). No se rechazan duplicados ni se reenumera el resultado.

**Impacto:** Reordenar visualmente el Repeater puede no cambiar el orden real del carousel; dos slides con el mismo `sort_order` tienen resultado dependiente del array/DB. La garantía “no por índice” no está cerrada.

**Corrección:** Definir un normalizador canónico: asignar `sort_order = 0..n-1` después del reorder, o rechazar duplicados y ordenar con desempate estable por UUID. Ejecutarlo antes de guardar o en el presenter, pero en un único punto. Probar payload deliberadamente invertido y con duplicados.

### M-2 — “CSP-safe” no está respaldado por un mecanismo concreto

**Estado:** CONFIRMADO.  
**Evidencia:** El diseño llama CSP-safe a la reutilización de los carousels actuales y deja el archivo como `resources/js/... o script del partial`: `docs/epicas/epica-12-1-mejora-ux-hero.md:126-132,210-219`. Los patrones actuales incluyen `<script>` inline (`resources/views/site/proyectos.blade.php:118-132`, `resources/views/site/partials/carousel-script.blade.php:4-69`) y `<style>` inline (`resources/views/welcome.blade.php:20-27`); no hay política CSP declarada que permita o controle esos bloques.

**Impacto:** La implementación puede funcionar hoy pero romper al activar CSP, o puede introducir concatenación de datos de contenido en JavaScript/CSS. “Reutilizar el patrón existente” no demuestra seguridad.

**Corrección:** Elegir y documentar un mecanismo: CSS-only con valores numéricos server-side validados, o JS compilado por Vite con datos serializados de forma segura. Prohibir clases Tailwind generadas desde valores del payload y handlers inline. Definir la compatibilidad CSP esperada y un test/inspección de respuesta que la compruebe.

### M-3 — El patrón `ctaFields` citado no existe como contrato reutilizable

**Estado:** CONFIRMADO.  
**Evidencia:** El diseño lo presenta como patrón ya entregado: `docs/epicas/epica-12-1-mejora-ux-hero.md:136-145`. La relación actual sólo tiene un Textarea JSON (`SectionsRelationManager.php:31-42`) y no se encontró `ctaFields` en `app/Filament` ni `resources`. Sí existe `CtaResolver` como autoridad de resolución runtime (`app/Support/Frontend/CtaResolver.php:20-58`).

**Impacto:** Dos implementadores pueden crear formularios CTA incompatibles o duplicar validación. La experiencia declarada para tipo de destino no es reproducible a partir del repositorio actual.

**Corrección:** Especificar la función/componente real que construirá los campos, sus nombres (`label`, `type`, `target`), visibilidad reactiva y validación server-side. Reutilizar `CtaResolver` en la frontera de guardado; no confiar sólo en la UI.

### M-4 — MIME, peso y dimensiones de imágenes no están cerrados

**Estado:** CONFIRMADO.  
**Evidencia:** Seguridad sólo dice “validación de MIME/tamaño” sin valores concretos: `docs/epicas/epica-12-1-mejora-ux-hero.md:151-157`. Los límites existentes varían: `FrontendServiceResource` usa PNG/JPEG/WebP y 3072 KB (`app/Filament/Resources/FrontendServiceResource.php:68-72`), mientras los logos tienen reglas propias (`app/Filament/Pages/FrontendSettingsPage.php:486-505`).

**Impacto:** La carga puede aceptar formatos o dimensiones que rompan el fondo, consumir almacenamiento excesivo o producir una UX distinta entre secciones.

**Corrección:** Declarar allowlist de MIME real, tamaño máximo, dimensiones mínimas/máximas, orientación/ratio recomendado, longitud máxima de alt y si se acepta SVG. Repetir las reglas en validación server-side y cubrir cada rechazo.

### M-5 — La accesibilidad del logo y el contraste son afirmaciones no medibles

**Estado:** PROBABLE, requiere cierre de diseño.  
**Evidencia:** El diseño exige “contraste suficiente” y muestra `logo_dark_url`, pero no fija `alt`, `aria-hidden`, nombre alternativo ni criterio de contraste: `docs/epicas/epica-12-1-mejora-ux-hero.md:114-124,161-167`. El footer actual usa `alt` igual a `site_name` (`resources/views/components/layouts/public.blade.php:244`).

**Impacto:** Activar el logo puede duplicar el nombre para lectores de pantalla o dejar una imagen sin alternativa. El tamaño elegido puede superar el área útil responsive.

**Corrección:** Definir: `alt=site_name` cuando el logo comunica la marca y `alt=""`/`aria-hidden` cuando sólo duplica el H1; tamaño máximo por breakpoint; y una verificación visual/automatizada de contraste del bloque de texto sobre el overlay.

---

## 5. Hallazgos menores

### Mn-1 — La referencia de líneas del fallback home es imprecisa

El documento cita `welcome.blade.php:5-10` como origen de las cuatro imágenes: `docs/epicas/epica-12-1-mejora-ux-hero.md:34-35`. En el código actual, las URLs están en `resources/views/welcome.blade.php:12-16`; las líneas 5-10 sólo contienen el dispatch y el inicio del fallback. Corregir la referencia para que la trazabilidad no apunte a un bloque equivocado.

### Mn-2 — `settings()` debe sustituirse por la llamada real

El diseño dice `settings()['brand']['logo_dark_url']`: `docs/epicas/epica-12-1-mejora-ux-hero.md:116`. En el código, el contrato concreto es `app(FrontendSettingsService::class)->settings()` y el layout guarda el resultado en `$profile`: `resources/views/components/layouts/public.blade.php:29-45`. Especificar el boundary exacto evita introducir un helper inexistente.

### Mn-3 — La lista de archivos esperados es demasiado abierta

`resources/js/... o script del partial` (`docs/epicas/epica-12-1-mejora-ux-hero.md:218`) no permite revisar ownership, CSP ni pruebas con precisión. Nombrar el archivo final y si el comportamiento será CSS-only o JS compilado.

### Mn-4 — Los defaults están descritos en render, no como normalización de payload

Los defaults `left`, `false` y `md` aparecen en `docs/epicas/epica-12-1-mejora-ux-hero.md:102-108,171-177`, pero no se define si se materializan al guardar o sólo al presentar. Preferir un DTO/presenter con defaults centralizados para que preview y público sean idénticos.

---

## 6. Riesgos de seguridad

| Riesgo | Estado | Control exigido |
| --- | --- | --- |
| UUID de media no elegible o de otra sección/owner | El servicio actual lo controla en `FrontendPageContentService:165-169,211-215`; debe conservarse en el adaptador del Repeater. | No aceptar UUID sólo porque el upload terminó; validar siempre en `saveSectionDraft` y publicación. |
| Borrado físico accidental de media publicada | Riesgo real si se usa el uploader stock en lugar del componente no destructivo. | Prohibir `deleteAbandonedFiles`, `singleFile` y `onlyKeepLatest` para `images`; prueba de que quitar/reordenar no borra archivos. |
| Inyección de CSS/clases por `text_align` o `logo_size` | Bien orientado en el diseño por enums, pero todavía no implementado. | Config allowlist + mapping literal de clases; nunca interpolar valores del payload en CSS o clases. |
| Scripts inline y CSP | El repositorio no declara CSP y los patrones existentes son inline. | Elegir bundle/CSS-only y documentar la política antes de afirmar CSP-safe. |
| Alt/estado ARIA inconsistente durante el fade | No resuelto por `prefers-reduced-motion` solo. | Estado activo explícito, pausa y pruebas con DOM/lector de pantalla. |

No encontré un bypass owner-only en el diseño: la policy existente exige `hasRole('owner') && can('frontend.manage')`, y el diseño correctamente propone no modificarla (`docs/epicas/epica-12-1-mejora-ux-hero.md:151-157`).

---

## 7. Riesgos de mantenimiento

- Formulario, schema, presenter y Blade pueden divergir si no se define un DTO/normalizador único para el payload Hero.
- El fallback puede quedar repartido entre cinco vistas legacy y el nuevo partial si no se centraliza por `pageKey`.
- La colección `images` es deliberadamente histórica/no destructiva; sin una política clara de referencias huérfanas, cada reemplazo aumenta almacenamiento indefinidamente. Esto no exige borrado físico en v1, pero sí debe registrarse como deuda operativa.
- La documentación del CTA menciona una abstracción inexistente, lo que favorece duplicación de formularios y validaciones.
- Una suite verde sólo con asserts de texto no demostraría orden, ARIA, autoplay, reduced-motion ni rehidratación de uploads.

---

## 8. Sobreingeniería detectada

No se detecta sobreingeniería grave en agregar `text_align`, `logo_enabled`, `logo_size` y un carousel para cerrar la desviación UX solicitada. Es correcto no introducir migraciones, tablas ni cambios al dominio de publicación.

Sí sería sobreingeniería para este incremento:

- crear un page builder nuevo o un sistema genérico de media cuando el contrato sólo requiere el Hero;
- añadir historial de versiones o borrado físico de media;
- introducir un framework de carousel adicional cuando CSS/Vite del proyecto alcanza;
- agregar preview avanzado antes de cerrar el flujo básico, fallback y accesibilidad.

---

## 9. Recomendaciones obligatorias

1. Resolver C-1 con un contrato implementable de lista de UUIDs y ciclo de vida no destructivo.
2. Resolver C-2 con fallback explícito por página y estado de publicación.
3. Resolver C-3 con política operable de autoplay/pausa/teclado/ARIA/reduced-motion.
4. Definir dónde se normaliza `sort_order` y probar reorder, inversión y duplicados.
5. Reemplazar la referencia a `ctaFields` por un componente/helper real y documentar el mapeo al value object de `CtaResolver`.
6. Cerrar MIME, peso, dimensiones, alt y SVG de las imágenes del Hero.
7. Definir un mecanismo concreto CSS-only o JS compilado; no llamar CSP-safe a scripts inline existentes.
8. Añadir tests de contrato antes de implementación: schema nuevo, upload/reload, media no elegible, fallback por página, orden, DOM ARIA, reduced-motion y no eliminación física.
9. Mantener sin cambios `FrontendPageContentService`, `FrontendPagePublisher`, policies y modelo de dominio salvo que el contrato de media revele una necesidad aditiva explícita.

---

## 10. Recomendaciones opcionales

- Crear un `HeroPresenter` pequeño o un normalizador compartido para defaults, orden, clases fijas y fallback.
- Añadir una prueba visual responsive del Hero en 320 px, 768 px y desktop.
- Exponer en el formulario una ayuda visible de ratio recomendado sin hacer el ratio una restricción rígida si el negocio necesita flexibilidad.
- Registrar métricas de fallback y media no elegible para detectar contenido que deja de mostrarse.

---

## 11. Evaluación de decisiones cerradas del diseño

| Decisión | Evaluación |
| --- | --- |
| Owner-only sin cambiar policy/gate | ✅ Correcta y confirmada por código y tests. |
| Sin migración ni cambios al dominio de publicación | ✅ Correcta; el cambio puede ser aditivo si se resuelve el payload y la media. |
| Campos visuales opcionales y allowlisted | ✅ Correcta en principio. Debe añadirse la normalización exacta de defaults y las claves de configuración antes de implementar. |
| Logo reutiliza settings y no crea media nueva | ✅ Correcta; `logo_dark_url` y fallback existen en producción de código. Falta cerrar alt/contraste. |
| Media no destructiva | ✅ Correcta y coherente con `NonDestructiveMediaUpload`, pero el Repeater requiere un adaptador de lista que el documento aún no define. |
| CSS-first / JS sólo si hace falta | ⚠️ Aceptable como orientación, no como contrato. Debe fijarse una estrategia concreta y compatible con CSP. |
| No formularios de otros tipos ni page builder | ✅ Alcance razonable y bien acotado. |

---

## 12. Checklist de corrección para el implementador

- [ ] Actualizar el documento con el ciclo de vida y la hidratación de `slides[].media_id`.
- [ ] Definir cómo el Repeater usa `FrontendSection` y cómo se impide el borrado físico.
- [ ] Definir fallback por `pageKey`, incluyendo `slides: []` y media inválida.
- [ ] Definir autoplay, pausa, teclado, estado activo, `aria-hidden`, alt y reduced-motion.
- [ ] Normalizar o validar `sort_order` con desempate estable.
- [ ] Sustituir `ctaFields` por el nombre de un componente/helper real.
- [ ] Declarar MIME, peso, dimensiones, alt y política SVG.
- [ ] Nombrar el archivo JS/CSS final y eliminar la ambigüedad de “script del partial”.
- [ ] Agregar casos de prueba que ejerciten el DOM y el flujo real de Filament, no sólo `assertSee`.
- [ ] Reejecutar la auditoría de diseño; no abrir implementación mientras exista C-1, C-2 o C-3.

---

**Conclusión:** el rediseño resuelve correctamente el problema de producto, pero todavía no contiene contratos suficientes para garantizar media, fallback y accesibilidad. La siguiente acción correcta es corregir el diseño y volver a auditarlo; no comenzar el lote de implementación.


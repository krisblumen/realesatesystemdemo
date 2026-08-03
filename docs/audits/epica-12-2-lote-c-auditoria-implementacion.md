# Reauditoría de implementación — Épica 12.2, Lote C

**Proyecto:** New Hauz — CMS inmobiliario
**Fecha:** 2026-07-27
**Auditor:** Codex, auditor de implementación independiente
**Rama:** `feature/epica-12-content-manager`
**HEAD auditado:** `29aad35`
**Contrato:** `docs/epicas/epica-12-2-lotes-implementacion.md` §6
**Código principal auditado:** `32c96ad`, `9bd6261`

## 1. Veredicto

### **APROBADO**

El bloqueo heredado de la auditoría anterior quedó cerrado con la aprobación de
los Lotes A y B. El Lote C pasa sus pruebas focales, la suite acumulada y la
verificación en vivo de los cuatro tipos dinámicos. El CMS no permite editar
ítems, IDs ni consultas; el renderer obtiene los datos desde las autoridades
operativas y el guardado de `ServiceType.active` invalida el cache mediante el
flujo real del panel.

> **GATE LOTE C: APROBADO**

El Lote D queda habilitado.

## 2. Evidencia real

### 2.1 Corrección del bloqueo heredado

- El Lote A está aprobado en
  `docs/audits/epica-12-2-lote-a-auditoria-implementacion.md`.
- El Lote B está aprobado en
  `docs/audits/epica-12-2-lote-b-auditoria-implementacion.md`.
- `9bd6261` corrigió la aridad del provider y configuró PHPUnit para fallar ante
  warnings/notices.

### 2.2 Verificación base

| Verificación | Resultado observado |
| --- | --- |
| `composer validate --strict` | ✅ `composer.json is valid` |
| `composer install --no-interaction --dry-run` | ✅ lock sincronizado; nada que instalar, actualizar o eliminar |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | ✅ limpio contra PostgreSQL real |
| `DB_DATABASE=inmo_test php artisan test tests/Feature/Frontend/FrontendDynamicSectionEditorTest.php` | ✅ 20/20 pruebas, 74 aserciones, código de salida `0` |
| `DB_DATABASE=inmo_test php artisan test` | ✅ 1003/1003 pruebas, 4380 aserciones, código de salida `0` |
| `./vendor/bin/pint --test` | ✅ limpio |
| `npm run build` | ✅ Vite y `build:filament` completados |

El build conserva avisos no bloqueantes de Browserslist desactualizado y de
`npx` instalando temporalmente `tailwindcss@3.4.19` para el tema Filament.

### 2.3 Verificación en vivo del CMS

Con Owner autenticado:

- En `/admin/frontend/paginas/1/edit`, el modal de **Propiedades destacadas**
  mostró únicamente antetítulo, título y `Cuántos mostrar`, con ayuda `Entre 1
  y 24. Vacío = 12.`. También mostró que los elementos provienen de las
  propiedades marcadas como destacadas y que esa pantalla solo cambia el
  encabezado.
- En `/admin/frontend/paginas/3/edit`, el modal de **Listado de servicios**
  mostró únicamente antetítulo y título, y explicó que se muestran los
  servicios activos del sitio. No ofreció límite, ítems, IDs ni consultas.
- Las cuatro secciones dinámicas aparecen con nombres humanos y claves internas
  secundarias: `service_list`, `featured_properties`,
  `opportunity_properties` y `featured_projects`.

### 2.4 Autoridad operativa y cache

En la base de pruebas se verificó que los cuatro `ServiceType` tienen
`FrontendService` vinculado y que el renderer usa `ServiceType.active` junto con
el toggle por ubicación.

Prueba end-to-end sobre el flujo soportado por el CMS:

1. Se precargó la página pública de servicios para crear el namespace de cache.
2. Owner abrió `/admin/service-types/arquitectura/edit`, desactivó `Activo` y
   guardó.
3. Sin ejecutar un clear manual, `/servicios` dejó de mostrar `02 · ARQUITECTURA`
   y su contenido.
4. Se reactivó el mismo servicio desde el panel y `/servicios` volvió a mostrarlo.

Esto confirma el bump de `EditServiceType::afterSave()` y el comportamiento
fail-closed en el camino que realmente usa el administrador. Una mutación SQL
directa, fuera del contrato de la aplicación, solo se consideró después de
limpiar el cache de prueba y no se reporta como defecto del flujo soportado.

### 2.5 Regresión HTTP/DOM/visual y autorización

- `/`, `/nosotros`, `/servicios`, `/inversionistas` y `/contacto` respondieron
  HTTP `200`; cada DOM emitió exactamente un `h1` y no mostró `Whoops` ni
  `Exception`.
- El agente autenticado recibió `403 Forbidden` en `/admin/frontend/paginas` y
  `/admin/frontend/servicios`.
- La vista móvil de `/servicios` se revisó a `390×844`; el header, hero y primer
  bloque dinámico no mostraron desborde visible.

## 3. Hallazgos críticos

No se encontraron hallazgos críticos abiertos.

## 4. Hallazgos medios

### M-C-1 — RESUELTO: gate acumulado bloqueado por A

La auditoría previa mantenía C rechazado únicamente porque la suite acumulada
terminaba con código `1` por warnings de A. A y B ya tienen gate aprobado y la
suite actual termina con código `0` en 1003/1003 pruebas. El bloqueo queda cerrado.

No quedan hallazgos medios abiertos propios de C.

## 5. Hallazgos menores

- `npm run build` conserva avisos de Browserslist y de la instalación temporal de
  Tailwind; no bloquean el lote.
- El árbol local conserva cambios ajenos a esta auditoría en
  `.atl/skill-registry.md`, `docs/audits/epica-12-2-lote-b-auditoria-implementacion.md`,
  `public/css/filament/admin/theme.css` y `docs/letras canciones hubiera.docx`.
  No fueron incluidos ni deben mezclarse con un commit del Lote C.

## 6. Regresiones

No se observó drift entre contenido editorial y autoridad operativa:

- `Property`, `Project` y `ServiceType` siguen resolviendo los ítems en cada
  render;
- el payload dinámico no fija ítems ni IDs;
- el servicio inactivo desaparece del listado público después del guardado real
  en el CMS;
- las cinco páginas públicas y la navegación del panel siguen funcionando.

## 7. Riesgos de seguridad

No se detectó una regresión de seguridad:

- el formulario no es fuente de autoridad para propiedades, proyectos ni
  servicios;
- `FrontendSectionSchema` rechaza `items`, `ids`, `property_ids`, `query` y
  otras claves no permitidas;
- la elegibilidad de servicios se resuelve con `ServiceType.active` y el
  `FrontendService` vigente;
- el agente no puede acceder al módulo Owner-only y recibió 403 real.

## 8. Riesgos de mantenimiento

La allowlist de campos dinámicos está declarada en el formulario, el compilador y
`FrontendSectionSchema`. Las pruebas focales detectan divergencias actuales,
pero al agregar otro tipo dinámico deben actualizarse las tres superficies y la
matriz `dynamicTypes()` en un mismo cambio.

El flujo de mutación de `ServiceType` debe conservar el bump global
`afterCommit`; depender únicamente del TTL volvería a permitir contenido
operativo obsoleto.

## 9. Tests faltantes

No se identifican escenarios TB2C-1 a TB2C-6 faltantes. La prueba focal cubre:

- ausencia de ítems/IDs/consultas en la UI;
- rechazo server-side de payloads con claves extra;
- resolución desde el kernel sin republicar;
- límites 1–24 y default acotado;
- `generated_from_ids` al publicar;
- servicio inactivo o sin configuración operativa en modo fail-closed.

## 10. Correcciones obligatorias

Ninguna. El hallazgo heredado M-C-1 está cerrado y no quedan bloqueantes de C.

## 11. Correcciones recomendadas

- Mantener `dynamicList()` sin capacidad de recibir IDs, ítems o consultas.
- Mantener el límite `24` en schema y UI como una única decisión contractual.
- Mantener una prueba de cache que cubra el guardado de `ServiceType.active` y
  confirme el bump después del commit.
- Tratar los avisos de Browserslist y Tailwind como higiene independiente, sin
  reabrir este lote.

## 12. Decisión explícita del gate

> **GATE LOTE C: APROBADO.** El Lote C cumple la suite focal, la suite
> acumulada, Composer, migración PostgreSQL, Pint, build, formularios dinámicos,
> autoridad operativa, invalidación por guardado, aislamiento por rol y
> verificación DOM/HTTP pública. El Lote D queda habilitado.

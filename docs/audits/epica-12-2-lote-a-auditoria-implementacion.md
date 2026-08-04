# Reauditoría de implementación — Épica 12.2, Lote A

**Proyecto:** New Hauz — CMS inmobiliario
**Fecha:** 2026-07-27
**Auditor:** Codex, auditor de implementación independiente
**Rama:** `feature/epica-12-content-manager`
**HEAD auditado:** `d66b7d8`
**Contrato:** `docs/epicas/epica-12-2-lotes-implementacion.md` §4
**Corrección auditada:** `9bd6261` — aridad exacta del `DataProvider` y fallo visible ante warnings

## 1. Veredicto

### **APROBADO**

La observación crítica de la auditoría anterior quedó corregida. El provider
`migratedTypeNames()` deriva una sola entrada por tipo y
`test_html_is_rejected_in_every_migrated_type()` lo consume con la aridad exacta;
la prueba focal y la suite completa terminan con código de salida `0`, sin
warnings de PHPUnit. La funcionalidad del Lote A también fue comprobada en el
panel real, con Owner, con 403 real para agente y con las páginas públicas.

> **GATE LOTE A: APROBADO**

El Lote B queda habilitado.

## 2. Evidencia real

### 2.1 Corrección del hallazgo previo

**Hallazgo cerrado:** C-A-1, warning por provider incompatible.

- `tests/Feature/Frontend/FrontendTextSectionEditorTest.php:107-110` define
  `migratedTypeNames()` y transforma cada dataset de cuatro valores a un
  dataset de un valor.
- `tests/Feature/Frontend/FrontendTextSectionEditorTest.php:267` usa
  `#[DataProvider('migratedTypeNames')]` para el método que recibe únicamente
  `string $type`.
- El cambio está en el commit `9bd6261`, que además activó `failOnWarning` y
  `failOnNotice` en `phpunit.xml`.

### 2.2 Verificación base

| Verificación | Resultado observado |
| --- | --- |
| `composer validate --strict` | ✅ `composer.json is valid` |
| `composer install --no-interaction --dry-run` | ✅ lock sincronizado; nada que instalar, actualizar o eliminar |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | ✅ migraciones y seeders limpios contra PostgreSQL real |
| `DB_DATABASE=inmo_test php artisan test tests/Feature/Frontend/FrontendTextSectionEditorTest.php` | ✅ 40/40 pruebas, 266 aserciones, código de salida `0` |
| `DB_DATABASE=inmo_test php artisan test` | ✅ 1003/1003 pruebas, 4380 aserciones, código de salida `0` |
| `./vendor/bin/pint --test` | ✅ limpio |
| `npm run build` | ✅ Vite y `build:filament` completados |

El build conserva avisos no bloqueantes de Browserslist desactualizado y de
`npx` instalando temporalmente `tailwindcss@3.4.19` para el tema Filament. No
provocaron fallo y quedan como mantenimiento separado.

### 2.3 Verificación funcional y de seguridad en vivo

- Owner autenticado en `/admin` y acceso real a `/admin/frontend/paginas`.
- El editor `/admin/frontend/paginas/1/edit` mostró las ocho secciones del
  home. El modal real de `investors_block` mostró grupos estructurados de CTA
  (`Antetítulo`, `Título`, `Texto`, botones y destino) y no mostró
  `Contenido (JSON)`.
- La prueba focal cubre los seis tipos sin media del Lote A: `cta`, `rich_text`,
  `values`, `metrics`, `partners` y `audience_outcomes`; valida formularios,
  payload canónico, rechazo de HTML, cardinalidades, `result` obligatorio y
  CTA server-side.
- Agente autenticado en vivo recibió `403 Forbidden` en:
  - `/admin/frontend/paginas`
  - `/admin/frontend/servicios`
- Las cinco rutas públicas respondieron por HTTP real con `200`:
  `/`, `/nosotros`, `/servicios`, `/inversionistas` y `/contacto`.
  En DOM cada una emitió exactamente un `h1` y no mostró `Whoops` ni
  `Exception`.
- La revisión visual en escritorio y móvil no mostró desborde visible del
  header, hero, CTA ni navegación móvil.

## 3. Hallazgos críticos

### C-A-1 — RESUELTO: aridad incompatible del DataProvider

**Cierre:** `9bd6261`, verificado en código y ejecución.

El provider específico `migratedTypeNames()` elimina los seis warnings que
dejaban la prueba focal y la suite con código de salida `1`. La ejecución actual
terminó con código `0` tanto en la prueba focal como en las 1003 pruebas de la
suite completa. No queda bloqueo crítico.

## 4. Hallazgos medios

No se encontraron hallazgos medios abiertos en esta reauditoría.

## 5. Hallazgos menores

- El build sigue reportando avisos de mantenimiento de Browserslist y del uso
  de `npx tailwindcss@3`; no bloquean el Lote A.
- El árbol local conserva cambios ajenos a esta auditoría en
  `.atl/skill-registry.md`, `public/css/filament/admin/theme.css` y
  `docs/letras canciones hubiera.docx`. No fueron incluidos ni deben incluirse
  en un commit del lote.

## 6. Regresiones

No se observaron regresiones en las cinco páginas públicas, en la navegación del
panel ni en el aislamiento de Owner frente a agente. La migración reconstruyó
correctamente `inmo_test`, la suite completa pasó y Pint no detectó problemas.

## 7. Riesgos de seguridad

No se detectó una regresión de seguridad en el cierre del Lote A:

- el agente no pudo acceder a las rutas administrativas del frontend y recibió
  403 real;
- los CTA siguen pasando por `CtaFields`/`CtaResolver`;
- `FrontendSectionSchema` rechaza HTML y destinos inseguros server-side;
- la corrección del provider no relaja autorización ni validación.

## 8. Riesgos de mantenimiento

El riesgo observado en la auditoría anterior queda mitigado por dos controles:

1. providers con aridad exacta para cada método consumidor;
2. `phpunit.xml` configurado para fallar ante warnings y notices.

Debe conservarse la regla de ejecutar y evaluar el código de salida del runner,
no solamente el JSON del reporter.

## 9. Tests faltantes

No se identifican tests funcionales obligatorios faltantes para TB2A-1 a TB2A-8.
La suite focal y la suite completa cubren el contrato definido y ya no tienen
warnings ocultos.

## 10. Correcciones obligatorias

Ninguna. C-A-1 está cerrado y el gate de la suite termina en código `0`.

## 11. Correcciones recomendadas

- Mantener `failOnWarning` y `failOnNotice` en CI.
- No mezclar los cambios locales ajenos con el commit del Lote A.
- Tratar los avisos de Browserslist y la dependencia temporal de Tailwind como
  una tarea de higiene independiente, sin reabrir este lote.

## 12. Decisión explícita del gate

> **GATE LOTE A: APROBADO.** El Lote A cumple sus pruebas focales, la suite
> acumulada, la verificación de formato, la migración PostgreSQL, el build y la
> comprobación funcional Owner/403/DOM/HTTP. El Lote B queda habilitado.

# Auditoría de implementación — Épica 12, Lote B: Tema visual

- **Proyecto:** New Hauz — CMS inmobiliario
- **Fecha:** 2026-07-22
- **Auditor:** Codex (modelo Sol), auditor independiente
- **Rama auditada:** `feature/epica-12-content-manager`
- **HEAD auditado:** `0e6dcd6 fix(epica-12): M-B4 — foreground SVG garantizado sobre superficie de acento`
- **Lote:** B — Tema visual runtime
- **Diseño de referencia:** `docs/epicas/epica-12-administrador-contenidos-frontend.md` §16.5 y §19.2, RFC-072
- **Auditoría anterior:** M-B4 pendiente por foreground SVG fijo sobre superficie configurable

## 1. Veredicto

## **APROBADO — el contrato runtime de tema y foreground queda verificado**

La reauditoría confirma la corrección de M-B4 en código, prueba de regresión y navegador real. Los hallazgos anteriores C-B2, M-B1, M-B2 y M-B3 también permanecen cerrados. El tema hostil se aplicó en PostgreSQL, se sirvió por HTTP y los cuatro iconos de Inversionistas resolvieron el foreground garantizado mediante `currentColor` y `text-on-brand-accent`.

## 2. Evidencia real

### 2.1 Verificaciones base ejecutadas

| Verificación | Resultado | Evidencia |
|---|---:|---|
| Dependencias | ✅ | `composer validate --strict`: `./composer.json is valid`; `composer install --dry-run --no-interaction --prefer-dist`: lock sincronizado, sin cambios. |
| Migración | ✅ | `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed`: terminó limpio contra PostgreSQL real. |
| Tests focales | ✅ | `DB_DATABASE=inmo_test php artisan test tests/Feature/Frontend tests/Unit/Frontend tests/Feature/Auth/PermissionSeederTest.php --no-coverage`: **169 tests, 169 passed, 904 assertions**. |
| Suite completa | ✅ | Ejecución única y limpia después de resetear la base: `DB_DATABASE=inmo_test php artisan test --no-coverage`: **668 tests, 668 passed, 2,734 assertions**, `duration_ms=353749`, `EXIT:0`. |
| Formato PHP | ✅ | `./vendor/bin/pint --test`: `{"tool":"pint","result":"passed"}`. |
| Build frontend | ✅ | `npm run build`: Vite 8 y build separado de Filament/Tailwind 3 terminaron correctamente; se generó `app-CGrJM-ne.css`. |
| Integridad del diff | ✅ | `git diff --check` limpio. El cambio no relacionado en `.atl/skill-registry.md` permanece fuera de esta auditoría. |

Durante la verificación se detectó que una primera invocación de la suite quedó activa cuando se lanzó una segunda. Ambas ejecuciones fueron detenidas, la base se migró nuevamente desde cero y **solo se utilizó como evidencia la ejecución única posterior**, que terminó con `EXIT:0`.

### 2.2 Verificación de M-B4 en código y pruebas

La fuente corregida es `resources/views/site/inversionistas.blade.php:106-110`:

```blade
<div class="... border-brand-primary/15 bg-navy-50 ...">
    <div class="... bg-brand-accent text-on-brand-accent">
        <svg ... fill="none" stroke="currentColor" ...>
```

La prueba `tests/Feature/Frontend/FrontendSurfaceForegroundTest.php:139-158` ahora inspecciona atributos `stroke` y `fill` de descendientes de superficies tematizadas. Solo permite valores que delegan en el color heredado (`currentColor`, `inherit`, `none`, `transparent`, `url(...)` o `var(...)`). El test de regresión `:264-274` demuestra que el detector rechaza un SVG con `stroke="#ffffff"` y acepta la variante segura con `currentColor`.

La matriz `tests/Feature/Frontend/FrontendPublicThemeCoverageTest.php` también incluye `border-navy` entre los roles fijos prohibidos. La corrección está documentada en `docs/epicas/epica-12-administrador-contenidos-frontend.md` §19.2.6.

### 2.3 Tema hostil servido por HTTP y navegador

Se aplicó temporalmente en PostgreSQL:

```text
primary=#fef08a
on_primary=#111111
accent=#fde68a
on_accent=#111111
background=#ffffff
text=#111111
radius=rounded
```

Las siguientes rutas respondieron `HTTP/1.1 200 OK`:

```text
/                                                                    200
/nosotros                                                            200
/servicios                                                           200
/inversionistas                                                      200
/proyectos                                                           200
/contacto                                                            200
/inmuebles                                                           200
/inmuebles/east-adonis-casa-auditoria-lote-b-final-property          200
/proyectos/auditoria-lote-b-final-uno                                200
```

La inspección CSSOM/DOM del navegador en `/inversionistas` devolvió:

```text
--nh-accent: #fde68a
--nh-on-accent: #111111
surface background: rgb(253, 230, 138)
inherited container color: rgb(17, 17, 17)
SVG stroke attribute: currentColor (4/4)
computed SVG stroke: rgb(17, 17, 17) (4/4)
fixed white stroke occurrences: 0
```

El recálculo con `App\Support\Frontend\ThemeContract::contrastRatio()` dio:

```text
on_accent_over_accent=15.162424191442
on_primary_over_primary=16.226121682692
```

La revisión visual del navegador mostró los iconos oscuros sobre los fondos amarillos, sin el defecto de blanco sobre amarillo detectado en la auditoría anterior. `/inmuebles` también mostró `bg-brand-primary`, `text-brand-primary-ink`, `focus:border-brand-focus` y `focus:ring-brand-focus`, con `--nh-focus: #111111`.

### 2.4 Valores inseguros rechazados en el render

Se persistieron temporalmente en PostgreSQL los valores:

```text
primary=}</style><script>alert(1)</script>
accent=javascript:alert(1)
```

La respuesta real de `/` emitió los fallbacks seguros:

```text
--nh-primary: #091a5b
--nh-accent: #f6a300
payload </style><script>: ausente
script ejecutable inyectado: ausente
```

Esto confirma en vivo la segunda validación del boundary de render, además de los tests T-8b/T-8c.

### 2.5 Cobertura acumulada de los hallazgos previos

- **C-B2:** shape de caché `3`, inks derivados y utilities `text-brand-*-ink`; **resuelto**.
- **M-B1:** vistas públicas de inmuebles migradas a roles runtime; listado y detalle publicados verificados; **resuelto**.
- **M-B2:** discovery cubre `inmuebles/**` y `livewire/leads/**`, con guardian y assertions HTTP/DOM; **resuelto**.
- **M-B3:** formularios y filtros consumen `focus:ring-brand-focus`/`focus:border-brand-focus`; DOM live sin `ring-orange`; **resuelto**.
- **M-B4:** SVG inline usa `currentColor`, foreground garantizado y detector de `stroke`/`fill`; ejecución visual confirma contraste; **resuelto**.

## 3. Hallazgos críticos

No hay hallazgos críticos abiertos.

## 4. Hallazgos medios

No hay hallazgos medios abiertos. M-B4 queda **RESUELTO** con evidencia de fuente, prueba de regresión, CSSOM, DOM, HTTP y contraste calculado.

## 5. Hallazgos menores

No hay hallazgos menores bloqueantes. Los shades decorativos fijos permitidos por RFC-072:152 (`navy-50/700/900`, `orange-*`, gradientes, sombras y estados) permanecen fuera del contrato de roles configurables y no se confundieron con foregrounds sobre superficies runtime.

## 6. Regresiones

- **No se detectaron regresiones de ejecución:** migración, suite, Pint, build y rutas públicas están verdes.
- Property, Project y Leads respondieron correctamente bajo el tema hostil.
- La corrección de M-B4 no modifica migraciones ni modelos de User, Property, Project, Zone, ServiceType o Media.
- Los valores maliciosos de tema degradan a fallbacks seguros y no aparecen en el HTML.

## 7. Riesgos de seguridad

- **CSS/XSS:** no se observó bypass; colores inválidos no se emiten en el `<style>` runtime.
- **Foreground SVG:** el vector descubierto quedó cubierto por una prueba que inspecciona `stroke`/`fill`, no solo clases Tailwind.
- **Clases dinámicas:** no se generan utilities Tailwind con valores arbitrarios persistidos por el owner.
- **Accesibilidad:** los pares verificados superan AA; el acento usado en la prueba obtuvo 15.16:1 y el primario 16.22:1 con sus foregrounds.

## 8. Riesgos de mantenimiento

1. Todo nuevo SVG colocado sobre `bg-brand-*` debe seguir usando `currentColor` y el token `text-on-brand-*` correspondiente.
2. La matriz/guardian de vistas públicas debe actualizarse si se agregan nuevas raíces de Blade.
3. La distinción entre shades decorativos permitidos y roles brand-critical debe conservarse en cualquier nuevo consumer.
4. La primera ejecución concurrente de la suite fue descartada; la evidencia oficial de este informe es exclusivamente la segunda ejecución limpia.
5. `.atl/skill-registry.md` conserva un cambio no relacionado y no debe incluirse en el commit del lote.

## 9. Tests faltantes

No quedan tests faltantes obligatorios para cerrar el Lote B. Como mejora futura, puede agregarse una fixture visual persistida desktop/móvil del tema hostil; la verificación visual manual de esta reauditoría fue satisfactoria.

## 10. Correcciones obligatorias

Ninguna.

## 11. Correcciones recomendadas

- Mantener el detector de atributos SVG junto con el detector de clases para evitar regresiones por vectores de color alternativos.
- Reutilizar la clasificación de consumers públicos como fuente común de la cobertura futura.
- Conservar los tokens derivados `primary_ink`/`accent_ink` separados de las superficies `bg-brand-*`.

## 12. Decisión explícita del gate

La corrección de M-B4 fue verificada en el código actual, en la prueba focal, en la suite completa, en el DOM/CSSOM y visualmente bajo un tema hostil. No quedan correcciones obligatorias ni regresiones bloqueantes.

> **GATE LOTE B: APROBADO**

El Lote C queda habilitado.

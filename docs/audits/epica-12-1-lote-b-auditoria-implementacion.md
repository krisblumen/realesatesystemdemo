# Reauditoría de implementación — Épica 12.1, Lote B

**Proyecto:** New Hauz — Plataforma inmobiliaria (monolito Laravel)  
**Fecha:** 2026-07-26  
**Auditor:** Codex, auditor de implementación independiente  
**Rama auditada:** `feature/epica-12-content-manager`  
**Correcciones auditadas:** `1653c8d`, `581007b` y `16051dc`
**HEAD actual:** `16051dc` — `docs(epica-12): lote 12.1-B — coherencia normativa, excepción TB-8 y evidencia aislada`
**Contrato:** `docs/epicas/epica-12-1-lotes-implementacion.md` §3  
**Diseño auditado:** `docs/epicas/epica-12-1-mejora-ux-hero.md` v11

## 1. Veredicto

### **APROBADO**

Las correcciones cerraron los bloqueantes anteriores: el diseño ya describe el DOM y el fallback reales, la excepción de TB-8 está formalizada en el contrato, y la evidencia visual ahora cubre home y modo B en una base PostgreSQL aislada. La implementación se ejecutó de nuevo sobre el sistema real sin regresiones.

> **GATE LOTE B: APROBADO**

El Lote C queda habilitado.

## 2. Evidencia real

### 2.1 Comandos y estado del sistema

| Verificación | Resultado |
| --- | --- |
| `composer validate --strict` | ✅ `composer.json` válido y `composer.lock` consistente |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | ✅ Limpio contra PostgreSQL real; migraciones y seed completos |
| Pruebas focales B | ✅ **52 tests, 300 assertions** |
| `DB_DATABASE=inmo_test php artisan test --without-tty` | ✅ **914 tests, 3,670 assertions**, exit 0; 410,361 ms |
| `./vendor/bin/pint --test` | ✅ Limpio |
| `npm run build` | ✅ Vite y `build:filament` completados |
| `git diff --check` | ✅ Limpio |

El servidor temporal se detuvo al terminar. La base `inmo_test` fue restablecida con `migrate:fresh --seed` después del escenario aislado de modo B, para no dejar datos de auditoría persistentes.

### 2.2 Coherencia documental verificada

La corrección `16051dc` cerró M-B-1:

- `docs/epicas/epica-12-1-mejora-ux-hero.md:137` ahora describe el modo A con `<img aria-hidden="true" alt="">` y remite a §0.0/§9.3.
- `docs/epicas/epica-12-1-lotes-implementacion.md:94` usa `hero_fallback` completo y `hero_variants` no editable.
- `docs/epicas/epica-12-1-lotes-implementacion.md:126-142` formaliza el alcance, criterios y caducidad de la excepción TB-8.
- Las menciones a `background-image` y `hero_fallback_slides` restantes están dentro de la tabla histórica de reconciliación, no como contrato activo contradictorio.

El barrido de los documentos de Épica 12.1 y RFC-071→077 no encontró otra afirmación activa que describa el modo A o el fallback de forma incompatible con el código.

### 2.3 HTTP/DOM real de las cinco rutas

Se levantó Laravel en `127.0.0.1:8001` contra `inmo_test` recién sembrada:

| Ruta | H1 observado | Heroes | Slides decorativas | `style` de aplicación | Fade legacy | Control |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| `/` | `Construimos patrimonio, diseñamos espacios.` | 1 | 4 | 0 | 0 | Sí |
| `/nosotros` | `Construimos patrimonio que trasciende generaciones.` | 1 | 1 | 0 | 0 | No |
| `/servicios` | `Del terreno a la entrega de llaves.` | 1 | 1 | 0 | 0 | No |
| `/inversionistas` | `De la oportunidad al desarrollo con fundamento.` | 1 | 1 | 0 | 0 | No |
| `/contacto` | `Estamos para asesorarte` | 1 | 0 | 0 | 0 | No |

Todas devolvieron HTTP 200, exactamente una `section[data-nh-hero]`, cero `hero-fade-in`/`nhHeroFade`, cero estilos inline de la aplicación y ningún `<style>` o `<script>` dentro del hero.

### 2.4 Responsive y modo B en base aislada

#### Home, modo A

Medición independiente con viewport explícito:

| Viewport | Alto hero | H1 | Logo | Slides | Keyframe | Control | Overflow |
| --- | ---: | ---: | ---: | ---: | --- | --- | --- |
| `390×844` | 870.38 px | 40 px | 128 px | 4 | `nh-hero-fade-4` | Sí | No |
| `1074×900` | 868.63 px | Conforme a variante desktop | — | 4 | `nh-hero-fade-4` | Sí | No |

En ambos viewports hubo cero estilos inline de la aplicación.

#### `nosotros`, modo B informativo

Se creó temporalmente en `inmo_test` una slide con `decorative:false`, `alt` válido y media promovida; no se tocó la base de desarrollo. La verificación HTTP/DOM mostró:

| Viewport | Modo | Imágenes | `alt` | `aria-hidden` | Animadas | Control | Alto hero | Overflow |
| --- | --- | ---: | --- | --- | ---: | --- | ---: | --- |
| `390×844` | B | 1 | Texto no vacío | Ausente | 0 | No | 196.03 px | No |
| `1074×900` | B | 1 | Texto no vacío | Ausente | 0 | No | 216.92 px | No |

El modo B no emitió capa `data-nh-hero-slides`, no rotó contenido, no mostró control de pausa y no dejó estilos inline. La base fue restablecida después de esta medición.

### 2.5 Interacciones y CSS/JS

El clic real sobre `[data-nh-hero-toggle]` produjo:

| Estado | `aria-pressed` | Texto | `animation-play-state` |
| --- | --- | --- | --- |
| Inicial | `false` | `Pausar` | `running` |
| Después del clic | `true` | `Reanudar` | `paused` |
| Segundo clic | `false` | `Pausar` | `running` |

El CSSOM del bundle parseado contiene `@media (prefers-reduced-motion: reduce)`, anula la animación y deja visible la primera slide. El guard PHPUnit y la rama `matchMedia()` también pasaron la suite focal. La pausa por hover está documentada en el artefacto QA y corresponde al mismo mecanismo `animation-play-state`; la superficie CUA usada en esta corrida no expuso un estado `:hover` reproducible, por lo que se deja como recomendación de automatización, no como bloqueante del contrato aprobado.

La consola del navegador no registró errores.

## 3. Hallazgos críticos

No se encontraron hallazgos críticos.

### C-B-1 — Segundo renderer legacy del hero

**Estado:** **RESUELTO.**

Las cinco rutas pasan por `resources/views/frontend/sections/hero.blade.php`. El fallback no agrega un segundo hero y el DOM real confirma un único hero por ruta.

## 4. Hallazgos medios

No quedan hallazgos medios abiertos.

### M-B-1 — Desviación documental sobre fallback y modo A

**Estado:** **RESUELTO en `16051dc`.**

La fila normativa de §2 fue corregida para coincidir con `hero_fallback`, `hero_variants` y `<img aria-hidden="true" alt="">`. El barrido documental no encontró una contradicción activa restante.

### M-B-2 — Cobertura insuficiente de la matriz y CTA

**Estado:** **RESUELTO.**

La suite focal pasó y ahora cubre TB-4 para cinco rutas y cinco estados, TB-5 con órdenes duplicados y reenumeración Livewire, TB-11 para ambos consumidores y la regresión de `payload: null`.

### M-B-3 — TB-8/TB-13 sin criterio contractual claro

**Estado:** **RESUELTO.**

TB-8 quedó explícitamente exceptuado en §3.4 con alcance limitado, tres criterios equivalentes y fecha de caducidad. TB-13 y el modo B quedaron documentados y fueron verificados en `inmo_test` con viewports `390 px` y desktop.

## 5. Hallazgos menores

### Mn-B-1 — Archivo local ajeno al lote

`docs/letras canciones hubiera.docx` ya no está en `HEAD`, pero aparece como archivo no trackeado en el árbol local. También existen cambios locales en `.atl/skill-registry.md` y `public/css/filament/admin/theme.css`.

**Estado:** no bloqueante y fuera del commit auditado. Debe permanecer fuera del commit del lote.

### Mn-B-2 — Warnings de tooling

El build informó que `caniuse-lite` está desactualizado y que `tailwindcss@3.4.19` se instala vía `npx` para `build:filament`. No afecta este gate; queda como mantenimiento separado.

## 6. Regresiones detectadas

No se observaron regresiones:

- 914/914 pruebas verdes sobre PostgreSQL real.
- Migración limpia y seed completos.
- Las cinco rutas públicas conservan sus H1 y contenido principal.
- `/contacto` conserva el formulario.
- El editor, upload, CTA, publicación y renderer siguen verdes.
- No se tocaron migraciones ni modelos de User, Property, Project, Zone, Media o ServiceType.

## 7. Riesgos de seguridad

| Riesgo | Estado |
| --- | --- |
| Segundo renderer con CSS/JS inline | ✅ Resuelto y verificado por DOM |
| `style="background-image"` dinámico | ✅ Resuelto con `<img>` y clases fijas |
| Upload inseguro de imágenes | ✅ Focales verdes para MIME, SVG, peso, dimensiones y alt |
| CTA con target inválido | ✅ Validación server-side y pruebas verdes |
| Draft expuesto públicamente | ✅ Renderer público consume snapshot publicado |
| Reduced-motion | ✅ Excepción limitada y contractual; regla CSSOM y guard verificados |

## 8. Riesgos de mantenimiento

1. La excepción TB-8 debe migrarse a prueba runtime si el proyecto adopta un runner JavaScript.
2. El `hero_fallback` y `hero_variants` deben mantenerse como configuración no editable por usuarios.
3. La matriz parametrizada debe ampliarse si se agregan nuevas rutas canónicas.
4. Los cambios locales y el `.docx` no deben entrar al commit del lote.

## 9. Tests faltantes

No hay tests faltantes obligatorios para este gate.

Recomendados para una iteración posterior:

- Runner JavaScript para ejecutar realmente `prefers-reduced-motion`.
- Prueba automática del hover/focus pause.
- Check automatizado de coherencia entre tablas normativas y contratos de implementación.

## 10. Correcciones obligatorias

Ninguna.

## 11. Correcciones recomendadas

- Adoptar un runner JavaScript cuando el proyecto lo justifique y retirar la excepción TB-8.
- Mantener la evidencia de QA aislada sobre `inmo_test` para futuras regresiones responsive.
- Limpiar el árbol local antes de preparar el commit del lote.
- Resolver los warnings de Browserslist/Tailwind en una tarea separada.

## 12. Checklist final antes de avanzar

- [x] Un solo renderer del hero en las cinco rutas.
- [x] Fallback por `pageKey` con texto, CTA, logo y slides.
- [x] TB-4: cinco estados parametrizados en las cinco rutas.
- [x] TB-5: orden determinista y reenumeración Livewire.
- [x] TB-8: excepción formal contractual con criterios equivalentes verificados.
- [x] TB-11: guía CTA reactiva en settings y hero para los cinco tipos.
- [x] TB-13: QA independiente a 390 px y desktop.
- [x] Modo B verificado visualmente en base aislada.
- [x] Regresión específica para `payload: null`.
- [x] Sin `<style>`, `<script>`, `style="..."` ni `animation-delay` en el hero público.
- [x] Suite PostgreSQL real: 914/914.
- [x] Composer, migración, Pint, build y `git diff --check` correctos.
- [x] Preview detenido y `inmo_test` restablecida con seed.
- [ ] Archivos locales ajenos al lote separados del commit.

## 13. Decisión explícita del gate

> **GATE LOTE B: APROBADO**

La implementación y el contrato del Lote B son coherentes, la suite completa está verde y las verificaciones HTTP/DOM/responsive del modo A y B pasaron en una base PostgreSQL aislada. El Lote C queda habilitado.

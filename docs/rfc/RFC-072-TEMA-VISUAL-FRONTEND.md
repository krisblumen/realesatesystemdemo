# RFC-072 Tema Visual Configurable del Frontend

> **⚠️ Enmienda normativa (P3 + correcciones posteriores a P3R, 2026-07-20).** Fuente única: **§16** de la épica; donde difiera, **prevalece §16**. Overrides: schema `theme` único; tokens semánticos runtime para primary/accent, `on_*`, background/text, radius y fuentes; `radius ∈ {soft,medium,rounded}` con expansión server-side exacta a `--nh-radius-md/lg/xl`; migración obligatoria de consumidores Blade brand-critical; shades decorativos fijos explícitamente fuera de alcance; **Poppins retirada**; validación al guardar y normalización defensiva al render.

## Objetivo

Permitir que el usuario `owner` configure colores y tipografías básicas del frontend público sin tocar código, sin recompilar assets y sin romper accesibilidad visual.

Este RFC extiende la base creada por RFC-071. Su foco es el contrato de tema visual: qué tokens se pueden editar, cómo se validan y cómo se aplican en el frontend público.

## Épica

Épica 12 — Administrador de Contenidos del Frontend

## Responsable

Por asignar

## Estado

🟡 Correcciones documentales aplicadas; reauditoría independiente pendiente. **Implementación bloqueada** hasta gate `APROBADO`.

---

## Contexto verificado

El frontend público usa Tailwind v4:

- `package.json` incluye `tailwindcss` `^4.0.0` y `@tailwindcss/vite`.
- `resources/css/app.css` usa `@import "tailwindcss"` y bloque `@theme`.
- El tema actual define tokens de marca como colores `navy`, `orange`, neutros, radios, sombras, fuentes y easing.
- El tema de Filament admin se compila aparte con Tailwind v3 y no debe mezclarse con este RFC.

Por eso, la personalización del frontend público no debe editar dinámicamente `resources/css/app.css` ni disparar `npm run build`. Debe aplicar variables CSS controladas en runtime.

---

## Alcance

### Incluye

- Campos de tema visual editables por `owner`.
- Presets iniciales equivalentes al diseño actual de New Hauz.
- Validación de colores en formato seguro.
- Validación básica de contraste WCAG AA para combinaciones críticas.
- Allowlist de tipografías permitidas.
- Render del tema mediante CSS variables en el layout público.
- Fallbacks si no hay tema configurado.
- Tests de autorización, validación y render.

### No incluye

- Cambiar el tema de Filament admin.
- Permitir CSS libre.
- Permitir JavaScript, `<style>` arbitrario o HTML editable.
- Subir fuentes personalizadas en v1.
- Descargar fuentes externas arbitrarias.
- Editor visual drag-and-drop.
- Modo oscuro configurable.
- Recompilar Tailwind al guardar cambios.

---

## Actor autorizado

Solo `owner` puede editar el tema visual.

| Rol | Acceso esperado |
| --- | --- |
| `owner` | ✅ Puede ver y editar tema visual. |
| `admin` | ❌ 403 / sin navegación. |
| `agente` | ❌ 403 / sin navegación. |
| `arquitectura` | ❌ 403 / sin navegación. |
| `proyectos` | ❌ 403 / sin navegación. |

---

## Modelo propuesto

El tema vive en `frontend_settings.theme` como JSON validado. No se crea `FrontendThemeSetting` en v1.

### Tokens editables

**Colores**

- `primary` y `on_primary` — fondo principal y texto accesible sobre él.
- `accent` y `on_accent` — fondo de CTA/acento y texto accesible sobre él.
- `background` y `text` — fondo y texto base del sitio.

**Tipografías**

- `heading_font` — fuente para títulos.
- `body_font` — fuente para texto general.

**Forma visual**

- `radius` — enum almacenado `soft | medium | rounded`; nunca se emite una variable singular.

---

## Presets permitidos

### Tipografías

Las fuentes deben venir de una allowlist controlada, no de entrada libre.

Propuesta inicial:

- Encabezados y cuerpo: `Montserrat` o `Inter`, las únicas familias presentes en el build.

La implementación debe usar fuentes ya disponibles o cargadas de forma controlada por el proyecto. No se aceptan URLs arbitrarias de Google Fonts u otros proveedores desde el CMS.

### Radios

El valor almacenado se expande en el servidor antes de construir el `<style>` del layout. Esta tabla es normativa y no admite cálculo CSS ni valores libres:

| `radius` almacenado | `--nh-radius-md` | `--nh-radius-lg` | `--nh-radius-xl` | Intención |
| --- | --- | --- | --- | --- |
| `soft` | `8px` | `12px` | `16px` | Geometría compacta. |
| `medium` | `12px` | `16px` | `24px` | Equivale a los radios actuales y es el fallback. |
| `rounded` | `16px` | `24px` | `32px` | Geometría más orgánica sin llegar a píldora. |

Cada preset produce las tres variables CSS anteriores, no clases Tailwind dinámicas ni una variable `--nh-radius` singular.

---

## Reglas de validación

- Colores requeridos deben ser HEX estricto `#RRGGBB`.
- No se aceptan nombres CSS libres (`red`, `transparent`, `inherit`, etc.).
- No se aceptan funciones CSS (`var()`, `url()`, `color-mix()`, `rgb()`, etc.) desde el CMS.
- `heading_font` y `body_font` deben existir en la allowlist.
- `radius` debe existir en la allowlist.
- Contraste mínimo recomendado:
  - `text` sobre `background`: 4.5:1.
  - `on_primary` sobre `primary`: 4.5:1 para texto normal.
  - `on_accent` sobre `accent`: 4.5:1 para texto normal.
  - Estados focus/contorno: mínimo 3:1 contra fondo inmediato.

Si una combinación falla contraste, el CMS debe rechazar el guardado con mensaje claro.

---

## Integración con Tailwind v4

El build actual de Tailwind v4 queda intacto. Los tokens configurables se aplican mediante variables CSS renderizadas en el layout público.

El layout emite valores normalizados en `--nh-primary`, `--nh-on-primary`, `--nh-accent`, `--nh-on-accent`, `--nh-bg`, `--nh-text`, `--nh-font-heading` y `--nh-font-body`; además expande `radius` exactamente según la tabla anterior a `--nh-radius-md`, `--nh-radius-lg` y `--nh-radius-xl`. `resources/css/app.css` los conecta mediante `@theme inline` a utilities semánticas: `bg-brand-primary`, `text-on-brand-primary`, `bg-brand-accent`, `text-on-brand-accent`, `bg-site-background`, `text-site-text`, `font-brand-heading`, `font-brand-body` y `rounded-brand-{md,lg,xl}`.

La implementación debe migrar a esas utilities los roles de marca en `button`, header/nav/drawer/footer, shells de página y tarjetas públicas principales. Un CTA configurable no puede conservar `text-white`; una superficie principal configurable no puede depender de `bg-navy-900`.

**Límite v1:** shades decorativos (`navy-50/700/900`, `orange-50/100/600`), gradientes, sombras, bordes neutros y colores de estado/WhatsApp pueden permanecer fijos cuando no representen un rol configurable. No se recalcula una escala cromática completa y no se promete un re-skin global sin migrar consumidores.

> Regla: no generar clases Tailwind dinámicas como `bg-[{{ $color }}]` en Blade. Es frágil, difícil de auditar y puede romper el purgado/build.

---

## Fallbacks iniciales

Si no existe configuración, el tema debe conservar el look actual del frontend:

- Azul principal equivalente a `navy` actual.
- Naranja/acento equivalente a `orange` actual.
- Fondo claro actual.
- Tipografías actuales: `Inter` y `Montserrat`.
- Radios y sombras equivalentes al diseño actual.

El sitio nunca debe renderizar sin colores o fuentes válidas.

---

## Interfaz en Filament

La UI debe integrarse con el área de frontend creada por RFC-071.

Secciones sugeridas:

1. Colores de marca.
2. Colores de texto y fondos.
3. Tipografías.
4. Estilo de bordes.
5. Vista previa básica.

La vista previa puede ser simple en este RFC: una tarjeta con título, párrafo, botón primario y botón secundario usando los tokens actuales.

---

## Seguridad

Este RFC tiene una superficie pequeña pero delicada: CSS editable.

Reglas obligatorias:

- Nunca guardar CSS libre.
- Nunca renderizar valores sin validación.
- Nunca permitir `url()`, `var()`, `expression()`, `calc()` ni funciones CSS desde campos editables.
- Escapar toda salida no numérica/textual.
- Usar allowlists para fuentes y radios.
- Mantener defaults si un valor guardado es inválido por datos legacy o importación manual.

---

## Accesibilidad

El tema visual debe cumplir WCAG AA en combinaciones principales. Esto no garantiza que toda página sea accesible, pero evita publicar combinaciones evidentemente ilegibles.

Validaciones mínimas:

- Texto normal: contraste 4.5:1.
- Texto grande: contraste 3:1 solo si la implementación distingue tamaño; si no, aplicar 4.5:1 por seguridad.
- Foco visible: 3:1.
- No depender solo de color para estados críticos.

---

## Archivos esperados

```text
app/
  Services/
    Frontend/
      FrontendThemeService.php
      ColorContrast.php                         (helper de contraste, si aplica)
  Filament/
    Resources/FrontendSettingResource.php       (sección nueva de tema)

resources/
  css/
    app.css                                     (`@theme inline` y utilities semánticas runtime)
  views/
    components/layouts/public.blade.php         (inyecta variables validadas)
    components/button.blade.php                 (CTA semántico)
    components/badge.blade.php                  (roles de marca semánticos)
    components/property-card.blade.php          (roles de marca semánticos)
    welcome.blade.php                           (shell y CTAs brand-critical)
    site/*.blade.php                            (shells y CTAs brand-critical)
    leads/create.blade.php                      (shell y formulario)

tests/
  Unit/Frontend/
    ColorContrastTest.php
  Feature/Frontend/
    FrontendThemeAccessTest.php
    FrontendThemeValidationTest.php
    FrontendThemeRenderTest.php
    FrontendThemeRuntimeTest.php                (variables + presets exactos + consumers semánticos)
```

---

## Reglas técnicas

- No modificar el build de Filament admin.
- No crear `tailwind.config.js` para el frontend público; el proyecto usa Tailwind v4 con `@theme`.
- No ejecutar build al guardar configuración.
- No guardar clases Tailwind generadas por usuario.
- La salida pública debe venir de valores normalizados por servicio.
- Cachear el tema es válido; al guardar se invalida únicamente con el bump global post-commit de RFC-076.

---

## Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| Contraste insuficiente | Sitio ilegible o no accesible. | Validación WCAG AA. |
| CSS injection | Riesgo visual/seguridad. | HEX + allowlists, sin CSS libre. |
| Romper Tailwind v4 | Build inestable. | Variables runtime, no clases dinámicas. |
| Drift entre tokens CSS y vistas | Algunas secciones no reflejan tema. | Migración obligatoria de roles brand-critical + test de consumers semánticos. |
| Fuentes externas arbitrarias | Performance/CSP/privacidad. | Allowlist controlada. |

---

## Definition of Done

- Owner puede editar tokens visuales permitidos.
- Otros roles no pueden acceder ni guardar cambios.
- El frontend público aplica primary/accent, ambos `on_*`, background/text, radios y fuentes sin ejecutar build.
- Cada `radius` almacenado (`soft|medium|rounded`) emite exactamente los tres valores `--nh-radius-md/lg/xl` de la tabla normativa; no existe variable singular.
- Componentes brand-critical usan utilities semánticas; shades decorativos restantes están identificados como fuera de alcance.
- Las combinaciones críticas de contraste inválido se rechazan.
- No existe campo de CSS libre.
- Si no hay configuración, el frontend conserva el tema actual.
- Tests cubren autorización, validación, contraste, expansión exacta de cada preset, variables emitidas y mappings/runtime consumers semánticos.
- `php artisan test` verde sobre PostgreSQL real.
- Pint limpio.
- `npm run build` verde.

---

## Dependencias

- RFC-071 — Perfil público y configuración base del frontend.
- Tailwind v4 público (`resources/css/app.css`).
- Épica 12 documento general: `docs/epicas/epica-12-administrador-contenidos-frontend.md`.

---

## Próximo RFC

RFC-073 — Navegación, footer y CTAs globales: control owner-only de links públicos permitidos, orden, footer, redes y llamadas a la acción.

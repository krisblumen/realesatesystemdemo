# RFC-073 Navegación, Footer y CTAs Globales del Frontend

> **⚠️ Enmienda normativa (P3 + correcciones posteriores a P3R, 2026-07-20).** Fuente única: **§16** de la épica; donde difiera, **prevalece §16**. Overrides: CTA como **value object tipado** `{label, type, target}` con resolver central (`type ∈ {route,url,whatsapp,tel,mailto}`); footer tipado con links `{label,type,target,enabled}`; `footer()` expone `enabled` y el renderer omite links deshabilitados sin fallback; **RFC-073 es la única autoridad** de navegación, footer y CTAs. Fallback del CTA header = "Agenda una cita".
>
> **Schema de navegación unificado (cierra C-1 de la reauditoría P5, 2026-07-21):** el schema persistido es **el de este RFC** — `{key, label, enabled, sort_order, open_in_new_tab}`. La épica eliminó su variante competidora `{route_name, label, enabled}`; `route_name` ya no existe en el diseño. `url`/`active_pattern` se **derivan de `key`** y no se persisten; `sort_order` es la única fuente de orden; `open_in_new_tab` debe ser `false` en v1 (no hay links externos en navegación) y un `true` persistido se normaliza al render. La regla de no-vaciado se implementa **bloqueando el guardado** cuando todos los links quedarían deshabilitados, no con fallback silencioso.

## Objetivo

Permitir que el usuario `owner` configure la navegación pública visible, el footer y las llamadas a la acción globales del sitio sin tocar código, manteniendo rutas controladas, accesibilidad y consistencia con el frontend actual.

Este RFC extiende RFC-071 y RFC-072. Su foco es controlar qué links públicos se muestran, cómo se ordenan y qué CTAs globales aparecen en header, footer y bloques compartidos.

## Épica

Épica 12 — Administrador de Contenidos del Frontend

## Responsable

Por asignar

## Estado

🟡 Correcciones documentales aplicadas; reauditoría independiente pendiente. **Implementación bloqueada** hasta gate `APROBADO`.

---

## Contexto verificado

El layout público actual tiene navegación y footer hardcodeados en:

- `resources/views/components/layouts/public.blade.php`.

Actualmente se definen manualmente:

- Links principales: Inicio, Nosotros, Servicios, Proyectos, Inmobiliaria, Inversionistas, Contacto.
- Menú móvil equivalente.
- CTA de header: `Agenda una cita`.
- Footer con descripción fija.
- Links de footer, varios apuntando a `#`.
- Teléfono, correo, dirección y WhatsApp hardcodeados.

RFC-071 centraliza identidad/contacto. RFC-073 centraliza navegación, footer y CTAs.

---

## Alcance

### Incluye

- Configuración owner-only de navegación pública permitida.
- Activar/desactivar links públicos existentes.
- Editar etiquetas visibles de navegación.
- Ordenar links del header y menú móvil.
- Configurar CTA principal del header.
- Configurar links del footer.
- Configurar CTAs globales reutilizables.
- Validación de rutas internas permitidas y URLs externas seguras.
- Render con fallbacks actuales si no hay configuración.
- Tests de autorización, render y validación.

### No incluye

- Crear páginas nuevas dinámicamente.
- Crear rutas Laravel desde el CMS.
- Page builder.
- Mega menú.
- Menús por rol para el frontend público.
- Personalización de navegación del panel Filament.
- Contenido completo de páginas institucionales — ver RFC-075.
- Servicios ofrecidos — ver RFC-074.

---

## Actor autorizado

Solo `owner` puede editar navegación, footer y CTAs globales.

| Rol | Acceso esperado |
| --- | --- |
| `owner` | ✅ Puede editar navegación/footer/CTAs. |
| `admin` | ❌ 403 / sin navegación. |
| `agente` | ❌ 403 / sin navegación. |
| `arquitectura` | ❌ 403 / sin navegación. |
| `proyectos` | ❌ 403 / sin navegación. |

---

## Modelo propuesto

La implementación usa JSON tipado en `FrontendSetting`: `navigation`, `footer`, `primary_cta` y `secondary_cta`. No se crean tablas de links en v1.

---

## Navegación pública

### Links permitidos

El CMS no debe aceptar rutas arbitrarias en v1. Debe partir de una allowlist de destinos públicos existentes.

Allowlist inicial sugerida:

| Key | Ruta | Label default |
| --- | --- | --- |
| `home` | `/` | Inicio |
| `nosotros` | `/nosotros` | Nosotros |
| `servicios` | `/servicios` | Servicios |
| `proyectos` | `/proyectos` | Proyectos |
| `inmuebles` | `/inmuebles` | Inmobiliaria |
| `inversionistas` | `/inversionistas` | Inversionistas |
| `contacto` | `/contacto` | Contacto |

Cada item configurable debe tener:

- `key`.
- `label`.
- `enabled`.
- `sort_order`.
- `open_in_new_tab` — solo aplica a URLs externas permitidas si se habilitan.

### Reglas

- La URL real se resuelve desde la `key`, no desde texto libre.
- El owner puede cambiar el label, no la ruta interna base.
- El orden debe ser estable.
- El menú móvil debe usar la misma fuente que desktop.
- Si todos los links están deshabilitados por error, el sistema debe mantener al menos `Inicio` y `Contacto` como fallback o bloquear el guardado.

---

## CTAs globales

Los CTAs globales son llamadas a la acción reutilizables en header, footer y bloques compartidos.

Schema autoritativo en `frontend_settings`:

- `primary_cta` — JSON `{label,type,target}`.
- `secondary_cta` — JSON `{label,type,target}`.

El mismo value object se reutiliza en links de footer y secciones `cta`; no existen columnas `header_cta_*`/`footer_cta_*` independientes.

### Targets permitidos

| Tipo | Validación |
| --- | --- |
| `route` | Nombre de ruta en allowlist pública. |
| `url` | Debe ser HTTPS. |
| `whatsapp` | Usa `whatsapp_phone` de RFC-071 o número validado. |
| `mailto` | Debe ser email válido. |
| `tel` | Debe ser teléfono válido. |

No se permiten `javascript:`, `data:`, `file:`, URLs relativas no controladas ni protocolos personalizados.

---

## Footer

El footer debe consumir:

- Identidad/contacto desde RFC-071.
- Tema visual desde RFC-072.
- Links configurados en este RFC.
- Redes sociales desde RFC-071 o sección equivalente.

### Secciones sugeridas

- Descripción de marca.
- Links principales.
- Servicios destacados — si RFC-074 ya existe, se alimenta de servicios activos; antes de eso, usar fallback o no mostrar.
- Contacto.
- Redes sociales.
- Texto legal.

### Reglas

- Cada link de footer tiene `{label,type,target,enabled}`; `enabled` es booleano requerido.
- Los links de footer deben validarse con el mismo `CtaResolver` de los CTAs.
- No debe haber links `#` como destino final configurable.
- Links externos deben agregar `rel="noopener noreferrer"` si abren en nueva pestaña.
- El footer debe mantener estructura semántica y accesible.
- `enabled=false` conserva la configuración para edición, pero `footer()` lo expone como deshabilitado y Blade lo omite. Un link apagado nunca activa el fallback; el fallback solo aplica cuando el footer no fue inicializado.

---

## Integración con el frontend público

La navegación y footer deben exponerse desde un servicio central, por ejemplo:

- `FrontendNavigationService`.
- O una extensión de `FrontendSettingsService` si el alcance se mantiene pequeño.

Responsabilidades:

- Resolver navegación activa.
- Aplicar orden.
- Aplicar fallbacks.
- Resolver URLs seguras.
- Entregar atributos para links externos.
- Compartir la misma estructura para desktop y móvil.
- Exponer `footer()` como `{columns:[{title,links:[{label,url,enabled}]}],legal_text,social:[...]}` y omitir en render todo link con `enabled=false`.

---

## Fallbacks iniciales

Si no hay configuración, el frontend debe renderizar la navegación actual:

- Inicio.
- Nosotros.
- Servicios.
- Proyectos.
- Inmobiliaria.
- Inversionistas.
- Contacto.

CTA default:

- Label: `Agenda una cita`.
- Target: `/contacto` o WhatsApp actual, según el comportamiento vigente que se confirme al implementar.

Footer default:

- Mantener links y datos actuales, reemplazando destinos `#` por rutas reales o escondiéndolos hasta que tengan destino válido.

---

## Interfaz en Filament

La UI debe integrarse en el área owner-only de frontend.

Secciones sugeridas:

1. Navegación principal.
2. CTA del header.
3. Links de footer.
4. CTA del footer.
5. Vista previa básica.

La edición debe ser simple:

- Toggle activo/inactivo.
- Campo label.
- Orden.
- Selector de destino permitido.

No debe pedir al owner escribir rutas manualmente para páginas internas.

---

## Seguridad

Reglas obligatorias:

- No aceptar protocolos inseguros: `javascript:`, `data:`, `file:`, `vbscript:`.
- No aceptar HTML en labels.
- Escapar labels en Blade.
- Validar URLs externas como HTTPS.
- Para links externos con nueva pestaña, usar `rel="noopener noreferrer"`.
- No permitir rutas internas fuera de allowlist en v1.
- No mostrar destinos inválidos: usar fallback o bloquear guardado.

---

## Accesibilidad

- Header debe usar `<nav>` con `aria-label` claro.
- Menú móvil debe ser navegable por teclado.
- Labels deben ser textos claros; evitar labels vacíos.
- CTA debe tener texto descriptivo.
- El estado activo de navegación no debe depender solo de color.
- Focus visible debe conservarse con el tema de RFC-072.

---

## Archivos esperados

```text
app/
  Services/
    Frontend/
      FrontendNavigationService.php
  Support/
    Frontend/
      CtaResolver.php
  Filament/
    Resources/FrontendSettingResource.php       (sección navegación/footer/CTAs)

resources/
  views/
    components/layouts/public.blade.php         (consume navegación/footer centralizados)

tests/
  Feature/Frontend/
    FrontendNavigationAccessTest.php
    FrontendNavigationValidationTest.php
    FrontendNavigationRenderTest.php
    FrontendFooterRenderTest.php                (link disabled no se renderiza ni revive por fallback)
```

## Reglas técnicas

- No crear rutas dinámicas desde BD.
- No duplicar navegación desktop/mobile.
- No usar links `#` como configuración válida.
- No guardar HTML editable.
- No mezclar navegación pública con navegación Filament.
- Cachear navegación es válido; guardar invalida únicamente mediante el bump global post-commit de RFC-076.
- Si RFC-074 está implementado, links/secciones de servicios deben respetar servicios activos.

---

## Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| Rutas arbitrarias | Links rotos o phishing desde el frontend. | Allowlist y HTTPS externo. |
| Menú vacío | Sitio difícil de navegar. | Fallback o validación mínima. |
| Drift desktop/mobile | Experiencia inconsistente. | Una sola fuente para ambos. |
| Links `#` persistentes | Mala UX y SEO débil. | Bloquear destinos vacíos. |
| Mezcla con servicios | Mostrar servicios deshabilitados. | Integración posterior con RFC-074. |

---

## Definition of Done

- Owner puede configurar links visibles, labels y orden de navegación pública.
- Owner puede configurar CTAs globales permitidos.
- Owner puede configurar links de footer válidos.
- Links de footer conservan `enabled` en schema/DTO y los deshabilitados no se renderizan.
- Otros roles no pueden acceder ni guardar cambios.
- Header desktop y móvil consumen la misma navegación centralizada.
- Footer consume links centralizados y datos de RFC-071.
- No se aceptan URLs inseguras ni HTML en labels.
- Si no hay configuración, el frontend conserva navegación usable.
- Tests cubren autorización, validación, render y omisión de footer links deshabilitados.
- `php artisan test` verde sobre PostgreSQL real.
- Pint limpio.
- `npm run build` verde.

---

## Dependencias

- RFC-071 — Perfil público y configuración base del frontend.
- RFC-072 — Tema visual configurable del frontend.
- Layout público actual: `resources/views/components/layouts/public.blade.php`.
- Épica 12 documento general: `docs/epicas/epica-12-administrador-contenidos-frontend.md`.

---

## Próximo RFC

RFC-074 — Servicios ofrecidos y disponibilidad: unificar contenido público de servicios con `ServiceType`, estado activo/inactivo y captura de leads.

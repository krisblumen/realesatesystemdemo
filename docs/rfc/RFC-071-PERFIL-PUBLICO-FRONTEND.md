# RFC-071 Perfil Público y Configuración Base del Frontend

> **⚠️ Enmienda normativa (P3 + correcciones posteriores a P3R, 2026-07-20).** La fuente normativa única es **§16 de `docs/epicas/epica-12-administrador-contenidos-frontend.md`**. Donde este RFC difiera, **prevalece §16**. Overrides: singleton con `CHECK(singleton_key='default') + UNIQUE`; acceso `owner` + permiso `frontend.manage`; permiso por migración idempotente; media editorial draft en disco privado, mientras logos/favicon/OG inmediatos de este RFC van a público; CTAs anidados `{label,type,target}` delegados a RFC-073, sin columnas planas; se elimina `is_active`.

## Objetivo

Crear la base del administrador de contenidos del frontend para que el usuario `owner` pueda configurar la identidad pública de la inmobiliaria sin tocar código: datos generales, logos, favicon, imagen social, contacto, SEO por defecto y CTAs globales.

Este RFC es el cimiento de la Épica 12. No administra todavía servicios, páginas completas ni tema visual avanzado; solo establece el perfil público editable y el contrato de render seguro para el frontend.

## Épica

Épica 12 — Administrador de Contenidos del Frontend

## Responsable

Por asignar

## Estado

🟡 Correcciones documentales aplicadas; reauditoría independiente pendiente. **Implementación bloqueada** hasta gate `APROBADO`.

---

## Contexto verificado

El frontend público actual tiene datos de marca y contacto hardcodeados en varias vistas:

- `resources/views/components/layouts/public.blade.php`: logo, favicon, metadatos, navegación, footer, teléfono, correo, dirección y WhatsApp.
- `resources/views/welcome.blade.php`: hero, logo de hero, CTAs y WhatsApp.
- `resources/views/site/contacto.blade.php`: textos y datos de contacto.
- `resources/views/site/nosotros.blade.php`, `servicios.blade.php`, `inversionistas.blade.php`: contenido institucional fijo.
- `resources/css/app.css`: tokens visuales base del frontend.

La consecuencia es clara: cualquier cambio de identidad pública requiere deploy. Este RFC elimina ese bloqueo para la configuración base.

---

## Alcance

### Incluye

- Nuevo módulo owner-only en Filament para configurar el perfil público.
- Modelo/migración para configuración singleton del sitio.
- Media Library para:
  - Logotipo sobre fondo claro.
  - Logotipo sobre fondo oscuro.
  - Favicon.
  - Imagen Open Graph por defecto.
- Campos editables de identidad y contacto.
- Campos SEO por defecto.
- CTAs globales mínimos.
- Servicio/helper para exponer configuración al frontend.
- Fallbacks equivalentes al contenido actual de New Hauz.
- Pruebas de autorización owner-only y render con fallbacks.

### No incluye

- Administrar colores y tipografías avanzadas — ver RFC-072.
- Administrar navegación pública completa — ver RFC-073.
- Administrar servicios ofrecidos — ver RFC-074.
- Administrar contenido completo de páginas institucionales — ver RFC-075.
- Page builder visual.
- HTML libre editable.
- Multitenancy completo.

---

## Actor autorizado

Solo el rol `owner` puede acceder al módulo.

| Rol | Acceso esperado |
| --- | --- |
| `owner` | ✅ Puede ver y editar configuración del frontend. |
| `admin` | ❌ 403 / sin navegación. |
| `agente` | ❌ 403 / sin navegación. |
| `arquitectura` | ❌ 403 / sin navegación. |
| `proyectos` | ❌ 403 / sin navegación. |

> Importante: ocultar el menú no alcanza. La restricción debe existir también en policy/gate y en pruebas HTTP.

---

## Modelo propuesto

Nombre sugerido: `FrontendSetting`.

La configuración será singleton: una sola fila activa para el sitio público actual.

### Campos

**Identidad**

- `site_name` — nombre comercial público.
- `tagline` — frase corta de posicionamiento.
- `short_description` — descripción breve para footer/metadatos.
- `legal_name` — nombre legal opcional.

**Contacto**

- `public_phone`.
- `whatsapp_phone`.
- `public_email`.
- `public_address`.
- `business_hours`.

**SEO por defecto**

- `default_meta_title`.
- `default_meta_description`.
- `default_og_title`.
- `default_og_description`.

**Marca — referencias por UUID explícito (obligatorias)**

- `logo_light_media_id`, `logo_dark_media_id`, `favicon_media_id`, `og_image_media_id` — `uuid` **nullable**, FK → `media.uuid`.
- Son la **única fuente de verdad** de qué archivo está vigente. En v1 **ninguna ruta borra media** (§16.4 de la épica), así que las colecciones acumulan versiones y **`getFirstMedia()` no es determinista**: el render resuelve el UUID guardado y **nunca** usa `getFirstMedia()`.
- Validación al guardar: el UUID debe existir, pertenecer a `FrontendSetting` y a su colección; si no, se rechaza. `null` o inválido → fallback de marca.
- Las colecciones `logo-light`, `logo-dark`, `favicon`, `default-og-image` son **solo almacenamiento** y **no** declaran `singleFile()` ni `onlyKeepLatest()` (dispararían `clearMediaCollectionExcept()`, `FileAdder.php:645-651`).

**CTAs globales**

- `primary_cta` — JSON tipado `{label,type,target}` según RFC-073.
- `secondary_cta` — JSON tipado `{label,type,target}` según RFC-073.

No existen columnas CTA planas legacy. RFC-073 es la autoridad única de schema, allowlist y resolución de destinos.

**Redes sociales**

- `social_links` JSON controlado, con allowlist de proveedores.

**Control**

- timestamps. El singleton no usa `is_active`; la ausencia de configuración cae a los fallbacks normativos.

### Media collections

En `FrontendSetting`:

- `logo-light` — logo para fondos claros.
- `logo-dark` — logo para fondos oscuros.
- `favicon`.
- `default-og-image`.

---

## Reglas de validación

- `site_name` requerido, máximo 120 caracteres.
- `tagline` opcional, máximo 180 caracteres.
- `short_description` opcional, máximo 300 caracteres.
- `public_email` debe ser email válido.
- `public_phone` y `whatsapp_phone` deben aceptar formato telefónico internacional razonable.
- `whatsapp_phone` debe normalizarse para generar links `wa.me` sin espacios ni símbolos inválidos.
- URLs de CTAs deben ser rutas internas permitidas o URLs HTTPS.
- Redes sociales solo pueden usar proveedores permitidos: Facebook, Instagram, LinkedIn, YouTube, TikTok, X/Twitter.
- Imágenes deben validar MIME y tamaño máximo.
- No se acepta HTML en campos de texto.

---

## Integración con el frontend público

Crear un servicio de lectura, por ejemplo `FrontendSettingsService`, que entregue una estructura estable al layout público.

Responsabilidades:

- Cargar la configuración activa.
- Aplicar fallbacks si no existe configuración.
- Resolver URLs de media o assets por defecto.
- Normalizar WhatsApp a link público.
- Entregar metadatos por defecto.
- Exponer datos a vistas públicas sin acoplar cada Blade al modelo Eloquent.

### Fallbacks iniciales

Los fallbacks deben conservar el estado actual del sitio:

- Nombre: `New Hauz`.
- Teléfono/WhatsApp actual del layout.
- Correo actual del layout.
- Dirección actual del layout.
- Logos actuales en `public/images/brand`.
- Imagen OG actual si existe.
- CTAs actuales equivalentes.

Esto evita que el sitio quede incompleto si la tabla está vacía después del deploy.

---

## Interfaz en Filament

Crear una página o recurso bajo un grupo claro del CMS, por ejemplo:

- Grupo: `Frontend` o `Sitio web`.
- Label: `Perfil público`.
- Acceso: solo `owner`.

La UI debe estar organizada en secciones:

1. Identidad.
2. Logotipos e imágenes.
3. Contacto.
4. SEO por defecto.
5. CTAs globales.
6. Redes sociales.

Debe ser una experiencia simple: el owner edita la configuración del sitio, no administra múltiples registros.

---

## Permisos y seguridad

Crear permiso sugerido: `frontend.manage`.

Regla:

- El permiso se asigna únicamente al rol `owner` en `PermissionSeeder`.
- La policy o `canAccess()` debe impedir acceso a cualquier usuario que no sea owner.
- Si se usa permiso nombrado, la semilla debe evitar asignarlo accidentalmente a `admin`.

Pruebas requeridas:

- Owner ve el menú y accede.
- Admin no ve el menú y recibe 403 al acceso directo.
- Agente no ve el menú y recibe 403.
- Roles `arquitectura` y `proyectos` reciben 403.

---

## Archivos esperados

```text
app/
  Filament/
    Resources/FrontendSettingResource.php        (o Page equivalente)
  Models/
    FrontendSetting.php
  Policies/
    FrontendSettingPolicy.php
  Services/
    Frontend/FrontendSettingsService.php

database/
  migrations/
    xxxx_create_frontend_settings_table.php
  seeders/
    PermissionSeeder.php                         (aditivo)
    FrontendSettingSeeder.php                    (opcional para defaults)

resources/
  views/
    components/layouts/public.blade.php          (consume configuración)

tests/
  Feature/Frontend/
    FrontendSettingAccessTest.php
    FrontendSettingsRenderTest.php
```

---

## Reglas técnicas

- La migración es aditiva.
- No se modifican migraciones existentes de `users`, `properties`, `projects`, `media`, `zones` ni `service_types`.
- El frontend no debe consultar directamente `FrontendSetting` en múltiples vistas; debe consumir un servicio/composer/shared data.
- No debe haber HTML editable por el owner.
- El render público debe funcionar aunque no exista fila de configuración.
- Las imágenes configurables deben pasar por Media Library.
- El guardado invalida únicamente mediante el bump global post-commit de RFC-076; no limpia keys dirigidas.

---

## Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| Acceso accidental de admin | Un usuario no autorizado cambia marca pública. | Policy/gate + pruebas 403 reales. |
| Frontend roto sin configuración | Sitio incompleto tras deploy. | Fallbacks obligatorios. |
| Datos duplicados en varias vistas | Drift de contacto/SEO/CTAs. | Servicio centralizado. |
| Link WhatsApp inválido | CTA público falla. | Normalización y validación. |
| Imágenes pesadas | Degradación de performance. | MIME/tamaño máximo y recomendaciones de dimensiones. |

---

## Definition of Done

- Existe configuración singleton editable por `owner`.
- Ningún otro rol puede acceder por navegación ni URL directa.
- El layout público consume nombre, logos, favicon, contacto, SEO defaults y CTAs desde la configuración o fallback.
- Media Library gestiona logos, favicon e imagen OG.
- Si no hay configuración en BD, el frontend mantiene el contenido actual sin error.
- Pruebas feature cubren autorización y render público.
- `php artisan test` verde sobre PostgreSQL real.
- Pint limpio.

---

## Dependencias

- Épica 2 — roles y permisos con Spatie.
- RFC-007 — Media Library.
- Frontend público actual Blade/Tailwind.
- Épica 12 documento general: `docs/epicas/epica-12-administrador-contenidos-frontend.md`.

---

## Próximo RFC

RFC-072 — Tema visual configurable: colores, tipografías permitidas, variables CSS, contraste y render seguro sin rebuild de Tailwind.

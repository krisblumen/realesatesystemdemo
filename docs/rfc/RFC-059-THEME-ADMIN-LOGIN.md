# RFC-059 THEME ADMIN + LOGIN

## Proyecto
NEW HAUZ

## RFC
RFC-059

## Estado
✅ Cerrado — listo para merge

## Rama base
`develop`

## Rama de trabajo
`features/ux-ui-admin-login-screen` *(ya creada)*

## Responsable Principal
Edgar

## Participantes

### Arquitectura
- Edgar
- Kristian

### QA
- Sebastián

## Fecha
2026-06-17

---

# Seguimiento del Pipeline Multimodelo

| Etapa | Agente | Estado | Fecha |
|---|---|---|---|
| 1. Generación del RFC | Claude (Arquitecto) | ✅ Completado | 2026-06-17 |
| 2. Auditoría de diseño | Gemini CLI | ✅ Completado | 2026-06-17 |
| 3. Aplicación de correcciones | Claude (Agente Impl.) | ✅ Completado | 2026-06-17 |
| 4. Implementación | Codex / Edgar | ✅ Completado | 2026-06-17 |
| 5. Auditoría de implementación | Gemini CLI | ✅ Completado | 2026-06-17 |
| 6. Cierre técnico | Claude (Arquitecto) | ✅ Completado | 2026-06-17 |

---

# Objetivo

Aplicar el design system de New Hauz al panel administrativo de Filament:
paleta de marca, tipografía, tarjeta glass en el login y todos los estados
de la pantalla de autenticación.

Esta RFC establece:

- Theme CSS del panel `/admin`
- Pantalla de login con glassmorphism estilo iOS 18
- Colores semánticos y tipografía del design system
- Branding (logo, favicon) en el provider
- Pie de página debajo de la tarjeta de login
- Todos los estados del login (default, foco, error, suspendido, cargando)
- Modo claro y modo oscuro

---

# Contexto y Dependencias

## Consume de Épica 1 (RFC-001 → RFC-010)

- Laravel 13.x + PHP 8.3 (RFC-001)
- **Filament v3 — PRERREQUISITO DE INSTALACIÓN:** La auditoría de diseño confirmó que
  `filament/filament` no está presente en `composer.json` de la rama `develop`.
  El Lote A de este RFC incluye el comando de instalación obligatorio antes de
  cualquier otro paso.
- Vite 8 como bundler (RFC-001)
- Tailwind CSS v4 configurado (RFC-001)

## Consume de Épica 2 (RFC-011 → RFC-014)

- `EnsureUserIsActive` middleware (RFC-014): redirige al login con el error
  `"Tu cuenta ha sido suspendida. Contacta al administrador."` — este RFC
  hace ese mensaje visible con el estilo correcto.
- **Coordinación obligatoria:** Este RFC modifica `AdminPanelProvider.php`, el mismo
  archivo que gestiona la Épica 2. Ver R-2 para la estrategia de merge.

## Activos de diseño disponibles

Los archivos de implementación ya están listos en `docs/files-login-design/`:

| Archivo | Destino final |
|---|---|
| `theme.css` | `resources/css/filament/admin/theme.css` |
| `footer.blade.php` | `resources/views/filament/auth/footer.blade.php` |
| `AdminPanelProvider.snippet.php` | Se integra en `app/Providers/Filament/AdminPanelProvider.php` |
| `logo-on-light.png` | `public/images/brand/logo-on-light.png` |
| `logo-on-dark.png` | `public/images/brand/logo-on-dark.png` |
| `favicon.png` | `public/images/brand/favicon.png` |

---

# Alcance

## Lo que entrega este RFC

- `AdminPanelProvider.php` creado y configurado con colores, fuente, logos y renderHook
- `resources/css/filament/admin/theme.css` con el design system completo
- `resources/views/filament/auth/footer.blade.php` inyectado vía renderHook
- `public/images/brand/` con los tres assets de marca
- Login estilizado: tarjeta glass, fondo degradado, foco naranja, pie de firma
- Modo oscuro operativo
- `APP_LOCALE=es` configurado en `.env.example`
- Página Login personalizada (fase 2, Lote D): título y subtítulo en español

## Lo que NO entrega este RFC

- Instalación de `filament/filament` (se realiza en Lote A como prerrequisito)
- Cambios en modelos, policies ni migraciones
- Temas para otros paneles (solo `/admin`)
- Librerías externas de mapas o pago
- Personalización del panel interior más allá de la pantalla de login
- Recuperación de contraseña personalizada (Épica 8)
- Self-hosting de fuentes Inter/Montserrat (diferido a fase de producción)
- Favicon `.ico` multi-resolución (diferido, PNG es suficiente para el alcance actual)

---

# Tokens de Diseño

## Paleta

| Token | Valor | Uso |
|---|---|---|
| `primary` | `#1E293B` (hover `#0F172A`) | Botones, estados activos del panel admin |
| `gray` | Escala `Color::Slate` | Neutros del panel |
| `danger` | `#C0392B` | Errores, alertas destructivas |
| `success` | `#1F8A4C` | Confirmaciones |
| `warning` | `#B8860B` | Advertencias |
| `info` | `#233488` | Informativos |
| `brand-orange` | `#F6A300` | Acento puntual (foco, hover) |
| `brand-blue` | `#091A5B` | Acento puntual (texto GESIF, degradado) |

> **Nota sobre desviación de marca (Auditoría M-1):** La guía `GUIA-UX-UI-newhauz.md`
> define el "Azul Corporativo" (`#091A5B`) como color principal de marca pública.
> El panel administrativo usa deliberadamente `#1E293B` (Slate oscuro) para
> transmitir sobriedad y diferenciar visualmente el panel del frontend público.
> `brand-blue` está disponible como token puntual para acentos.
> Esta decisión queda cerrada y no debe revertirse en futuros módulos del panel.

## Tipografía

| Rol | Familia | Pesos |
|---|---|---|
| Títulos (`h1`–`h4`, headings Filament) | Montserrat | 500, 600, 700, 800 |
| Cuerpo / formularios | Inter (vía `->font('Inter')`) | 400, 500, 600 |

## Geometría

| Elemento | Valor |
|---|---|
| Radio botones / inputs | 12 px |
| Radio tarjeta login | 16 px *(confirmado — consistente con "Cards" de la Guía UX/UI)* |
| Altura botón | 52 px |
| Altura input | 52–56 px |
| Altura logo | 2.5 rem |

## Tarjeta Glass — Claro

```
background:                rgba(255,255,255,.66)
backdrop-filter:           blur(20px) saturate(180%)
-webkit-backdrop-filter:   blur(20px) saturate(180%)    ← requerido para Safari/iOS
border:                    1px solid rgba(255,255,255,.75)
box-shadow:                0 18px 50px rgba(15,23,42,.16)
                           + inset 0 1px 0 rgba(255,255,255,.7)
border-radius:             16px
```

## Tarjeta Glass — Oscuro

```
background:       rgba(28,30,36,.55)
border:           1px solid rgba(255,255,255,.12)
box-shadow:       0 18px 50px rgba(0,0,0,.45)
                  + inset 0 1px 0 rgba(255,255,255,.08)
```

## Fondo Login

| Modo | Valor |
|---|---|
| Claro | `radial-gradient(navy .09)` + `linear #F7F7F7 → #EAEAEA` |
| Oscuro | `radial-gradient(orange .12)` + `linear #111111 → #2D2D2D` |

## Input: estado foco

```
border-color: #F6A300
box-shadow:   0 0 0 3px rgba(246,163,0,.22)
```
*(único naranja visible en reposo — no se usa en otros elementos)*

## Pie de página

```
"NewHauz Admin System © 2026 By GESIF"
"GESIF" en color brand-blue (#091A5B) / claro azulado en oscuro (#7C9BE8)
```

---

# Alcance Técnico

## Árbol de archivos

```
Crear:
  app/Providers/Filament/
    AdminPanelProvider.php              ← NUEVO (requiere filament:install --panels)

  resources/css/filament/admin/
    theme.css                           ← NUEVO (make:filament-theme → reemplazar contenido)

  resources/views/filament/auth/
    footer.blade.php                    ← NUEVO (copiar de docs/files-login-design/)

  public/images/brand/
    logo-on-light.png                   ← NUEVO (copiar de docs/files-login-design/)
    logo-on-dark.png                    ← NUEVO (copiar de docs/files-login-design/)
    favicon.png                         ← NUEVO (copiar de docs/files-login-design/)

  [Lote D — opcional]
  app/Filament/Pages/Auth/
    Login.php                           ← NUEVO (extiende Filament\Pages\Auth\Login)
  resources/views/filament/pages/auth/
    login.blade.php                     ← NUEVO (si se requiere vista personalizada)

Modificar:
  app/Providers/Filament/AdminPanelProvider.php   ← integrar snippet (colors, font, logos, viteTheme, renderHook)
  bootstrap/providers.php                          ← registrar AdminPanelProvider
  .env.example                                     ← añadir APP_LOCALE=es
```

## Archivos que NO se tocan

```
app/Providers/AppServiceProvider.php        ← Épica 1/2 — solo lectura
app/Models/User.php                         ← Épica 2 — no modificar
app/Http/Middleware/EnsureUserIsActive.php  ← Épica 2 — consumido, no modificado
database/migrations/*                       ← sin cambios
```

---

# Plan de Implementación por Lotes

## Lote A — Prerrequisitos, sincronización y assets

**Objetivo:** Filament instalado, rama sincronizada con develop (Épica 2 incluida),
provider existente y assets de marca en su lugar.

### Pre-vuelo obligatorio (antes de cualquier paso)

```bash
# 1. Sincronizar con develop para obtener los cambios de la Épica 2
git pull origin develop
git rebase develop
# Si hay conflictos en AdminPanelProvider.php, resolverlos preservando
# los middlewares y recursos de la Épica 2 (EnsureUserIsActive, UserResource).
```

### Pasos

1. **Instalar Filament** (prerrequisito confirmado por auditoría — no está en composer.json):
   ```bash
   composer require filament/filament:"^3.2"
   ```

2. Crear `AdminPanelProvider.php`:
   ```bash
   php artisan filament:install --panels
   # Elige "admin" como ID de panel cuando lo pregunte
   ```

3. Registrar en `bootstrap/providers.php`:
   ```php
   App\Providers\Filament\AdminPanelProvider::class,
   ```

4. Verificar que `AdminPanelProvider.php` ya contiene los elementos de la Épica 2.
   Si proviene de rebase, confirmar que estos bloques están presentes:
   ```php
   // Middleware de la Épica 2 — NO eliminar:
   ->authMiddleware([
       \App\Http\Middleware\EnsureUserIsActive::class,
   ])
   // Resource de usuarios de la Épica 2 — NO eliminar:
   ->resources([
       \App\Filament\Resources\UserResource::class,
   ])
   ```

5. Copiar assets de marca:
   ```bash
   mkdir -p public/images/brand
   cp docs/files-login-design/logo-on-light.png  public/images/brand/
   cp docs/files-login-design/logo-on-dark.png   public/images/brand/
   cp docs/files-login-design/favicon.png        public/images/brand/
   ```

6. Verificar que `/admin/login` carga (panel base sin tema).

### DoD del Lote A

- `composer show filament/filament` muestra la versión instalada.
- `php artisan route:list | grep admin` muestra rutas del panel.
- Los tres archivos PNG existen en `public/images/brand/`.
- Sin errores en `php artisan about`.
- `AdminPanelProvider.php` contiene el middleware `EnsureUserIsActive` de la Épica 2.

---

## Lote B — Theme CSS y configuración del provider

**Objetivo:** El panel usa la paleta, tipografía y logos del design system.

### Pasos

1. Generar el theme:
   ```bash
   php artisan make:filament-theme
   # Selecciona el panel "admin"
   ```
   Esto crea `resources/css/filament/admin/theme.css` y `tailwind.config.js`.

2. Reemplazar el contenido de `theme.css` con el archivo de `docs/files-login-design/theme.css`.
   > Conservar la línea `@import '/vendor/filament/...'` y `@config '...'`
   > que haya generado `make:filament-theme` si difieren del archivo fuente.

3. Integrar el snippet en `AdminPanelProvider.php` (solo los métodos dentro de `panel()`):
   ```php
   // Imports al tope del archivo:
   use Filament\Support\Colors\Color;
   use Filament\View\PanelsRenderHook;

   // Dentro de panel() — encadenar junto a los elementos existentes de Épica 2:
   ->brandLogo(asset('images/brand/logo-on-light.png'))
   ->darkModeBrandLogo(asset('images/brand/logo-on-dark.png'))
   ->brandLogoHeight('2.5rem')
   ->favicon(asset('images/brand/favicon.png'))
   ->font('Inter')
   ->colors([
       'primary'      => Color::hex('#1E293B'),
       'gray'         => Color::Slate,
       'danger'       => Color::hex('#C0392B'),
       'success'      => Color::hex('#1F8A4C'),
       'warning'      => Color::hex('#B8860B'),
       'info'         => Color::hex('#233488'),
       'brand-orange' => Color::hex('#F6A300'),
       'brand-blue'   => Color::hex('#091A5B'),
   ])
   ->viteTheme('resources/css/filament/admin/theme.css')
   ```

4. Compilar:
   ```bash
   npm install
   npm run build
   php artisan filament:optimize-clear
   ```

### DoD del Lote B

- `/admin/login` muestra tipografía Inter/Montserrat correcta.
- Logo de marca visible en el header del panel.
- Favicon de New Hauz en la pestaña del navegador.
- Botones primarios en slate `#1E293B`.
- Sin errores en consola del navegador.

---

## Lote C — Login screen y footer

**Objetivo:** Tarjeta glass, fondo degradado, foco naranja y pie "By GESIF" operativos.

### Pasos

1. Crear la vista del footer:
   ```bash
   mkdir -p resources/views/filament/auth
   cp docs/files-login-design/footer.blade.php resources/views/filament/auth/footer.blade.php
   ```

2. Registrar el renderHook en `AdminPanelProvider.php`:
   ```php
   ->renderHook(
       PanelsRenderHook::SIMPLE_PAGE_END,
       fn (): string => view('filament.auth.footer')->render(),
   )
   ```

3. Añadir `APP_LOCALE=es` en `.env.example`.

4. Limpiar y rebuild:
   ```bash
   npm run build
   php artisan filament:optimize-clear
   php artisan icons:clear
   ```

### DoD del Lote C

- Fondo del login: degradado gris claro en modo light, degradado oscuro en modo dark.
- Tarjeta glass: blur + borde translúcido en ambos modos.
- Logo flota sobre la tarjeta (margin-bottom negativo).
- Foco en cualquier input → borde naranja + anillo `rgba(246,163,0,.22)`.
- Pie de página: "NewHauz Admin System © 2026 **By GESIF**" visible debajo de la tarjeta.
- Mensaje "Tu cuenta ha sido suspendida..." aparece visualmente integrado en el estilo del login.

---

## Lote D — Página Login personalizada (opcional, fase 2)

**Objetivo:** Título "Iniciar sesión" y subtítulo "Accede a tu panel de administración" fijos en español.

### Pasos

1. Crear la clase:
   ```php
   // app/Filament/Pages/Auth/Login.php
   namespace App\Filament\Pages\Auth;

   use Filament\Pages\Auth\Login as BaseLogin;

   class Login extends BaseLogin
   {
       public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
       {
           return 'Iniciar sesión';
       }

       public function getHeading(): string | \Illuminate\Contracts\Support\Htmlable
       {
           return 'Iniciar sesión';
       }

       public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
       {
           return 'Accede a tu panel de administración';
       }
   }
   ```

2. Registrar en `AdminPanelProvider.php`:
   ```php
   use App\Filament\Pages\Auth\Login;
   // ...
   ->login(Login::class)
   ```

3. Verificar que los textos aparecen correctamente.

### DoD del Lote D

- Título de la página: "Iniciar sesión".
- Subtítulo: "Accede a tu panel de administración".
- El resto del comportamiento (validación, redirección) no cambia.

---

# Criterios de Aceptación / Casos QA

## QA-026 — Fondo claro del login

Entrar a `/admin/login` sin modo oscuro activo.
**Esperado:** fondo con degradado `#F7F7F7 → #EAEAEA` con toque navy sutil.

## QA-027 — Fondo oscuro del login

Activar modo oscuro del sistema operativo.
**Esperado:** fondo `#111111 → #2D2D2D` con toque naranja sutil.

## QA-028 — Tarjeta glass modo claro

Inspeccionar `.fi-simple-main` *(verificar nombre en runtime)*.
**Esperado:** `backdrop-filter: blur(20px)`, `-webkit-backdrop-filter: blur(20px)`,
borde `rgba(255,255,255,.75)`, sombra definida.

## QA-029 — Tarjeta glass modo oscuro

Mismo elemento en dark mode.
**Esperado:** `background rgba(28,30,36,.55)`, borde `rgba(255,255,255,.12)`.

## QA-030 — Logo sobre la tarjeta

**Esperado:** logo aparece flotando encima de la tarjeta con `margin-bottom` negativo.
Logo claro en light mode, logo oscuro en dark mode.

## QA-031 — Foco naranja en input

Hacer clic en el campo Email.
**Esperado:** borde `#F6A300` + anillo naranja translúcido. Sin otro naranja visible en la pantalla.

## QA-032 — Error de credenciales

Ingresar email/contraseña incorrectos.
**Esperado:** mensaje de error visible, estilizado con el token `danger` (`#C0392B`).

## QA-033 — Cuenta suspendida

Iniciar sesión con un usuario suspendido (middleware `EnsureUserIsActive`).
**Esperado:** mensaje "Tu cuenta ha sido suspendida. Contacta al administrador." visible
en la tarjeta, correctamente estilizado.

## QA-034 — Botón en estado cargando

Enviar el formulario (con credenciales válidas o inválidas).
**Esperado:** botón muestra estado loading (spinner o texto "Cargando…") mientras Livewire procesa.

## QA-035 — Pie de página

**Esperado:** texto "NewHauz Admin System © 2026 By **GESIF**" debajo de la tarjeta,
"GESIF" en navy en modo claro y azul claro en modo oscuro.

## QA-036 — Regresión Épica 2

**Esperado:** login con credenciales válidas funciona, redirección al dashboard correcta,
CRUD de usuarios operativo sin regresiones visuales.

## QA-037 — Contraste WCAG del mensaje de error en modo oscuro *(nuevo — Auditoría Sg-7)*

Provocar un error de credenciales con modo oscuro activo.
**Esperado:** texto del mensaje de error (`danger` `#C0392B`) es legible sobre la tarjeta
glass oscura `rgba(28,30,36,.55)`. Relación de contraste ≥ 4.5:1 (WCAG 2.1 AA).
Si el contraste es insuficiente, ajustar el tono de `danger` en modo oscuro.

---

# Riesgos Técnicos y Mitigaciones

## R-1 — `AdminPanelProvider.php` no existe

**Riesgo:** Filament instalado (RFC-004) pero el archivo del provider no está en el repo.
Sin este archivo, el panel no carga y los cambios del theme no tienen efecto.
**Mitigación:** Lote A incluye `composer require filament/filament:"^3.2"` y
`php artisan filament:install --panels` como primeros pasos obligatorios.
Registrar el provider en `bootstrap/providers.php`.

## R-2 — Conflicto en `AdminPanelProvider.php` con Épica 2

**Riesgo:** La Épica 2 (`feature/epica-2-usuarios-y-seguridad`) también modifica
`AdminPanelProvider.php` (registra `EnsureUserIsActive`, `UserResource`).
Si ambas ramas tocan el mismo archivo, el merge generará conflictos.

**Mitigación:**
- **Paso previo obligatorio (auditado):** Antes de cualquier cambio,
  ejecutar `git pull origin develop && git rebase develop`.
- Este RFC trabaja en `features/ux-ui-admin-login-screen`.
- **Estrategia:** Mergear Épica 2 en `develop` primero; luego hacer rebase de esta
  rama. Resolver conflictos en `AdminPanelProvider.php` a mano, preservando AMBOS
  bloques: los middlewares/recursos de la Épica 2 Y los tokens de diseño de este RFC.
- Los cambios de este RFC son ADITIVOS (nuevos métodos encadenados, nuevos imports).
  No eliminan ni sobreescriben lógica de la Épica 2.

## R-3 — Clases CSS internas de Filament frágiles

**Riesgo:** Las clases `.fi-simple-main`, `.fi-simple-layout`, `.fi-logo`, `.fi-input-wrp`
son internas de Filament v3 y pueden cambiar en un patch minor sin aviso.
**Mitigación:** Confirmadas como válidas para Filament v3.x en la auditoría. Están
comentadas en `theme.css` como "verificar en runtime". Si un selector deja de aplicar,
inspeccionar el elemento en el navegador y actualizar el selector.
No usar `!important` de forma masiva; preferir selectores más específicos.

## R-4 — `backdrop-filter` en Safari / iOS

**Riesgo:** `backdrop-filter` requiere el prefijo `-webkit-backdrop-filter` en Safari.
**Mitigación:** Ambos prefijos están incluidos en el `theme.css` proporcionado
(verificado en auditoría, checklist ítem 3). Confirmar explícitamente en el
pre-commit que el archivo generado los contiene.

## R-5 — Google Fonts en entornos sin internet

**Riesgo:** El `theme.css` importa Inter y Montserrat desde Google Fonts (CDN). En
entornos offline o con red restringida, las fuentes no cargan.
**Mitigación:** CDN es aceptable para desarrollo. En producción, evaluar servir las
fuentes vía `laravel-vite-plugin/fonts` (ya disponible en el stack). Diferido a Épica 8.

## R-6 — Solape del logo en pantallas pequeñas *(nuevo — Auditoría Riesgo 1)*

**Riesgo:** El `margin-bottom: -1.25rem` para el efecto de logo flotante depende
del padding del contenedor `.fi-simple-layout`. En pantallas pequeñas (iPhone SE,
375 px de ancho), el logo podría empujar la tarjeta fuera del viewport.
**Mitigación:** Verificar en QA-030 con viewport de 375 px (iPhone SE). Si hay
desbordamiento, ajustar el margen negativo o añadir `max-height: 2rem` al logo
en media queries para pantallas pequeñas.

---

# Notas de Implementación

## Selector `.fi-simple-main`

El theme apunta a `.fi-simple-main` para la tarjeta de login.
Si al inspeccionar en runtime el elemento tiene una clase diferente
(p. ej. `.fi-simple-page` en alguna versión), actualizar el selector en `theme.css`.

## Solape del logo

El solape del logo sobre la tarjeta se logra con `margin-bottom: -1.25rem` en
`.fi-simple-layout .fi-logo`. Es ajustable pixel a pixel según la versión de Filament
o el tamaño del logo. Ver R-6 para consideraciones en pantallas pequeñas.

## `->viteTheme()` requiere que `make:filament-theme` se haya ejecutado

La línea `->viteTheme('resources/css/filament/admin/theme.css')` en el provider
sólo funciona si existe el `tailwind.config.js` que genera el comando. No añadir
la línea antes de ejecutar el comando.

## `primary` vs `brand-blue`

El token `primary` del panel admin (`#1E293B`) es diferente del "Azul Corporativo"
(`#091A5B`) de la marca pública. Esta decisión está documentada en la sección de
Tokens de Diseño y es definitiva para el panel admin. Ver Nota de Auditoría M-1.

---

# Hallazgos no Aplicados de la Auditoría

| # | Hallazgo | Tipo | Razón de no aplicación |
|---|---|---|---|
| Op-1 | Self-hosting de fuentes Inter/Montserrat | Opcional | Diferido a fase de producción (Épica 8). El CDN de Google Fonts es aceptable en desarrollo. Documentado en R-5. |
| Op-2 | Favicon `.ico` multi-resolución | Opcional | El PNG es suficiente para los navegadores modernos en el alcance actual. Queda como mejora futura sin impacto en la funcionalidad. |

---

# Registro de Cambios desde la Auditoría

| # | Hallazgo auditado | Tipo | Cambio aplicado | Sección(es) afectada(s) |
|---|---|---|---|---|
| C-1 | `filament/filament` no en `composer.json` | Crítico | Agregado `composer require filament/filament:"^3.2"` como paso 1 de Lote A. Actualizado "Consume de Épica 1" con nota de prerrequisito. | Contexto y Dependencias, Lote A |
| C-2 | `AdminPanelProvider.php` inexistente | Crítico | Ya contemplado; fortalecido el DoD del Lote A con verificación de presencia del middleware de Épica 2. | Lote A |
| M-1 | Desviación `primary` vs `brand-blue` | Medio | Agregada nota explicativa en la tabla de Paleta justificando la decisión de diseño y cerrándola explícitamente. | Tokens de Diseño |
| M-2 | Conflicto merge Épica 2 | Medio | Agregado bloque "Pre-vuelo obligatorio" en Lote A con `git pull` y `git rebase`. Reforzado R-2 con instrucción explícita de preservar middleware/resources. | Lote A, R-2 |
| Ob-1 | `composer require filament/filament:"^3.2"` | Obligatoria | Incorporado como paso 1 del Lote A. | Lote A |
| Ob-2 | `git pull origin develop` antes de AdminPanelProvider | Obligatoria | Incorporado como "Pre-vuelo obligatorio" en Lote A. | Lote A |
| Ob-3 | `border-radius: 16px` para tarjeta | Obligatoria | Confirmado que ya estaba en la tabla de Geometría. Agregada nota "(confirmado)" en la celda. | Tokens de Diseño |
| Ck-2 | Middlewares Épica 2 en AdminPanelProvider | Checklist | Agregado paso 4 en Lote A para verificar y preservar `EnsureUserIsActive` y `UserResource` tras rebase. | Lote A |
| Ck-3 | `-webkit-backdrop-filter` en `theme.css` | Checklist | Agregado prefijo explícito en la especificación de Tarjeta Glass en Tokens de Diseño. Agregado checkbox en Pre-commit. | Tokens de Diseño, Checklist |
| Sg-7 | Contraste WCAG `danger` en modo oscuro | Seguridad | Agregado QA-037 con criterio de contraste ≥ 4.5:1 (WCAG 2.1 AA). | Criterios de Aceptación / QA |
| R-6 | Logo flotante en pantallas pequeñas | Riesgo | Agregado como nuevo riesgo R-6 con mitigación. Referenciado desde Notas de Implementación. | Riesgos, Notas |

---

# Checklist de Cierre Técnico

## Pre-commit (Edgar)

- [ ] `composer require filament/filament:"^3.2"` ejecutado → `composer show filament/filament` sin errores
- [ ] `git pull origin develop && git rebase develop` ejecutado antes de modificar `AdminPanelProvider.php`
- [ ] `php artisan filament:install --panels` ejecutado → `AdminPanelProvider.php` existe
- [ ] `bootstrap/providers.php` registra `AdminPanelProvider::class`
- [ ] `AdminPanelProvider.php` contiene middleware `EnsureUserIsActive` de Épica 2
- [ ] Los tres assets PNG están en `public/images/brand/`
- [ ] `theme.css` contiene tanto `backdrop-filter` como `-webkit-backdrop-filter`
- [ ] `npm run build` → sin errores de Vite
- [ ] `php artisan filament:optimize-clear` → sin errores
- [ ] `/admin/login` carga sin errores en consola
- [ ] Tarjeta glass visible en modo claro y oscuro
- [ ] Logo aparece sobre la tarjeta (verificar en viewport 375px — iPhone SE)
- [ ] Foco naranja en inputs
- [ ] Pie "By GESIF" visible

## Pre-merge (Sebastián — QA)

- [ ] QA-026 Fondo claro ✓
- [ ] QA-027 Fondo oscuro ✓
- [ ] QA-028 Tarjeta glass claro ✓ (incl. `-webkit-backdrop-filter`)
- [ ] QA-029 Tarjeta glass oscuro ✓
- [ ] QA-030 Logo flotando ✓ (verificar en iPhone SE 375px)
- [ ] QA-031 Foco naranja ✓
- [ ] QA-032 Error de credenciales ✓
- [ ] QA-033 Cuenta suspendida ✓
- [ ] QA-034 Botón cargando ✓
- [ ] QA-035 Pie de página ✓
- [ ] QA-036 Regresión Épica 2 ✓
- [ ] QA-037 Contraste WCAG danger modo oscuro ✓

## Post-merge (Kristian)

- [ ] Merge `features/ux-ui-admin-login-screen` → `develop`
  > Resolver conflicto de `AdminPanelProvider.php` con Épica 2 antes del merge.
- [ ] Crear tag: `v0.X.0-theme-admin-login`
- [ ] Actualizar estado de este RFC a `✅ IMPLEMENTADO`

---

# Estimación

Arquitectura: Edgar

Duración estimada:
0.5 Sprint

Complejidad:
Baja (los archivos de implementación ya están listos en `docs/files-login-design/`)

---

Estado:
✅ Cerrado — listo para merge (pendiente rebase con Épica 2)

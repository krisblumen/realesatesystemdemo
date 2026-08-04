# New Hauz — Theme Admin (Filament v3) · Instalación

Tema sobrio del panel `/admin`, portado del design system de Claude Design.
Tono: gris/blanco dominante, navy `#091A5B` y naranja `#F6A300` solo como acentos.

## Contenido del bundle
- `theme.css` → contenido para `resources/css/filament/admin/theme.css`
- `footer.blade.php` → `resources/views/filament/auth/footer.blade.php`
- `AdminPanelProvider.snippet.php` → métodos a integrar en tu panel
- `brand/` → `logo-on-light.png`, `logo-on-dark.png`, `favicon.png` (PNG transparentes)

## Pasos

### 1. Assets
Copia los 3 archivos de `brand/` a:
```
public/images/brand/
```

### 2. Genera el theme (si aún no existe)
```bash
php artisan make:filament-theme
```
Esto crea `resources/css/filament/admin/theme.css` + `tailwind.config.js` y te
imprime la línea `->viteTheme(...)` (ya incluida en el snippet del provider).

### 3. Reemplaza el theme.css
Pega el contenido de `theme.css` de este bundle en
`resources/css/filament/admin/theme.css`.
> Conserva las líneas `@import '/vendor/...'` y `@config '...'` que tu versión
> haya generado, si difieren de las del archivo.

### 4. Integra el AdminPanelProvider
Abre `app/Providers/Filament/AdminPanelProvider.php` y añade los imports y los
métodos del snippet dentro de `panel()`. NO reemplaces el archivo completo
(coordina con Edgar: este archivo lo toca también la Épica 2).

### 5. Crea el partial del pie
Coloca `footer.blade.php` en `resources/views/filament/auth/footer.blade.php`.

### 6. Textos en español
Para que el login muestre "Iniciar sesión", etc. en español, asegúrate de:
```
APP_LOCALE=es
```
en `.env`. (El título exacto "Iniciar sesión" y el subtítulo
"Accede a tu panel de administración" del mockup requieren una página Login
personalizada; opcional, se puede hacer después.)

### 7. Compila y limpia
```bash
npm install
npm run build
php artisan filament:optimize-clear
php artisan icons:clear
```

### 8. Verifica
Entra a `/admin/login` y compara contra el mockup en claro y oscuro.
Revisa: tarjeta glass, logo arriba, foco naranja en inputs, botón slate, pie "By GESIF".

## Notas
- Las clases `.fi-simple-main`, `.fi-simple-layout`, `.fi-logo`, `.fi-input-wrp`
  son internas de Filament y podrían variar en algún patch 3.x. Si algo no pinta,
  inspecciona el elemento y ajusta el selector (van comentados en el theme.css).
- El solape del logo sobre la tarjeta (`margin-bottom: -1.25rem`) es ajustable.
- `primary` está en slate `#1E293B`. Si lo quieres más claro, cámbialo a
  `Color::Slate`.

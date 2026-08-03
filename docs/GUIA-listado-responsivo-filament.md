# Guía — Listado responsivo en Filament (tabla en escritorio, tarjetas en móvil)

**Para qué sirve esta guía:** cuando un listado (tabla de un Resource de Filament)
se vea bien en escritorio pero mal en móvil, y quieras que en móvil se muestre
como **tarjetas** (como Inmuebles, Propietarios, Leads o Proyectos), esto te dice
exactamente qué tocar. Sin buscar por todo el proyecto y sin tocar PHP.

---

## TL;DR (3 pasos)

1. Averiguá la clase CSS del Resource: `.fi-resource-<plural-del-slug>` (ver
   [cómo obtenerla](#cómo-obtener-la-clase-del-resource)).
2. Agregá esa clase a **cada** selector `:is(...)` del bloque responsivo en
   `resources/css/filament/admin/theme.css` (son ~10 líneas; usá buscar-y-reemplazar
   sobre la lista de clases).
3. Rebuildeá el tema y commiteá fuente + compilado:
   ```bash
   npm run build:filament
   git add resources/css/filament/admin/theme.css public/css/filament/admin/theme.css
   ```

Listo. **No se toca el Resource PHP.**

---

## Lo que hay que entender primero (no te saltees esto)

Filament **NO** convierte tablas en tarjetas en móvil por defecto. Su comportamiento
nativo es una tabla `<table>` con **scroll horizontal**. Lo que en este proyecto hace
que ciertos listados se vean como tarjetas en móvil es una **regla CSS propia**, no
una función de Filament.

Esa regla vive en:

```
resources/css/filament/admin/theme.css   ← fuente (se edita acá)
public/css/filament/admin/theme.css       ← compilado (se genera, se commitea)
```

Dentro de un `@media (max-width: 1023px)` que:

- oculta el `thead` de la tabla,
- pone cada fila (`.fi-ta-row`) en `display:flex; flex-direction:column`,
- la estiliza como tarjeta (borde, radio, sombra),
- acomoda la celda de acciones a lo ancho.

**Clave:** la regla está *scopeada por Resource* con un selector `:is(...)`. Solo los
Resources listados ahí reciben el tratamiento. Si tu listado no se ve como tarjeta en
móvil, es porque **su clase no está en ese `:is(...)`**. Eso es todo.

---

## Cómo obtener la clase del Resource

Filament le pone a la página del listado una clase `fi-resource-<plural-kebab>`,
derivada del slug de la ruta. Ejemplos reales:

| Resource | Ruta | Clase CSS |
| --- | --- | --- |
| Inmuebles (`PropertyResource`) | `/admin/properties` | `.fi-resource-properties` |
| Propietarios (`PropertyOwnerResource`) | `/admin/property-owners` | `.fi-resource-property-owners` |
| Contratos (`ContratoIntermediacionResource`) | `/admin/contrato-intermediacions` | `.fi-resource-contrato-intermediacions` |

Si tenés dudas, abrí el listado en el navegador y en la consola:

```js
[...document.querySelector('[class*="fi-resource-"]').classList]
  .filter(c => c.startsWith('fi-resource-'))
// => ["fi-resource-list-records-page", "fi-resource-<lo-que-buscás>"]
```

La que te interesa es la que **no** es `fi-resource-list-records-page`.

---

## Paso a paso (detallado)

### 1. Editar el selector

En `resources/css/filament/admin/theme.css`, dentro del bloque
`@media (max-width: 1023px)` comentado como *"tabla en desktop, tarjetas en móvil"*,
cada regla empieza con el mismo selector:

```css
:is(.fi-resource-properties,.fi-resource-property-owners,.fi-resource-leads,.fi-resource-projects,.fi-resource-contrato-intermediacions) ...
```

Agregá tu clase al final de esa lista, en **todas** las apariciones (son ~10). La forma
segura es un reemplazo-todo sobre el string exacto de la lista de clases, así no se te
escapa ninguna regla.

También actualizá el comentario de arriba del bloque para que liste el Resource nuevo.

### 2. Rebuildear el tema

El tema de Filament usa **Tailwind 3** por separado del build principal (Tailwind v4).
El script está en `package.json`:

```bash
npm run build:filament
# npx tailwindcss@3 --input ...theme.css --output public/css/filament/admin/theme.css --minify
```

Esto regenera `public/css/filament/admin/theme.css`. **Sin este paso el cambio no se ve.**

### 3. Verificar en el navegador

Abrí el listado y comprobá:

- **Móvil (< 1024px):** cada registro es una tarjeta apilada; no hay scroll horizontal.
  Chequeo rápido en consola: `getComputedStyle(document.querySelector('table tbody tr')).display` debe dar `"flex"`.
- **Escritorio (≥ 1024px):** sigue siendo la tabla clásica con encabezados y orden.

### 4. Commitear

Van juntos la fuente y el compilado:

```bash
git add resources/css/filament/admin/theme.css public/css/filament/admin/theme.css
git commit -m "fix(<resource>): tarjetas en móvil reusando la regla responsiva del tema"
```

---

## Qué NO hacer

- ❌ **No** metas `Split` / `Stack` / `contentGrid` en el `table()` del Resource para
  esto. Rompe la tabla de escritorio (pierde los encabezados alineados) y NO es la
  convención del proyecto. El fix correcto es solo CSS.
- ❌ **No** edites únicamente `public/css/.../theme.css` a mano: es generado, se
  sobrescribe en el próximo build. Editá la **fuente** y rebuildeá.
- ❌ **No** olvides el rebuild ni el commit del compilado: sin eso, funciona en tu
  máquina pero no en el deploy.

---

## Deploy

El cambio es solo CSS del tema. En producción alcanza con `git pull` + `npm run build`
(que incluye `build:filament`). No requiere migraciones ni seeders.

---

## Resources que hoy tienen el comportamiento

`properties` (Inmuebles), `property-owners` (Propietarios), `leads`, `projects`
(Proyectos), `contrato-intermediacions` (Contratos), `zones` (Zonas), el grupo
**Lonas** (`lona-batches`, `lona-requests`, `lona-evidences`), el grupo
**Configuración** (`features`, `project-types`, `service-types`) y `users` (Usuarios).

> **Nota sobre Usuarios:** originalmente usaba `Split` + `Stack` + `->contentGrid()`,
> que mostraba tarjetas en TODOS los tamaños (también en escritorio). Se migró a
> columnas clásicas + esta regla CSS para que quede consistente con el resto (tabla
> en escritorio, tarjetas en móvil). Es el ejemplo vivo del [Qué NO hacer](#qué-no-hacer).

> Si agregás uno nuevo, sumalo también a esta lista al final de la guía.

# Lote 12.1-B — Evidencia de comportamiento y QA visual

**Fecha:** 2026-07-26 · **Corrección auditada:** `1653c8d` + correcciones posteriores
**Cierra:** TB-8 (reduced-motion), TB-13 (QA visual 390 px / desktop) y el comportamiento de pausa de §9.3

Se registran **mediciones**, no capturas: un número es reproducible y comparable entre corridas; una imagen no. Todo se obtuvo con el servidor de preview sobre la base de desarrollo, y el servidor se detuvo al terminar.

## Cómo reproducirlo

```bash
composer dev   # o el preview del proyecto
```

Luego, en la consola del navegador sobre `/` y `/contacto`, en cada viewport:

```js
const hero = document.querySelector('[data-nh-hero]');
const slide = hero.querySelector('.nh-hero-slide');
({
  viewport: window.innerWidth,
  heroAlto: Math.round(hero.getBoundingClientRect().height),
  h1: getComputedStyle(hero.querySelector('h1')).fontSize,
  logoAlto: Math.round([...hero.querySelectorAll('img')].find(i => i.src.includes('brand'))?.getBoundingClientRect().height ?? 0),
  slides: hero.querySelectorAll('.nh-hero-slide').length,
  keyframe: slide ? getComputedStyle(slide).animationName : null,
  controlPausa: !!hero.querySelector('[data-nh-hero-toggle]'),
  estiloInline: hero.querySelectorAll('[style]').length,
})
```

## TB-13 — Medido en los dos viewports

### `/` — variante `featured`, modo A (4 slides decorativas)

| Métrica | 390 px | Desktop (1074 px) | Contrato |
| --- | --- | --- | --- |
| Alto del hero | 870 px | 869 px | ≥ 92 vh (822 px en desktop) ✅ |
| `font-size` del H1 | 40 px | 64.44 px | `clamp(40px, 6vw, 68px)` ✅ |
| Alto del logo | 128 px | **192 px** | `h-32 sm:h-40 lg:h-48` — 192 px es exactamente el `h-48` del hero legacy ✅ |
| Slides renderizadas | 4 | 4 | Fallback por página de `home` ✅ |
| Keyframe aplicado | `nh-hero-fade-4` | `nh-hero-fade-4` | El ciclo depende de la CANTIDAD ✅ |
| Control de pausa | presente | presente | >1 slide ⇒ WCAG 2.2.2 ✅ |
| Elementos con `style` | **0** | **0** | Sin superficie inline (M-B-2) ✅ |

### `/contacto` — variante `compact`, sin imagen

| Métrica | Desktop | Contrato |
| --- | --- | --- |
| Alto del hero | 343 px | Compacto, como la página tenía ✅ |
| `font-size` del H1 | 47.26 px | `clamp(30px, 4.4vw, 48px)` ✅ |
| Fondo | `rgb(9, 26, 91)` | Superficie de marca sólida, no un translúcido sobre el fondo de página ✅ |
| Slides | 0 | Su fallback es la ausencia de imagen ✅ |
| Capa `aria-hidden` | ausente | Sin slides no hay capa que ocultar ✅ |
| Control de pausa | ausente | Nada se mueve, nada que pausar ✅ |
| `<h1>` en la página | **1** | Un solo H1 ✅ |
| Elementos con `style` | **0** | ✅ |

## TB-8 — `prefers-reduced-motion`, en el CSS **compilado**

Leído del CSSOM del navegador (no del archivo fuente), o sea: la regla viajó al bundle y el navegador la parseó.

```
@media (prefers-reduced-motion: reduce)
  .nh-hero-slide { opacity: 0; animation: … none !important; }
  .nh-hero-slide.nh-hero-delay-0 { opacity: 1; }
```

Es decir: sin movimiento, la animación se anula y **la primera slide queda visible y estática**.

**Excepción formalizada en el contrato**, no sólo acá: `docs/epicas/epica-12-1-lotes-implementacion.md` **§3.4** define su alcance exacto, los tres criterios de aceptación equivalentes y cuándo caduca. No se ejecuta la media query con la preferencia activada: el proyecto no tiene runner de JS (`package.json` sólo define `build` y `dev`) y añadir uno excede el alcance del lote. Queda cubierto por (a) esta lectura del CSSOM, (b) el guard de contrato sobre el source en `FrontendHeroContractMatrixTest`, y (c) el módulo, que oculta el control cuando `matchMedia('(prefers-reduced-motion: reduce)')` coincide. Si se adopta un runner de JS, este es el primer caso a migrar.

## Comportamiento de la pausa — verificado, no inferido

| Acción | `is-paused` | Etiqueta | `aria-pressed` | `animation-play-state` |
| --- | --- | --- | --- | --- |
| Estado inicial | no | «Pausar» | `false` | **running** |
| Clic en el control | sí | «Reanudar» | `true` | **paused** |
| Segundo clic | no | «Pausar» | `false` | running |
| `mouseenter` en el hero | — | — | — | **paused** |
| `mouseleave` | — | — | — | running |

La animación se detiene de verdad (`animation-play-state`), no sólo cambia una clase.

## Modo B (informativo) — medido sobre base AISLADA

La objeción anterior era justa: no alcanzaba con decir «lo cubre PHPUnit». Pero tampoco correspondía provocar el modo B mutando la base de desarrollo del cliente. Se resolvió levantando un servidor contra **`inmo_test`**, recién migrada y sembrada, publicando allí un hero con `decorative:false` y midiendo. Los datos de desarrollo quedaron **intactos**.

### Cómo reproducirlo

```bash
DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed
# publicar en 'nosotros' un hero con una slide decorative:false + alt, y promover su media
DB_DATABASE=inmo_test php artisan serve --env=testing --host=127.0.0.1 --port=8199
```

### Medición

| Métrica | Desktop (1074 px) | 390 px | Contrato §9.3 |
| --- | --- | --- | --- |
| Modo detectado | **B (informativo)** | **B** | Una slide con significado fuerza el modo B ✅ |
| Capa `aria-hidden` | **ausente** | ausente | En modo B nada se oculta: el alt debe anunciarse ✅ |
| Control de pausa | **ausente** | ausente | Sin autoplay no hay nada que pausar ✅ |
| Imágenes en el hero | **1** | 1 | Sólo la primera con significado; las demás no se renderizan ✅ |
| `alt` | «Fachada de la oficina de New Hauz en Querétaro» | ídem | Anunciable, no vacío ✅ |
| Slides animadas (`.nh-hero-slide`) | **0** | 0 | Sin rotación ✅ |
| `object-fit` | — | `cover` | Cubre sin deformar ✅ |
| La imagen cubre el ancho | — | sí | ✅ |
| `<h1>` en la página | 1 | **1** | Un solo H1 ✅ |
| Alto del hero | — | 278 px | Variante `standard` ✅ |
| `font-size` del H1 | — | 34 px | `clamp(34px, 5vw, 56px)` ✅ |
| Desborde horizontal | — | **no** | ✅ |
| Elementos con `style` | **0** | **0** | Sin superficie inline ✅ |

La diferencia observable respecto del modo A queda confirmada por medición, no por inferencia: donde el modo A tiene 4 slides animadas, una capa oculta y un control de pausa, el modo B tiene **una** imagen anunciable, ninguna animación y ningún control.

# Especificación: cms-pagina-proyectos

## Purpose

`/proyectos` pasa a ser la sexta página canónica del CMS (extensión de RFC-075):
`hero`→`hero`, `projects_list`→`featured_projects`, `final_cta`→`cta`. El
cutover NO debe cambiar el aspecto actual (§16.7) y cierra dos reglas propias
de esta página: qué proyectos lista y de qué fondo cuando nadie eligió uno.

## Requirements

### Requirement: Registro canónico

El sistema DEBE registrar `proyectos` en `config('frontend-sections.pages')`
con `hero→hero`, `projects_list→featured_projects`, `final_cta→cta`, y un
`section_labels.projects_list` humano — mismo patrón que las otras cinco
páginas.

#### Scenario: Único conjunto de claves aceptado

- GIVEN el registro después de este cambio
- WHEN se intenta guardar una sección `proyectos.hero_2` (clave no registrada)
- THEN se rechaza, igual que en cualquier otra página

### Requirement: Fallback y límites de §16.7

La migración/seed DEBE publicar `proyectos` con un snapshot inicial idéntico
al contenido hardcodeado actual (hero, header+fondo del listado, cierre), en
el mismo cambio que registra la página. Sólo `hero` tiene fallback literal
por página (`hero_fallback`); `projects_list` y `final_cta` NO lo tienen — su
resiliencia depende exclusivamente de ese seed inicial, nunca de un fallback
en tiempo de render.

#### Scenario: Ambiente sin seed corrido

- GIVEN no existe fila `FrontendPage` para `proyectos`
- WHEN se renderiza la ruta pública
- THEN el hero usa `hero_fallback.proyectos` y no hay listado ni cierre —
  igual que cualquier otra página del CMS hoy

#### Scenario: Recién cortado, sin edición del owner

- GIVEN corrió la migración/seed de este cambio y el owner no tocó nada
- WHEN se visita `/proyectos`
- THEN hero, header+fondo del listado y cierre se ven IDÉNTICOS a la versión
  blade anterior

#### Scenario: Cierre publicado vacío a propósito

- GIVEN el owner publica `final_cta` sin título ni botón
- WHEN se renderiza `/proyectos`
- THEN el cierre no muestra título ni botón — NO vuelve a
  "¿Tienes un terreno...?"

### Requirement: Fondos de paleta cerrada (§6.1 + §16.7)

En `projects_list` y `final_cta` de esta página, `background_color` ausente
DEBE usar la clase de gradiente literal actual de cada sección (no
interpolada del payload); presente y válido DEBE usar la clase plana de
`brand_palette[clave]`; un valor fuera de `brand_palette` DEBE rechazarse en
la validación, antes de publicar.

#### Scenario: Sin elegir color

- GIVEN `projects_list` sin `background_color`
- WHEN se renderiza
- THEN el grid conserva su gradiente literal actual

#### Scenario: Color de la paleta

- GIVEN `background_color: "primary-l1"`
- WHEN se publica y renderiza
- THEN el fondo usa `bg-brand-primary-l1`

#### Scenario: Color fuera de la paleta

- GIVEN `background_color: "#ff00ff"`
- WHEN se valida el payload
- THEN se rechaza; ningún valor llega a interpolarse en una clase

### Requirement: Listado de catálogo completo (`limit`)

`projects_list` (tipo `featured_projects`) en `/proyectos` DEBE listar TODOS
los proyectos publicados, sin filtrar por `is_featured` — igual que
`ProjectController@index` hoy. Esto difiere de `home`, cuya sección homónima
sólo muestra destacados. Sin `limit` elegido el listado DEBE ser ilimitado;
con `limit` elegido (1–24, mismo tope que el resto del sitio) DEBE acotarse a
ese número, sin aplicar el filtro de destacados.

#### Scenario: Sin límite elegido

- GIVEN 30 proyectos publicados, 5 con `is_featured: true`,
  `projects_list.limit` ausente
- WHEN se renderiza `/proyectos`
- THEN aparecen los 30 — no sólo los 5 destacados, no acotado a 12

#### Scenario: Límite elegido

- GIVEN el owner fija `projects_list.limit: 10`
- WHEN se renderiza
- THEN aparecen exactamente 10, sin filtrar por destacados

#### Scenario: Home no cambia

- GIVEN `home.featured_projects` sin `limit`
- WHEN se renderiza `home`
- THEN sigue mostrando hasta 12 proyectos `is_featured: true` — sin cambios

### Requirement: CTA externo seguro al sitio del asociado

El botón del hero al sitio del asociado DEBE reutilizar `CtaResolver` tipo
`url` sin código nuevo: sólo destinos `^https://` que pasan
`FILTER_VALIDATE_URL` se aceptan y se marcan `external: true`; cualquier otro
esquema se rechaza.

#### Scenario: URL externa válida

- GIVEN `primary_cta: {type: url, target: 'https://a74.example.com'}`
- WHEN se resuelve
- THEN el botón enlaza ahí con `external: true`

#### Scenario: Esquemas inseguros rechazados

- GIVEN `target` es `javascript:alert(1)`, `data:text/html,x`, o
  `//evil.example.com`
- WHEN se resuelve el CTA
- THEN en los tres casos resuelve `null` y el botón se omite

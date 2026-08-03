# Especificación: hero-logo-propio

## Purpose

El hero de una página puede llevar un logo PROPIO, opcional e independiente
del logo de marca del sitio — hoy lo usa sólo `/proyectos` (A-74
Arquitectura), pero el campo queda disponible para cualquier hero.

## Requirements

### Requirement: Schema opcional del logo propio

`FrontendSectionSchema::SPECS['hero']` DEBE aceptar la clave opcional
`logo: {media_id: '?media', alt: '?string'}` — misma convención que
`team.spotlight`.

#### Scenario: Payload válido

- GIVEN `logo: {media_id: <uuid válido>, alt: 'A-74 Arquitectura'}`
- WHEN se valida el hero
- THEN pasa sin errores

### Requirement: Accesibilidad — alt obligatorio junto a media_id

Un `logo.media_id` no vacío DEBE exigir un `logo.alt` no vacío hermano
(regla universal §16.1.1); `logo` no tiene `decorative`, así que no hay
escape.

#### Scenario: Sin alt

- GIVEN `logo: {media_id: <uuid>, alt: null}`
- WHEN se valida
- THEN se rechaza: "«logo» tiene una imagen sin texto alternativo"

#### Scenario: Alt en blanco

- GIVEN `logo: {media_id: <uuid>, alt: '   '}`
- WHEN se valida
- THEN se rechaza igual (trim vacío)

### Requirement: Convención `media_id` para visibilidad del pipeline

El logo DEBE usar literalmente la clave `media_id` (no una clave plana tipo
`hero_logo_media_id`) para que la MISMA lógica de elegibilidad, promoción al
publicar y reporte de huérfanas lo encuentre, sin rama de código nueva.

#### Scenario: Promoción al publicar

- GIVEN un hero con `logo.media_id` apuntando a un media sin promover
- WHEN el owner publica la página
- THEN ese media se promueve, igual que cualquier otro `media_id` del payload

#### Scenario: No aparece como huérfano

- GIVEN un media referenciado únicamente por `logo.media_id`
- WHEN corre el reporte de huérfanas
- THEN no se lo reporta mientras `logo.media_id` lo siga referenciando

### Requirement: Precedencia logo propio vs. logo de marca

> **⚠️ CORREGIDO por el design (decisión cerrada, apply Fase 2).** El texto
> original de este requirement (conservado abajo tachado, por trazabilidad)
> proponía que el logo propio resuelto ignora `logo_enabled` siempre. El
> design detectó que esa regla no deja forma de APAGAR el logo en la página:
> si el owner borra su imagen, la clave `logo` desaparece del payload, el
> fallback §16.7 revive el logo A-74 hardcodeado del blade estático, y
> `logo_enabled` no tiene ningún efecto sobre él. Se resolvió a favor del
> design — no es un empate de tradeoffs, es un defecto de capacidad del texto
> original. Regla vigente, implementada y verificada: **`logo_enabled`
> gobierna AMBOS logos.** Prendido, se muestra el logo propio si existe y el
> de marca si no. Apagado, no se muestra ninguno. Modelo mental resultante:
> `logo_enabled` responde «¿va un logo?»; `logo` responde «¿cuál?» — dos
> decisiones ortogonales y componibles, en vez de un interruptor con
> excepciones. Detalle completo de la reconciliación: engram
> `sdd/cms-pagina-proyectos/decision-precedencia-logo`.

~~El hero tiene UN solo lugar para logo. Un logo propio cuyo `media_id`
resuelve a un `media_url` no nulo SIEMPRE gana sobre `logo_enabled`: subir
una imagen es una acción completa y suficiente por sí misma, y exigir además
prender un toggle llamado "mostrar el logo del SITIO" para revelar una
imagen propia y no relacionada mezclaría dos decisiones distintas en un solo
control. `logo_enabled` sigue gobernando EXCLUSIVAMENTE el logo de marca, sin
cambios, en todo hero que no defina `logo`.~~ **Texto histórico, no
normativo** — ver corrección arriba.

#### Scenario: Logo propio + logo_enabled true

- GIVEN `logo.media_id` resuelve a una url Y `logo_enabled: true`
- WHEN se renderiza el hero
- THEN se muestra el logo PROPIO; el de marca no se muestra

#### Scenario: Logo propio + logo_enabled false (CORREGIDO)

- GIVEN `logo.media_id` resuelve a una url Y `logo_enabled: false` (o
  ausente)
- WHEN se renderiza el hero
- THEN NO se muestra ningún logo — `logo_enabled` apagado bloquea también el
  propio. ~~(texto original: "el logo PROPIO se muestra igual — el toggle no
  lo bloquea")~~

#### Scenario: Sin logo propio + logo_enabled true

- GIVEN no hay clave `logo` (o su `media_id` está ausente) Y
  `logo_enabled: true`
- WHEN se renderiza el hero
- THEN se muestra el logo de MARCA del sitio — sin cambios respecto de hoy

#### Scenario: Sin logo propio + logo_enabled false

- GIVEN no hay clave `logo` Y `logo_enabled: false`
- WHEN se renderiza el hero
- THEN no se muestra ningún logo — sin cambios respecto de hoy

#### Scenario: Media sin promover no cuenta como "presente"

- GIVEN `logo.media_id` referencia un media TODAVÍA no promovido Y
  `logo_enabled: true`
- WHEN se ejecuta el render PÚBLICO (no el preview)
- THEN `logo.media_url` resuelve `null` y el hero cae al comportamiento "sin
  logo propio": muestra el logo de marca

### Requirement: Compatibilidad con snapshots previos

Un snapshot de hero publicado ANTES de este cambio (las otras cinco páginas)
no tiene clave `logo`. Su ausencia DEBE tratarse igual que "sin logo propio
resuelto" — cero error nuevo, cero cambio de render.

#### Scenario: Página existente sin la clave nueva

- GIVEN el snapshot publicado de `nosotros` (sin `logo`)
- WHEN se renderiza tras el deploy
- THEN se ve exactamente igual que antes: logo de marca si `logo_enabled`
  era true, ninguno si no

# New Hauz — Design System

**New Hauz** is a luxury, corporate **real estate firm in Querétaro, México** that accompanies a client across the full property cycle: **architecture → construction → commercialization → investment**. The positioning line is *"Construimos patrimonio, diseñamos espacios y comercializamos oportunidades."* The brand must read **premium, corporate, sophisticated, modern, minimalist** — explicitly *not* a budget real-estate aesthetic.

This design system encodes the brand's UX/UI guide into tokens, components, and product kits so any agent can generate on-brand New Hauz interfaces and assets.

> **Language:** the product is Spanish (es-MX). Write UI copy in Spanish.

---

## Sources

This system was built from materials the user provided. You may not have access, but they are recorded so you can go deeper if you do:

- **GitHub repo:** [`krisblumen/newhauz`](https://github.com/krisblumen/newhauz) — Laravel 13 + Filament v3 + Livewire + Tailwind v4 monolith. The public frontend is still the default Laravel scaffold; **the design source of truth is the docs, not the rendered code.**
  - `docs/GUIA-UX-UI-newhauz.md` — the visual UX/UI guide (colors, type, components, photography). **Primary reference.**
  - `docs/PRD-NEWHAUZ.md`, `docs/PRD-FRONTEND.md` — product scope, information architecture, lead-conversion priorities.
- **Brand assets:** `logo-on-light.png`, `logo-on-dark.png`, `favicon.png` (monogram) → copied into `assets/logos/`.

Explore the repo further to recreate real product screens with higher fidelity.

---

## Content Fundamentals

**Language & voice.** Spanish (Mexico). Institutional first-person plural — *"nosotros"*: "Construimos", "Diseñamos", "Comercializamos", "Acompañamos". Speaks *about* the firm and *to* the client with quiet authority; never casual or hype-y.

**Tone.** Premium, confident, restrained. Aspirational but credible — sells *patrimonio* (wealth/legacy) and *oportunidades*, not "deals". The flagship investor line shows the register: *"Invertimos donde otros ven terrenos. Creamos valor donde otros ven metros cuadrados."*

**Casing.** Headlines in Title Case or sentence case (Montserrat). Eyebrows/labels/buttons in **UPPERCASE**, tracked. Prices and specs are plain and factual.

**Do.** Short declarative statements. Lead with value (precio, zona). Clear single CTAs per block. SEO-aware property copy ("Casa en venta en Juriquilla").

**Avoid.** Emoji. Exclamation-heavy marketing. Cartoonish or cute language. Inflated promises about investment returns (legal/expectation risk — route to personal advisory instead). Generic "your dream home" clichés.

**CTA vocabulary (real, from the brief):** *Ver Propiedades · Agenda una Asesoría · Publicar Propiedad · Solicitar Valuación · Contactar Asesor · Conocer Proyectos.* Conversion priority order: **WhatsApp → Formulario → Valuación → Publicar → Agendar.**

---

## Visual Foundations

**Palette.** Two brand colors carry everything: **Azul Corporativo `#091A5B`** (navy — authority, trust, the dominant brand color) and **Naranja Corporativo `#F6A300`** (orange — energy, used *only* for conversion: CTAs, focus, key accents, hover → `#D98C00`). Supported by Negro Premium `#111111`, white, and a restrained gray ramp on a **Fondo Claro `#F7F7F7`** page base. Discipline is the rule: orange is precious — one primary CTA per view. Premium "Inversionistas" sections invert to a deep navy (`#050F38`) surface.

**Type.** **Montserrat** (500–800) for everything structural — headlines, nav, buttons, card titles, eyebrows — set tight (−0.02em) and heavy (800) at display sizes. **Inter** (400–500) for body, property copy and forms, at generous line-height (1.5–1.65). The pairing reads corporate-premium, not decorative.

**Spacing & layout.** 4px base grid; generous section rhythm (96px vertical), 1200px max container. Mobile-first (mobile 0–767, tablet 768–1023, desktop 1024+). Property grids: 3 col desktop / 2 tablet / 1 mobile.

**Shape.** Buttons & inputs **12px** radius; cards **16px**; pills for badges. Controls are tall and comfortable — **buttons 52px, inputs 56px**.

**Elevation.** Soft, **navy-tinted** shadows (never neutral gray, never harsh). Cards rest on a light `shadow-sm` and **lift on hover** (`shadow-lg` + −4px translate). Orange CTAs carry a subtle orange glow.

**Imagery.** Editorial real-estate photography is central: contemporary architecture, drone/aerial shots, premium interiors, **real projects** — never posed families or obvious stock. Hero is a full-height (100vh) cinematic video/photo under a ~60% navy overlay. *This system ships branded navy placeholders where real photography is required — replace them with real assets.*

**Motion.** Purposeful and soft. Buttons transition ~200ms; cards/hover ~350ms; scroll reveals (fade up/left/right) 300–500ms. No bounce, no infinite decorative loops.

**Interaction states.** Hover — buttons darken (orange→`#D98C00`, navy→lighter) and lift 1px; cards lift + image zooms (scale 1.06) + soft shadow; ghost buttons fill with navy-50. Focus — orange border + soft orange ring. Press — color deepen.

**Overall.** *Luxury Corporate Real Estate.* Minimal, lots of white space, navy structure, orange only where it converts.

---

## Iconography

- **Style:** clean **line icons, ~1.75 stroke** — professional, geometric, never cartoonish (the guide explicitly forbids "iconografía caricaturizada"). The bundled `Icon` component (`components/realestate/Icon.jsx`) ships a curated set built on **Lucide** geometry: `bed, bath, area, car, pin, search, phone, whatsapp, arrow, heart, check, ruler` — exactly the property-spec and conversion glyphs the product needs.
- **Substitution flag:** the codebase had no first-party icon set (default Laravel scaffold), so the set above approximates the guide's intent using **Lucide** geometry. For broader needs, link **[Lucide](https://lucide.dev)** from CDN at the same stroke weight. *Flagged — confirm or supply an official icon set.*
- **No emoji. No unicode-glyph icons.** The one chevron (`▾`) on selects is a deliberate, minimal exception.
- **Logos:** `assets/logos/` — `newhauz-logo-color.png` (principal, on light), `newhauz-logo-on-dark.png` (white, on navy), `newhauz-monogram.png` (NH mark / favicon).

---

## Index / Manifest

**Foundations (root)**
- `styles.css` — global entry point (consumers link this one file). `@import` manifest only.
- `tokens/` — `fonts.css`, `colors.css`, `typography.css`, `spacing.css`, `effects.css`, `base.css`.
- `assets/logos/` — brand logos + monogram; `assets/favicon.png`.

**Specimen cards** (Design System tab) — `guidelines/*.card.html`: Colors (primary, CTA, navy, orange, neutral, semantic), Type (display, headings, body, eyebrow), Spacing (scale, radii, elevation, controls), Brand (logo, hero lockup).

**Components** — `components/`
- `core/` — `Button`, `Badge`, `Input`, `Card`
- `realestate/` — `PropertyCard`, `AgentCard`, `SearchBar`, `Icon`
- Each has `<Name>.jsx` + `.d.ts` + `.prompt.md`; one `*.card.html` demo per directory.
- Mount via `const { Button } = window.NewHauzDesignSystem_e288df` after loading `_ds_bundle.js`.

**Product kits** — `ui_kits/website/` *(in progress — see Status)*: Home, property listing, property detail recreations of the public New Hauz site.

**Skill** — `SKILL.md` (Agent-Skills compatible).

---

## Status / Next steps

- ✅ Tokens, fonts, 16 specimen cards, 8 components (core + real estate), brand assets.
- 🚧 **UI kit (`ui_kits/website/`)** — Home, listing and property-detail screens still to be built from `docs/` IA.
- ⚠️ **Fonts** load from Google Fonts (`@import`), not self-hosted binaries — Montserrat + Inter *are* the brand fonts, so no substitution, but supply woff2 files if you need offline/self-hosted.
- ⚠️ **Photography** is represented by branded navy placeholders — replace with real editorial/drone/interior photography.
- ⚠️ **Icons** approximate the guide via Lucide geometry — confirm or supply an official set.

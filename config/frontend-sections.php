<?php

/**
 * Canonical section registry (§16.1.1, RFC-075) — the closed allowlist of what
 * the page CMS can edit. It is NOT a free page builder: every editable region
 * of the current frontend maps to a stable `section_key` bound to an executable
 * `type`, and no user-created type or key is accepted in v1.
 *
 * - `pages`: per page key, the ordered canonical sections `section_key => type`.
 *   This is the seed and the validation source; a section whose (page,key) is
 *   not here is rejected.
 * - `types`: the allowlist of section types. `media` marks a type whose payload
 *   references images (validated media_id, §16.4). `dynamic` marks a type whose
 *   items come from a kernel authority (Property/Project/ServiceType); its
 *   payload only carries allowlisted parameters, never a query.
 *
 * The Blade renderers per type are Lote F (the render cutover); this registry is
 * the domain contract the draft/publish engine and validation run against.
 */
return [

    'pages' => [
        'home' => [
            'hero' => 'hero',
            'what_we_do' => 'capability_cards',
            'featured_properties' => 'featured_properties',
            'opportunity_properties' => 'opportunity_properties',
            'featured_projects' => 'featured_projects',
            'investors_block' => 'cta',
            'partners' => 'partners',
            'final_cta' => 'cta',
        ],
        'nosotros' => [
            'hero' => 'hero',
            'metrics' => 'metrics',
            'story' => 'rich_text',
            'values' => 'values',
            'team' => 'team',
            'final_cta' => 'cta',
        ],
        'servicios' => [
            'hero' => 'hero',
            'services_list' => 'service_list',
            'final_cta' => 'cta',
        ],
        'inversionistas' => [
            'hero' => 'hero',
            'investment_path' => 'feature_sequence',
            'service_scope' => 'values',
            'audience_outcomes' => 'audience_outcomes',
            'final_cta' => 'cta',
        ],
        'contacto' => [
            'hero' => 'hero',
            'contact_intro' => 'rich_text',
        ],
        // Sexta página canónica (extensión de RFC-075, cambio
        // cms-pagina-proyectos). `projects_list` reusa el tipo
        // `featured_projects`, pero con la variante `catalog`
        // (`project_list_variants` abajo): TODOS los proyectos publicados, no
        // sólo los destacados — es el listado completo que `/proyectos` ya
        // muestra hoy, no el resumen de la home.
        'proyectos' => [
            'hero' => 'hero',
            'projects_list' => 'featured_projects',
            'final_cta' => 'cta',
        ],
    ],

    /**
     * Los íconos que puede llevar una tarjeta de `capability_cards`.
     *
     * Viven acá y no en el Blade para que el SELECTOR del panel y el RENDER
     * lean la misma lista: agregar un ícono es tocar un solo lugar, y el schema
     * puede rechazar uno que no exista en vez de dibujar un hueco.
     *
     * `path` es el atributo `d` de un `<path>` sobre un viewBox 0 0 24 24, con
     * trazo y sin relleno — el mismo lenguaje del resto del sitio.
     */
    'card_icons' => [
        'home' => ['label' => 'Casa', 'path' => 'M12 2 3 7v13h18V7z M3 7l9 5 9-5 M12 12v8'],
        'building' => ['label' => 'Edificio', 'path' => 'M3 21h18 M5 21V7l7-4 7 4v14 M9 9h.01 M15 9h.01 M9 13h.01 M15 13h.01'],
        'trending-up' => ['label' => 'Crecimiento', 'path' => 'M3 17l6-6 4 4 7-7 M17 8h4v4'],
        'ruler' => ['label' => 'Diseño', 'path' => 'M21.3 8.7 8.7 21.3a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4Z M14.5 12.5l2 2 M11.5 15.5l2 2 M17.5 9.5l2 2'],
        'key' => ['label' => 'Entrega de llaves', 'path' => 'M15.5 5.5a4 4 0 1 1-5.66 5.66L3 18v3h3v-2h2v-2h2l.84-.84A4 4 0 0 1 15.5 5.5Z M16.5 8.5h.01'],
        'users' => ['label' => 'Equipo', 'path' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75'],
        'map-pin' => ['label' => 'Ubicación', 'path' => 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0 M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4'],
        'shield' => ['label' => 'Respaldo', 'path' => 'M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10 M9 12l2 2 4-4'],
        'hammer' => ['label' => 'Obra', 'path' => 'M14 10 3.5 20.5a2.12 2.12 0 0 0 3 3L17 13 M18 8l4-4-3-3-4 4 M13 5l6 6'],
        'hard-hat' => ['label' => 'Supervisión de obra', 'path' => 'M2 18a1 1 0 0 1 1-1h18a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2Z M4 17v-4a8 8 0 0 1 16 0v4 M10 9V5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4'],
        'file-text' => ['label' => 'Documentación', 'path' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M9 13h6 M9 17h6'],
        'calculator' => ['label' => 'Financiamiento', 'path' => 'M5 2h14a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z M8 6h8 M8 11h.01 M12 11h.01 M16 11h.01 M8 15h.01 M12 15h.01 M16 15h.01 M8 19h4'],
        'search' => ['label' => 'Búsqueda de inmuebles', 'path' => 'M11 3a8 8 0 1 0 0 16 8 8 0 0 0 0-16Z M21 21l-4.35-4.35'],
        'camera' => ['label' => 'Fotografía profesional', 'path' => 'M3 7h3l2-3h8l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1Z M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
        'award' => ['label' => 'Certificación', 'path' => 'M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z M8.5 13.5 7 22l5-3 5 3-1.5-8.5'],
        'bar-chart' => ['label' => 'Plusvalía', 'path' => 'M3 3v18h18 M7 17V10 M12 17V6 M17 17v-4'],
    ],

    /**
     * Los tres íconos de `FrontendService` (arquitectura / construcción /
     * comercialización). Es un catálogo APARTE de `card_icons`: aunque
     * comparten selector en el panel, son dos modelos distintos con dos
     * significados distintos —«Casa» acá es «Arquitectura», no una tarjeta de
     * «Qué hacemos»— y mezclarlos dejaría elegir «Obra» donde el sitio sólo
     * sabe dibujar tres.
     *
     * Vive acá y no en `welcome.blade.php` por la misma razón que
     * `card_icons`: selector y render leen la misma lista.
     */
    'service_icons' => [
        'home' => ['label' => 'Arquitectura', 'path' => 'M12 2 3 7v13h18V7z M3 7l9 5 9-5 M12 12v8'],
        'building' => ['label' => 'Construcción', 'path' => 'M3 21h18 M5 21V7l7-4 7 4v14'],
        'trending-up' => ['label' => 'Comercialización / Inversión', 'path' => 'M3 17l6-6 4 4 7-7 M17 8h4v4'],
    ],

    /**
     * El nombre HUMANO de cada `section_key`, para el panel.
     *
     * `investment_path` o `audience_outcomes` son identificadores del registro:
     * estables, en inglés y sin significado para quien administra el sitio. El
     * panel muestra estos nombres en su lugar, y `FrontendSectionEditorClosureTest`
     * exige que toda sección canónica tenga el suyo — una sección sin nombre
     * humano vuelve a exponer la clave interna.
     */
    'section_labels' => [
        'hero' => 'Portada',
        'what_we_do' => 'Qué hacemos',
        'services_list' => 'Listado de servicios',
        'featured_properties' => 'Propiedades destacadas',
        'opportunity_properties' => 'Oportunidades de inversión',
        'featured_projects' => 'Proyectos destacados',
        'investors_block' => 'Bloque para inversionistas',
        'partners' => 'Aliados',
        'final_cta' => 'Cierre con llamado a la acción',
        'metrics' => 'Cifras',
        'story' => 'Nuestra historia',
        'values' => 'Valores',
        'team' => 'Equipo',
        'investment_path' => 'Ruta de inversión',
        'service_scope' => 'Alcance del servicio',
        'audience_outcomes' => 'A quién le sirve y qué obtiene',
        'contact_intro' => 'Presentación de contacto',
        'projects_list' => 'Listado de proyectos',
    ],

    /**
     * Allowlisted section types. `dynamic` types resolve their items in the
     * kernel from their authority; their payload only holds parameters.
     */
    'types' => [
        'hero' => ['media' => true],
        'rich_text' => ['media' => true],
        'metrics' => ['media' => false],
        'values' => ['media' => false],
        'team' => ['media' => true],
        'partners' => ['media' => true],
        'capability_cards' => ['media' => false],
        'feature_sequence' => ['media' => true],
        'audience_outcomes' => ['media' => false],
        'cta' => ['media' => false],
        'service_list' => ['media' => false, 'dynamic' => true],
        'featured_projects' => ['media' => false, 'dynamic' => true],
        'featured_properties' => ['media' => false, 'dynamic' => true],
        'opportunity_properties' => ['media' => false, 'dynamic' => true],
    ],

    /** Layout variants a feature_sequence panel may use. */
    'feature_sequence_layouts' => ['split_media_end', 'split_media_start', 'full_overlay'],

    /**
     * Hero presentation allowlists (Épica 12.1 §6.1). They are enums, never free
     * values: the render maps each one to a FIXED utility class, so nothing from
     * the payload is ever interpolated into a class name or a style attribute.
     */
    // Sirve para CUALQUIER tipo con `text_align`, no sólo el hero: el schema la
    // consulta de forma genérica. Conserva el nombre por compatibilidad con lo
    // ya publicado y auditado.
    'hero_text_aligns' => ['left', 'center', 'right'],
    'hero_logo_sizes' => ['sm', 'md', 'lg', 'xl'],

    /**
     * Grosores de borde de una tarjeta de `capability_cards`, en píxeles.
     *
     * El COLOR no se elige: es siempre el acento de la marca. Dejarlo libre
     * abriría la puerta a tarjetas que no combinan con el resto del sitio, y el
     * owner ya define su acento una vez en la configuración; el borde lo sigue.
     */
    'card_border_widths' => [1, 2, 3, 4],

    /**
     * LA paleta de la marca: los DOS colores del sitio con dos variantes más
     * oscuras y dos más claras cada uno.
     *
     * Es una paleta cerrada a propósito. Un selector de color abierto dejaría al
     * owner poner un verde en un sitio azul y ámbar; estas diez opciones se
     * derivan por `color-mix` de las variables que él mismo configura, así que
     * cambiar su acento recalcula las cinco variantes de acento sin tocar nada
     * más. Flexible dentro de su identidad, no fuera de ella.
     *
     * Es UNA SOLA lista para todos los que eligen color —hoy el borde de las
     * tarjetas y el fondo del bloque de cierre— y por eso cada entrada trae la
     * clase de cada uso. Dos listas paralelas se habrían separado a la primera
     * vez que alguien agregue un color en una sola.
     */
    'brand_palette' => [
        // Los NEUTROS llevan su hexadecimal escrito: no se derivan de la marca,
        // son la escala de grises del sitio. Van en la misma lista que el resto
        // para que exista UN solo selector de color en todo el panel — dos
        // paletas serían dos gestos distintos para la misma decisión.
        // El fondo del SITIO: el que el cliente configuró. Es el valor por
        // defecto de las secciones que dejan elegir fondo, y también la forma de
        // volver atrás después de haber elegido otro — sin él, elegir un color
        // sería un camino de ida.
        'site' => ['label' => 'Fondo del sitio', 'bg' => 'bg-site-background', 'border' => 'border-site-background', 'text' => 'text-site-text'],

        'neutral-0' => ['label' => 'Blanco', 'hex' => '#ffffff', 'bg' => 'bg-white', 'border' => 'border-white', 'text' => 'text-white'],
        'neutral-1' => ['label' => 'Gris muy claro', 'hex' => '#f2f2f2', 'bg' => 'bg-fog', 'border' => 'border-fog', 'text' => 'text-fog'],
        'neutral-2' => ['label' => 'Gris claro', 'hex' => '#eaeaea', 'bg' => 'bg-cloud', 'border' => 'border-cloud', 'text' => 'text-cloud'],
        'neutral-3' => ['label' => 'Gris', 'hex' => '#c9c9c9', 'bg' => 'bg-mist', 'border' => 'border-mist', 'text' => 'text-mist'],
        'neutral-4' => ['label' => 'Gris oscuro', 'hex' => '#5b5b5b', 'bg' => 'bg-stone', 'border' => 'border-stone', 'text' => 'text-stone'],
        'neutral-5' => ['label' => 'Negro', 'hex' => '#111111', 'bg' => 'bg-ink', 'border' => 'border-ink', 'text' => 'text-ink'],

        // El azulado de los paneles suaves. Lleva su hexadecimal escrito por la
        // misma razón que los grises: es un tono FIJO del sitio y no una variante
        // del primario —mezclarlo con blanco da `primary-l2`, que es bastante más
        // saturado—, así que derivarlo daría otro color. Existe en la paleta
        // porque es el fondo con el que se dibuja la banda de cifras, y sin él
        // elegirle otro color a esa banda sería un camino de ida.
        'navy' => ['label' => 'Azul muy claro', 'hex' => '#eef1f8', 'bg' => 'bg-navy-50', 'border' => 'border-navy-50', 'text' => 'text-navy-50'],

        'accent-d2' => ['label' => 'Acento muy oscuro', 'border' => 'border-brand-accent-d2', 'bg' => 'bg-brand-accent-d2', 'text' => 'text-brand-accent-d2'],
        'accent-d1' => ['label' => 'Acento oscuro', 'border' => 'border-brand-accent-d1', 'bg' => 'bg-brand-accent-d1', 'text' => 'text-brand-accent-d1'],
        'accent' => ['label' => 'Acento', 'border' => 'border-brand-accent', 'bg' => 'bg-brand-accent', 'text' => 'text-brand-accent'],
        'accent-l1' => ['label' => 'Acento claro', 'border' => 'border-brand-accent-l1', 'bg' => 'bg-brand-accent-l1', 'text' => 'text-brand-accent-l1'],
        'accent-l2' => ['label' => 'Acento muy claro', 'border' => 'border-brand-accent-l2', 'bg' => 'bg-brand-accent-l2', 'text' => 'text-brand-accent-l2'],
        'primary-d2' => ['label' => 'Principal muy oscuro', 'border' => 'border-brand-primary-d2', 'bg' => 'bg-brand-primary-d2', 'text' => 'text-brand-primary-d2'],
        'primary-d1' => ['label' => 'Principal oscuro', 'border' => 'border-brand-primary-d1', 'bg' => 'bg-brand-primary-d1', 'text' => 'text-brand-primary-d1'],
        'primary' => ['label' => 'Principal', 'border' => 'border-brand-primary', 'bg' => 'bg-brand-primary', 'text' => 'text-brand-primary'],
        'primary-l1' => ['label' => 'Principal claro', 'border' => 'border-brand-primary-l1', 'bg' => 'bg-brand-primary-l1', 'text' => 'text-brand-primary-l1'],
        'primary-l2' => ['label' => 'Principal muy claro', 'border' => 'border-brand-primary-l2', 'bg' => 'bg-brand-primary-l2', 'text' => 'text-brand-primary-l2'],
    ],

    /**
     * Hero fallback PER PAGE (§16.7 + §18.18): «no inicializado → valor
     * hardcodeado ACTUAL». No son solo las imágenes: es TODO el hero que cada
     * página muestra hoy, porque el fallback debe pasar por el mismo presenter
     * y el mismo partial que el contenido publicado (C-B-1 de la auditoría del
     * Lote B). Sin esto habría dos renderers del hero: uno con las garantías de
     * accesibilidad y CSP, y otro sin ellas justo en la primera visita.
     *
     * Las URLs absolutas pasan tal cual; las rutas relativas se vuelven `asset()`.
     * `contacto` no tiene imagen de fondo hoy, así que su lista va vacía a
     * propósito. Un `slides: []` publicado nunca revive nada de esto (§16.1.1).
     */
    'hero_fallback' => [
        'home' => [
            'eyebrow' => 'Inmobiliaria · Arquitectura · Inversión · Querétaro',
            'title' => 'Construimos patrimonio, diseñamos espacios.',
            'subtitle' => 'Te acompañamos en todo el ciclo de tu propiedad: encuentra tu próxima inversión o tu hogar con asesoría experta en las mejores zonas de Querétaro.',
            'logo_enabled' => true,
            'logo_size' => 'xl',
            'primary_cta' => ['label' => 'Ver Propiedades', 'type' => 'route', 'target' => 'inmuebles'],
            'secondary_cta' => ['label' => 'Conocer Proyectos', 'type' => 'route', 'target' => 'proyectos'],
            // welcome.blade.php:12-16 — arquitectura, construcción, comercialización, inversión.
            'slides' => [
                'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=1920&q=70',
                'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1920&q=70',
                'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1920&q=70',
                'https://images.unsplash.com/photo-1460317442991-0ec209397118?auto=format&fit=crop&w=1920&q=70',
            ],
        ],
        'nosotros' => [
            'eyebrow' => 'Quiénes somos',
            'title' => 'Construimos patrimonio que trasciende generaciones.',
            'subtitle' => 'Somos un equipo de arquitectos, constructores y asesores inmobiliarios que acompaña a cada cliente en todo el ciclo de su propiedad en Querétaro.',
            'slides' => ['images/nosotros/header_nosotros.png'],
        ],
        'servicios' => [
            'eyebrow' => 'Qué hacemos',
            'title' => 'Del terreno a la entrega de llaves.',
            'slides' => ['images/servicios/header_servicios.png'],
        ],
        'inversionistas' => [
            'eyebrow' => 'Inversión inmobiliaria',
            'title' => 'De la oportunidad al desarrollo con fundamento.',
            'slides' => ['images/inversionistas/header_inversionistas.png'],
        ],
        'contacto' => [
            'eyebrow' => 'Hablemos',
            'title' => 'Estamos para asesorarte',
            'subtitle' => 'Escríbenos y un asesor de New Hauz te contacta el mismo día. Sin compromiso.',
            'slides' => [],
        ],
        // A-74 Arquitectura (proyectos.blade.php:23-42). `logo` acá es el logo
        // PROPIO de la página en su forma cruda de fallback (`src`/`alt`, no
        // `media_id`): lo resuelve `fallbackHeroLogo()`, no el schema — este es
        // el ÚNICO hero con un logo propio hoy, así que es también el único
        // `hero_fallback` que trae la clave.
        'proyectos' => [
            'eyebrow' => 'Desarrollos & obra',
            'title' => 'Proyectos con visión, diseño y propósito.',
            'subtitle' => 'Desarrollos residenciales, propiedades y soluciones arquitectónicas seleccionadas para vivir, invertir y construir futuro.',
            'logo_enabled' => true,
            'logo_size' => 'xl',
            'logo' => ['src' => 'images/brand/a74-arquitectura.png', 'alt' => 'A-74 Arquitectura'],
            'slides' => [
                'https://picsum.photos/seed/a74-arch1/1920/900',
                'https://picsum.photos/seed/a74-build2/1920/900',
                'https://picsum.photos/seed/a74-const3/1920/900',
                'https://picsum.photos/seed/a74-design4/1920/900',
            ],
        ],
    ],

    /**
     * Visual treatment of the hero, PER PAGE. Not editable and not part of the
     * payload: it is what each page looks like today, and the fallback must not
     * change the site's appearance just because it now goes through the shared
     * partial. `home` is the only outlier (full-height, larger type, its own
     * overlay); everything else already matched the shared treatment exactly.
     */
    'hero_variants' => ['home' => 'featured', 'contacto' => 'compact'],

    /**
     * Variante de presentación de `featured_projects` POR PÁGINA (design D6,
     * cambio cms-pagina-proyectos). `home.featured_projects` sin entrada acá
     * sigue siendo el resumen de siempre (autoridad `is_featured`, grid, sin
     * estado vacío). `catalog` es el listado COMPLETO que hoy usa
     * `ProjectController@index`: autoridad TODOS los proyectos publicados
     * (`latest()`, sin filtrar por destacado), carrusel de a 6 y estado vacío
     * propio — tres divergencias reales, no una de layout, así que la
     * autoridad de datos también depende de esta variante y no sólo el Blade.
     */
    'project_list_variants' => ['proyectos' => 'catalog'],

];

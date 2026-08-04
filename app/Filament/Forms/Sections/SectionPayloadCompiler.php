<?php

namespace App\Filament\Forms\Sections;

use App\Models\FrontendSection;
use App\Services\Frontend\Media\OptimizeSectionImage;

/**
 * Turns the state of a section's friendly form into its CANONICAL payload
 * (Épica 12.1 §7.4 para el hero; Épica 12.2 §3 para el resto).
 *
 * Vive fuera del RelationManager por una razón concreta: el editor de secciones
 * ya es la pantalla más grande del panel, y cada tipo que se migra le suma un
 * formulario y una compilación. Separar «cómo se ve» de «en qué se convierte»
 * mantiene ambas legibles y hace que la transformación se pueda probar sola.
 *
 * Dos reglas transversales, y las dos importan:
 *
 * 1. **Un campo opcional vacío se OMITE, no se guarda como `''`.** El schema
 *    aceptaría la cadena vacía, pero el render la trataría como contenido y
 *    dibujaría un párrafo en blanco. Ausente significa ausente.
 * 2. **Una fila de repeater incompleta se descarta.** El owner que
 *    agrega una fila y no la llena está indeciso, no declarando un vacío; el
 *    schema rechazaría el guardado entero y perdería el resto de su trabajo.
 */
class SectionPayloadCompiler
{
    /**
     * Qué listado lleva a qué página, con qué texto si el owner no pone uno, y
     * con qué filtro si el destino lo lleva.
     *
     * El DESTINO se define acá y no en el formulario: cada listado tiene una
     * sola página que lo continúa, así que ofrecer elegirla sería ofrecer
     * equivocarse en algo que no tiene alternativa.
     */
    private const CATALOGO = [
        'featured_properties' => ['target' => 'inmuebles', 'label' => 'Ver todas las propiedades'],
        // Al catálogo, pero filtrado a oportunidades: mandarlo al catálogo
        // entero perdería en el camino justo lo que la sección promete.
        'opportunity_properties' => [
            'target' => 'inmuebles',
            'label' => 'Ver todas las oportunidades',
            'query' => ['oportunidad' => '1'],
        ],
        'featured_projects' => ['target' => 'proyectos', 'label' => 'Ver todos los proyectos'],
    ];

    /**
     * Qué listados dinámicos admiten un texto descriptivo bajo el título.
     *
     * No todos: el compilador escribe sólo lo que el schema del tipo declara, y
     * mandar un `body` a un tipo que no lo tiene haría fallar el guardado
     * entero.
     */
    private const CON_DESCRIPCION = ['opportunity_properties'];

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    public function compile(FrontendSection $section, array $state): ?array
    {
        return match ($section->type) {
            'hero' => $this->hero($section, $state),
            'cta' => $this->cta($state),
            'rich_text' => $this->richText($section, $state),
            'values' => $this->values($state),
            'metrics' => $this->metrics($state),
            'partners' => $this->partners($section, $state),
            'audience_outcomes' => $this->audienceOutcomes($state),
            'capability_cards' => $this->capabilityCards($state),
            'team' => $this->team($section, $state),
            'feature_sequence' => $this->featureSequence($section, $state),
            'service_list' => $this->texts($state, ['eyebrow', 'title']),
            'featured_properties', 'opportunity_properties', 'featured_projects' => $this->dynamicList($section, $state),
            // Un tipo que todavía se edita como JSON no pasa por acá.
            default => null,
        };
    }

    /** Qué tipos tienen formulario amigable hoy. El resto conserva el editor JSON. */
    public function handles(string $type): bool
    {
        return in_array($type, [
            'hero', 'cta', 'rich_text', 'values', 'metrics', 'partners', 'audience_outcomes',
            'team', 'feature_sequence',
            'service_list', 'featured_properties', 'opportunity_properties', 'featured_projects',
            'capability_cards',
        ], true);
    }

    // ------------------------------------------------------------- hero -----

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function hero(FrontendSection $section, array $state): array
    {
        $payload = $this->texts($state, ['title', 'subtitle', 'eyebrow']);

        foreach (['primary_cta', 'secondary_cta'] as $cta) {
            if ($resolved = $this->ctaOrNull($state[$cta] ?? null)) {
                $payload[$cta] = $resolved;
            }
        }

        $payload['text_align'] = $state['text_align'] ?? 'left';
        $payload['logo_enabled'] = ($state['logo_enabled'] ?? false) === true;
        $payload['logo_size'] = $state['logo_size'] ?? 'md';

        // El logo PROPIO (hero-logo-propio). Se OMITE si el owner no subió
        // nada: ausente es «no inicializado» (D4), igual que `slides` —
        // fijar un objeto vacío competiría con el fallback de la página sin
        // que el owner lo haya decidido.
        if ($logo = $this->heroLogo($section, $state)) {
            $payload['logo'] = $logo;
        }

        $payload['slides'] = $this->slides($section, is_array($state['slides'] ?? null) ? $state['slides'] : []);

        return $payload;
    }

    /**
     * El logo PROPIO del hero, independiente del logo de marca del sitio.
     *
     * Va bajo `media_id` como cualquier otra imagen del sistema — no por
     * gusto: la validación, la promoción al publicar y el reporte de
     * huérfanas recorren el payload buscando exactamente esa clave (misma
     * razón que `spotlight()`).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function heroLogo(FrontendSection $section, array $state): array
    {
        $logoState = is_array($state['logo'] ?? null) ? $state['logo'] : [];

        if ($mediaId = $this->mediaId($section, $logoState)) {
            return ['media_id' => $mediaId] + $this->texts($logoState, ['alt']);
        }

        return [];
    }

    /**
     * @param  array<int|string, mixed>  $slides
     * @return list<array<string, mixed>>
     */
    private function slides(FrontendSection $section, array $slides): array
    {
        $compiled = [];

        foreach (array_values($slides) as $index => $slide) {
            if (! is_array($slide)) {
                continue;
            }

            $mediaId = $this->mediaId($section, $slide);

            if ($mediaId === null) {
                continue;
            }

            $decorative = ($slide['decorative'] ?? true) === true;
            $alt = trim((string) ($slide['alt'] ?? ''));

            $compiled[] = [
                'media_id' => $mediaId,
                'alt' => $decorative ? null : ($alt !== '' ? $alt : null),
                'decorative' => $decorative,
                // Lo que el owner ve ES el orden; el render nunca depende de la
                // posición del array (§7.5).
                'sort_order' => $index,
            ];
        }

        return $compiled;
    }

    // -------------------------------------------------- tipos sin media -----

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function cta(array $state): array
    {
        $payload = $this->texts($state, ['eyebrow', 'title', 'body']);

        // Sin elegir, el título sigue saliendo del juego de tinta que decide el
        // fondo — que es lo que garantiza que se lea. Por eso no se guarda un
        // default: guardarlo desactivaría esa garantía para siempre.
        if (($tituloColor = trim((string) ($state['title_color'] ?? ''))) !== '') {
            $payload['title_color'] = $tituloColor;
        }

        foreach (['primary_cta', 'secondary_cta'] as $cta) {
            if ($resolved = $this->ctaOrNull($state[$cta] ?? null)) {
                $payload[$cta] = $resolved;
            }
        }

        // El fondo. Siempre presente: la paleta es cerrada y el schema la valida,
        // así que un valor inventado rebota antes de llegar a una clase.
        $payload['background_color'] = $state['background_color'] ?? 'primary';

        // Los datos destacados de la derecha. `rows()` descarta la fila a la que
        // le falta un campo, así que una fila a medio llenar no publica un dato
        // sin su explicación —o al revés— en el sitio.
        //
        // La clave se OMITE si no quedó ninguna: el payload canónico no lleva
        // listas vacías, y además es lo que distingue la tarjeta partida de la
        // centrada de siempre.
        if ($bullets = $this->rows($state['bullets'] ?? null, ['value', 'text'])) {
            $payload['bullets'] = array_slice($bullets, 0, 5);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function richText(FrontendSection $section, array $state): array
    {
        // `body` es obligatorio en el schema; el formulario también lo exige.
        $payload = $this->texts($state, ['eyebrow', 'title', 'body']);

        // La foto es opcional. Si la hay, el `alt` deja de serlo — lo impone la
        // regla universal de accesibilidad del schema, y el formulario lo pide
        // en el mismo momento para que no se entere recién al guardar.
        if ($mediaId = $this->mediaId($section, $state)) {
            $payload['media_id'] = $mediaId;
            $payload += $this->texts($state, ['alt']);

            // La disposición sólo se guarda CON foto: sin imagen no hay nada
            // que ubicar, y una clave suelta invitaría a creer que hace algo.
            $payload['layout'] = in_array($state['layout'] ?? null, (array) config('frontend-sections.feature_sequence_layouts'), true)
                ? $state['layout']
                : 'split_media_end';
        }

        // A la izquierda por defecto: es como se ven los cierres y la entrada de
        // contacto publicados hasta hoy, así que guardar sin tocar la alineación
        // no le mueve el texto a nadie.
        $payload['text_align'] = $state['text_align'] ?? 'left';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function values(array $state): array
    {
        // Se recorre el ESTADO y no el resultado de `rows()`: ese helper exige
        // todos los campos que se le nombran —y el ícono es opcional— y además
        // reindexa al descartar una fila incompleta, así que emparejar por
        // índice le habría puesto a un valor el ícono de otro.
        $items = [];

        foreach (array_values(is_array($state['items'] ?? null) ? $state['items'] : []) as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $valor = $this->texts($fila, ['title', 'description']);

            // Sin título o sin descripción la fila está a medio llenar.
            if (count($valor) !== 2) {
                continue;
            }

            if (($icono = trim((string) ($fila['icon'] ?? ''))) !== '') {
                $valor['icon'] = $icono;
            }

            $items[] = $valor;
        }

        return $this->texts($state, ['eyebrow', 'title']) + $this->iconColors($state) + [
            // Por defecto, el fondo del SITIO: así una sección que nadie tocó se
            // ve exactamente como antes, en vez de quedar clavada a un blanco
            // que el cliente podría no estar usando.
            'background_color' => $state['background_color'] ?? 'site',
            // APAGADO por defecto: es como se ve toda sección publicada hasta
            // hoy —texto suelto de a cuatro—, así que guardar sin tocar nada no
            // le cambia el aspecto a nadie (§16.7). Encenderlo es una decisión
            // explícita del owner.
            'as_cards' => ($state['as_cards'] ?? false) === true,
            // El aspecto de la tarjeta, cuando la hay. Los defaults reproducen
            // el de «¿Qué incluye?» en Inversionistas: azulado muy claro con un
            // contorno tenue del principal.
            'card_bg_color' => $state['card_bg_color'] ?? 'navy',
            'card_border' => ($state['card_border'] ?? true) === true,
            'card_border_width' => (int) ($state['card_border_width'] ?? 1),
            'card_border_color' => $state['card_border_color'] ?? 'primary-l2',
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function metrics(array $state): array
    {
        $payload = [
            // El azulado de siempre: una banda que nadie tocó tiene que verse
            // igual que antes de que existiera el selector.
            'background_color' => $state['background_color'] ?? 'navy',
            'items' => $this->rows($state['items'] ?? null, ['label', 'value']),
        ];

        // El color de la cifra se guarda SÓLO si el owner lo eligió. Sin la
        // clave, la vista lo deduce del fondo — y esa deducción no se puede
        // congelar acá: el token que usa sobre fondo oscuro es el foreground
        // garantizado del contrato, que no es un color de la paleta y por lo
        // tanto no es algo que se pueda elegir.
        if (($color = trim((string) ($state['value_color'] ?? ''))) !== '') {
            $payload['value_color'] = $color;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function partners(FrontendSection $section, array $state): array
    {
        // Sin traducción: el formulario produce exactamente la forma del SPECS.
        // La versión anterior usaba un repeater `simple()` y convertía acá de
        // lista plana a objetos — y esa conversión existía en UN solo sentido,
        // así que al abrir el editor los datos volvían mal.
        $items = [];

        foreach (array_values(is_array($state['items'] ?? null) ? $state['items'] : []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));

            // Sin nombre no hay aliado, aunque haya subido el logo: el nombre es
            // lo que lo identifica y lo que describe su imagen.
            if ($name === '') {
                continue;
            }

            $row = ['name' => $name];

            // El logo es opcional. Si lo hay, su texto alternativo ES el nombre
            // del aliado: preguntarlo aparte sería pedir dos veces lo mismo para
            // que la segunda quede peor. Así se cumple §16.1.1 sin que el owner
            // tenga que enterarse de que existe la regla.
            if ($mediaId = $this->mediaId($section, $item)) {
                $row['media_id'] = $mediaId;
                $row['alt'] = $name;
            }

            $items[] = $row;
        }

        return [
            'card_border' => ($state['card_border'] ?? false) === true,
            // El grosor y el color viajan siempre, aunque el borde esté apagado:
            // así apagar y volver a encender no pierde la elección del owner.
            'card_border_width' => (int) ($state['card_border_width'] ?? 1),
            'card_border_color' => $state['card_border_color'] ?? 'accent',
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function audienceOutcomes(array $state): array
    {
        $result = is_array($state['result'] ?? null) ? $state['result'] : [];

        return $this->texts($state, ['eyebrow', 'title']) + [
            'audience_items' => $this->stringList($state['audience_items'] ?? null),
            // `result` es un objeto OBLIGATORIO en el schema: siempre viaja,
            // aunque sus campos internos sean opcionales.
            'result' => $this->texts($result, ['eyebrow', 'title', 'quote']) + [
                'items' => $this->stringList($result['items'] ?? null),
            ],
        ];
    }

    // -------------------------------------------------- tipos con media -----

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function team(FrontendSection $section, array $state): array
    {
        $members = [];

        foreach (array_values(is_array($state['members'] ?? null) ? $state['members'] : []) as $member) {
            if (! is_array($member)) {
                continue;
            }

            $name = trim((string) ($member['name'] ?? ''));

            // Sin nombre no hay integrante: la fila está a medio llenar.
            if ($name === '') {
                continue;
            }

            $row = ['name' => $name] + $this->texts($member, ['role']);

            // La foto es OPCIONAL acá: un integrante sin retrato es válido. Pero
            // si la hay, el `alt` deja de serlo — lo impone la regla universal
            // de accesibilidad del schema, y el formulario lo pide igual.
            if ($mediaId = $this->mediaId($section, $member)) {
                $row['media_id'] = $mediaId;
                $row += $this->texts($member, ['alt']);
            }

            $members[] = $row;
        }

        $payload = $this->texts($state, ['eyebrow', 'title']) + [
            // Siempre presentes: la paleta es cerrada y el schema la valida, así
            // que un valor inventado rebota antes de llegar a una clase.
            'background_color' => $state['background_color'] ?? 'neutral-1',
            'title_color' => $state['title_color'] ?? 'primary',
            'members' => $members,
        ];

        // El destacado. Se OMITE entero si quedó vacío: el payload canónico no
        // lleva objetos vacíos, y el render trataría uno como contenido.
        if ($spotlight = $this->spotlight($section, is_array($state['spotlight'] ?? null) ? $state['spotlight'] : [])) {
            $payload['spotlight'] = $spotlight;
        }

        return $payload;
    }

    /**
     * El bloque destacado del equipo: una división con su propia identidad.
     *
     * Su logo va bajo la clave `media_id` como el de cualquier otra imagen del
     * sistema — no por gusto: la validación, la promoción al publicar y el
     * reporte de huérfanas recorren el payload buscando exactamente esa clave.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function spotlight(FrontendSection $section, array $state): array
    {
        $spotlight = $this->texts($state, ['eyebrow', 'title', 'body']);

        if ($mediaId = $this->mediaId($section, $state)) {
            $spotlight['media_id'] = $mediaId;
            $spotlight += $this->texts($state, ['alt']);
        }

        return $spotlight;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function featureSequence(FrontendSection $section, array $state): array
    {
        $panels = [];

        foreach (array_values(is_array($state['items'] ?? null) ? $state['items'] : []) as $panel) {
            if (! is_array($panel)) {
                continue;
            }

            $title = trim((string) ($panel['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $row = ['title' => $title] + $this->texts($panel, ['eyebrow', 'body', 'alt']);
            $row['layout'] = $panel['layout'] ?? null;

            // A diferencia de `team`, acá la imagen es OBLIGATORIA. Un panel sin
            // ella NO se descarta: se emite sin `media_id` para que el schema lo
            // rechace y el owner se entere, en vez de ver desaparecer su panel.
            if ($mediaId = $this->mediaId($section, $panel)) {
                $row['media_id'] = $mediaId;
            }

            $panels[] = $row;
        }

        return $this->texts($state, ['eyebrow', 'title']) + ['items' => $panels];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    /**
     * El color de la placa del ícono y el del dibujo.
     *
     * El de la PLACA se guarda siempre —sin elegir, el azulado de siempre— y el
     * del DIBUJO sólo si el owner lo eligió. Es la misma regla que las cifras de
     * la banda de métricas, y por el mismo motivo: sin elección el dibujo sigue
     * al fondo de su placa, porque una placa oscura con el dibujo en el color
     * principal deja el ícono invisible. Y el token que usa sobre placa oscura
     * es el foreground garantizado del contrato, que no es un color de la paleta
     * y por lo tanto no se puede congelar acá.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function iconColors(array $state): array
    {
        $out = ['icon_bg_color' => $state['icon_bg_color'] ?? 'navy'];

        if (($color = trim((string) ($state['icon_color'] ?? ''))) !== '') {
            $out['icon_color'] = $color;
        }

        return $out;
    }

    private function capabilityCards(array $state): array
    {
        return $this->texts($state, ['eyebrow', 'title', 'body']) + $this->iconColors($state) + [
            // Centrado por defecto: es como se ve hoy en el sitio publicado, y
            // un encabezado sin alineación explícita no debería cambiar de
            // aspecto sólo por haberse guardado.
            'text_align' => $state['text_align'] ?? 'center',
            'card_border' => ($state['card_border'] ?? false) === true,
            // El grosor viaja siempre, aunque el borde esté apagado: así apagar y
            // volver a encender no pierde la elección del owner.
            'card_border_width' => (int) ($state['card_border_width'] ?? 1),
            'card_border_color' => $state['card_border_color'] ?? 'accent',
            // La descripción es opcional, así que una tarjeta se conserva con
            // sólo su título: `rows()` descartaría la fila entera por un campo
            // vacío que el schema acepta.
            'items' => $this->optionalRows($state['items'] ?? null, 'title', ['description', 'icon']),
        ];
    }

    // ------------------------------------------------- tipos dinámicos -----

    /**
     * Un listado dinámico: SÓLO parámetros de presentación.
     *
     * Acá no se compila ningún ítem, y esa ausencia es el contrato: las
     * propiedades y los proyectos los resuelve el kernel en CADA render desde su
     * autoridad (`Property::featured()`, `Project::is_featured`). Si el payload
     * pudiera fijarlos, un destacado que se da de baja seguiría apareciendo en
     * el sitio hasta que alguien se acordara de republicar la página.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function dynamicList(FrontendSection $section, array $state): array
    {
        $payload = $this->texts(
            $state,
            in_array($section->type, self::CON_DESCRIPCION, true) ? ['eyebrow', 'title', 'body'] : ['eyebrow', 'title'],
        );
        $limit = $state['limit'] ?? null;

        // Ausente significa «el que decida el render», no cero.
        if (is_numeric($limit)) {
            $payload['limit'] = (int) $limit;
        }

        // El botón al catálogo completo. El DESTINO lo pone el compilador, no el
        // owner: «el catálogo» es una sola página del sitio, así que preguntarlo
        // sería ofrecerle equivocarse en algo que no tiene alternativa. Lo que
        // sí elige es cómo se llama el botón.
        if ($destino = self::CATALOGO[$section->type] ?? null) {
            $etiqueta = trim((string) ($state['cta_label'] ?? ''));

            $payload['primary_cta'] = array_filter([
                'label' => $etiqueta !== '' ? $etiqueta : $destino['label'],
                'type' => 'route',
                'target' => $destino['target'],
                // El filtro, cuando el destino lo lleva. Sale de acá y nunca del
                // formulario, y el resolver igual lo valida contra su propia
                // lista cerrada.
                'query' => $destino['query'] ?? null,
            ]);
        }

        // El logo del autor y el fondo de su banda, sólo en `featured_projects`
        // — es el único listado dinámico con una tarjeta destacada propia.
        if ($section->type === 'featured_projects') {
            if ($mediaId = $this->mediaId($section, $state)) {
                $payload['media_id'] = $mediaId;
                $payload += $this->texts($state, ['alt']);
            }

            // Sin elegir, sigue el gesto automático de siempre: banda gris
            // cuando hay logo —separa la tarjeta blanca del fondo—, fondo del
            // sitio cuando no lo hay, que es como se ve toda sección publicada
            // antes de que este selector existiera.
            $payload['background_color'] = $state['background_color'] ?? ($mediaId ? 'neutral-1' : 'site');
        }

        return $payload;
    }

    // ---------------------------------------------------------- helpers -----

    /**
     * El `media_id` de una fila: el que ya tenía, o el del archivo recién subido,
     * o NINGUNO si el owner pidió quitar la imagen.
     *
     * Es el ÚNICO punto donde un upload se convierte en media, compartido por el
     * hero, `team` y `feature_sequence`. `addMediaFromDisk` MUEVE el temporal a
     * la colección (sin `preservingOriginal`), así no queda duplicado ni sobra un
     * archivo suelto en el disco privado.
     *
     * Quitar la imagen sólo la SACA DEL PAYLOAD: el archivo no se borra. Es la
     * misma regla que rige todo el módulo (§18.18) y no un olvido — una revisión
     * ya publicada puede seguir apuntando a esa media, y borrarla dejaría el
     * sitio con un hueco. La limpieza es tarea del reporte de huérfanas.
     *
     * @param  array<string, mixed>  $row
     */
    private function mediaId(FrontendSection $section, array $row): ?string
    {
        // Gana sobre todo lo demás, incluido un archivo recién elegido: si el
        // owner marcó quitar Y subió algo, lo último que hizo fue pedir que no
        // haya imagen.
        if (($row['remove_media'] ?? false) === true) {
            return null;
        }

        $mediaId = is_string($row['media_id'] ?? null) && $row['media_id'] !== '' ? $row['media_id'] : null;
        $upload = $row['upload'] ?? null;

        // Filament entrega un archivo suelto como ruta o como array de un
        // elemento según el contexto; ambos son el mismo archivo.
        if (is_array($upload)) {
            $upload = reset($upload) ?: null;
        }

        if (is_string($upload) && $upload !== '') {
            // Se optimiza ANTES de adjuntar: el archivo que queda en la
            // colección ya es el definitivo. Procesarlo después obligaría a
            // reemplazar una media viva, y una conversión de Spatie rompería el
            // contrato de promoción, que mueve un solo archivo por media.
            $optimizada = app(OptimizeSectionImage::class)($upload);

            $mediaId = (string) $section->addMediaFromDisk($optimizada, 'frontend-private')
                ->toMediaCollection('images')->uuid;
        }

        return $mediaId;
    }

    /**
     * Los campos de texto presentes y no vacíos, ya recortados — y el PESO de
     * los que lo admiten.
     *
     * El peso viaja acá y no en cada tipo por un motivo concreto: los doce tipos
     * con título pasan todos por este helper, así que es el único lugar donde la
     * regla se escribe una vez. Declararla tipo por tipo habría sido copiarla
     * doce veces y perderla en el que se agregue mañana.
     *
     * @param  array<string, mixed>  $state
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function texts(array $state, array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            $value = trim((string) ($state[$field] ?? ''));

            if ($value !== '') {
                $out[$field] = $value;
            }
        }

        foreach (['title' => 'title_bold', 'eyebrow' => 'eyebrow_bold'] as $campo => $peso) {
            if (! in_array($campo, $fields, true)) {
                continue;
            }

            // AUSENTE significa «lo que diga la configuración del sitio», así que
            // no elegir no se guarda. Guardar el valor global acá lo congelaría:
            // a partir de ese momento cambiar la configuración no movería nada,
            // que es exactamente lo que el owner quiso evitar al configurarlo en
            // un solo lugar.
            $valor = $state[$peso] ?? null;

            if ($valor === null || $valor === '') {
                continue;
            }

            // El selector devuelve '1'/'0' —son claves de opciones, texto— y la
            // hidratación devuelve el booleano guardado. Los dos casos tienen que
            // terminar en el mismo booleano.
            $out[$peso] = (bool) (int) $valor;
        }

        return $out;
    }

    /**
     * Filas de un repeater, conservando sólo las claves permitidas y
     * descartando las que no están completas.
     *
     * En los tres tipos que la usan (`values`, `metrics`, `partners`) TODOS los
     * campos de la fila son obligatorios en su SPECS, así que «incompleta» es
     * «le falta cualquiera»: dejar pasar media fila haría fallar el guardado
     * entero contra el schema y el owner perdería el resto de su trabajo.
     *
     * @param  list<string>  $fields
     * @return list<array<string, string>>
     */
    private function rows(mixed $rows, array $fields): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];

        foreach (array_values($rows) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean = $this->texts($row, $fields);

            if (count($clean) !== count($fields)) {
                continue;
            }

            $out[] = $clean;
        }

        return $out;
    }

    /**
     * Filas cuyo campo obligatorio es UNO y el resto opcional. Se descarta la
     * fila sin ese campo; los opcionales vacíos se omiten, no se guardan como
     * cadena vacía.
     *
     * @param  list<string>  $optional
     * @return list<array<string, string>>
     */
    private function optionalRows(mixed $rows, string $required, array $optional): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];

        foreach (array_values($rows) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean = $this->texts($row, [$required, ...$optional]);

            if (($clean[$required] ?? '') === '') {
                continue;
            }

            $out[] = $clean;
        }

        return $out;
    }

    /**
     * Una lista plana de textos. Un repeater `simple()` ya entrega este formato;
     * esto sólo limpia vacíos y recorta, para que el schema no reciba ruido.
     *
     * @return list<string>
     */
    private function stringList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];

        foreach ($items as $item) {
            // Un repeater no-simple entregaría ['campo' => 'texto']; se acepta
            // por robustez, pero la UI usa `simple()`.
            $value = is_array($item) ? reset($item) : $item;
            $value = trim((string) $value);

            if ($value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * Un CTA con destino, o null. Un CTA vacío está AUSENTE, no a medio armar:
     * el schema rechazaría `{label:'', type:null}` y el owner nunca pidió botón.
     *
     * @return array<string, mixed>|null
     */
    private function ctaOrNull(mixed $cta): ?array
    {
        if (! is_array($cta) || trim((string) ($cta['target'] ?? '')) === '') {
            return null;
        }

        return [
            'label' => trim((string) ($cta['label'] ?? '')),
            'type' => $cta['type'] ?? null,
            'target' => trim((string) $cta['target']),
        ];
    }
}

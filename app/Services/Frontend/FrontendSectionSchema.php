<?php

namespace App\Services\Frontend;

use App\Support\Frontend\CtaResolver;

/**
 * CLOSED payload validation per section type (RFC-075, §16.1.1). The JSON is
 * never free-form: each type has an explicit shape and anything outside it —
 * an unknown key, a wrong value type, too many list items, an unsafe CTA, an
 * unlisted layout — is rejected. The same validator runs on draft edit and on
 * publish.
 *
 * The shape spec language, per field:
 *   'string' | 'int' | 'bool'      required scalar of that type
 *   'int_min0'                     required non-negative integer
 *   'media'                        required media_id string (uuid reference)
 *   '?string' | '?int' | '?bool'   optional scalar
 *   '?cta'                         optional nested value object {label,type,target}
 *   '?media' | '?layout'           optional media_id / allowlisted layout
 *   '?string_list'                 optional list of strings
 *   ['list', max, itemFields]      optional list of objects, each of itemFields
 *   ['object', fields]             optional nested object of `fields`
 *   ['object!', fields]            REQUIRED nested object of `fields`
 */
class FrontendSectionSchema
{
    public function __construct(private readonly CtaResolver $resolver) {}

    /** The closed shape of each section type's payload. */
    private const SPECS = [
        'hero' => [
            'eyebrow' => '?string', 'title' => 'string', 'subtitle' => '?string',
            'title_bold' => '?bool', 'eyebrow_bold' => '?bool',
            'primary_cta' => '?cta', 'secondary_cta' => '?cta',
            // A slide always names its image and its order; the alt/decorative
            // pairing is enforced by a cross-field rule below.
            'slides' => ['list', 6, ['media_id' => 'media', 'alt' => '?string', 'decorative' => '?bool', 'sort_order' => 'int_min0']],
            // Presentation (Épica 12.1 §6.1). All three OPTIONAL, so every
            // snapshot published before this increment stays valid; the render
            // applies left / false / md when they are absent.
            'text_align' => '?text_align', 'logo_enabled' => '?bool', 'logo_size' => '?logo_size',
            // The hero's OWN logo (domain hero-logo-propio), independent of the
            // site's brand logo. Nested and not a flat `hero_logo_media_id` for
            // the same reason as `team.spotlight`: `mediaIds()` finds every
            // image by walking the payload for the literal key `media_id`, so a
            // flat key would be invisible to eligibility, promotion and the
            // orphan report. ABSENT is "not initialised" — identical to
            // `slides` — and every hero published before this key existed has
            // no `logo`, so nothing invalidates.
            'logo' => ['object', ['media_id' => '?media', 'alt' => '?string']],
        ],
        // El antetítulo y la foto son OPCIONALES: este tipo también arma la
        // entrada de contacto, que no los usa, y exigirlos la dejaría inválida
        // de golpe. Sin foto el texto ocupa todo el ancho, como hasta ahora.
        // `text_align` es el mismo token cerrado que ya usan el hero y
        // `capability_cards`. Opcional: sin la clave el render alinea a la
        // izquierda, que es como se ven los snapshots publicados hasta hoy.
        // `layout` reusa el mismo token —y por lo tanto la misma allowlist de
        // tres— que `feature_sequence`: es la misma decisión sobre la misma
        // foto, y dos vocabularios para eso obligarían al owner a aprender la
        // diferencia dos veces. Sólo tiene efecto cuando hay imagen.
        'rich_text' => ['eyebrow' => '?string', 'title' => '?string', 'title_bold' => '?bool', 'eyebrow_bold' => '?bool', 'body' => 'string', 'media_id' => '?media', 'alt' => '?string', 'text_align' => '?text_align', 'layout' => '?layout'],
        'metrics' => ['background_color' => '?palette_color', 'value_color' => '?palette_color', 'items' => ['list', 12, ['label' => 'string', 'value' => 'string']]],
        // El ícono es OPCIONAL: los valores que ya estaban publicados no lo
        // tienen, y exigirlo los dejaría inválidos de golpe. Sin ícono el valor
        // se dibuja igual, sólo que sin su placa.
        // `as_cards` elige entre los DOS tratamientos que este tipo ya tenía en
        // el sitio: «Nuestros valores» los muestra como texto suelto de a
        // cuatro, y «¿Qué incluye?» como tarjetas de a dos. Era la misma
        // sección viéndose de dos formas, así que la diferencia pasa a ser una
        // decisión del owner en vez de un detalle cableado en cada página.
        //
        // Las `card_*` son las mismas cuatro claves que usa `capability_cards`,
        // con el mismo significado: compartir los nombres evita dos
        // vocabularios para lo mismo. Todas OPCIONALES — los payloads ya
        // publicados no las traen, y sin ellas la sección se ve como hoy.
        'values' => ['eyebrow' => '?string', 'title' => '?string', 'title_bold' => '?bool', 'eyebrow_bold' => '?bool', 'background_color' => '?palette_color', 'icon_bg_color' => '?palette_color', 'icon_color' => '?palette_color', 'as_cards' => '?bool', 'card_bg_color' => '?palette_color', 'card_border' => '?bool', 'card_border_width' => '?border_width', 'card_border_color' => '?palette_color', 'items' => ['list', 12, ['title' => 'string', 'description' => 'string', 'icon' => '?icon']]],
        // El DESTACADO es un objeto anidado y no tres claves sueltas
        // `spotlight_*`, y la razón no es de estilo: TODO el pipeline de
        // imágenes —validación de elegibilidad, promoción al publicar y el
        // reporte de huérfanas— encuentra las fotos recorriendo el payload en
        // busca de la clave `media_id`. Un `spotlight_media_id` plano habría
        // sido invisible para los tres: nunca se validaba, nunca se publicaba, y
        // el reporte lo habría dado por borrable. Anidado, el logo entra en la
        // misma convención que el resto y hereda la regla de accesibilidad.
        'team' => [
            'eyebrow' => '?string', 'title' => '?string',
            'title_bold' => '?bool', 'eyebrow_bold' => '?bool',
            // El fondo de la banda y el color del encabezado, de la misma paleta
            // cerrada que todo lo demás. Opcionales: sin elegir, el render usa
            // el gris claro y la tinta principal, que es como se ve hoy.
            'background_color' => '?palette_color', 'title_color' => '?palette_color',
            'spotlight' => ['object', ['eyebrow' => '?string', 'title' => '?string', 'body' => '?string', 'media_id' => '?media', 'alt' => '?string']],
            'members' => ['list', 24, ['name' => 'string', 'role' => '?string', 'media_id' => '?media', 'alt' => '?string']],
        ],
        // Aliados. El LOGO es opcional: los que ya estaban cargados sólo tienen
        // nombre, y exigir imagen los dejaría inválidos de golpe. Sin logo, la
        // tarjeta muestra el nombre.
        //
        // El `alt` no se lo pedimos al owner: para el logotipo de un aliado, su
        // texto alternativo ES el nombre del aliado, y preguntarlo aparte sería
        // pedir dos veces lo mismo para que la segunda quede peor. Lo escribe el
        // compilador, que es lo que satisface la regla universal de §16.1.1.
        'partners' => [
            'card_border' => '?bool', 'card_border_width' => '?border_width',
            'card_border_color' => '?palette_color',
            'items' => ['list', 24, ['name' => 'string', 'media_id' => '?media', 'alt' => '?string']],
        ],
        // Encabezado editorial + tarjetas propias. Entre 1 y 8: con una sola la
        // sección sigue teniendo sentido (ocupa todo el ancho) y más de ocho deja
        // de leerse como un resumen y pasa a ser un catálogo.
        'capability_cards' => [
            'eyebrow' => '?string', 'title' => '?string', 'body' => '?string',
            'title_bold' => '?bool', 'eyebrow_bold' => '?bool',
            // Alineación del ENCABEZADO. Las tarjetas conservan su propia
            // alineación: son bloques, no texto corrido.
            'text_align' => '?text_align',
            'card_border' => '?bool', 'card_border_width' => '?border_width',
            'card_border_color' => '?palette_color',
            'icon_bg_color' => '?palette_color', 'icon_color' => '?palette_color',
            'items' => ['list!', 1, 8, ['title' => 'string', 'description' => '?string', 'icon' => '?icon']],
        ],
        'feature_sequence' => [
            'eyebrow' => '?string', 'title' => '?string',
            'title_bold' => '?bool', 'eyebrow_bold' => '?bool',
            // At least one panel; each panel is a captioned media image.
            'items' => ['list!', 1, 8, ['eyebrow' => '?string', 'title' => 'string', 'body' => '?string', 'media_id' => 'media', 'alt' => '?string', 'layout' => 'layout']],
        ],
        'audience_outcomes' => [
            'eyebrow' => '?string', 'title' => '?string',
            'title_bold' => '?bool', 'eyebrow_bold' => '?bool',
            // audience_items and result.items are part of the required composition.
            'audience_items' => 'string_list',
            'result' => ['object!', ['eyebrow' => '?string', 'title' => '?string', 'items' => 'string_list', 'quote' => '?string']],
        ],
        // `bullets` parte la tarjeta en dos: el texto a la izquierda y los datos
        // destacados a la derecha. Es OPCIONAL y sin mínimo a propósito — el
        // mismo tipo `cta` cierra cuatro páginas más, y exigir bullets dejaría
        // esos cierres inválidos de golpe. Sin bullets se sigue viendo como
        // hasta hoy, centrado y a todo el ancho.
        //
        // `background_color` sale de la MISMA paleta cerrada que el borde de las
        // tarjetas. También opcional: sin elegir, la tarjeta usa el color
        // principal de la marca, que es como se ve hoy.
        'cta' => [
            'eyebrow' => '?string', 'title' => '?string', 'body' => '?string',
            'title_bold' => '?bool', 'eyebrow_bold' => '?bool',
            'primary_cta' => '?cta', 'secondary_cta' => '?cta',
            'background_color' => '?palette_color', 'title_color' => '?palette_color',
            'bullets' => ['list', 5, ['value' => 'string', 'text' => 'string']],
        ],
        // Dynamic types resolve their items in the kernel; the payload only
        // carries allowlisted presentation parameters, never a query or ids.
        'service_list' => ['title' => '?string', 'eyebrow' => '?string', 'title_bold' => '?bool', 'eyebrow_bold' => '?bool'],
        // `primary_cta` lleva al catálogo completo. Se guarda como el objeto CTA
        // de siempre —y no como un texto suelto— para que pase por el mismo
        // resolver que todos los enlaces del sitio: el destino se valida una vez
        // y en un solo lugar. Lo que el owner elige es SÓLO la etiqueta; a dónde
        // va lo fija el compilador, porque «el catálogo» es una sola página.
        'featured_properties' => ['title' => '?string', 'eyebrow' => '?string', 'title_bold' => '?bool', 'eyebrow_bold' => '?bool', 'limit' => '?int', 'primary_cta' => '?cta'],
        // Su botón lleva al catálogo FILTRADO a oportunidades. El filtro viaja
        // dentro del CTA y el resolver sólo acepta los que tiene en su propia
        // lista cerrada: un parámetro libre en la URL sería texto del owner
        // llegando a una consulta.
        'opportunity_properties' => ['title' => '?string', 'eyebrow' => '?string', 'title_bold' => '?bool', 'eyebrow_bold' => '?bool', 'body' => '?string', 'limit' => '?int', 'primary_cta' => '?cta'],
        'featured_projects' => ['title' => '?string', 'eyebrow' => '?string', 'title_bold' => '?bool', 'eyebrow_bold' => '?bool', 'limit' => '?int', 'primary_cta' => '?cta', 'media_id' => '?media', 'alt' => '?string', 'background_color' => '?palette_color'],
    ];

    public function isAllowedType(string $type): bool
    {
        return array_key_exists($type, (array) config('frontend-sections.types'));
    }

    public function isCanonicalSection(string $pageKey, string $sectionKey, string $type): bool
    {
        return (config("frontend-sections.pages.{$pageKey}.{$sectionKey}") ?? null) === $type;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return list<string> validation errors ([] means valid)
     */
    public function validate(string $type, ?array $payload): array
    {
        if (! $this->isAllowedType($type)) {
            return ["El tipo de sección «{$type}» no está permitido."];
        }

        // A section may be an empty draft; page(key) serves the fallback until
        // there is content. Only a populated payload is validated.
        if ($payload === null || $payload === []) {
            return [];
        }

        $spec = self::SPECS[$type] ?? null;
        if ($spec === null) {
            return ["El tipo «{$type}» no tiene un esquema de validación."];
        }

        $errors = $this->checkObject($payload, $spec, $type);

        // Cross-field rule: a decorative slide (the default) must have alt=null;
        // a non-decorative one must carry alt text (§16.1.1 accessibility).
        if ($type === 'hero') {
            foreach ($payload['slides'] ?? [] as $i => $slide) {
                if (! is_array($slide)) {
                    continue;
                }
                $decorative = $slide['decorative'] ?? true;
                $alt = $slide['alt'] ?? null;
                if ($decorative === false && (! is_string($alt) || trim($alt) === '')) {
                    $errors[] = "El slide {$i} no decorativo requiere texto alternativo (alt).";
                } elseif ($decorative !== false && $alt !== null && $alt !== '') {
                    $errors[] = "El slide {$i} decorativo debe tener alt vacío.";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function checkObject(mixed $value, array $spec, string $context): array
    {
        if (! is_array($value)) {
            return ["«{$context}» debe ser un objeto."];
        }

        $errors = [];

        // Unknown keys are rejected: the payload cannot smuggle fields no
        // renderer will read (C-E1).
        foreach (array_keys($value) as $key) {
            if (! array_key_exists($key, $spec)) {
                $errors[] = "El campo «{$context}.{$key}» no está permitido.";
            }
        }

        foreach ($spec as $field => $fieldSpec) {
            $errors = array_merge($errors, $this->checkField($value[$field] ?? null, $fieldSpec, array_key_exists($field, $value), "{$context}.{$field}"));
        }

        // Universal accessibility rule (épica §16.1.1): any object carrying an
        // image must caption it — a non-empty media_id needs `alt` text or an
        // explicit `decorative: true`. Applies to every media-bearing type
        // (slides, feature_sequence, team), not just the hero.
        if (array_key_exists('media_id', $spec)) {
            $mediaId = $value['media_id'] ?? null;
            if (is_string($mediaId) && trim($mediaId) !== '') {
                $alt = $value['alt'] ?? null;
                if ((! is_string($alt) || trim($alt) === '') && ($value['decorative'] ?? false) !== true) {
                    $errors[] = "«{$context}» tiene una imagen sin texto alternativo; agrega «alt» o marca «decorative».";
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkField(mixed $value, mixed $spec, bool $present, string $path): array
    {
        // Object / list composite specs.
        if (is_array($spec)) {
            if ($spec[0] === 'list') {
                return $present ? $this->checkList($value, 0, $spec[1], $spec[2], $path) : [];
            }
            if ($spec[0] === 'list!') {
                return $present && $value !== null
                    ? $this->checkList($value, $spec[1], $spec[2], $spec[3], $path)
                    : ["El campo «{$path}» es obligatorio."];
            }
            if ($spec[0] === 'object') {
                return $present && $value !== null ? $this->checkObject($value, $spec[1], $path) : [];
            }
            if ($spec[0] === 'object!') {
                return $present && $value !== null
                    ? $this->checkObject($value, $spec[1], $path)
                    : ["El campo «{$path}» es obligatorio."];
            }

            return [];
        }

        $optional = str_starts_with($spec, '?');
        $type = ltrim($spec, '?');

        if (! $present || $value === null) {
            return $optional ? [] : ["El campo «{$path}» es obligatorio."];
        }

        return match ($type) {
            'string' => is_string($value) ? $this->noHtml($value, $path) : ["«{$path}» debe ser texto."],
            'int' => is_int($value) ? [] : ["«{$path}» debe ser un número entero."],
            'int_min0' => is_int($value) && $value >= 0 ? [] : ["«{$path}» debe ser un entero mayor o igual a 0."],
            'bool' => is_bool($value) ? [] : ["«{$path}» debe ser verdadero/falso."],
            // A media_id must be a well-formed uuid: this rejects a malformed
            // reference at the schema, before the eligibility query would crash
            // casting it to a uuid column.
            'media' => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1
                ? []
                : ["«{$path}» debe ser una referencia de imagen válida (uuid)."],
            'layout' => in_array($value, (array) config('frontend-sections.feature_sequence_layouts'), true) ? [] : ["El layout «{$value}» de «{$path}» no está permitido."],
            // El ícono es un ENUM, nunca un valor libre: el render lo mapea a un
            // path fijo, así que uno desconocido dibujaría un hueco.
            'icon' => array_key_exists($value, (array) config('frontend-sections.card_icons')) ? [] : ["El ícono «{$value}» de «{$path}» no está permitido."],
            'border_width' => in_array($value, (array) config('frontend-sections.card_border_widths'), true) ? [] : ["El grosor «{$value}» de «{$path}» no está permitido."],
            'palette_color' => array_key_exists($value, (array) config('frontend-sections.brand_palette')) ? [] : ["El color «{$value}» de «{$path}» no está permitido."],
            // Same closed-enum mechanism as `layout`: the render maps the value to
            // a fixed class, so anything outside the allowlist is rejected here
            // rather than reaching a class name.
            'text_align' => in_array($value, (array) config('frontend-sections.hero_text_aligns'), true) ? [] : ["La alineación «{$value}» de «{$path}» no está permitida."],
            'logo_size' => in_array($value, (array) config('frontend-sections.hero_logo_sizes'), true) ? [] : ["El tamaño de logo «{$value}» de «{$path}» no está permitido."],
            'cta' => $this->checkCta($value, $path),
            'string_list' => $this->checkStringList($value, $path),
            default => [],
        };
    }

    /**
     * @param  array<string, string>  $itemFields
     * @return list<string>
     */
    private function checkList(mixed $value, int $min, int $max, array $itemFields, string $path): array
    {
        if (! is_array($value) || array_is_list($value) === false && $value !== []) {
            return ["«{$path}» debe ser una lista."];
        }

        if (count($value) < $min) {
            return ["«{$path}» requiere al menos {$min} elemento(s)."];
        }

        if (count($value) > $max) {
            return ["«{$path}» admite como máximo {$max} elementos."];
        }

        $errors = [];
        foreach ($value as $i => $item) {
            $errors = array_merge($errors, $this->checkObject($item, $itemFields, "{$path}[{$i}]"));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkStringList(mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return ["«{$path}» debe ser una lista de textos."];
        }

        foreach ($value as $i => $item) {
            if (! is_string($item)) {
                return ["«{$path}[{$i}]» debe ser texto."];
            }
            if ($this->noHtml($item, "{$path}[{$i}]") !== []) {
                return ["«{$path}[{$i}]» no puede incluir HTML."];
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function checkCta(mixed $cta, string $path): array
    {
        // The nested value object {label,type,target} of RFC-073; a flat legacy
        // shape (a string) or an unsafe target does not resolve and is rejected.
        return $this->resolver->resolve($cta) === null
            ? ["El CTA «{$path}» no es válido o su destino no es seguro."]
            : [];
    }

    /**
     * @return list<string>
     */
    private function noHtml(string $value, string $path): array
    {
        return preg_match('/[<>]/', $value) === 1 ? ["«{$path}» no puede incluir HTML."] : [];
    }
}

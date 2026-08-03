<?php

namespace App\Filament\Resources\FrontendPageResource\RelationManagers;

use App\Filament\Forms\Components\CtaFields;
use App\Filament\Forms\Components\SectionImageFields;
use App\Filament\Forms\Components\TypographyFields;
use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Models\FrontendSection;
use App\Services\Frontend\BrandPalette;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendThemeService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * The draft sections of a page (RFC-075). Every edit is routed through
 * FrontendPageContentService so it takes the page lock, validates the payload by
 * type and its media, and bumps draft_revision — the UI never writes JSON
 * directly (a technical rule of §16.1.1). No create/delete: sections are the
 * canonical rows seeded from the registry.
 *
 * The `hero` type is edited with a STRUCTURED form (Épica 12.1 §7.1–§7.5): the
 * owner of an inmobiliaria should not need to know what a JSON is to change a
 * headline. Every other type still falls back to the raw editor until its own
 * batch migrates it (Épica 12.2) — which is why both live here, under DIFFERENT
 * state paths, so a hero payload never round-trips through a string.
 */
class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Secciones';

    public function form(Form $form): Form
    {
        return $form->schema([
            Toggle::make('is_enabled')->label('Visible en la página')
                ->helperText('Apagado, la sección deja de mostrarse sin perder su contenido.'),
            // El orden NO se escribe acá. Pedirle un número al owner era pedirle
            // que adivinara cuál está libre: repetir uno ocupado le mostraba un
            // error crudo de la base, y uno negativo se guardaba sin quejarse y
            // metía la sección arriba de la portada. Se mueve con las flechas
            // del listado, donde el único movimiento posible es válido.

            // SÓLO los campos del tipo que se está editando.
            //
            // Antes se declaraban los de TODOS los tipos y se ocultaban con
            // `visible()`. Eso rompía la hidratación: cinco tipos distintos
            // —values, metrics, partners, feature_sequence, capability_cards—
            // declaran un repeater sobre el MISMO `payload.items`, y varios
            // repeaters sobre un mismo state path se pisan. El resultado era que
            // las filas aparecían pero VACÍAS, así que quien abría «Cifras» veía
            // los campos en blanco y al guardar borraba su contenido.
            //
            // Los campos simples no sufrían el problema —`payload.title` se
            // hidrataba bien aunque lo declararan varios tipos—, y por eso el
            // defecto pasó desapercibido: sólo se rompía en los repeaters.
            ...$this->fieldsForMountedType(),
        ]);
    }

    /**
     * Los campos del tipo de la sección que está abierta, y nada más.
     *
     * @return list<Component>
     */
    private function fieldsForMountedType(): array
    {
        $type = $this->getMountedTableActionRecord()?->type;

        if ($type === null) {
            return [];
        }

        $campos = match (true) {
            $type === 'hero' => $this->heroFields(),
            in_array($type, ['team', 'feature_sequence', 'partners', 'rich_text'], true) => $this->mediaTypeFields($type),
            $type === 'featured_projects' => $this->featuredProjectsFields(),
            in_array($type, ['service_list', 'featured_properties', 'opportunity_properties'], true) => $this->dynamicTypeFields(),
            default => $this->textTypeFields($type),
        };

        // El grosor del encabezado se agrega ACÁ y no en cada bloque de tipo: lo
        // declaran los mismos doce tipos y repetirlo doce veces garantiza que al
        // trece se olvide. `partners` y `metrics` quedan afuera porque no tienen
        // encabezado propio.
        if (in_array($type, self::CON_ENCABEZADO, true)) {
            $campos[] = Fieldset::make('Grosor del encabezado')
                ->columns(2)
                ->schema(TypographyFields::make())
                ->columnSpanFull();
        }

        return $campos;
    }

    /**
     * Los tipos con título y antetítulo propios — los que pueden elegir grosor.
     *
     * Es la misma lista que declara `title_bold`/`eyebrow_bold` en el schema; si
     * se agrega un tipo con encabezado hay que sumarlo en los dos lados, y el
     * test que compara ambas listas es lo que avisa cuando falta uno.
     */
    private const CON_ENCABEZADO = [
        'hero', 'rich_text', 'values', 'team', 'capability_cards', 'feature_sequence',
        'audience_outcomes', 'cta', 'service_list', 'featured_properties',
        'opportunity_properties', 'featured_projects',
    ];

    /**
     * The friendly hero editor. Bound to `payload.*`, so what the form produces
     * IS the canonical payload of `SPECS['hero']` — there is no translation layer
     * that could drift away from the schema.
     *
     * @return list<Component>
     */
    private function heroFields(): array
    {
        $onlyHero = fn (?FrontendSection $record): bool => $record?->type === 'hero';

        return [
            Fieldset::make('Texto')
                ->visible($onlyHero)
                ->columns(1)
                ->schema([
                    TextInput::make('payload.title')->label('Título')->required()->maxLength(120)
                        ->helperText('El encabezado grande sobre la imagen. Es el título principal de la página.'),
                    TextInput::make('payload.subtitle')->label('Subtítulo')->maxLength(200)
                        ->helperText('Una frase corta debajo del título. Opcional.'),
                    TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80)
                        ->helperText('Texto chico en mayúsculas arriba del título. Opcional.'),
                ]),

            Fieldset::make('Botones')
                ->visible($onlyHero)
                ->columns(1)
                ->schema([
                    Fieldset::make('Botón principal')->columns(3)->schema(CtaFields::make('payload.primary_cta')),
                    Fieldset::make('Botón secundario (opcional)')->columns(3)->schema(CtaFields::make('payload.secondary_cta')),
                ]),

            Fieldset::make('Presentación')
                ->visible($onlyHero)
                ->columns(3)
                ->schema([
                    Select::make('payload.text_align')->label('Alineación')->native(false)
                        ->options(['left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha'])
                        ->default('left')
                        ->helperText('Dónde se acomodan el texto y los botones.'),
                    Toggle::make('payload.logo_enabled')->label('Mostrar logotipo')->inline(false)->live()
                        ->helperText('Muestra tu logotipo arriba del título.'),
                    Select::make('payload.logo_size')->label('Tamaño del logotipo')->native(false)
                        ->options(['sm' => 'Chico', 'md' => 'Mediano', 'lg' => 'Grande', 'xl' => 'Muy grande'])
                        ->default('md')
                        ->visible(fn (Get $get): bool => (bool) $get('payload.logo_enabled')),
                ]),

            // El logo PROPIO del hero (hero-logo-propio), independiente del
            // logo de marca del sitio — hoy sólo lo usa /proyectos (A-74
            // Arquitectura), pero queda disponible para cualquier hero.
            //
            // INCONDICIONAL, SIN visible(): un Fieldset con statePath() Y un
            // visible($record) hermano de otro que usa rutas absolutas al
            // mismo `payload` corrompe la hidratación del hermano — mismo
            // defecto real de Filament que featuredProjectsFields() ya evita
            // (D9). heroFields() sólo se invoca para type==='hero', así que
            // la condición ya la puso el match() de fieldsForMountedType().
            Fieldset::make('Logo propio (opcional)')
                ->statePath('payload.logo')
                ->columns(1)
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 12])
                        ->schema([
                            // Apaisado y con mínimos bajos: un logotipo no es
                            // una fotografía, y pedirle medidas de foto
                            // rechazaría el archivo que normalmente existe.
                            ...SectionImageFields::make(
                                $this->preview(),
                                minWidth: 200, minHeight: 80, shape: 'Apaisado, con el fondo transparente si se puede',
                                previewSpan: 3, uploadSpan: 5,
                            ),

                            TextInput::make('alt')->label('Descripción del logo')->maxLength(150)
                                ->placeholder('Para quien no puede verlo')
                                ->required(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                ->visible(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                ->columnSpan(['default' => 1, 'sm' => 4]),
                        ]),
                ]),

            Repeater::make('payload.slides')
                ->label('Fotos de fondo')
                ->columnSpanFull()
                ->visible($onlyHero)
                ->helperText('Si pones más de una, se alternan solas con un fundido. Máximo 6.')
                ->maxItems(6)
                ->reorderable()
                ->addActionLabel('Agregar foto')
                ->columns(['default' => 1, 'sm' => 12])
                ->schema([
                    ...SectionImageFields::make(
                        $this->preview(),
                        minWidth: 1200, minHeight: 675, shape: 'Apaisada',
                        previewSpan: 4, uploadSpan: 4,
                    ),

                    Toggle::make('decorative')->label('Solo decorativa')->default(true)->live()->inline(false)
                        ->helperText('Va de fondo, sin aportar información.')
                        ->columnSpan(['default' => 1, 'sm' => 4]),

                    TextInput::make('alt')->label('Texto alternativo')->maxLength(150)
                        ->helperText('Describe la imagen para quien no puede verla. Obligatorio si NO es decorativa.')
                        ->required(fn (Get $get): bool => $get('decorative') !== true)
                        ->visible(fn (Get $get): bool => $get('decorative') !== true)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Los tipos SIN media (Épica 12.2-A). Cada uno se muestra sólo en su sección,
     * atado a `payload.*`, así que lo que el formulario produce ES el payload
     * canónico de su `SPECS` — sin capa de traducción que pueda separarse.
     *
     * Las etiquetas y las ayudas están en el idioma del owner y describen QUÉ es
     * el campo en la página, no cómo se llama en el JSON: quien edita no debería
     * necesitar saber que existe una clave `eyebrow`.
     *
     * @return list<Component>
     */
    private function textTypeFields(string $type): array
    {
        return match ($type) {
            // -------- cta
            'cta' => [
                Fieldset::make('Llamado a la acción')
                    ->columns(1)
                    ->schema([
                        TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80)
                            ->helperText('Texto chico en mayúsculas arriba del título. Opcional.'),
                        TextInput::make('payload.title')->label('Título')->maxLength(120),
                        Textarea::make('payload.body')->label('Texto')->rows(3)->maxLength(400)
                            ->helperText('Un párrafo breve que acompañe al botón. Opcional.'),
                        // La MISMA paleta de muestras que el borde de las
                        // tarjetas de «Qué hacemos»: una sola lista, un solo
                        // gesto para elegir color en todo el panel.
                        ViewField::make('payload.background_color')
                            ->label('Color de fondo')
                            ->view('filament.forms.color-palette')
                            ->default('primary')
                            ->helperText('El color elegido se dibuja con un degradado apenas más oscuro hacia abajo. Los textos y botones se aclaran o se oscurecen solos para que se sigan leyendo.')
                            ->columnSpanFull(),
                        // SÓLO el título. El antetítulo y el texto siguen saliendo
                        // del juego de tinta que decide el fondo, que es lo que
                        // garantiza que se lean: dejar elegir los tres por
                        // separado es la forma de terminar con tres colores que
                        // no se hablan entre sí.
                        ViewField::make('payload.title_color')
                            ->label('Color del título')
                            ->view('filament.forms.color-palette')
                            ->helperText('Sólo el título. Sin elegir, sale del color que mejor contrasta con el fondo de la tarjeta.')
                            ->columnSpanFull(),
                        Fieldset::make('Botón principal')->columns(3)->schema(CtaFields::make('payload.primary_cta')),
                        Fieldset::make('Botón secundario (opcional)')->columns(3)->schema(CtaFields::make('payload.secondary_cta')),
                        Repeater::make('payload.bullets')
                            ->label('Datos destacados (opcional)')
                            ->helperText('Van a la derecha, al lado del texto. Hasta 5. Si no cargás ninguno, el bloque se ve centrado y a todo el ancho, como hasta ahora. A partir del cuarto se muestran más chicos para que la tarjeta no crezca de alto.')
                            ->schema([
                                // Ocho caracteres: el dato comparte el renglón con
                                // su explicación, y uno largo se comería el ancho
                                // que necesita el texto para leerse.
                                TextInput::make('value')->label('Dato')->required()->maxLength(8)
                                    ->helperText('Corto y contundente: +12%, +150, 100%.'),
                                TextInput::make('text')->label('Qué significa')->required()->maxLength(120),
                            ])
                            ->columns(2)
                            ->maxItems(5)
                            ->reorderable()
                            ->defaultItems(0)
                            ->addActionLabel('Agregar dato')
                            ->columnSpanFull(),
                    ]),
            ],

            // -------- values
            'values' => [
                Fieldset::make('Aspecto de la sección')
                    ->columns(1)
                    ->schema([
                        ViewField::make('payload.background_color')
                            ->label('Color del fondo')
                            ->view('filament.forms.color-palette')
                            ->default('site')
                            ->helperText('La banda que va detrás de toda la sección. «Fondo del sitio» la deja como el resto de la página.'),
                        // El sitio ya mostraba este tipo de DOS formas: suelto
                        // de a cuatro en «Nuestros valores» y en tarjetas de a
                        // dos en «¿Qué incluye?». Era la misma sección con dos
                        // aspectos cableados en cada página; ahora se elige.
                        Toggle::make('payload.as_cards')->label('Mostrar como tarjetas')->inline(false)->live()
                            ->helperText('Encendido, cada valor va en su propia tarjeta y entran dos por fila. Apagado, van como texto suelto de a cuatro.'),
                        // El fondo de la TARJETA es otra decisión que la de la
                        // banda: la sección puede ir sobre el fondo del sitio y
                        // las tarjetas destacarse encima.
                        ViewField::make('payload.card_bg_color')
                            ->label('Color de la tarjeta')
                            ->view('filament.forms.color-palette')
                            ->default('navy')
                            ->helperText('El fondo de cada valor. Se ve encima del color de la sección.')
                            // Sin tarjetas no hay fondo de tarjeta que elegir:
                            // preguntarlo igual sería una decisión sin efecto.
                            ->visible(fn (Get $get): bool => (bool) $get('payload.as_cards')),
                    ]),

                // Las mismas cuatro perillas que «Qué hacemos», con el mismo
                // nombre y el mismo comportamiento: acá cada valor también
                // puede ser una tarjeta, y dos vocabularios para lo mismo
                // obligarían al owner a aprender la diferencia dos veces.
                ...self::cardBorderFields('Borde de las tarjetas'),

                Fieldset::make('Valores')
                    ->columns(1)
                    ->schema([
                        TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80)
                            ->helperText('Texto chico en mayúsculas arriba del título. Opcional.'),
                        TextInput::make('payload.title')->label('Título de la sección')->maxLength(120)
                            ->helperText('Opcional.'),
                        // Los COLORES del ícono van pegados a la galería y no en
                        // un apartado aparte: quien está eligiendo el dibujo es
                        // quien está decidiendo de qué color va, y separarlos
                        // obligaba a buscar la decisión en dos lugares.
                        //
                        // Van EN LÍNEA, uno junto al otro, y no apilados: son la
                        // misma decisión compuesta —envolvente y dibujo— y verlos
                        // uno debajo del otro los leía como dos pasos separados.
                        Grid::make(2)
                            ->columnSpanFull()
                            ->schema([
                                ViewField::make('payload.icon_bg_color')
                                    ->label('Color de la placa del ícono')
                                    ->view('filament.forms.color-palette')
                                    ->default('navy')
                                    ->helperText('El cuadrito redondeado que va detrás del dibujo.'),
                                ViewField::make('payload.icon_color')
                                    ->label('Color del dibujo')
                                    ->view('filament.forms.color-palette')
                                    // Sin elección sigue a su placa: la ficha marcada
                                    // tiene que decir lo mismo que la página.
                                    ->default(fn (Get $get): string => app(BrandPalette::class)
                                        ->needsDarkText($get('payload.icon_bg_color') ?? 'navy')
                                            ? 'primary'
                                            : 'neutral-0')
                                    ->helperText('Si no elegís, se ajusta solo para que se vea sobre la placa.'),
                            ]),

                        // La galería va ARRIBA de la lista y es sólo referencia
                        // visual: el selector muestra el nombre del ícono, no el
                        // dibujo, así que sin esto habría que elegir a ciegas.
                        Placeholder::make('galeria_iconos_valores')
                            ->label('Íconos disponibles')
                            ->content(fn (Component $component): HtmlString => $this->iconGallery($component))
                            ->columnSpanFull(),

                        Repeater::make('payload.items')
                            ->label('Valores')
                            ->columnSpanFull()
                            ->maxItems(12)
                            ->reorderable()
                            ->addActionLabel('Agregar valor')
                            ->columns(['default' => 1, 'sm' => 12])
                            ->schema([
                                ViewField::make('icon')->label('Ícono')
                                    ->view('filament.forms.icon-picker')
                                    ->viewData(['iconos' => (array) config('frontend-sections.card_icons')])
                                    ->columnSpan(['default' => 1, 'sm' => 3]),
                                TextInput::make('title')->label('Título')->required()->maxLength(80)
                                    ->columnSpan(['default' => 1, 'sm' => 4]),
                                TextInput::make('description')->label('Descripción')->required()->maxLength(300)
                                    ->columnSpan(['default' => 1, 'sm' => 5]),
                            ]),
                    ]),
            ],

            // -------- metrics
            'metrics' => [
                Fieldset::make('Aspecto de la tarjeta')
                    ->columns(1)
                    ->schema([
                        ViewField::make('payload.background_color')
                            ->label('Color de la tarjeta')
                            ->view('filament.forms.color-palette')
                            ->default('navy')
                            ->helperText('El fondo de la banda donde van las cifras.'),
                        ViewField::make('payload.value_color')
                            ->label('Color de las cifras')
                            ->view('filament.forms.color-palette')
                            // Sin elección, la cifra sigue al fondo. La ficha
                            // marcada tiene que decir lo mismo que la página, o
                            // el panel estaría mintiendo sobre una tarjeta
                            // oscura: ahí lo que se ve es tinta clara, no el
                            // primario.
                            ->default(fn (Get $get): string => app(BrandPalette::class)
                                ->needsDarkText($get('payload.background_color') ?? 'navy')
                                    ? 'primary'
                                    : 'neutral-0')
                            ->helperText('Sólo el número grande. Si no elegís, se ajusta solo para que se lea sobre la tarjeta. El texto que explica la cifra siempre se ajusta solo.'),
                    ]),

                Repeater::make('payload.items')
                    ->label('Cifras')
                    ->columnSpanFull()
                    ->hint('Máximo 12')
                    ->helperText('Se muestran como tarjetas en la página.')
                    ->maxItems(12)
                    ->reorderable()
                    ->addActionLabel('Agregar cifra')
                    ->columns(['default' => 1, 'sm' => 12])
                    ->schema([
                        TextInput::make('value')->label('Cifra')->required()->maxLength(20)
                            ->placeholder('+150')
                            ->columnSpan(['default' => 1, 'sm' => 4]),
                        TextInput::make('label')->label('Qué mide')->required()->maxLength(120)
                            ->placeholder('Operaciones cerradas')
                            ->columnSpan(['default' => 1, 'sm' => 8]),
                    ]),
            ],

            // -------- capability_cards
            'capability_cards' => [
                Fieldset::make('Encabezado')
                    ->columns(1)
                    ->schema([
                        TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80)
                            ->placeholder('QUÉ HACEMOS')
                            ->helperText('Texto chico en mayúsculas arriba del título.'),
                        TextInput::make('payload.title')->label('Frase principal')->maxLength(120)
                            ->placeholder('Cuatro disciplinas, un solo equipo'),
                        Textarea::make('payload.body')->label('Texto descriptivo')->rows(2)->maxLength(400)
                            ->placeholder('Del terreno a la entrega de llaves…'),
                        Select::make('payload.text_align')->label('Alineación del encabezado')->native(false)
                            ->options(['left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha'])
                            ->default('center')
                            ->selectablePlaceholder(false)
                            ->helperText('Afecta al antetítulo, la frase y el texto descriptivo. Las tarjetas no se mueven.'),
                    ]),

                ...self::cardBorderFields('Aspecto de las tarjetas'),

                // Los COLORES del ícono, pegados a la galería por la misma razón
                // que en «Valores»: quien elige el dibujo es quien decide de qué
                // color va. En línea y no apilados: es una sola decisión compuesta
                // —envolvente y dibujo—, la misma razón que en «Valores».
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        ViewField::make('payload.icon_bg_color')
                            ->label('Color de la placa del ícono')
                            ->view('filament.forms.color-palette')
                            ->default('navy')
                            ->helperText('El cuadrito redondeado que va detrás del dibujo.'),
                        ViewField::make('payload.icon_color')
                            ->label('Color del dibujo')
                            ->view('filament.forms.color-palette')
                            ->default(fn (Get $get): string => app(BrandPalette::class)
                                ->needsDarkText($get('payload.icon_bg_color') ?? 'navy')
                                    ? 'primary'
                                    : 'neutral-0')
                            ->helperText('Si no elegís, se ajusta solo para que se vea sobre la placa.'),
                    ]),

                // La galería va ARRIBA del repeater y es sólo de referencia: el
                // selector de cada tarjeta muestra el nombre, no el dibujo, así
                // que sin esto habría que elegir «Respaldo» a ciegas.
                Placeholder::make('galeria_iconos')
                    ->label('Íconos disponibles')
                    ->columnSpanFull()
                    ->content(fn (Component $component): HtmlString => $this->iconGallery($component)),

                Repeater::make('payload.items')
                    ->label('Tarjetas')
                    ->columnSpanFull()
                    ->hint('Entre 1 y 8')
                    ->helperText('El ancho se reparte solo según cuántas pongas: una ocupa todo, dos van a la mitad, y a partir de cinco la fila de arriba lleva cuatro y el resto se reparte abajo.')
                    ->minItems(1)
                    ->maxItems(8)
                    ->reorderable()
                    ->addActionLabel('Agregar tarjeta')
                    ->columns(['default' => 1, 'sm' => 12])
                    ->schema([
                        ViewField::make('icon')->label('Ícono')
                            ->view('filament.forms.icon-picker')
                            ->viewData(['iconos' => (array) config('frontend-sections.card_icons')])
                            ->columnSpan(['default' => 1, 'sm' => 3]),
                        TextInput::make('title')->label('Título')->required()->maxLength(80)
                            ->columnSpan(['default' => 1, 'sm' => 4]),
                        TextInput::make('description')->label('Descripción')->maxLength(300)
                            ->helperText('Opcional.')
                            ->columnSpan(['default' => 1, 'sm' => 5]),
                    ]),
            ],

            // -------- audience_outcomes
            'audience_outcomes' => [
                Fieldset::make('A quién le sirve y qué obtiene')
                    ->columns(1)
                    ->schema([
                        TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80),
                        TextInput::make('payload.title')->label('Título')->maxLength(120),
                        Repeater::make('payload.audience_items')
                            ->label('A quién le sirve')
                            ->columnSpanFull()
                            ->helperText('Un punto por línea. Se muestran como lista.')
                            ->addActionLabel('Agregar punto')
                            ->simple(TextInput::make('item')->label('Punto')->required()->maxLength(200)),
                        Fieldset::make('Qué obtiene')
                            ->columns(1)
                            ->schema([
                                TextInput::make('payload.result.eyebrow')->label('Antetítulo')->maxLength(80),
                                TextInput::make('payload.result.title')->label('Título')->maxLength(120),
                                Repeater::make('payload.result.items')
                                    ->label('Resultados')
                                    ->addActionLabel('Agregar resultado')
                                    ->simple(TextInput::make('item')->label('Resultado')->required()->maxLength(200)),
                                Textarea::make('payload.result.quote')->label('Frase de cierre')->rows(2)->maxLength(300)
                                    ->helperText('Opcional.'),
                            ]),
                    ]),
            ],
            default => [],
        };
    }

    /**
     * Los tipos CON media que no son el hero (Épica 12.2-B). Comparten con él el
     * adaptador de imagen y el pipeline de promoción ya auditados en 12.1-A; lo
     * propio de cada uno es qué acompaña a la foto y cuán obligatoria es.
     *
     * @return list<Component>
     */
    private function mediaTypeFields(string $type): array
    {
        return match ($type) {
            // -------- rich_text
            'rich_text' => [
                Fieldset::make('Texto')
                    ->columns(1)
                    ->schema([
                        TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80)
                            ->helperText('Texto chico en mayúsculas arriba del título. Opcional.'),
                        TextInput::make('payload.title')->label('Título')->maxLength(120)
                            ->helperText('Opcional.'),
                        Textarea::make('payload.body')->label('Contenido')->rows(8)->required()->maxLength(4000)
                            ->helperText('Texto plano. No se permite HTML.'),
                        // La misma allowlist de tres que ya usan el hero y «Qué
                        // hacemos», con las mismas etiquetas: es la misma
                        // decisión, y nombrarla distinto obligaría al owner a
                        // deducir que se trata de lo mismo.
                        Select::make('payload.text_align')->label('Alineación del texto')->native(false)
                            ->options(['left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha'])
                            ->default('left')
                            ->selectablePlaceholder(false)
                            ->helperText('Afecta al antetítulo, al título y al contenido.'),
                    ]),

                Fieldset::make('Fotografía (opcional)')
                    // Los campos de imagen se declaran con nombres RELATIVOS —
                    // nacieron para vivir dentro de un repeater, donde el
                    // contenedor ya los ancla. Acá el ancla es esta: sin ella
                    // caerían en la raíz del formulario y el compilador, que
                    // recibe el payload, no los encontraría.
                    ->statePath('payload')
                    ->columns(1)
                    ->schema([
                        // La grilla va en un `Grid` propio y no en el `columns()`
                        // del fieldset: ahí no se aplicaba —la grilla quedaba de
                        // una sola columna— y la vista previa se estiraba a todo
                        // el ancho del modal, más de 1000 px de foto para elegir
                        // una foto. En el hero se ve chica porque vive dentro de
                        // un repeater, que sí reparte sus columnas.
                        Grid::make(['default' => 1, 'sm' => 12])
                            ->schema([
                                // Mínimos de una foto editorial apaisada. Sin
                                // foto, el texto sigue ocupando todo el ancho.
                                ...SectionImageFields::make(
                                    $this->preview(),
                                    minWidth: 900, minHeight: 600, shape: 'Apaisada',
                                    previewSpan: 3, uploadSpan: 5,
                                ),

                                // Obligatorio SÓLO si hay foto: es la regla
                                // universal de accesibilidad del schema, dicha
                                // acá para que el owner no se entere recién al
                                // guardar.
                                TextInput::make('alt')->label('Descripción de la foto')
                                    ->required(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                    ->visible(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                    ->maxLength(160)
                                    ->helperText('Qué se ve en la foto. Lo leen los buscadores y los lectores de pantalla.')
                                    ->columnSpan(['default' => 1, 'sm' => 4]),

                                // Las mismas tres opciones y las mismas
                                // etiquetas que los pasos: es la misma decisión
                                // sobre la misma foto. Sólo con imagen — sin
                                // ella no hay nada que ubicar.
                                Select::make('layout')->label('Dónde va la foto')->native(false)
                                    ->options([
                                        'split_media_end' => 'A la derecha',
                                        'split_media_start' => 'A la izquierda',
                                        'full_overlay' => 'Imagen de fondo',
                                    ])
                                    ->default('split_media_end')
                                    ->selectablePlaceholder(false)
                                    ->visible(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                    ->helperText('Con «Imagen de fondo», el texto va encima de la foto.')
                                    ->columnSpan(['default' => 1, 'sm' => 12]),
                            ]),
                    ]),
            ],

            // -------- team
            'team' => [
                // Los dos colores de la sección, de la misma paleta cerrada que
                // el resto del panel: un selector abierto dejaría poner un verde
                // en un sitio azul y ámbar.
                Fieldset::make('Aspecto de la sección')
                    ->columns(1)
                    ->schema([
                        ViewField::make('payload.background_color')
                            ->label('Color del fondo')
                            ->view('filament.forms.color-palette')
                            ->default('neutral-1')
                            ->helperText('La banda que va detrás de toda la sección.'),
                        ViewField::make('payload.title_color')
                            ->label('Color del título')
                            ->view('filament.forms.color-palette')
                            ->default('primary')
                            ->helperText('Afecta al antetítulo y al título de la sección.'),
                    ]),

                Fieldset::make('Equipo')
                    ->columns(1)
                    ->schema([
                        TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80)
                            ->helperText('Texto chico en mayúsculas arriba del título. Opcional.'),
                        TextInput::make('payload.title')->label('Título de la sección')->maxLength(120)
                            ->helperText('Opcional.'),
                        Repeater::make('payload.members')
                            ->label('Integrantes')
                            ->columnSpanFull()
                            ->helperText('Máximo 24. La foto es opcional; si la pones, describí qué se ve.')
                            ->maxItems(24)
                            ->reorderable()
                            ->addActionLabel('Agregar integrante')
                            ->columns(['default' => 1, 'sm' => 12])
                            ->schema([
                                // Vertical: los retratos del equipo son de ese
                                // formato, y un recuadro apaisado haría que la
                                // vista previa mienta sobre el encuadre — que es
                                // justo lo que el recuadro existe para evitar.
                                ...SectionImageFields::make(
                                    $this->preview('9/16'),
                                    minWidth: 600, minHeight: 800, shape: 'Vertical 9:16',
                                    previewSpan: 3, uploadSpan: 4,
                                ),

                                TextInput::make('name')->label('Nombre')->required()->maxLength(120)
                                    ->columnSpan(['default' => 1, 'sm' => 5]),
                                TextInput::make('role')->label('Puesto')->maxLength(120)
                                    ->helperText('Opcional.')
                                    ->columnSpan(['default' => 1, 'sm' => 6]),

                                // Obligatorio SÓLO si hay foto: es la regla universal
                                // de accesibilidad del schema, dicha en la UI para que
                                // el owner no se entere recién al guardar. Aparece
                                // pegado a la foto, cuando la foto existe.
                                TextInput::make('alt')->label('Qué se ve en la foto')->maxLength(150)
                                    ->placeholder('Para quien no puede verla')
                                    ->required(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                    ->visible(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                    ->columnSpan(['default' => 1, 'sm' => 6]),
                            ]),
                        // El destacado puede ser una DIVISIÓN de la empresa con
                        // su propia imagen comercial, así que lleva su logo
                        // aparte del de la marca principal.
                        //
                        // Los campos se anclan a `payload.spotlight`: el logo se
                        // guarda bajo `media_id` dentro de ese objeto porque la
                        // validación, la promoción al publicar y el reporte de
                        // huérfanas recorren el payload buscando esa clave. Un
                        // `spotlight_media_id` plano habría sido invisible para
                        // los tres.
                        Fieldset::make('Destacado (opcional)')
                            ->statePath('payload.spotlight')
                            ->columns(1)
                            ->schema([
                                TextInput::make('eyebrow')->label('Antetítulo')->maxLength(80)
                                    ->helperText('Texto chico en mayúsculas arriba del título. Opcional.'),
                                TextInput::make('title')->label('Título')->maxLength(120),
                                Textarea::make('body')->label('Texto')->rows(3)->maxLength(600),

                                Grid::make(['default' => 1, 'sm' => 12])
                                    ->schema([
                                        // Apaisado y con mínimos bajos: un
                                        // logotipo no es una fotografía, y
                                        // pedirle medidas de foto rechazaría el
                                        // archivo que normalmente existe.
                                        ...SectionImageFields::make(
                                            $this->preview(),
                                            minWidth: 200, minHeight: 80, shape: 'Apaisada, con el fondo transparente si se puede',
                                            previewSpan: 3, uploadSpan: 5,
                                        ),

                                        TextInput::make('alt')->label('Descripción del logo')->maxLength(150)
                                            ->placeholder('Para quien no puede verlo')
                                            ->required(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                            ->visible(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                            ->columnSpan(['default' => 1, 'sm' => 4]),
                                    ]),
                            ]),
                    ]),
            ],

            // -------- partners
            'partners' => [
                Fieldset::make('Aliados')
                    ->columns(1)
                    ->schema([
                        // Repeater NORMAL y no `simple()`: el simple hidrata desde
                        // una lista PLANA de textos, pero el payload canónico de
                        // `partners` guarda objetos `{name}`. Ese desajuste metía
                        // el objeto entero bajo `name` al abrir el editor. Con un
                        // campo normal, formulario y payload tienen la misma
                        // forma y no hay que traducir en ningún sentido.
                        Repeater::make('payload.items')
                            ->label('Aliados')
                            ->columnSpanFull()
                            ->helperText('Se muestran 5 a la vez; a partir del sexto la fila avanza sola. El logo es opcional: sin logo se muestra el nombre. Máximo 24.')
                            ->maxItems(24)
                            ->reorderable()
                            ->addActionLabel('Agregar aliado')
                            ->columns(['default' => 1, 'sm' => 12])
                            ->schema([
                                // Mínimos bajos y forma apaisada: un logotipo no es
                                // una fotografía. Exigirle 1200 px de ancho rechazaría
                                // el archivo que la mayoría de los aliados manda.
                                ...SectionImageFields::make(
                                    $this->preview(),
                                    minWidth: 200, minHeight: 80, shape: 'Apaisada, con el fondo transparente si se puede',
                                    previewSpan: 3, uploadSpan: 5,
                                ),

                                // NO se pide texto alternativo: para el logotipo de
                                // un aliado, su alt ES este nombre. Lo escribe el
                                // compilador, así que la regla de accesibilidad se
                                // cumple sin preguntar dos veces lo mismo.
                                TextInput::make('name')->label('Nombre')
                                    ->placeholder('Nombre del aliado')
                                    ->required()->maxLength(80)
                                    ->helperText('También es el texto que leen los lectores de pantalla sobre el logo.')
                                    ->columnSpan(['default' => 1, 'sm' => 4]),
                            ]),
                    ]),

                // El mismo borde opcional que las tarjetas de «Qué hacemos», con
                // la misma paleta: son dos rejillas de tarjetas en la misma
                // página y deberían poder verse hermanas.
                ...self::cardBorderFields('Borde de las tarjetas'),
            ],

            // -------- feature_sequence
            'feature_sequence' => [
                Fieldset::make('Secuencia')
                    ->columns(1)
                    ->schema([
                        TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80),
                        TextInput::make('payload.title')->label('Título de la sección')->maxLength(120),
                        Repeater::make('payload.items')
                            ->label('Pasos')
                            ->columnSpanFull()
                            ->helperText('Entre 1 y 8. Cada paso lleva su imagen.')
                            ->minItems(1)
                            ->maxItems(8)
                            ->reorderable()
                            ->addActionLabel('Agregar paso')
                            ->columns(['default' => 1, 'sm' => 12])
                            ->schema([
                                ...SectionImageFields::make(
                                    $this->preview(),
                                    minWidth: 1200, minHeight: 675, shape: 'Apaisada',
                                    required: true, previewSpan: 3, uploadSpan: 4,
                                ),

                                // El `alt` va JUNTO a la imagen y no al final de la
                                // tarjeta: describe esa foto, y pedirlo a seis campos
                                // de distancia es pedir que el owner se acuerde.
                                TextInput::make('alt')->label('Qué se ve en la imagen')->maxLength(150)
                                    ->placeholder('Para quien no puede verla')
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 6]),

                                TextInput::make('title')->label('Título del paso')->required()->maxLength(120)
                                    ->columnSpan(['default' => 1, 'sm' => 4]),

                                Select::make('layout')->label('Disposición')->native(false)
                                    ->options([
                                        'split_media_end' => 'Imagen a la derecha',
                                        'split_media_start' => 'Imagen a la izquierda',
                                        'full_overlay' => 'Imagen de fondo',
                                    ])
                                    ->default('split_media_end')
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 4]),

                                TextInput::make('eyebrow')->label('Antetítulo')->maxLength(80)
                                    ->columnSpan(['default' => 1, 'sm' => 4]),
                                Textarea::make('body')->label('Texto')->rows(2)->maxLength(600)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ],
            default => [],
        };
    }

    /**
     * Los tipos dinámicos (Épica 12.2-C): encabezado y, cuando aplica, cuántos
     * mostrar. Nada más.
     *
     * Lo que este formulario NO tiene es tan parte del contrato como lo que
     * tiene: no hay campo de ítems, ni de ids, ni de consulta. Las propiedades,
     * los proyectos y los servicios los resuelve el kernel desde su autoridad en
     * cada render, así que dar de baja un destacado lo saca del sitio al
     * instante, sin republicar la página.
     *
     * @return list<Component>
     */
    private function dynamicTypeFields(): array
    {
        $dynamic = ['service_list', 'featured_properties', 'opportunity_properties', 'featured_projects'];
        $conLimite = ['featured_properties', 'opportunity_properties', 'featured_projects'];
        // Los que llevan a un catálogo, y los que admiten texto descriptivo.
        // Las dos listas son las mismas que gobiernan el compilador.
        $conCatalogo = ['featured_properties', 'opportunity_properties', 'featured_projects'];
        $conDescripcion = ['opportunity_properties'];

        $botonCatalogo = [
            'featured_properties' => ['Ver todas las propiedades', 'El botón lleva al catálogo completo. Vacío usa el texto de ejemplo.'],
            'opportunity_properties' => ['Ver todas las oportunidades', 'El botón lleva al catálogo filtrado a oportunidades de inversión. Vacío usa el texto de ejemplo.'],
            'featured_projects' => ['Ver todos los proyectos', 'El botón lleva al catálogo completo de proyectos. Vacío usa el texto de ejemplo.'],
        ];

        $queMuestra = [
            'service_list' => 'Se muestran los servicios activos del sitio.',
            'featured_properties' => 'Se muestran las propiedades marcadas como destacadas.',
            'opportunity_properties' => 'Se muestran las propiedades marcadas como oportunidad.',
            'featured_projects' => 'Se muestran los proyectos marcados como destacados.',
        ];

        return [
            Fieldset::make('Encabezado del listado')
                ->columns(1)
                ->schema([
                    Placeholder::make('origen')
                        ->label('Qué se lista')
                        ->content(fn (?FrontendSection $record): string => ($queMuestra[$record?->type] ?? '')
                            .' Esta pantalla solo cambia el encabezado; el contenido se administra en su propia sección.'),

                    TextInput::make('payload.eyebrow')->label('Antetítulo')->maxLength(80)
                        ->helperText('Texto chico en mayúsculas arriba del título. Opcional.'),
                    TextInput::make('payload.title')->label('Título')->maxLength(120)
                        ->helperText('Opcional.'),

                    Textarea::make('payload.body')->label('Texto descriptivo')->rows(2)->maxLength(400)
                        ->helperText('Un párrafo breve debajo del título. Opcional.')
                        ->visible(fn (?FrontendSection $record): bool => in_array($record?->type, $conDescripcion, true)),

                    TextInput::make('payload.limit')->label('Cuántos mostrar')
                        ->numeric()->minValue(1)->maxValue(24)
                        ->helperText('Entre 1 y 24. Vacío = 12.')
                        ->visible(fn (?FrontendSection $record): bool => in_array($record?->type, $conLimite, true)),

                    // Sólo el TEXTO del botón. El destino no se pregunta: el
                    // catálogo es una sola página, así que ofrecer elegirlo sería
                    // ofrecer equivocarse en algo que no tiene alternativa.
                    TextInput::make('payload.cta_label')->label('Texto del botón')->maxLength(60)
                        ->placeholder(fn (?FrontendSection $record): string => $botonCatalogo[$record?->type ?? ''][0] ?? 'Ver todas las propiedades')
                        ->helperText(fn (?FrontendSection $record): string => $botonCatalogo[$record?->type ?? ''][1] ?? 'El botón lleva al catálogo completo. Vacío usa el texto de ejemplo.')
                        // El payload guarda el CTA entero; este campo es sólo su
                        // etiqueta. Sin esto el editor abriría en blanco y
                        // guardar volvería al texto de ejemplo, borrando en
                        // silencio lo que el owner había escrito.
                        ->afterStateHydrated(function (TextInput $component, ?FrontendSection $record): void {
                            $component->state($record?->payload['primary_cta']['label'] ?? null);
                        })
                        ->visible(fn (?FrontendSection $record): bool => in_array($record?->type, $conCatalogo, true)),
                ]),
        ];
    }

    /**
     * `featured_projects` con su logo de autor, además de los campos comunes a
     * los cuatro tipos dinámicos.
     *
     * NO va como un Fieldset más, condicionado por `visible()`, dentro de
     * `dynamicTypeFields()`: se probó así primero y rompía la hidratación de
     * `cta_label` en los OTROS tres tipos —un defecto real de Filament, no de
     * esta lógica: un `Fieldset` con `statePath()` Y un `visible()` que
     * depende de `$record`, hermano de otro que usa rutas absolutas al mismo
     * `payload`, interfiere con la deshidratación del hermano—. Separado en su
     * propio método, como ya hacen `rich_text` y `team`, el Fieldset del logo
     * es incondicional porque el método entero sólo se invoca para este tipo.
     *
     * @return list<Component>
     */
    private function featuredProjectsFields(): array
    {
        return [
            ...$this->dynamicTypeFields(),

            // El fondo de la sección, de la misma paleta cerrada que el resto
            // del panel. Sin elegir, sigue el gesto automático de siempre: gris
            // con logo de autor, fondo del sitio sin él (ver compilador).
            Fieldset::make('Aspecto de la sección')
                ->columns(1)
                ->schema([
                    ViewField::make('payload.background_color')
                        ->label('Color del fondo')
                        ->view('filament.forms.color-palette')
                        ->default(fn (Get $get): string => (filled($get('payload.media_id')) || filled($get('payload.upload')))
                            ? 'neutral-1'
                            : 'site')
                        ->helperText('La banda detrás del encabezado y el listado. Sin logo de autor, sigue el fondo del sitio; con logo, una banda gris lo separa.'),
                ]),

            // El logo del AUTOR de los proyectos: una tarjeta destacada arriba
            // del listado, con el mismo diseño que el destacado del equipo —
            // puede ser una división con imagen comercial propia (A-74
            // Arquitectura), no la marca principal repetida.
            Fieldset::make('Autor de los proyectos (opcional)')
                ->statePath('payload')
                ->columns(1)
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 12])
                        ->schema([
                            // Apaisado y con mínimos bajos: un logotipo no es
                            // una fotografía, y pedirle medidas de foto
                            // rechazaría el archivo que normalmente existe.
                            ...SectionImageFields::make(
                                $this->preview(),
                                minWidth: 200, minHeight: 80, shape: 'Apaisada, con el fondo transparente si se puede',
                                previewSpan: 3, uploadSpan: 5,
                            ),

                            TextInput::make('alt')->label('Descripción del logo')->maxLength(150)
                                ->placeholder('Para quien no puede verlo')
                                ->required(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                ->visible(fn (Get $get): bool => filled($get('media_id')) || filled($get('upload')))
                                ->columnSpan(['default' => 1, 'sm' => 4]),
                        ]),
                ]),
        ];
    }

    /**
     * El nombre humano de una sección, o su clave si alguien agregó una al
     * registro sin etiquetarla. Ese caso no debería existir —
     * `FrontendSectionEditorClosureTest` lo prohíbe— pero mostrar la clave es
     * mejor que mostrar un vacío, y deja el olvido a la vista.
     */
    public static function sectionLabel(FrontendSection $section): string
    {
        return (string) (config("frontend-sections.section_labels.{$section->section_key}") ?: $section->section_key);
    }

    /**
     * La tira de íconos disponibles, con su nombre debajo.
     *
     * Se dibuja desde la MISMA lista que alimenta el selector
     * (`config('frontend-sections.card_icons')`), así que no puede quedar
     * desincronizada: un ícono nuevo aparece en los dos lados o en ninguno.
     */
    /**
     * La galería de íconos, pintada EN VIVO con los colores que se eligen arriba.
     *
     * Recibe el componente para poder alcanzar el estado de sus hermanos: los dos
     * selectores de color viven en el mismo contenedor, y sin su ruta la galería
     * no tendría a qué engancharse.
     */
    private function iconGallery(Component $component): HtmlString
    {
        $paleta = app(BrandPalette::class);
        $muestras = $paleta->swatches();

        // Las claves cuyo fondo es OSCURO: sobre ellas el dibujo va en tinta
        // clara. Sale del mismo `needsDarkText()` que corre la vista, así que la
        // galería y la página no pueden discrepar.
        $oscuras = [];
        $hex = [];

        foreach ($muestras as $clave => $muestra) {
            $hex[$clave] = $muestra['hex'];

            if (! $paleta->needsDarkText($clave)) {
                $oscuras[] = $clave;
            }
        }

        $tema = app(FrontendThemeService::class)->theme();

        // El contenedor es el que sabe dónde vive el payload de esta sección.
        $prefijo = $component->getContainer()->getStatePath();
        $prefijo = $prefijo === '' ? '' : $prefijo.'.';

        return new HtmlString(view('filament.forms.icon-gallery', [
            'iconos' => (array) config('frontend-sections.card_icons'),
            'rutaPlaca' => $prefijo.'payload.icon_bg_color',
            'rutaGlifo' => $prefijo.'payload.icon_color',
            'hexPorClave' => $hex,
            'clavesOscuras' => $oscuras,
            'tintaClara' => $tema['on_primary'],
            'tintaOscura' => $tema['primary'],
        ])->render());
    }

    /**
     * The preview closure the image adapter calls for every row.
     *
     * La PROPORCIÓN se pide por uso. Por defecto es apaisada, que es como se ven
     * casi todas las fotos del sitio; los retratos del equipo son verticales y
     * mostrarlos en un recuadro 16/9 haría que la vista previa mienta sobre el
     * encuadre — justo lo que este recuadro existe para evitar.
     */
    private function preview(string $proporcion = '16/9'): \Closure
    {
        return fn (mixed $mediaId): HtmlString => $this->imagePreview($mediaId, $proporcion);
    }

    /** The current image of a row, served by the owner-only private route. */
    private function imagePreview(mixed $mediaId, string $proporcion = '16/9'): HtmlString
    {
        $section = $this->getMountedTableActionRecord();

        // Un recuadro y no un texto suelto: sin imagen, la celda igual reserva su
        // lugar, así la fila no cambia de alto al subir la primera foto.
        //
        // La proporción es la que la foto va a tener DE VERDAD en el sitio, así
        // que la vista previa no miente sobre el encuadre. Sale de una lista
        // cerrada porque termina dentro de un `style`: cualquier otro valor se
        // trata como apaisado.
        $proporcion = in_array($proporcion, ['16/9', '9/16', '3/4', '1/1'], true) ? $proporcion : '16/9';

        $marco = 'display:flex;align-items:center;justify-content:center;aspect-ratio:'.$proporcion.';'
            .'width:100%;border-radius:8px;border:1px dashed #d1d5db;background:#f9fafb;overflow:hidden';

        if (! is_string($mediaId) || $mediaId === '' || ! $section instanceof FrontendSection) {
            return new HtmlString('<div style="'.$marco.'"><span style="font-size:11px;color:#9ca3af">Sin imagen</span></div>');
        }

        $url = route('frontend.sections.media', ['section' => $section->getKey(), 'uuid' => $mediaId]);

        return new HtmlString(
            '<div style="'.$marco.';border-style:solid;border-color:#e5e7eb">'
            .'<img src="'.e($url).'" alt="" style="width:100%;height:100%;object-fit:cover">'
            .'</div>'
        );
    }

    /**
     * Los ids de las secciones que se pueden mover, en el orden en que se ven.
     *
     * Se resuelve una vez por request: cada fila pregunta por sus dos flechas,
     * así que sin esto una página de ocho secciones haría dieciséis consultas
     * para dibujar el mismo listado.
     *
     * @var list<int>|null
     */
    private ?array $movibles = null;

    /** @return list<int> */
    private function movableIds(): array
    {
        return $this->movibles ??= $this->getOwnerRecord()->sections()
            ->where('type', '!=', 'hero')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function canMove(FrontendSection $record, int $direction): bool
    {
        // La portada no se mueve: es lo primero que se ve de la página, y
        // debajo de otra sección el título principal queda perdido a mitad
        // del scroll.
        if ($record->type === 'hero') {
            return false;
        }

        $ids = $this->movableIds();
        $i = array_search($record->getKey(), $ids, true);

        if ($i === false) {
            return false;
        }

        return $direction < 0 ? $i > 0 : $i < count($ids) - 1;
    }

    /**
     * Una flecha por fila. En los extremos NO se dibuja: una flecha apagada
     * igual invita a apretarla y no hace nada, así que era un botón que sólo
     * servía para desilusionar.
     *
     * Esconderlas no descoloca la fila porque las acciones van alineadas a la
     * derecha: «Editar» queda anclado al borde y no se mueve nunca, y en la
     * primera fila la que se va —«Subir»— es la del extremo izquierdo, así que
     * «Bajar» se queda donde estaba.
     *
     * `hidden()` además no es sólo cosmético: Filament revisa la visibilidad
     * antes de montar o ejecutar una acción, así que la flecha escondida
     * tampoco se puede disparar a mano. Y si igual llegara, `moveSectionDraft`
     * no tiene a dónde mover en el extremo y no hace nada.
     */
    private function moveAction(string $name, string $label, string $icon, int $direction): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->iconButton()
            ->color('gray')
            ->tooltip($label)
            ->hidden(fn (FrontendSection $record): bool => ! $this->canMove($record, $direction))
            ->action(function (FrontendSection $record) use ($direction): void {
                app(FrontendPageContentService::class)->moveSectionDraft($record, $direction);

                // Las flechas se recalculan sobre el orden nuevo, no sobre el
                // que se leyó para dibujar la fila que se acaba de tocar.
                $this->movibles = null;

                // Mover TAMBIÉN sube `draft_revision`, igual que guardar. Sin
                // este aviso, el botón Publicar seguiría mandando la revisión
                // que la pantalla capturó al abrirse y el publisher la
                // rechazaría por «el contenido cambió desde que abriste la
                // página» — el mismo síntoma que ya se corrigió al guardar.
                $this->dispatch('borrador-actualizado');
            });
    }

    /**
     * Las secciones que este bloque lista. La portada se muestra aparte, en su
     * propio bloque arriba, así que acá no aparece.
     */
    protected function tableQueryFilter(Builder $query): Builder
    {
        return $query->where('type', '!=', 'hero');
    }

    /**
     * Las flechas de mover. La portada no las tiene: no está en esta lista.
     *
     * @return list<Action>
     */
    protected function rowActions(): array
    {
        return [
            $this->moveAction('subir', 'Subir', 'heroicon-m-arrow-up', -1),
            $this->moveAction('bajar', 'Bajar', 'heroicon-m-arrow-down', 1),
        ];
    }

    /**
     * El borde opcional de una rejilla de tarjetas: encendido, grosor y color.
     *
     * Lo comparten «Qué hacemos» y «Aliados» — dos rejillas de tarjetas en la
     * misma página que deberían poder verse hermanas. Duplicar el bloque habría
     * dejado dos paletas y dos listas de grosores que se separan la primera vez
     * que alguien toque una sola.
     *
     * @return list<Component>
     */
    private static function cardBorderFields(string $titulo): array
    {
        return [
            Fieldset::make($titulo)
                ->columns(2)
                ->schema([
                    Toggle::make('payload.card_border')->label('Con borde')->inline(false)->live()
                        ->helperText('Un contorno alrededor de cada tarjeta.'),
                    Select::make('payload.card_border_width')->label('Grosor del borde')->native(false)
                        ->options(collect((array) config('frontend-sections.card_border_widths'))
                            ->mapWithKeys(fn (int $w): array => [$w => $w.' px'])
                            ->all())
                        ->default(1)
                        ->selectablePlaceholder(false)
                        // Sólo aparece con el borde encendido: un grosor que no
                        // se ve en ningún lado es una pregunta sin consecuencia.
                        ->visible(fn (Get $get): bool => (bool) $get('payload.card_border')),
                    // Paleta de MUESTRAS y no un desplegable: un menú obliga
                    // a leer «Principal claro» y traducirlo mentalmente a un
                    // color. Acá se ve el color y se elige el que sirve.
                    ViewField::make('payload.card_border_color')
                        ->label('Color del borde')
                        ->view('filament.forms.color-palette')
                        ->default('accent')
                        ->helperText('Los dos colores de tu marca, con dos variantes más oscuras y dos más claras. Si cambiás tu paleta en la configuración del sitio, el borde te sigue.')
                        ->visible(fn (Get $get): bool => (bool) $get('payload.card_border'))
                        ->columnSpanFull(),
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->tableQueryFilter($query))
            // Sin paginar. Las secciones son un conjunto fijo y chico —el
            // registro de la página—, así que el selector «por página» sobra. Y
            // sobre todo: paginar una lista que se ordena con flechas es una
            // trampa, porque bajar la última fila de una página la mandaría a
            // otra pantalla y el movimiento se vería como una desaparición.
            ->paginated(false)
            ->columns([
                // El número de orden ya no se muestra: el listado ES el orden.
                // Mientras se escribía a mano tenía sentido verlo para saber cuál
                // estaba libre; ahora sólo sería ruido técnico —y empezando en 0—
                // para alguien que piensa en «esta va antes que aquella».
                TextColumn::make('section_key')->label('Sección')
                    ->formatStateUsing(fn (FrontendSection $record): string => self::sectionLabel($record))
                    ->description(fn (FrontendSection $record): string => $record->section_key),
                IconColumn::make('is_enabled')->label('Visible')->boolean(),
            ])
            ->actions([
                ...$this->rowActions(),
                EditAction::make()
                    ->modalWidth('6xl')
                    ->modalHeading(fn (FrontendSection $record): string => self::sectionLabel($record))
                    ->modalDescription('Los cambios quedan en el borrador de la página hasta que la publiques.')
                    ->using(function (FrontendSection $record, array $data): FrontendSection {
                        // Todo tipo canónico tiene formulario (12.2-A/B/C), así que
                        // el payload SIEMPRE sale de campos validados. No queda vía
                        // para escribirlo como texto libre.
                        $payload = app(SectionPayloadCompiler::class)
                            ->compile($record, is_array($data['payload'] ?? null) ? $data['payload'] : []);

                        // Sin `sort_order`: guardar el contenido de una sección ya
                        // no puede moverla. Un POST armado a mano tampoco, porque
                        // el servicio dejó de aceptar el campo.
                        app(FrontendPageContentService::class)->saveSectionDraft($record, [
                            'payload' => $payload,
                            'is_enabled' => (bool) ($data['is_enabled'] ?? $record->is_enabled),
                        ]);

                        // Guardar una sección SUBE `draft_revision`, y la pantalla
                        // llevaba la revisión que capturó al abrirse: sin avisar,
                        // el botón Publicar seguía mandando la vieja y el
                        // publisher la rechazaba por «el contenido cambió desde
                        // que abriste la página».
                        //
                        // Ese guard protege de que OTRA sesión te pise. Un cambio
                        // hecho en esta misma pantalla no es ese caso: el owner
                        // acaba de verlo. Se avisa a la página para que se ponga
                        // al día sin recargar ni sacarlo de donde está.
                        $this->dispatch('borrador-actualizado');

                        return $record->refresh();
                    }),
            ]);
    }
}

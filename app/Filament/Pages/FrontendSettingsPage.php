<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\CtaFields;
use App\Forms\Components\NonDestructiveMediaUpload;
use App\Models\FrontendSetting;
use App\Services\Frontend\FrontendMediaReference;
use App\Services\Frontend\FrontendThemeService;
use App\Support\Frontend\CtaResolver;
use App\Support\Frontend\PublicRoutes;
use App\Support\Frontend\ThemeContract;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Owner-only editor for the frontend singleton: identity, contact, default SEO,
 * brand media (batch A) and the runtime visual theme (batch B).
 *
 * Strategy A — saving IS publishing: hard validation on save plus a generation
 * bump afterCommit. There are no draft columns here.
 *
 * Brand media: NonDestructiveMediaUpload only (§16.4). The *_media_id columns
 * are synced from the form state after saving relationships — they are the
 * single source of truth the render resolves; collections just store files.
 *
 * Theme: this page is the FIRST of the two boundaries of §16.5. It rejects
 * unreadable or uncompiled values outright; FrontendThemeService re-normalizes
 * again at render because the form is not the only writer.
 */
class FrontendSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Frontend';

    protected static ?string $navigationLabel = 'Configuración del sitio';

    protected static ?string $title = 'Configuración del sitio público';

    protected static ?string $slug = 'frontend/configuracion';

    protected static string $view = 'filament.pages.frontend-settings-page';

    /** Field name in the form => [brand column, media collection]. */
    private const BRAND_FIELDS = [
        'logo_light' => ['logo_light_media_id', 'logo-light'],
        'logo_dark' => ['logo_dark_media_id', 'logo-dark'],
        'favicon' => ['favicon_media_id', 'favicon'],
        'og_image' => ['og_image_media_id', 'default-og-image'],
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Hard validation of §16.5. The render re-normalizes anyway, but silently
     * repairing what the owner just chose would be worse than telling them:
     * they would see one palette in the form and another on the site.
     *
     * @param  array<string, mixed>  $theme
     */
    private function assertThemeIsValid(array $theme): void
    {
        $errors = [];

        foreach (['primary', 'on_primary', 'accent', 'on_accent', 'background', 'text'] as $colour) {
            $value = $theme[$colour] ?? null;

            if ($value !== null && $value !== '' && ! ThemeContract::isHex($value)) {
                $errors["data.theme.{$colour}"] = 'Usa un color hexadecimal de seis dígitos, por ejemplo #2e3842.';
            }
        }

        foreach (['heading_font', 'body_font', 'eyebrow_font'] as $font) {
            $value = $theme[$font] ?? null;

            if ($value !== null && $value !== '' && ! ThemeContract::isFont($value)) {
                $errors["data.theme.{$font}"] = 'Elige una de las tipografías disponibles: '.implode(' o ', ThemeContract::FONTS).'.';
            }
        }

        $radius = $theme['radius'] ?? null;
        if ($radius !== null && $radius !== '' && ! ThemeContract::isRadius($radius)) {
            $errors['data.theme.radius'] = 'Elige uno de los redondeos disponibles.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Owner override: the owner may explicitly accept low-contrast brand
        // colours (their call, their responsibility). When on, the AA pair check
        // is skipped at BOTH boundaries — here and in FrontendThemeService, which
        // otherwise reverts an unreadable pair at render.
        if (! empty($theme['allow_low_contrast'])) {
            return;
        }

        // Contrast is checked only once every colour is known to be valid hex,
        // otherwise the ratio would be computed on garbage.
        $effective = array_merge(ThemeContract::DEFAULTS, array_filter(
            $theme,
            fn ($v) => $v !== null && $v !== '',
        ));

        foreach (ThemeContract::CONTRAST_PAIRS as [$foreground, $background]) {
            if (! ThemeContract::meetsAa($effective[$foreground], $effective[$background])) {
                $ratio = round(ThemeContract::contrastRatio($effective[$foreground], $effective[$background]), 2);

                $errors["data.theme.{$foreground}"] = "El contraste es de {$ratio}:1 y necesita al menos 4.5:1 para leerse bien. Usa un color más claro u oscuro.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, string> allowlist key => default label */
    private function navigationOptions(): array
    {
        $options = [];
        foreach (PublicRoutes::ALLOWLIST as $key => $entry) {
            $options[$key] = $entry['label'];
        }

        return $options;
    }

    /**
     * The shared CTA value-object fields (RFC-073). Reused by the header CTAs
     * and every footer link so there is one destination editor, not several.
     *
     * @return array<int, Field>
     */

    /**
     * RFC-073 save-time guards. The render re-validates too (defense in depth),
     * but the owner must be told here rather than silently losing a link:
     *
     * - navigation can never end up empty (block the save, don't fall back);
     * - labels never carry HTML;
     * - every non-empty CTA / footer link must resolve through CtaResolver, so
     *   an unsafe scheme or a bad target is rejected before it is stored.
     *
     * @param  array<string, mixed>  $state
     */
    private function assertNavigationIsValid(array $state): void
    {
        $errors = [];
        $resolver = app(CtaResolver::class);

        $nav = $state['navigation'] ?? null;
        if (is_array($nav) && $nav !== []) {
            $enabled = array_filter(
                $nav,
                fn ($item): bool => is_array($item)
                    && ($item['enabled'] ?? false) === true
                    && PublicRoutes::isKey($item['key'] ?? null),
            );

            if ($enabled === []) {
                $errors['data.navigation'] = 'Deja al menos una página visible; la navegación no puede quedar vacía.';
            }

            foreach ($nav as $i => $item) {
                if (is_string($item['label'] ?? null) && preg_match('/[<>]/', $item['label']) === 1) {
                    $errors["data.navigation.{$i}.label"] = 'La etiqueta no puede contener HTML.';
                }
            }
        }

        foreach (['primary_cta', 'secondary_cta'] as $field) {
            $cta = $state[$field] ?? null;
            if ($this->ctaHasContent($cta) && $resolver->resolve($cta) === null) {
                $errors["data.{$field}.target"] = 'Este destino no es válido o no es seguro.';
            }
        }

        foreach ($state['footer']['columns'] ?? [] as $c => $column) {
            foreach ($column['links'] ?? [] as $l => $link) {
                if ($this->ctaHasContent($link) && $resolver->resolve($link) === null) {
                    $errors["data.footer.columns.{$c}.links.{$l}.target"] = 'Este enlace no es válido o no es seguro.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** A CTA the owner started filling — an all-empty one just means "unset". */
    private function ctaHasContent(mixed $cta): bool
    {
        if (! is_array($cta)) {
            return false;
        }

        foreach (['label', 'type', 'target'] as $field) {
            if (trim((string) ($cta[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        // Double gate (§16.2): role AND permission, like FrontendSettingPolicy.
        return ($user?->hasRole('owner') && $user->can('frontend.manage')) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $attributes = FrontendSetting::current()->attributesToArray();
        // Present the stored hours as the friendly per-day editor (the owner's
        // «bypass»): the input is per-day, the storage stays the key-value map.
        $attributes['hours_ui'] = $this->decompileHours($attributes['business_hours'] ?? []);

        $this->form->fill($attributes);
    }

    public function form(Form $form): Form
    {
        $raster = ['image/png', 'image/jpeg', 'image/webp'];

        return $form
            ->statePath('data')
            ->model(FrontendSetting::current())
            ->schema([
                Section::make('Identidad')
                    ->description('Nombre y descripción con los que se presenta tu inmobiliaria.')
                    ->schema([
                        TextInput::make('site_name')->label('Nombre del sitio')->required()->maxLength(120)
                            ->helperText($this->currentValueHint('site_name', 'Nombre')),
                        TextInput::make('tagline')->label('Lema')->maxLength(180)
                            ->helperText($this->currentValueHint('tagline', 'Lema')),
                        Textarea::make('short_description')->label('Descripción corta')->maxLength(300)->rows(2)
                            ->helperText($this->currentValueHint('short_description', 'Descripción')),
                        TextInput::make('legal_name')->label('Razón social')->maxLength(255)
                            ->helperText($this->currentValueHint('legal_name', 'Razón social')),
                    ])->columns(2),

                Section::make('Contacto')
                    ->description('Estos datos se muestran en el sitio público y en el footer.')
                    ->schema([
                        TextInput::make('public_phone')->label('Teléfono')->tel()->maxLength(30),
                        TextInput::make('whatsapp_phone')->label('WhatsApp')->tel()->maxLength(30)
                            ->helperText('Solo dígitos con código de país, por ejemplo 5214421234567.'),
                        TextInput::make('public_email')->label('Correo')->email()->maxLength(255),
                        TextInput::make('public_address')->label('Dirección')->maxLength(255),
                        Fieldset::make('Horario de atención')
                            ->schema($this->hoursEditor())
                            ->columns(1)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Cómo se ve tu sitio al compartirlo (SEO)')
                    ->description('Cuando alguien comparte el enlace de tu sitio en WhatsApp, Facebook o LinkedIn, o lo encuentra en Google, se muestra un título, una descripción y la imagen de redes. Escribí los textos y mirá abajo la vista previa en vivo. Si una página define los suyos, esos ganan; si no, se usan estos.')
                    ->schema([
                        Fieldset::make('En buscadores (Google)')->columns(1)->schema([
                            TextInput::make('default_meta_title')->label('Título')->maxLength(255)->live(onBlur: true)
                                ->helperText('El título azul que se ve en los resultados de Google. Lo ideal: hasta 60 caracteres.'),
                            Textarea::make('default_meta_description')->label('Descripción')->maxLength(300)->rows(2)->live(onBlur: true)
                                ->helperText('El texto gris debajo del título en Google. Lo ideal: hasta 160 caracteres.'),
                        ]),
                        Fieldset::make('Al compartir en redes (WhatsApp, Facebook…)')->columns(1)->schema([
                            TextInput::make('default_og_title')->label('Título')->maxLength(255)->live(onBlur: true)
                                ->helperText('Si lo dejás vacío, se usa el título de buscadores.'),
                            Textarea::make('default_og_description')->label('Descripción')->maxLength(300)->rows(2)->live(onBlur: true)
                                ->helperText('Si la dejás vacía, se usa la descripción de buscadores.'),
                        ]),
                        Placeholder::make('seo_preview')
                            ->label('Vista previa al compartir el enlace')
                            ->columnSpanFull()
                            ->content(fn (Get $get): HtmlString => new HtmlString($this->seoPreviewHtml($get))),
                    ])->columns(2),

                Section::make('Tema visual')
                    ->description('Colores y tipografías del sitio público. Los pares de texto deben tener contraste suficiente para leerse bien (WCAG AA).')
                    ->schema([
                        Placeholder::make('theme_logos')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn (): HtmlString => new HtmlString($this->themeLogosHtml())),
                        ColorPicker::make('theme.primary')->label('Color principal')->hex()
                            ->live(onBlur: true)
                            ->helperText('Se usa en encabezados y superficies de marca.'),
                        ColorPicker::make('theme.on_primary')->label('Texto sobre el principal')->hex()->live(onBlur: true),
                        ColorPicker::make('theme.accent')->label('Color de acento')->hex()
                            ->live(onBlur: true)
                            ->helperText('Se usa en botones y llamados a la acción.'),
                        ColorPicker::make('theme.on_accent')->label('Texto sobre el acento')->hex()->live(onBlur: true),
                        ColorPicker::make('theme.background')->label('Fondo del sitio')->hex()->live(onBlur: true),
                        ColorPicker::make('theme.text')->label('Texto sobre el fondo')->hex()->live(onBlur: true),
                        Select::make('theme.heading_font')->label('Tipografía de títulos')
                            ->options(array_combine(ThemeContract::FONTS, ThemeContract::FONTS))
                            ->native(false)
                            ->live()
                            ->helperText('Se aplica a los títulos de todas las secciones.'),
                        Toggle::make('theme.heading_bold')->label('Títulos en negrita')->inline(false)->live()
                            ->helperText('Cada sección puede llevarle la contra sin cambiar esto.'),
                        // El ANTETÍTULO se configura aparte del título. Son dos
                        // decisiones distintas —la gracia del par suele ser que
                        // NO se parezcan— y hasta ahora el antetítulo ni siquiera
                        // respetaba la tipografía elegida.
                        Select::make('theme.eyebrow_font')->label('Tipografía de antetítulos')
                            ->options(array_combine(ThemeContract::FONTS, ThemeContract::FONTS))
                            ->native(false)
                            ->live()
                            ->helperText('El texto chico en mayúsculas que va arriba del título.'),
                        Toggle::make('theme.eyebrow_bold')->label('Antetítulos en negrita')->inline(false)->live()
                            ->helperText('Cada sección puede llevarle la contra sin cambiar esto.'),
                        Select::make('theme.body_font')->label('Tipografía de texto')
                            ->options(array_combine(ThemeContract::FONTS, ThemeContract::FONTS))
                            ->native(false)
                            ->live()
                            ->helperText('Los párrafos y todo lo que no sea título.'),
                        Select::make('theme.radius')->label('Redondeo de esquinas')
                            ->options([
                                'none' => 'Sin redondeo',
                                'soft' => 'Pequeño',
                                'medium' => 'Medio',
                                'rounded' => 'Redondeado',
                                'xl' => 'Muy redondeado',
                            ])
                            ->native(false)
                            ->live(),
                        // El redondeo NO tiene vista previa propia: vive dentro de
                        // la del sitio, arriba a la derecha. Dos maquetas separadas
                        // obligaban a mirar en dos lados una decisión que se ve
                        // mejor junto al resto.
                        Placeholder::make('site_preview')
                            ->label('Así se va a ver tu sitio')
                            ->columnSpanFull()
                            ->content(fn (Get $get): HtmlString => new HtmlString(
                                $this->sitePreviewHtml(is_array($get('theme')) ? $get('theme') : []),
                            )),
                        Toggle::make('theme.allow_low_contrast')
                            ->label('Permitir combinaciones de bajo contraste')
                            ->live()
                            ->helperText('Por defecto se exige contraste suficiente (WCAG AA) para que los textos se lean bien. Actívalo solo si tu marca lo requiere y asumes que algunos textos pueden costar más de leer. Aplica a los tres pares de color de arriba.')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Navegación principal')
                    ->description('Cada fila: la página, su etiqueta (vacío = nombre por defecto) y si está visible. Arrastrá para reordenar. Solo se pueden elegir páginas existentes; si dejás todo oculto, no se podrá guardar. Sin configuración, el menú muestra las siete páginas actuales.')
                    ->schema([
                        Repeater::make('navigation')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('key')->hiddenLabel()->prefix('Página')->placeholder('Elegí una')
                                    ->options($this->navigationOptions())->required()->distinct()->native(false)
                                    ->columnSpan(['default' => 1, 'sm' => 5]),
                                TextInput::make('label')->hiddenLabel()->prefix('Etiqueta')->placeholder('Nombre por defecto')->maxLength(40)
                                    ->columnSpan(['default' => 1, 'sm' => 5]),
                                Toggle::make('enabled')->label('Visible')->default(true)->inline()
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ])
                            ->columns(['default' => 1, 'sm' => 12])
                            ->reorderable()
                            ->addActionLabel('Agregar página'),
                    ]),

                Section::make('Llamados a la acción (CTA)')
                    ->description('El botón principal del header y del menú móvil. Sin configuración, es «Agenda una cita» hacia Contacto.')
                    ->schema([
                        Fieldset::make('CTA principal')->schema(CtaFields::make('primary_cta'))->columns(3),
                        Fieldset::make('CTA secundario (opcional)')->schema(CtaFields::make('secondary_cta'))->columns(3),
                    ]),

                Section::make('Footer')
                    ->description('Columnas de enlaces del pie de página. Un enlace oculto conserva su configuración pero no se muestra; los destinos deben ser seguros.')
                    ->schema([
                        Repeater::make('footer.columns')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('title')->label('Título de la columna')->maxLength(40),
                                Repeater::make('links')->label('Enlaces')
                                    ->schema([
                                        // Etiquetas como prefijo (inline) y toggle inline en una
                                        // sola fila; el Destino ocupa todo el ancho para que su
                                        // explicación se lea completa y la tarjeta no crezca a lo alto.
                                        TextInput::make('label')->hiddenLabel()->prefix('Texto')->maxLength(40)
                                            ->placeholder('Nombre del enlace')
                                            ->columnSpan(['default' => 1, 'sm' => 5]),
                                        Select::make('type')->hiddenLabel()->prefix('Tipo')->native(false)->live()->options([
                                            'route' => 'Página del sitio',
                                            'url' => 'URL externa (https)',
                                            'whatsapp' => 'WhatsApp',
                                            'mailto' => 'Correo',
                                            'tel' => 'Teléfono',
                                        ])->columnSpan(['default' => 1, 'sm' => 4]),
                                        Toggle::make('enabled')->label('Visible')->default(true)->inline()
                                            ->columnSpan(['default' => 1, 'sm' => 3]),
                                        // La instrucción del Destino cambia según el tipo elegido
                                        // (el Select es ->live()): así el usuario ve exactamente
                                        // qué pegar para página, URL, WhatsApp, correo o teléfono.
                                        TextInput::make('target')->hiddenLabel()->prefix('Destino')->maxLength(255)
                                            ->placeholder(fn (Get $get): string => CtaFields::guidance($get('type'))['placeholder'])
                                            ->helperText(fn (Get $get): string => CtaFields::guidance($get('type'))['help'])
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(['default' => 1, 'sm' => 12])
                                    ->addActionLabel('Agregar enlace'),
                            ])
                            ->addActionLabel('Agregar columna'),
                        TextInput::make('footer.legal_text')->label('Texto legal')->maxLength(200)
                            ->helperText('Vacío = aviso de derechos por defecto.'),
                        Fieldset::make('Redes sociales')
                            ->columns(1)
                            ->schema([
                                $this->socialField('instagram', 'Instagram', 'https://instagram.com/tu_perfil'),
                                $this->socialField('tiktok', 'TikTok', 'https://tiktok.com/@tu_perfil'),
                                $this->socialField('facebook', 'Facebook', 'https://facebook.com/tu_pagina'),
                            ]),
                    ]),

                Section::make('Marca')
                    ->description('Cada imagen muestra lo que se ve hoy en el sitio. Toca «Cambiar imagen» para subir una nueva; la anterior nunca se borra, solo deja de usarse. Sin imagen propia, se usa la marca de Landra por defecto.')
                    ->schema([
                        $this->brandAsset(
                            NonDestructiveMediaUpload::make('logo_light')
                                ->collection('logo-light')->uuidColumn('logo_light_media_id')
                                ->acceptedFileTypes($raster)->maxSize(3072)->maxFiles(1)->image(),
                            column: 'logo_light_media_id', collection: 'logo-light', variant: 'logo-light',
                            title: 'Logo — fondo claro', hint: 'Se usa en el encabezado y en fondos blancos.',
                            specs: 'PNG con fondo transparente · cualquier forma, mínimo 200 px de lado · máx. 3 MB',
                        ),
                        $this->brandAsset(
                            NonDestructiveMediaUpload::make('logo_dark')
                                ->collection('logo-dark')->uuidColumn('logo_dark_media_id')
                                ->acceptedFileTypes($raster)->maxSize(3072)->maxFiles(1)->image(),
                            column: 'logo_dark_media_id', collection: 'logo-dark', variant: 'logo-dark',
                            title: 'Logo — fondo oscuro', hint: 'Se usa en el pie de página y sobre fondos de color.',
                            specs: 'PNG con fondo transparente · cualquier forma, mínimo 200 px de lado · máx. 3 MB',
                        ),
                        $this->brandAsset(
                            NonDestructiveMediaUpload::make('favicon')
                                ->collection('favicon')->uuidColumn('favicon_media_id')
                                ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'])
                                ->maxSize(1024)->maxFiles(1),
                            column: 'favicon_media_id', collection: 'favicon', variant: 'favicon',
                            title: 'Favicon', hint: 'El ícono chico de la pestaña del navegador.',
                            specs: 'PNG o ICO cuadrado · 512×512 px (mín. 32×32) · máx. 1 MB',
                        ),
                        $this->brandAsset(
                            NonDestructiveMediaUpload::make('og_image')
                                ->collection('default-og-image')->uuidColumn('og_image_media_id')
                                ->acceptedFileTypes($raster)->maxSize(3072)->maxFiles(1)->image()
                                ->rules(['dimensions:min_width=1200,min_height=630']),
                            column: 'og_image_media_id', collection: 'default-og-image', variant: 'og',
                            title: 'Imagen para redes', hint: 'La vista previa al compartir el sitio en WhatsApp, Facebook o LinkedIn.',
                            specs: 'JPG o PNG · 1200×630 px (proporción 1.91:1) · máx. 3 MB',
                        ),
                    ])->columns(2),
            ]);
    }

    /**
     * A brand asset as a card: the CURRENT image shown on its intended backdrop
     * (a light-bg logo on white, a dark-bg logo on navy, etc.) plus the
     * non-destructive uploader relabeled «Cambiar imagen». The uploader is the
     * SAME component the audited save() flow reads — only the presentation
     * around it changes, so §16.4 (media is never deleted) is untouched.
     */
    private function brandAsset(NonDestructiveMediaUpload $upload, string $column, string $collection, string $variant, string $title, string $hint, string $specs): Group
    {
        return Group::make([
            Placeholder::make("preview_{$column}")
                ->hiddenLabel()
                ->content(fn (): HtmlString => new HtmlString($this->brandPreviewHtml($column, $collection, $variant, $title, $hint, $specs))),
            $upload->label('Cambiar imagen')->hiddenLabel()->helperText(null),
        ]);
    }

    /**
     * The saved brand logos above the theme colours, with a colour picker so the
     * owner can pull an exact colour straight from the logo: click anywhere on a
     * logo to sample that pixel (canvas), or use the native eyedropper. The
     * picked hex shows with a copy button to paste into any colour field. Fully
     * client-side (Alpine) — it reads nothing and writes nothing on the server.
     */
    private function themeLogosHtml(): string
    {
        $setting = FrontendSetting::current();
        $refs = app(FrontendMediaReference::class);
        $light = $refs->resolve($setting->logo_light_media_id, $setting, 'logo-light')?->getUrl()
            ?? asset('images/brand/logo-on-light.svg');
        $dark = $refs->resolve($setting->logo_dark_media_id, $setting, 'logo-dark')?->getUrl()
            ?? asset('images/brand/logo-on-dark.svg');

        return <<<HTML
<div x-data="{
        picked: null,
        copied: false,
        sample(e) {
            try {
                const img = e.target; const r = img.getBoundingClientRect();
                const w = img.naturalWidth || r.width, h = img.naturalHeight || r.height;
                const cx = (e.clientX - r.left) * (w / r.width), cy = (e.clientY - r.top) * (h / r.height);
                const c = document.createElement('canvas'); c.width = w; c.height = h;
                const ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0, w, h);
                const p = ctx.getImageData(cx, cy, 1, 1).data;
                this.picked = '#' + [p[0], p[1], p[2]].map(v => v.toString(16).padStart(2, '0')).join(''); this.copied = false;
            } catch (err) { alert('No se pudo leer el color de esta imagen. Probá el botón Cuentagotas.'); }
        },
        async eyedrop() {
            if (!window.EyeDropper) { alert('El cuentagotas necesita Chrome o Edge. También podés hacer clic sobre el logo para tomar un color.'); return; }
            try { const res = await new EyeDropper().open(); this.picked = res.sRGBHex; this.copied = false; } catch (e) {}
        },
        copy() { if (this.picked) { navigator.clipboard.writeText(this.picked); this.copied = true; } }
    }" style="border:1px solid #e5e7eb;border-radius:12px;padding:14px">
    <div style="font-size:13px;font-weight:600;color:#111827">Tu logotipo guardado</div>
    <div style="font-size:12px;color:#6b7280;margin-top:2px">Hacé clic en cualquier parte de un logo para tomar ese color, o usá el cuentagotas.</div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:12px">
        <div style="flex:1;min-width:160px;background:#ffffff;border:1px solid #f3f4f6;border-radius:10px;padding:16px;display:flex;align-items:center;justify-content:center">
            <img src="{$light}" alt="Logo fondo claro" @click="sample(\$event)" style="max-height:56px;max-width:90%;object-fit:contain;cursor:crosshair">
        </div>
        <div style="flex:1;min-width:160px;background:#050f38;border-radius:10px;padding:16px;display:flex;align-items:center;justify-content:center">
            <img src="{$dark}" alt="Logo fondo oscuro" @click="sample(\$event)" style="max-height:56px;max-width:90%;object-fit:contain;cursor:crosshair">
        </div>
    </div>
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:12px">
        <button type="button" @click="eyedrop()" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:500;color:#374151;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:7px 12px;cursor:pointer">
            <svg style="width:15px;height:15px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 22 1-1h3l9-9"/><path d="M3 21v-3l9-9"/><path d="m15 6 3.4-3.4a2.1 2.1 0 1 1 3 3L21 6l-3.4 3.4a2 2 0 0 1-2.83 0l-.17-.17a2 2 0 0 1 0-2.83z"/></svg>
            Cuentagotas
        </button>
        <template x-if="picked">
            <div style="display:inline-flex;align-items:center;gap:8px">
                <span :style="'display:inline-block;width:22px;height:22px;border-radius:6px;border:1px solid rgba(0,0,0,.1);background:' + picked"></span>
                <code style="font-size:13px;color:#111827" x-text="picked"></code>
                <button type="button" @click="copy()" style="font-size:12px;font-weight:500;color:#2563eb;background:none;border:none;cursor:pointer" x-text="copied ? '¡Copiado!' : 'Copiar'"></button>
            </div>
        </template>
    </div>
</div>
HTML;
    }

    /**
     * One social network as a compact row: its brand icon + name on the left, the
     * profile URL on the right. Three fixed fields (Instagram, TikTok, Facebook)
     * always visible, so the owner just pastes each URL. Empty ⇒ its icon does not
     * render in the footer (FrontendNavigationService filters unset networks).
     */
    private function socialField(string $network, string $label, string $example): Grid
    {
        return Grid::make(['default' => 1, 'sm' => 12])->schema([
            Placeholder::make("{$network}_icon")->hiddenLabel()
                ->content(fn (): HtmlString => new HtmlString(
                    '<div style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:14px;color:#374151">'
                    .view('frontend.social-icon', ['network' => $network, 'class' => 'h-5 w-5'])->render()
                    ."<span>{$label}</span></div>"
                ))
                ->columnSpan(['default' => 1, 'sm' => 3]),
            TextInput::make("social_links.{$network}")->hiddenLabel()->url()->maxLength(255)
                ->placeholder($example)
                ->columnSpan(['default' => 1, 'sm' => 9]),
        ]);
    }

    /** The seven days, in order: form key => public label. */
    private const DAYS = [
        'lunes' => 'Lunes',
        'martes' => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves' => 'Jueves',
        'viernes' => 'Viernes',
        'sabado' => 'Sábado',
        'domingo' => 'Domingo',
    ];

    /** Horario por defecto al abrir un día: 9:00 am – 6:00 pm. */
    private const DEFAULT_OPEN = '09:00';

    private const DEFAULT_CLOSE = '18:00';

    /**
     * The friendly weekly-hours editor: one row per day with an «Abierto» toggle
     * and an opening/closing time picker. It is a PRESENTATION over the existing
     * `business_hours` key-value — save() compiles these rows back into that same
     * shape (`{"Lunes": "09:00 – 18:00", …}`), so the stored contract and the
     * render never change (the owner's «bypass»). A closed day is simply omitted.
     *
     * @return list<Grid>
     */
    private function hoursEditor(): array
    {
        $rows = [];
        foreach (self::DAYS as $key => $label) {
            $rows[] = Grid::make(['default' => 1, 'sm' => 12])->schema([
                Toggle::make("hours_ui.{$key}.enabled")->label($label)->inline()->live()
                    ->columnSpan(['default' => 1, 'sm' => 4])
                    // Al abrir un día, precargar 9:00–18:00 si está vacío, para que
                    // los selectores no queden en blanco (fuente de confusión).
                    ->afterStateUpdated(function (bool $state, Get $get, Set $set) use ($key): void {
                        if ($state) {
                            if (blank($get("hours_ui.{$key}.open"))) {
                                $set("hours_ui.{$key}.open", self::DEFAULT_OPEN);
                            }
                            if (blank($get("hours_ui.{$key}.close"))) {
                                $set("hours_ui.{$key}.close", self::DEFAULT_CLOSE);
                            }
                        }
                    }),
                TimePicker::make("hours_ui.{$key}.open")->hiddenLabel()->placeholder('Apertura')
                    ->prefixIcon('heroicon-m-arrow-right-start-on-rectangle')
                    ->seconds(false)->format('H:i')->displayFormat('H:i')->minutesStep(5)
                    ->columnSpan(['default' => 1, 'sm' => 4])
                    ->visible(fn (Get $get): bool => (bool) $get("hours_ui.{$key}.enabled")),
                TimePicker::make("hours_ui.{$key}.close")->hiddenLabel()->placeholder('Cierre')
                    ->prefixIcon('heroicon-m-arrow-right-end-on-rectangle')
                    ->seconds(false)->format('H:i')->displayFormat('H:i')->minutesStep(5)
                    ->columnSpan(['default' => 1, 'sm' => 4])
                    ->visible(fn (Get $get): bool => (bool) $get("hours_ui.{$key}.enabled")),
            ]);
        }

        return $rows;
    }

    /**
     * Rebuild the per-day editor state from the stored `business_hours` map.
     * Best-effort: a value of the exact shape «HH:MM – HH:MM» under a day label
     * populates that day; anything else (legacy free-form, unparseable) leaves the
     * day closed for the owner to set. No data is lost — the render still reads
     * whatever is stored until the owner saves the new shape.
     *
     * @param  mixed  $stored
     * @return array<string, array{enabled: bool, open: ?string, close: ?string}>
     */
    private function decompileHours($stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        // Index the stored map by a normalized day label for a tolerant lookup.
        $byLabel = [];
        foreach ($stored as $k => $v) {
            $byLabel[mb_strtolower(trim((string) $k))] = is_string($v) ? $v : '';
        }

        $ui = [];
        foreach (self::DAYS as $key => $label) {
            $value = $byLabel[mb_strtolower($label)] ?? '';
            if (preg_match('/^\s*(\d{1,2}:\d{2})\s*[–-]\s*(\d{1,2}:\d{2})\s*$/u', $value, $m) === 1) {
                $ui[$key] = ['enabled' => true, 'open' => $m[1], 'close' => $m[2]];
            } else {
                $ui[$key] = ['enabled' => false, 'open' => null, 'close' => null];
            }
        }

        return $ui;
    }

    /**
     * Compile the per-day editor state back into the stored `business_hours`
     * map: only enabled days with both times, as «Día => HH:MM – HH:MM», in the
     * canonical week order the render expects.
     *
     * @param  mixed  $ui
     * @return array<string, string>
     */
    private function compileHours($ui): array
    {
        $ui = is_array($ui) ? $ui : [];
        $hours = [];
        foreach (self::DAYS as $key => $label) {
            $day = is_array($ui[$key] ?? null) ? $ui[$key] : [];
            $open = $this->normalizeTime($day['open'] ?? null);
            $close = $this->normalizeTime($day['close'] ?? null);
            if (($day['enabled'] ?? false) && $open !== null && $close !== null) {
                $hours[$label] = "{$open} – {$close}";
            }
        }

        return $hours;
    }

    /**
     * A time to «HH:MM», or null when it is not a readable time. Tolerant of a
     * full datetime string (some pickers dehydrate «2024-01-01 09:00:00»): the
     * first HH:MM in the value wins.
     */
    private function normalizeTime($value): ?string
    {
        if (! is_string($value) || preg_match('/(\d{1,2}):(\d{2})/', trim($value), $m) !== 1) {
            return null;
        }

        $h = (int) $m[1];
        $min = (int) $m[2];

        return $h >= 0 && $h <= 23 && $min >= 0 && $min <= 59
            ? sprintf('%02d:%02d', $h, $min)
            : null;
    }

    /**
     * A LIVE social-share preview card (WhatsApp / Facebook style) so a
     * non-technical owner sees what "SEO" actually produces. It reflects the same
     * fallback the public layout applies — og_* falls back to meta_*, then to the
     * site name / shipped default — and uses the saved «Imagen para redes».
     * The SEO fields are `->live(onBlur:true)`, so the card refreshes as they type.
     */
    private function seoPreviewHtml(Get $get): string
    {
        $siteName = trim((string) ($get('site_name') ?: 'Landra'));
        $title = trim((string) ($get('default_og_title') ?: $get('default_meta_title') ?: $siteName));
        $description = trim((string) ($get('default_og_description') ?: $get('default_meta_description')
            ?: 'Landra — Real Estate. Construimos patrimonio, diseñamos espacios y comercializamos oportunidades.'));

        // The saved OG image (upload lives in the «Marca» section), or the default.
        $setting = FrontendSetting::current();
        $image = app(FrontendMediaReference::class)->resolve($setting->og_image_media_id, $setting, 'default-og-image')?->getUrl()
            ?? asset('images/metaimage/meta_image_landra.jpg');

        $host = mb_strtoupper((string) (parse_url((string) url('/'), PHP_URL_HOST) ?: $siteName));

        $t = e($title);
        $d = e($description);
        $h = e($host);

        return <<<HTML
<div style="max-width:340px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <img src="{$image}" alt="" style="display:block;width:100%;height:auto;background:#0b1120">
    <div style="padding:10px 12px">
        <div style="font-size:10px;letter-spacing:.04em;color:#9ca3af;text-transform:uppercase">{$h}</div>
        <div style="font-size:14px;font-weight:600;color:#111827;margin-top:2px;line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{$t}</div>
        <div style="font-size:12px;color:#6b7280;margin-top:3px;line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{$d}</div>
    </div>
</div>
HTML;
    }

    /**
     * A "current value" hint for an identity field, so the owner always sees
     * what is live even after they start typing a replacement in the input. The
     * saved value is shown verbatim (bold); an empty column reads "sin definir".
     */
    private function currentValueHint(string $column, string $noun): HtmlString
    {
        $value = trim((string) (FrontendSetting::current()->{$column} ?? ''));

        return new HtmlString($value === ''
            ? "{$noun} actual: <span style=\"color:#6b7280\">sin definir</span>"
            : "{$noun} actual: <strong>".e($value).'</strong>');
    }

    /**
     * Una maqueta del sitio con TODO lo configurado, a lo ancho del panel.
     *
     * Los colores, las tipografías y el redondeo se eligen en nueve controles
     * separados, y hasta acá la única forma de saber si el conjunto funcionaba
     * era guardar y salir a mirar el sitio. Esto lo muestra antes de guardar.
     *
     * Pide el tema NORMALIZADO —las mismas reglas que corre el sitio— y no los
     * valores crudos del formulario. La diferencia importa justo cuando importa
     * mirar: si un par no llega a AA y el owner no pidió bajo contraste, el sitio
     * cambia la tinta, y acá se ve ese cambio en vez del color elegido.
     *
     * Todo va en `style` inline: el panel compila su propio CSS y no tiene
     * ninguna de las utilities del sitio. Es la misma razón por la que la paleta
     * de colores también usa `style`.
     */
    private function sitePreviewHtml(array $crudo): string
    {
        $t = app(FrontendThemeService::class)->normalize($crudo);

        $r = $t['radius_scale'];
        $titulo = $this->fontStack($t['heading_font']);
        $ante = $this->fontStack($t['eyebrow_font']);
        $cuerpo = $this->fontStack($t['body_font']);
        $pesoTitulo = $t['heading_bold'] ? '700' : '400';
        $pesoAnte = $t['eyebrow_bold'] ? '700' : '600';

        $ante = "font-family:{$ante};font-size:11px;font-weight:{$pesoAnte};letter-spacing:.08em;text-transform:uppercase";

        // La muestra de ESQUINAS va arriba a la derecha del encabezado, y se
        // dibuja con el fondo del SITIO en vez de un gris fijo: así se lee sobre
        // cualquier color principal —incluso uno claro— y el color que muestra es
        // uno real del tema en vez de un gris que no existe en ningún lado.

        return <<<HTML
<div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
    <div style="background:{$t['primary']};padding:22px 24px;display:flex;gap:20px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <p style="{$ante};color:{$t['accent_on_primary']};margin:0 0 8px">Quiénes somos</p>
            <p style="font-family:{$titulo};font-weight:{$pesoTitulo};font-size:26px;line-height:1.15;color:{$t['on_primary']};margin:0 0 10px">Construimos patrimonio que trasciende.</p>
            <p style="font-family:{$cuerpo};font-size:13px;line-height:1.6;color:{$t['on_primary']};opacity:.8;margin:0 0 16px;max-width:420px">Así se va a ver un encabezado sobre el color principal de tu marca.</p>
            <span style="display:inline-block;background:{$t['accent']};color:{$t['on_accent']};font-family:{$cuerpo};font-size:13px;font-weight:600;padding:9px 18px;border-radius:{$r['md']}">Agenda una cita</span>
        </div>

        <div style="flex-shrink:0;text-align:center">
            <p style="{$ante};color:{$t['on_primary']};opacity:.65;margin:0 0 7px">Esquinas</p>
            <div style="width:124px;height:76px;background:{$t['background']};border-radius:{$r['lg']};display:flex;align-items:center;justify-content:center">
                <span style="background:{$t['accent']};color:{$t['on_accent']};font-family:{$cuerpo};font-size:11px;font-weight:600;padding:5px 13px;border-radius:{$r['md']}">Botón</span>
            </div>
        </div>
    </div>

    <div style="background:{$t['background']};padding:22px 24px">
        <p style="{$ante};color:{$t['accent_ink']};margin:0 0 8px">Lo que nos guía</p>
        <p style="font-family:{$titulo};font-weight:{$pesoTitulo};font-size:20px;line-height:1.2;color:{$t['primary_ink']};margin:0 0 14px">Nuestros valores</p>

        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:180px;background:#ffffff;border-radius:{$r['lg']};padding:16px">
                <p style="font-family:{$titulo};font-weight:{$pesoTitulo};font-size:15px;color:{$t['primary_ink']};margin:0 0 6px">Confianza</p>
                <p style="font-family:{$cuerpo};font-size:12.5px;line-height:1.6;color:{$t['text']};margin:0">Una tarjeta sobre el fondo del sitio, con el redondeo elegido.</p>
            </div>
            <div style="flex:1;min-width:180px;background:{$t['accent']};border-radius:{$r['lg']};padding:16px">
                <p style="font-family:{$titulo};font-weight:{$pesoTitulo};font-size:15px;color:{$t['on_accent']};margin:0 0 6px">Acento</p>
                <p style="font-family:{$cuerpo};font-size:12.5px;line-height:1.6;color:{$t['on_accent']};opacity:.85;margin:0">Y así se lee un texto sobre el color de acento.</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * La familia lista para un `style` inline, con su reserva.
     *
     * Sale de la lista CERRADA de `ThemeContract`, así que no hay texto del owner
     * entrando en un atributo de estilo. Las comillas van simples porque el
     * atributo las lleva dobles.
     */
    private function fontStack(string $font): string
    {
        $font = in_array($font, ThemeContract::FONTS, true) ? $font : 'Montserrat';

        return "'{$font}',ui-sans-serif,system-ui,sans-serif";
    }

    /** The preview card HTML for a brand asset: current image on its backdrop + state note. */
    private function brandPreviewHtml(string $column, string $collection, string $variant, string $title, string $hint, string $specs): string
    {
        $setting = FrontendSetting::current();
        $custom = app(FrontendMediaReference::class)->resolve($setting->{$column}, $setting, $collection)?->getUrl();

        // The exact fallbacks the render uses when there is no custom image.
        $defaults = [
            'logo-light' => asset('images/brand/logo-on-light.svg'),
            'logo-dark' => asset('images/brand/logo-on-dark.svg'),
            'favicon' => asset('images/brand/isotipo-on-light.png'),
            'default-og-image' => asset('images/metaimage/meta_image_landra.jpg'),
        ];
        $url = $custom ?? ($defaults[$collection] ?? '');
        $isCustom = $custom !== null;

        // Each asset previews on the backdrop it is designed for, regardless of
        // the admin theme — a light-bg logo is unreadable on navy and vice versa.
        [$bg, $imgStyle] = match ($variant) {
            'logo-dark' => ['#050f38', 'max-height:64px;max-width:80%;object-fit:contain'],
            'favicon' => ['#f3f4f6', 'height:48px;width:48px;object-fit:contain;border-radius:8px'],
            'og' => ['#0b1120', 'width:100%;aspect-ratio:1200/630;object-fit:cover;display:block'],
            default => ['#ffffff', 'max-height:64px;max-width:80%;object-fit:contain'],
        };

        $badge = $isCustom
            ? '<span style="color:#047857">● Imagen personalizada</span>'
            : '<span style="color:#92400e">● Marca de Landra por defecto</span>';

        $imgBox = $variant === 'og'
            ? "<img src=\"{$url}\" alt=\"{$title}\" style=\"{$imgStyle}\">"
            : "<div style=\"background:{$bg};min-height:120px;display:flex;align-items:center;justify-content:center;padding:20px\"><img src=\"{$url}\" alt=\"{$title}\" style=\"{$imgStyle}\"></div>";

        return <<<HTML
<div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
    <div style="padding:10px 12px;border-bottom:1px solid #f3f4f6">
        <div style="font-size:13px;font-weight:600;color:#111827">{$title}</div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px">{$hint}</div>
        <div style="font-size:11px;color:#4b5563;margin-top:6px;display:flex;align-items:center;gap:5px">
            <svg style="width:13px;height:13px;flex:none;color:#9ca3af" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            <span>{$specs}</span>
        </div>
    </div>
    {$imgBox}
    <div style="padding:8px 12px;font-size:12px;font-weight:500">{$badge}</div>
</div>
HTML;
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $record = FrontendSetting::current();

        // Capture the SUBMITTED intent before getState(): getState() saves
        // relationships and then re-runs loadStateFromRelationships
        // (forms/Concerns/HasState.php:244-245), which would overwrite what
        // the owner actually chose in this request.
        $submitted = [];
        foreach (self::BRAND_FIELDS as $field => [$column, $collection]) {
            $submitted[$field] = array_values((array) ($this->form->getRawState()[$field] ?? []));
        }

        // Validates, persists new uploads (saveRelationships runs inside) and
        // returns the non-upload state (upload fields are not dehydrated).
        $state = $this->form->getState();

        // Compile the per-day editor back into the stored key-value shape (the
        // «bypass») and drop the ephemeral UI state, which is not a column.
        $state['business_hours'] = $this->compileHours($state['hours_ui'] ?? []);
        unset($state['hours_ui']);

        // §16.5 first boundary: reject an unreadable or uncompiled theme here,
        // instead of storing it and quietly repairing it at render.
        $this->assertThemeIsValid($state['theme'] ?? []);

        // RFC-073: navigation cannot end up empty, labels carry no HTML and
        // every CTA / footer target must be safe. The repeater order IS the
        // display order, so persist it as an explicit sort_order.
        $this->assertNavigationIsValid($state);
        if (is_array($state['navigation'] ?? null)) {
            $state['navigation'] = array_values(array_map(
                // sort_order from the repeater order; open_in_new_tab is part of
                // the normative schema but forced false in v1 (no external nav).
                fn (array $item, int $order): array => [...$item, 'sort_order' => $order, 'open_in_new_tab' => false],
                $state['navigation'],
                array_keys($state['navigation']),
            ));
        }

        $references = app(FrontendMediaReference::class);
        $record->refresh();

        foreach (self::BRAND_FIELDS as $field => [$column, $collection]) {
            $value = $submitted[$field][0] ?? null;

            // Column = the file the owner selected; empty = back to fallback.
            // A string is an existing uuid; anything else is a fresh upload,
            // stored by getState() as the newest media of its collection
            // (nothing is ever deleted, so "newest" is exactly the new file).
            $uuid = match (true) {
                $value === null => null,
                is_string($value) => $value,
                default => $record->getMedia($collection)->last()?->uuid,
            };

            // §16.1: existence is not enough — the uuid must belong to THIS
            // singleton and to THIS collection. Rejecting at the render
            // boundary alone would still let invalid state reach the database.
            if ($uuid !== null && ! $references->isEligible($uuid, $record, $collection)) {
                throw ValidationException::withMessages([
                    "data.{$field}" => 'La imagen seleccionada no es válida para este campo.',
                ]);
            }

            $state[$column] = $uuid;
        }

        // All-or-nothing: reaching here means every reference is eligible.
        $record->update($state);

        Notification::make()
            ->title('Configuración guardada')
            ->body('Los cambios ya están publicados en el sitio.')
            ->success()
            ->send();
    }
}

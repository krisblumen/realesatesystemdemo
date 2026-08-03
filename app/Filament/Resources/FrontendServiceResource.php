<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrontendServiceResource\Pages;
use App\Forms\Components\NonDestructiveMediaUpload;
use App\Models\FrontendService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Owner-only editor for services shown on the frontend (RFC-074). "Save is
 * publishing" (Strategy A): the content columns are the single payload, and the
 * availability toggles take effect immediately.
 *
 * A FrontendService exists 1:1 for a ServiceType, so this resource never
 * creates or deletes rows — the owner edits content, order and availability.
 * `ServiceType.active` is NOT exposed here: it stays on ServiceTypeResource
 * (owner + admin); this module is owner-only and must not open it to admin.
 */
class FrontendServiceResource extends Resource
{
    protected static ?string $model = FrontendService::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Frontend';

    protected static ?string $navigationLabel = 'Servicios del sitio';

    protected static ?string $modelLabel = 'servicio del sitio';

    protected static ?string $pluralModelLabel = 'servicios del sitio';

    protected static ?string $slug = 'frontend/servicios';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Contenido')
                ->description('Se muestra en la home y en la página de servicios. No se permite HTML.')
                ->schema([
                    TextInput::make('title')->label('Título')->required()->maxLength(120),
                    TextInput::make('short_description')->label('Descripción corta')->maxLength(300)
                        ->helperText('Para las tarjetas de la home.'),
                    Textarea::make('long_description')->label('Descripción larga')->rows(3)
                        ->helperText('Para la página de servicios.')->columnSpanFull(),
                    TagsInput::make('bullets')->label('Beneficios')->columnSpanFull(),
                    ViewField::make('icon')->label('Ícono')
                        ->view('filament.forms.icon-picker')
                        ->viewData(['iconos' => (array) config('frontend-sections.service_icons')]),
                    // Obligatorio SÓLO si hay imagen (Épica 12.3 §10.2). La regla
                    // universal de accesibilidad ya rige para toda media de
                    // secciones; dejar servicios afuera crearía dos reglas
                    // distintas en el mismo panel. Y acá no existe «decorativa»:
                    // la foto de un servicio siempre comunica algo.
                    TextInput::make('image_alt')->label('Qué se ve en la imagen')
                        ->placeholder('Para quien no puede verla')
                        ->maxLength(150)
                        ->rules(['not_regex:/[<>]/'])
                        ->validationMessages(['not_regex' => 'El texto alternativo no puede incluir HTML.'])
                        ->required(fn (Get $get): bool => filled($get('image')))
                        ->helperText('Se lee en voz alta a quien usa un lector de pantalla.'),
                    NonDestructiveMediaUpload::make('image')->label('Imagen')
                        ->collection('image')->uuidColumn('image_media_id')
                        // La colección vive en el disco privado (Épica 12.3): la
                        // vista previa se sirve por la ruta owner-only, nunca por
                        // /storage, que para esta media no existe hasta que una
                        // publicación la promueve.
                        ->previewUrlUsing(fn (Media $media): ?string => $media->model instanceof FrontendService
                            ? route('frontend.services.media', [
                                'service' => $media->model_id,
                                'uuid' => (string) $media->uuid,
                            ])
                            : null)
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->maxSize(3072)->maxFiles(1)->image()->live()->columnSpanFull(),
                ])->columns(2),

            Section::make('Disponibilidad')
                ->description('Dónde aparece el servicio y si acepta solicitudes de contacto. Un servicio con su tipo inactivo no se muestra ni acepta leads, aunque estos toggles digan lo contrario.')
                ->schema([
                    Toggle::make('show_in_home')->label('Mostrar en la home'),
                    Toggle::make('show_in_services')->label('Mostrar en la página de servicios'),
                    Toggle::make('allow_leads')->label('Acepta solicitudes de contacto'),
                    TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->label('Servicio')->searchable(),
                TextColumn::make('service_type_code')->label('Tipo')->badge(),
                IconColumn::make('serviceType.active')->label('Tipo activo')->boolean(),
                IconColumn::make('show_in_home')->label('Home')->boolean(),
                IconColumn::make('show_in_services')->label('Servicios')->boolean(),
                IconColumn::make('allow_leads')->label('Leads')->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrontendServices::route('/'),
            'edit' => Pages\EditFrontendService::route('/{record}/edit'),
        ];
    }
}

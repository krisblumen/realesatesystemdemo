<?php

namespace App\Filament\Resources;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\UserStatus;
use App\Filament\Resources\PropertyResource\Pages;
use App\Models\Municipality;
use App\Models\PostalCode;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\State;
use App\Models\User;
use App\Models\Zone;
use App\Rules\UniquePropertyOwner;
use App\Services\PropertyStatusService;
use App\Support\PropertySlugGenerator;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    /**
     * Cuánto pesa como máximo una foto, en KB.
     *
     * Estaba en 5 MB y una foto de teléfono pasa de eso sin esfuerzo. El
     * resultado no era un aviso: FilePond marcaba el archivo como inválido, el
     * navegador bloqueaba el envío del formulario ENTERO por validación nativa
     * —sin disparar `submit`, sin pedido al servidor— y el globo de error queda
     * anclado a un input que no se ve. Para el agente, «Guardar cambios» dejaba
     * de responder sin explicación.
     *
     * El techo REAL no es este número sino `upload_max_filesize` de PHP, que en
     * esta instalación está en 10 MB: por encima de eso el archivo no llega ni
     * al servidor. Este límite se queda por debajo a propósito, para que todo lo
     * que el formulario acepta se pueda subir de verdad. Si algún día se sube el
     * de PHP, este puede acompañarlo.
     *
     * Hubo una versión que aceptaba 12 MB reduciendo la foto en el navegador.
     * Se revirtió: Filament DERIVA la proporción del recorte de las medidas de
     * ese redimensionado, así que fijar 1920×1920 dejaba el editor cuadrado y
     * era imposible recortar en cualquier otra forma.
     */
    public const MAX_FOTO_KB = 8192;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Operación';

    protected static ?string $modelLabel = 'inmueble';

    protected static ?string $pluralModelLabel = 'inmuebles';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del inmueble')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(180),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Destacado en la portada')
                        ->helperText('Aparece en "Inmuebles destacados" de la Home. Excluye "Oportunidad".')
                        ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false)
                        ->live()
                        ->afterStateUpdated(fn (bool $state, Forms\Set $set) => $state ? $set('is_opportunity', false) : null),
                    Forms\Components\Toggle::make('is_opportunity')
                        ->label('Oportunidad de inversión')
                        ->helperText('Aparece en "Oportunidades de inversión" de la Home. Excluye "Destacado".')
                        ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false)
                        ->live()
                        ->afterStateUpdated(fn (bool $state, Forms\Set $set) => $state ? $set('is_featured', false) : null),
                    Forms\Components\Select::make('operation_type')
                        ->label('Operación')
                        ->options(self::operationOptions())
                        ->required(),
                    Forms\Components\Select::make('property_type')
                        ->label('Tipo de inmueble')
                        ->options(self::propertyTypeOptions())
                        ->required(),
                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Responsable')
                ->schema([
                    Forms\Components\Select::make('agent_id')
                        ->label('Agente responsable')
                        ->relationship('agent', 'name', fn (Builder $query): Builder => $query
                            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente'))
                            ->where('status', UserStatus::Active->value))
                        ->searchable()
                        ->preload()
                        ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
                ]),
            Forms\Components\Section::make('Dirección')
                ->description('Elige estado y municipio para filtrar la zona y la colonia. La calle y el número sólo los ven el agente asignado, owner y admin.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('estado_filter')
                        ->label('Estado')
                        ->options(fn (): array => self::stateOptions())
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Forms\Set $set, ?Property $record): void {
                            if ($record?->zone?->state_id) {
                                $set('estado_filter', $record->zone->state_id);
                            }
                        })
                        ->afterStateUpdated(function (Forms\Set $set): void {
                            $set('municipio_filter', null);
                            $set('zone_id', null);
                            $set('colonia', null);
                            $set('postal_code', null);
                        }),
                    Forms\Components\Select::make('municipio_filter')
                        ->label('Municipio')
                        ->options(fn (Forms\Get $get): array => self::municipalityOptions($get('estado_filter')))
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Forms\Set $set, ?Property $record): void {
                            if ($record?->zone?->municipality_id) {
                                $set('municipio_filter', $record->zone->municipality_id);
                            }
                        })
                        ->afterStateUpdated(function (Forms\Set $set): void {
                            $set('zone_id', null);
                            $set('colonia', null);
                            $set('postal_code', null);
                        }),
                    Forms\Components\Select::make('zone_id')
                        ->label('Zona')
                        ->options(fn (Forms\Get $get): array => self::zoneOptions($get('municipio_filter')))
                        ->searchable()
                        ->live()
                        ->helperText('Elige estado y municipio. Sólo se muestran zonas de ese municipio.')
                        ->afterStateUpdated(function (mixed $state, Forms\Set $set): void {
                            $set('postal_code', $state ? Zone::whereKey($state)->value('postal_code') : null);
                            $set('colonia', null);
                        }),
                    Forms\Components\TextInput::make('postal_code')
                        ->label('Código Postal')
                        ->live(onBlur: true)
                        ->dehydrated()
                        ->helperText('Se completa al elegir la zona. También puedes escribirlo para autocompletar estado, municipio y zona.')
                        ->afterStateUpdated(function (?string $state, Forms\Get $get, Forms\Set $set): void {
                            $set('colonia', null);

                            // Si el CP pertenece a la zona QUE YA ESTÁ ELEGIDA,
                            // no se toca nada: el owner está precisando el CP
                            // dentro de su zona, no cambiando de zona. Sin esto,
                            // un CP válido de la misma zona la reasignaba —y con
                            // un CP compartido por dos zonas podía saltar a la
                            // otra, que es peor que no hacer nada.
                            if (self::zoneCoversPostalCode($get('zone_id'), $state)) {
                                return;
                            }

                            $zone = self::zoneByPostalCode($state);

                            if ($zone instanceof Zone) {
                                $set('estado_filter', $zone->state_id);
                                $set('municipio_filter', $zone->municipality_id);
                                $set('zone_id', $zone->id);
                            } else {
                                $set('zone_id', null);
                            }
                        }),
                    Forms\Components\Select::make('colonia')
                        ->label('Colonia')
                        ->options(fn (Forms\Get $get): array => self::coloniaOptions($get('postal_code')))
                        ->searchable()
                        ->live()
                        ->helperText('Colonias del código postal de la zona. Requerida para publicar.'),
                    Forms\Components\TextInput::make('street')
                        ->label('Calle')
                        ->maxLength(180)
                        ->helperText('Requerida para publicar.')
                        ->visible(fn (?Property $record): bool => self::canSeePreciseAddress($record))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('exterior_number')
                        ->label('Número exterior')
                        ->maxLength(30)
                        ->visible(fn (?Property $record): bool => self::canSeePreciseAddress($record)),
                    Forms\Components\TextInput::make('interior_number')
                        ->label('Número interior')
                        ->maxLength(30)
                        ->visible(fn (?Property $record): bool => self::canSeePreciseAddress($record)),
                ]),
            Forms\Components\Section::make('Propietario y comisión')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('owner_id')
                        ->label('Propietario')
                        ->relationship(
                            name: 'owner',
                            titleAttribute: 'first_name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->visibleTo(auth()->user()),
                        )
                        ->getOptionLabelFromRecordUsing(fn (PropertyOwner $record): string => $record->fullName())
                        ->searchable()
                        ->preload()
                        ->helperText('Requerido para publicar.')
                        // El alta rápida pide los MISMOS datos obligatorios que
                        // la pantalla de propietarios. Acá el email era opcional
                        // y ese hueco alcanzaba para colar el duplicado que la
                        // otra puerta rechaza: la comprobación necesita los dos.
                        ->createOptionForm([
                            Forms\Components\TextInput::make('first_name')->label('Nombre')->required()->maxLength(120),
                            Forms\Components\TextInput::make('last_name')->label('Apellidos')->required()->maxLength(120),
                            Forms\Components\TextInput::make('phone')->label('Teléfono')->tel()->required()->maxLength(40),
                            Forms\Components\TextInput::make('email')->label('Email')->email()->required()->maxLength(180),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $user = auth()->user();

                            if (! $user?->hasAnyRole(['owner', 'admin'])) {
                                $data['agent_id'] = $user?->id;
                            }

                            $duplicado = PropertyOwner::findDuplicate(
                                $data['phone'] ?? null,
                                $data['email'] ?? null,
                            );

                            if ($duplicado !== null) {
                                $mensaje = (new UniquePropertyOwner)->mensajeDe($duplicado);

                                // Aviso FLOTANTE y no otro modal: esto ya corre
                                // dentro del modal de alta rápida, y encimar uno
                                // sobre otro obliga a cerrar dos cosas para
                                // volver a lo que se estaba haciendo.
                                Notification::make()
                                    ->title('Ese cliente ya está registrado')
                                    ->body($mensaje)
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                // Y además el error bajo los campos. La ruta
                                // lleva el prefijo del modal anidado: con la
                                // clave suelta —`phone`— el mensaje se emitía y
                                // no encontraba dónde mostrarse, así que el alta
                                // se frenaba sin decir nada.
                                throw ValidationException::withMessages([
                                    'mountedFormComponentActionsData.0.phone' => $mensaje,
                                    'mountedFormComponentActionsData.0.email' => $mensaje,
                                ]);
                            }

                            return PropertyOwner::create($data)->getKey();
                        }),
                    Forms\Components\TextInput::make('commission_percentage')
                        ->label('Comisión (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step('0.01')
                        ->suffix('%')
                        ->helperText('Requerida para publicar.'),
                ]),
            Forms\Components\Section::make('Precio y dimensiones')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->label('Precio')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0.01)
                        ->required(),
                    Forms\Components\TextInput::make('bedrooms')
                        ->label('Recámaras')
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\TextInput::make('bathrooms')
                        ->label('Baños')
                        ->numeric()
                        ->step(0.5)
                        ->minValue(0),
                    Forms\Components\TextInput::make('parking_spaces')
                        ->label('Estacionamientos')
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\TextInput::make('land_area')
                        ->label('Terreno')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('m²'),
                    Forms\Components\TextInput::make('construction_area')
                        ->label('Construcción')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('m²'),
                ]),
            Forms\Components\Section::make('Características')
                ->schema([
                    Forms\Components\Select::make('features')
                        ->label('Características')
                        ->relationship('features', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),
                ]),
            Forms\Components\Section::make('Galería')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('cover')
                        ->label('Imagen principal')
                        ->collection('cover')
                        ->image()
                        ->imageEditor()
                        ->maxSize(self::MAX_FOTO_KB)
                        ->imageEditorAspectRatios([null, '16:9', '4:3', '3:2', '1:1'])
                        ->helperText('Obligatoria para publicar el inmueble. Podés recortarla libremente o elegir una proporción fija.'),
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->label('Galería')
                        ->collection('gallery')
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->image()
                        ->imageEditor()
                        ->panelLayout('grid')
                        ->panelAspectRatio('1:1')
                        ->maxSize(self::MAX_FOTO_KB)
                        ->imageEditorAspectRatios([null, '16:9', '4:3', '3:2', '1:1'])
                        ->maxFiles(30),
                ]),
            Forms\Components\Section::make('SEO')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->label('Meta title')
                        ->maxLength(180)
                        ->placeholder(fn (?Property $record): ?string => $record?->title),
                    Forms\Components\Textarea::make('meta_description')
                        ->label('Meta description')
                        ->maxLength(320)
                        ->rows(2),
                    Forms\Components\TextInput::make('canonical_url')
                        ->label('URL canónica')
                        ->url()
                        ->maxLength(255),
                ]),
            Forms\Components\Section::make('Estado')
                ->visible(fn (string $operation): bool => $operation === 'edit')
                ->schema([
                    Forms\Components\Placeholder::make('status_display')
                        ->label('Estado actual')
                        ->content(fn (?Property $record): string => $record?->status->label() ?? 'Borrador'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('pdf')
                    ->label('')
                    ->getStateUsing(fn (): string => asset('images/assets/PDF-doc-256.png'))
                    ->size(40)
                    ->tooltip('Descargar ficha PDF')
                    ->url(fn (Property $record): ?string => auth()->user()?->can('view', $record)
                        ? route('properties.pdf.show', $record)
                        : null)
                    ->openUrlInNewTab(),
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label('Portada')
                    ->collection('cover')
                    ->conversion('thumb'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zona')
                    ->badge(),
                Tables\Columns\TextColumn::make('colonia')
                    ->label('Colonia')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('Sin colonia'),
                Tables\Columns\TextColumn::make('operation_type')
                    ->label('Operación')
                    ->badge()
                    ->formatStateUsing(fn (OperationType $state): string => $state->label())
                    ->color(fn (OperationType $state): string => $state->color()),
                Tables\Columns\TextColumn::make('property_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (PropertyType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('MXN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agente')
                    ->badge()
                    ->default('Sin agente asignado')
                    ->color(fn (Property $record): string => $record->agent_id === null ? 'danger' : 'gray')
                    ->icon(fn (Property $record): ?string => $record->agent_id === null ? 'heroicon-m-exclamation-triangle' : null),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (PropertyStatus $state): string => $state->label())
                    ->color(fn (PropertyStatus $state): string => $state->color()),
                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Destacado')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
                Tables\Columns\ToggleColumn::make('is_opportunity')
                    ->label('Oportunidad')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('zone')->relationship('zone', 'name')->label('Zona'),
                SelectFilter::make('operation_type')->options(self::operationOptions())->label('Operación'),
                SelectFilter::make('property_type')->options(self::propertyTypeOptions())->label('Tipo'),
                SelectFilter::make('status')->options(self::statusOptions())->label('Estado'),
                SelectFilter::make('agent')
                    ->label('Agente')
                    ->relationship('agent', 'name', fn (Builder $query): Builder => $query
                        ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente')))
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
                TrashedFilter::make()
                    ->label('Papelera')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
            ])
            ->actions([
                self::statusAction('publish', 'Publicar', PropertyStatus::Publicado, 'success'),
                self::statusAction('pause', 'Pausar', PropertyStatus::Pausado, 'warning'),
                self::statusAction('markSold', 'Marcar vendido', PropertyStatus::Vendido, 'info')
                    ->visible(fn (Property $record): bool => self::canTransition($record, PropertyStatus::Vendido)
                        && $record->operation_type === OperationType::Venta),
                self::statusAction('markRented', 'Marcar rentado', PropertyStatus::Rentado, 'info')
                    ->visible(fn (Property $record): bool => self::canTransition($record, PropertyStatus::Rentado)
                        && $record->operation_type === OperationType::Renta),
                self::statusAction('reopen', 'Reabrir', PropertyStatus::Borrador, 'gray'),
                Tables\Actions\Action::make('regenerateSlug')
                    ->label('Regenerar slug')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(fn (Property $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->requiresConfirmation()
                    ->action(function (Property $record): void {
                        app(PropertySlugGenerator::class)->persist($record);
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Property $record): bool => auth()->user()?->can('update', $record) ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Property $record): bool => auth()->user()?->can('delete', $record) ?? false),
                Tables\Actions\RestoreAction::make()
                    ->visible(fn (Property $record): bool => auth()->user()?->can('restore', $record) ?? false)
                    ->before(function (Property $record): void {
                        if ($record->status === PropertyStatus::Publicado) {
                            $record->status = PropertyStatus::Borrador;
                            $record->save();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = Property::query();

        // Sólo owner/admin tienen papelera; el agente nunca debe ver registros
        // eliminados (soft-deleted) en su listado.
        if ($user instanceof User && $user->hasAnyRole(['owner', 'admin'])) {
            $query->withoutGlobalScopes([SoftDeletingScope::class]);
        }

        return $user instanceof User ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'edit' => Pages\EditProperty::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Property::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Property::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('restore', $record) ?? false;
    }

    /** @return array<int, string> */
    private static function stateOptions(): array
    {
        return State::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private static function municipalityOptions(mixed $stateId): array
    {
        if (! $stateId) {
            return [];
        }

        return Municipality::query()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Zonas del municipio elegido. Un agente sólo ve sus zonas asignadas;
     * owner/admin ven todas las del municipio.
     *
     * @return array<int, string>
     */
    private static function zoneOptions(mixed $municipalityId): array
    {
        if (! $municipalityId) {
            return [];
        }

        $query = Zone::query()->where('municipality_id', $municipalityId);
        $user = auth()->user();

        if ($user instanceof User && ! $user->hasAnyRole(['owner', 'admin'])) {
            $query->whereIn('id', $user->zones()->select('zones.id'));
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Colonias del código postal de la zona. Cada zona se delimita por un CP
     * y un CP agrupa varias colonias, así que la colonia se filtra por CP.
     *
     * @return array<string, string>
     */
    private static function coloniaOptions(mixed $postalCode): array
    {
        if (blank($postalCode)) {
            return [];
        }

        return PostalCode::query()
            ->where('postal_code', $postalCode)
            ->orderBy('colonia')
            ->pluck('colonia', 'colonia')
            ->all();
    }

    /**
     * Resuelve la zona a partir de un CP. Un agente sólo puede resolver CPs de
     * sus zonas asignadas.
     *
     * Busca en el PIVOTE `zone_postal_code` y no en la columna `zones.
     * postal_code`. Esa columna guarda un solo código —el que la zona tenía
     * cuando se creó— y desde que las zonas admiten varios
     * (`2026_07_04_000000_zones_support_multiple_postal_codes`) dejó de ser la
     * fuente de verdad: `syncPostalCodes()` reescribe el pivote y NO la toca,
     * así que puede quedar apuntando a un CP que la zona ya no cubre.
     *
     * Mientras miró la columna, escribir cualquier CP de la zona que no fuera
     * ese primero no encontraba nada y el formulario borraba la zona elegida —y
     * después publicar fallaba por «zona no válida».
     */
    private static function zoneByPostalCode(mixed $postalCode): ?Zone
    {
        if (blank($postalCode)) {
            return null;
        }

        $query = Zone::query()->whereIn('id', function ($sub) use ($postalCode): void {
            $sub->select('zone_id')->from('zone_postal_code')->where('postal_code', $postalCode);
        });

        $user = auth()->user();

        if ($user instanceof User && ! $user->hasAnyRole(['owner', 'admin'])) {
            $query->whereIn('id', $user->zones()->select('zones.id'));
        }

        return $query->first();
    }

    /** Si esa zona cubre ese CP, según el pivote. */
    private static function zoneCoversPostalCode(mixed $zoneId, mixed $postalCode): bool
    {
        if (blank($zoneId) || blank($postalCode)) {
            return false;
        }

        return DB::table('zone_postal_code')
            ->where('zone_id', $zoneId)
            ->where('postal_code', $postalCode)
            ->exists();
    }

    private static function canSeePreciseAddress(?Property $record): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        // En alta (sin record) lo ve quien crea (será el agente asignado u owner/admin).
        return $record === null
            ? true
            : $record->preciseAddressVisibleTo($user);
    }

    /** @return array<string, string> */
    private static function operationOptions(): array
    {
        return collect(OperationType::cases())
            ->mapWithKeys(fn (OperationType $type): array => [$type->value => $type->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function propertyTypeOptions(): array
    {
        return collect(PropertyType::cases())
            ->mapWithKeys(fn (PropertyType $type): array => [$type->value => $type->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(PropertyStatus::cases())
            ->mapWithKeys(fn (PropertyStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    private static function statusAction(
        string $name,
        string $label,
        PropertyStatus $target,
        string $color,
    ): Tables\Actions\Action {
        return Tables\Actions\Action::make($name)
            ->label($label)
            ->color($color)
            ->visible(fn (Property $record): bool => self::canTransition($record, $target))
            ->requiresConfirmation()
            ->action(function (Property $record) use ($target, $label): void {
                try {
                    app(PropertyStatusService::class)->transition($record, $target);

                    Notification::make()
                        ->title('Estado actualizado')
                        ->success()
                        ->send();
                } catch (ValidationException $exception) {
                    // El servicio explica por qué no se puede (sin foto, sin
                    // propietario, etc.); se muestra el motivo en vez de fallar
                    // en silencio.
                    Notification::make()
                        ->title('No se pudo '.mb_strtolower($label).' el inmueble')
                        ->body(collect($exception->errors())->flatten()->first())
                        ->danger()
                        ->send();
                }
            });
    }

    private static function canTransition(Property $record, PropertyStatus $target): bool
    {
        return (auth()->user()?->can('update', $record) ?? false)
            && $record->status->canTransitionTo($target);
    }
}

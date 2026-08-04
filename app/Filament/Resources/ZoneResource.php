<?php

namespace App\Filament\Resources;

use App\Enums\ZoneStatus;
use App\Filament\Resources\ZoneResource\Pages;
use App\Filament\Resources\ZoneResource\RelationManagers\AgentsRelationManager;
use App\Models\Municipality;
use App\Models\PostalCode;
use App\Models\PostalCodeArea;
use App\Models\Zone;
use App\Services\Zones\ZoneCompositionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Operación';

    protected static ?string $modelLabel = 'zona';

    protected static ?string $pluralModelLabel = 'zonas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la zona')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get): void {
                                if ($operation === 'create' && blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('state_id')
                            ->label('Estado')
                            ->relationship('state', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('municipality_id', null)),
                        Forms\Components\Select::make('municipality_id')
                            ->label('Municipio')
                            ->options(fn (Get $get) => filled($get('state_id'))
                                ? Municipality::where('state_id', $get('state_id'))->orderBy('name')->pluck('name', 'id')
                                : [])
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get) => blank($get('state_id')))
                            ->afterStateUpdated(function (Set $set): void {
                                $set('postal_codes', []);
                                $set('colonia_finder', []);
                            })
                            ->helperText('Selecciona primero el estado.'),
                        Forms\Components\Select::make('status')
                            ->label('Estatus')
                            ->options(static::statusOptions())
                            ->required()
                            ->default(ZoneStatus::Active->value),
                        Forms\Components\Select::make('colonia_finder')
                            ->label('Buscar colonia')
                            ->helperText('¿No recuerdas el CP? Busca la colonia aquí y se agrega sola a "Códigos postales".')
                            ->multiple()
                            ->searchable()
                            ->default([])
                            ->dehydrated(false)
                            ->options(fn (Get $get): array => static::coloniaOptions($get('municipality_id')))
                            ->live()
                            ->disabled(fn (Get $get): bool => blank($get('municipality_id')))
                            ->afterStateUpdated(function (?array $state, Set $set, Get $get): void {
                                if (blank($state)) {
                                    return;
                                }

                                $codes = collect($get('postal_codes') ?? [])
                                    ->merge(PostalCode::query()->whereIn('id', $state)->pluck('postal_code'))
                                    ->unique()
                                    ->values()
                                    ->all();

                                $set('postal_codes', $codes);
                                $set('description', app(ZoneCompositionService::class)->descriptionFor($codes));
                                $set('map_zones', static::mapZonesFor($codes));
                                $set('colonia_finder', []);
                            })
                            ->columnSpanFull(),
                        Forms\Components\Select::make('postal_codes')
                            ->label('Códigos postales')
                            ->helperText('Elige uno o más CP del municipio: la zona se arma con los polígonos de todos, y la descripción lista todas sus colonias.')
                            ->multiple()
                            ->searchable()
                            ->options(fn (Get $get): array => static::postalCodeOptions($get('municipality_id')))
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get): bool => blank($get('municipality_id')))
                            ->afterStateUpdated(function (?array $state, Set $set): void {
                                $codes = $state ?? [];
                                $set('description', app(ZoneCompositionService::class)->descriptionFor($codes));
                                $set('map_zones', static::mapZonesFor($codes));
                            })
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción (colonias)')
                            ->helperText('Se genera automáticamente con las colonias de los CP seleccionados.')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('map_zones')
                            ->dehydrated(false),
                        Forms\Components\View::make('filament.forms.components.zone-cp-map')
                            ->viewData(['apiKey' => config('services.google_maps.key')])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * CP (con geometría en el catálogo) del municipio seleccionado.
     *
     * @return array<string, string>
     */
    public static function postalCodeOptions(mixed $municipalityId): array
    {
        if (blank($municipalityId)) {
            return [];
        }

        $codes = PostalCode::query()
            ->where('municipality_id', $municipalityId)
            ->distinct()
            ->pluck('postal_code');

        return PostalCodeArea::query()
            ->whereIn('postal_code', $codes)
            ->orderBy('postal_code')
            ->pluck('postal_code', 'postal_code')
            ->all();
    }

    /**
     * Colonias del municipio seleccionado, para el buscador de ayuda: el admin
     * escribe la colonia y encuentra el CP sin tener que memorizarlo.
     * Solo colonias cuyo CP tiene geometría cargada (elegible en "Códigos postales").
     *
     * @return array<int, string>
     */
    public static function coloniaOptions(mixed $municipalityId): array
    {
        if (blank($municipalityId)) {
            return [];
        }

        $catalogedCodes = PostalCodeArea::query()->pluck('postal_code');

        return PostalCode::query()
            ->where('municipality_id', $municipalityId)
            ->whereIn('postal_code', $catalogedCodes)
            ->orderBy('colonia')
            ->get(['id', 'colonia', 'postal_code'])
            ->mapWithKeys(fn (PostalCode $postalCode): array => [
                $postalCode->id => "{$postalCode->colonia} — CP {$postalCode->postal_code}",
            ])
            ->all();
    }

    /**
     * Polígonos (coloreados) de los CP seleccionados, para el mapa de preview.
     *
     * @param  list<string>  $codes
     * @return list<array{color: string, geometry: mixed}>
     */
    public static function mapZonesFor(array $codes): array
    {
        $palette = ['#dc2626', '#2563eb', '#16a34a', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d'];

        return PostalCodeArea::query()
            ->whereIn('postal_code', collect($codes)->filter()->values()->all())
            ->orderBy('postal_code')
            ->get()
            ->values()
            ->map(fn (PostalCodeArea $area, int $i): array => [
                'color' => $palette[$i % count($palette)],
                'geometry' => json_decode((string) $area->polygonAsGeoJson(), true),
            ])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('state.name')
                    ->label('Estado')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('postalCodeAreas.postal_code')
                    ->label('C.P.')
                    ->badge()
                    ->separator(',')
                    ->limitList(4)
                    ->expandableLimitedList(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (ZoneStatus|string|null $state): ?string => self::statusLabel($state))
                    ->color(fn (ZoneStatus|string|null $state): string => self::statusColor($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('state_id')
                    ->label('Estado')
                    ->relationship('state', 'name'),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(static::statusOptions()),
                TrashedFilter::make()
                    ->label('Papelera')
                    ->visible(fn (): bool => auth()->user()?->hasRole('owner') ?? false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Zone $record): bool => auth()->user()?->can('update', $record) ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Zone $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->before(function (Tables\Actions\DeleteAction $action, Zone $record): void {
                        static::guardDeletable($action, $record);
                    }),
                Tables\Actions\RestoreAction::make()
                    ->visible(fn (Zone $record): bool => auth()->user()?->can('restore', $record) ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole('owner') ?? false)
                        ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records): void {
                            /** @var Zone $record */
                            foreach ($records as $record) {
                                static::guardDeletable($action, $record);
                            }
                        }),
                    Tables\Actions\RestoreBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole('owner') ?? false),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZones::route('/'),
            'create' => Pages\CreateZone::route('/create'),
            'edit' => Pages\EditZone::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            AgentsRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('zones.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('zones.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    private static function guardDeletable(Tables\Actions\Action|Tables\Actions\BulkAction $action, Zone $record): void
    {
        try {
            $record->assertDeletable();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('No se puede eliminar la zona "'.$record->name.'"')
                ->body(collect($exception->errors())->flatten()->first())
                ->send();

            $action->cancel();
        }
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('restore', $record) ?? false;
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(ZoneStatus::cases())
            ->mapWithKeys(fn (ZoneStatus $status): array => [$status->value => self::statusLabel($status)])
            ->all();
    }

    private static function statusLabel(ZoneStatus|string|null $status): ?string
    {
        $status = is_string($status) ? ZoneStatus::tryFrom($status) : $status;

        return match ($status) {
            ZoneStatus::Active => 'Activa',
            ZoneStatus::Inactive => 'Inactiva',
            null => null,
        };
    }

    private static function statusColor(ZoneStatus|string|null $status): string
    {
        $status = is_string($status) ? ZoneStatus::tryFrom($status) : $status;

        return match ($status) {
            ZoneStatus::Active => 'success',
            ZoneStatus::Inactive => 'danger',
            null => 'gray',
        };
    }
}

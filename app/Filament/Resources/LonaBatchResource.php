<?php

namespace App\Filament\Resources;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\LonaBatchResource\Pages;
use App\Models\LonaBatch;
use App\Models\Property;
use App\Models\User;
use App\Services\Lonas\LonaEligibilityService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LonaBatchResource extends Resource
{
    protected static ?string $model = LonaBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Lonas';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Lonas asignadas';

    protected static ?string $modelLabel = 'lote de lonas';

    protected static ?string $pluralModelLabel = 'lotes de lonas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('agent_id')
                ->label('Agente')
                ->options(fn (): array => self::activeAgentOptions())
                ->searchable()
                ->required(),
            Forms\Components\Select::make('operation_type')
                ->label('Tipo')
                ->options(self::operationTypeOptions())
                ->required(),
            Forms\Components\TextInput::make('cantidad')
                ->label('Cantidad de lonas')
                ->helperText('Máximo '.LonaEligibilityService::CAP_PER_TYPE.' sin colocar por tipo. Si el agente ya tiene lonas de este tipo sin colocar, el sistema sólo permitirá completar el cupo.')
                ->numeric()
                ->minValue(1)
                ->maxValue(LonaEligibilityService::CAP_PER_TYPE)
                ->required(),
            Forms\Components\Select::make('property_id')
                ->label('Inmueble para el QR (opcional)')
                ->helperText('Sólo inmuebles publicados. El QR del PDF apuntará a su detalle público.')
                ->options(fn (): array => self::publishedPropertyOptions())
                ->searchable()
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('operation_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (OperationType $state): string => $state->label())
                    ->color(fn (OperationType $state): string => $state->color()),
                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->sortable(),
                Tables\Columns\TextColumn::make('units_count')
                    ->label('Unidades')
                    ->counts('units'),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Autorizó')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Entregado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('downloadPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (LonaBatch $record): ?string => $record->getFirstMediaUrl('diseno-pdf') ?: null)
                    ->openUrlInNewTab()
                    ->visible(fn (LonaBatch $record): bool => $record->getFirstMedia('diseno-pdf') !== null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLonaBatches::route('/'),
            'create' => Pages\CreateLonaBatch::route('/create'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', LonaBatch::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', LonaBatch::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        // Un lote entregado es inmutable (su PDF y unidades ya se generaron).
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return array<int, string> */
    private static function activeAgentOptions(): array
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, string> */
    private static function operationTypeOptions(): array
    {
        return collect(OperationType::cases())
            ->mapWithKeys(fn (OperationType $type): array => [$type->value => $type->label()])
            ->all();
    }

    /** @return array<int, string> */
    private static function publishedPropertyOptions(): array
    {
        return Property::query()
            ->where('status', PropertyStatus::Publicado->value)
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }
}

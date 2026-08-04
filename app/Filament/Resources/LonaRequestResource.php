<?php

namespace App\Filament\Resources;

use App\Enums\LonaRequestStatus;
use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Filament\Resources\LonaRequestResource\Pages;
use App\Models\LonaRequest;
use App\Models\Property;
use App\Services\Lonas\LonaBatchApprovalService;
use App\Services\Lonas\LonaEligibilityService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LonaRequestResource extends Resource
{
    protected static ?string $model = LonaRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Lonas';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Solicitudes de lonas';

    protected static ?string $modelLabel = 'solicitud de lonas';

    protected static ?string $pluralModelLabel = 'solicitudes de lonas';

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
                Tables\Columns\TextColumn::make('cantidad_solicitada')
                    ->label('Cantidad')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (LonaRequestStatus $state): string => $state->label())
                    ->color(fn (LonaRequestStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('property.title')
                    ->label('Inmueble sugerido')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(self::statusOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (LonaRequest $record): bool => $record->isPending() && self::canManage())
                    ->form(fn (LonaRequest $record): array => [
                        Forms\Components\TextInput::make('cantidad')
                            ->label('Cantidad a entregar')
                            ->helperText('Cupo disponible del agente para '.$record->operation_type->label().': '.self::availableToGrant($record).' (máximo '.LonaEligibilityService::CAP_PER_TYPE.' sin colocar por tipo).')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (): int => self::availableToGrant($record))
                            ->default(fn (): int => min($record->cantidad_solicitada, self::availableToGrant($record)))
                            ->required(),
                        Forms\Components\Select::make('property_id')
                            ->label('Inmueble para el QR (opcional)')
                            ->options(fn (): array => self::publishedPropertyOptions())
                            ->default($record->property_id)
                            ->searchable()
                            ->nullable(),
                    ])
                    ->action(function (LonaRequest $record, array $data): void {
                        $property = ! empty($data['property_id'])
                            ? Property::query()->find($data['property_id'])
                            : null;

                        app(LonaBatchApprovalService::class)->grant(
                            agent: $record->agent,
                            type: $record->operation_type,
                            cantidad: (int) $data['cantidad'],
                            authorizedBy: auth()->user(),
                            property: $property,
                            request: $record,
                        );
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (LonaRequest $record): bool => $record->isPending() && self::canManage())
                    ->form([
                        Forms\Components\Textarea::make('motivo_rechazo')
                            ->label('Motivo del rechazo')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (LonaRequest $record, array $data): void {
                        app(LonaBatchApprovalService::class)
                            ->reject($record, auth()->user(), $data['motivo_rechazo']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLonaRequests::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', LonaRequest::class) ?? false;
    }

    public static function canCreate(): bool
    {
        // El agente crea solicitudes desde su página "Mis Lonas", no desde esta bandeja.
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! self::canManage()) {
            return null;
        }

        $count = LonaRequest::query()
            ->where('estado', LonaRequestStatus::Pendiente->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    private static function canManage(): bool
    {
        return auth()->user()?->can('lonas.manage') ?? false;
    }

    /**
     * Cupo que el admin puede entregarle al agente de esta solicitud ahora mismo:
     * el tope por tipo menos lo que ya tiene sin colocar.
     */
    private static function availableToGrant(LonaRequest $request): int
    {
        $sinColocar = app(LonaEligibilityService::class)
            ->uncolocatedCount($request->agent, $request->operation_type);

        return max(0, LonaEligibilityService::CAP_PER_TYPE - $sinColocar);
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(LonaRequestStatus::cases())
            ->mapWithKeys(fn (LonaRequestStatus $status): array => [$status->value => $status->label()])
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

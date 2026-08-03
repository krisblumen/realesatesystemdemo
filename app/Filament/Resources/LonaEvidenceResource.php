<?php

namespace App\Filament\Resources;

use App\Enums\LonaUnitStatus;
use App\Enums\OperationType;
use App\Enums\UserStatus;
use App\Filament\Resources\LonaEvidenceResource\Pages;
use App\Models\LonaUnit;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vista de owner/admin para verificar visualmente la evidencia fotográfica que los
 * agentes registraron al colocar sus lonas. Sólo lectura: lista las unidades ya
 * colocadas (status = colocada) con su foto, agente, tipo, ubicación y fecha.
 */
class LonaEvidenceResource extends Resource
{
    protected static ?string $model = LonaUnit::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Lonas';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Evidencias';

    protected static ?string $modelLabel = 'evidencia de lona';

    protected static ?string $pluralModelLabel = 'evidencias de lonas';

    public static function getEloquentQuery(): Builder
    {
        return LonaUnit::query()
            ->where('status', LonaUnitStatus::Colocada->value)
            ->with(['agent', 'property']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('evidencia')
                    ->label('Foto')
                    ->collection('evidencia')
                    ->height(72),
                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('operation_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (OperationType $state): string => $state->label())
                    ->color(fn (OperationType $state): string => $state->color()),
                Tables\Columns\TextColumn::make('property.title')
                    ->label('Colocada en')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ubicacion_referencia')
                    ->label('Referencia')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Colocada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->filters([
                SelectFilter::make('agent_id')
                    ->label('Agente')
                    ->relationship('agent', 'name', fn (Builder $query): Builder => $query
                        ->where('status', UserStatus::Active->value)
                        ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente')))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('operation_type')
                    ->label('Tipo')
                    ->options(self::operationTypeOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('verEvidencia')
                    ->label('Ver evidencia')
                    ->icon('heroicon-o-magnifying-glass-plus')
                    ->modalHeading('Evidencia de colocación')
                    ->modalContent(fn (LonaUnit $record) => view(
                        'filament.lonas.evidence-view-modal',
                        ['unit' => $record],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLonaEvidence::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        // Herramienta de verificación de owner/admin. El agente ve sus propias lonas
        // en "Mis Lonas", no esta bandeja global.
        return auth()->user()?->can('lonas.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** @return array<string, string> */
    private static function operationTypeOptions(): array
    {
        return collect(OperationType::cases())
            ->mapWithKeys(fn (OperationType $type): array => [$type->value => $type->label()])
            ->all();
    }
}

<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ZonesRelationManager extends RelationManager
{
    protected static string $relationship = 'zones';

    protected static ?string $title = 'Zonas asignadas';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Sólo los agentes tienen zonas asignadas; para owner/admin no aplica.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && $ownerRecord->hasRole('agente');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Zona')
                    ->searchable(),
                Tables\Columns\TextColumn::make('postal_code')
                    ->label('C.P.')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->placeholder('—'),
            ])
            // Las zonas se asignan desde la pantalla de Zonas; aquí sólo se quitan.
            ->headerActions([])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Quitar'),
            ])
            ->emptyStateHeading('Sin zonas asignadas')
            ->emptyStateDescription('Asigna zonas a este agente desde la pantalla de Zonas.');
    }
}

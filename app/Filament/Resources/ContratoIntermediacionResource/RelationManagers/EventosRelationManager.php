<?php

namespace App\Filament\Resources\ContratoIntermediacionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de auditoría del contrato (RFC-057/070): solo lectura. Muestra cada evento del
 * ciclo de vida (generado, enviado, leído, firmado, rechazado, cancelado, reenviado, etc.).
 */
class EventosRelationManager extends RelationManager
{
    protected static string $relationship = 'eventos';

    protected static ?string $title = 'Historial de auditoría';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tipo')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('tipo')->label('Evento')->badge(),
                Tables\Columns\TextColumn::make('actor.name')->label('Actor')->placeholder('Cliente / sistema'),
                Tables\Columns\TextColumn::make('ip')->label('IP')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}

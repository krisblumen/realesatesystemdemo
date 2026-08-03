<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LeadNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Seguimiento';

    protected static ?string $recordTitleAttribute = 'body';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('body')
                ->label('Nuevo comentario')
                ->placeholder('Anota el avance con el cliente: llamada, mensaje, lo acordado…')
                ->required()
                ->rows(3)
                ->maxLength(2000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Autor'),
                Tables\Columns\TextColumn::make('body')
                    ->label('Comentario')
                    ->wrap(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar comentario')
                    // Un lead cerrado (ganado/perdido) ya no recibe seguimiento.
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->isClosed())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                // Las notas son inmutables (append-only): no hay edición.
                // Sólo el owner puede borrar.
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->hasRole('owner') ?? false),
            ])
            ->emptyStateHeading('Sin comentarios todavía')
            ->emptyStateDescription('Agrega el primer avance con el cliente.');
    }
}

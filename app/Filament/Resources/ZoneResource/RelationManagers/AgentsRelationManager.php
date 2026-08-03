<?php

namespace App\Filament\Resources\ZoneResource\RelationManagers;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AgentsRelationManager extends RelationManager
{
    protected static string $relationship = 'agents';

    protected static ?string $title = 'Agentes asignados';

    protected static ?string $modelLabel = 'agente';

    protected static ?string $pluralModelLabel = 'agentes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Asignado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Asignar agente')
                    ->modalHeading('Asignar agente a zona')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => self::assignableAgentsQuery($query))
                    ->recordSelect(fn (Select $select): Select => $select->rules([
                        Rule::exists('users', 'id')
                            ->where('status', UserStatus::Active->value)
                            ->whereIn('id', User::query()->role('agente')->select('users.id')),
                    ]))
                    ->attachAnother(false),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Quitar'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->label('Quitar seleccionados'),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('update', $ownerRecord) ?? false;
    }

    protected function canAttach(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    protected function canDetach(Model $record): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    protected function canDetachAny(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function assignableAgentsQuery(Builder $query): Builder
    {
        return $query
            ->role('agente')
            ->active();
    }
}

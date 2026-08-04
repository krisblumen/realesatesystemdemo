<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyOwnerResource\Pages;
use App\Models\PropertyOwner;
use App\Rules\UniquePropertyOwner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PropertyOwnerResource extends Resource
{
    protected static ?string $model = PropertyOwner::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Operación';

    protected static ?string $modelLabel = 'propietario';

    protected static ?string $pluralModelLabel = 'propietarios';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del propietario')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Apellidos')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->required()
                            ->maxLength(40)
                            ->rule(fn (?PropertyOwner $record): UniquePropertyOwner => new UniquePropertyOwner($record?->id)),
                        // Teléfono y email son OBLIGATORIOS los dos: son los que
                        // identifican al cliente para evitar registros dobles, y
                        // sin uno de ellos el mismo cliente vuelve a entrar por
                        // el hueco que quedó.
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(180)
                            // La misma regla que el teléfono: alcanza con que
                            // coincida uno para que sea el mismo cliente, así
                            // que el aviso tiene que salir se escriba donde se
                            // escriba el dato repetido.
                            ->rule(fn (?PropertyOwner $record): UniquePropertyOwner => new UniquePropertyOwner($record?->id)),
                        Forms\Components\Select::make('agent_id')
                            ->label('Agente responsable')
                            ->relationship('agent', 'name', fn (Builder $query): Builder => $query
                                ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente')))
                            ->searchable()
                            ->preload()
                            ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Apellidos')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agente')
                    ->badge()
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
                Tables\Columns\TextColumn::make('properties_count')
                    ->label('Inmuebles')
                    ->counts('properties')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->label('Papelera')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->visible(fn (PropertyOwner $record): bool => auth()->user()?->can('update', $record) ?? false),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (PropertyOwner $record): bool => auth()->user()?->can('delete', $record) ?? false),
                Tables\Actions\RestoreAction::make()
                    ->iconButton()
                    ->visible(fn (PropertyOwner $record): bool => auth()->user()?->can('restore', $record) ?? false),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = auth()->user();

        return $user ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropertyOwners::route('/'),
            'create' => Pages\CreatePropertyOwner::route('/create'),
            'edit' => Pages\EditPropertyOwner::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('owners.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('owners.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }
}

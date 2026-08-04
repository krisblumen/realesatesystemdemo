<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceTypeResource\Pages;
use App\Models\ServiceType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ServiceTypeResource extends Resource
{
    protected static ?string $model = ServiceType::class;

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $modelLabel = 'tipo de servicio';

    protected static ?string $pluralModelLabel = 'tipos de servicio';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Código')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true)
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->helperText('Identificador interno. No modificable después de creado.'),
            Forms\Components\TextInput::make('label')
                ->label('Etiqueta')
                ->required()
                ->maxLength(100),
            Forms\Components\Select::make('color')
                ->label('Color del badge')
                ->options([
                    'gray' => 'Gris',
                    'info' => 'Azul',
                    'success' => 'Verde',
                    'warning' => 'Amarillo',
                    'danger' => 'Rojo',
                    'primary' => 'Primario',
                ])
                ->required(),
            Forms\Components\TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('active')
                ->label('Activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Etiqueta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('color')
                    ->label('Color')
                    ->badge()
                    ->color(fn (string $state): string => $state),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceTypes::route('/'),
            'create' => Pages\CreateServiceType::route('/create'),
            'edit' => Pages\EditServiceType::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}

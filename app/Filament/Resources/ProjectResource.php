<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use App\Models\ProjectType;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationGroup = 'Operación';

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $modelLabel = 'proyecto';

    protected static ?string $pluralModelLabel = 'proyectos';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del proyecto')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Título del proyecto')
                        ->required()
                        ->maxLength(180)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('project_type')
                        ->label('Tipo de proyecto')
                        ->options(fn (): array => ProjectType::query()
                            ->where('active', true)
                            ->orderBy('sort_order')
                            ->pluck('label', 'code')
                            ->all())
                        ->required()
                        ->searchable()
                        ->native(false),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Proyecto destacado')
                        ->helperText('Se muestra en la sección de proyectos del home.')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->label('Descripción del proyecto')
                        ->rows(5)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Fotografías')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('cover')
                        ->label('Foto principal')
                        ->collection('cover')
                        ->image()
                        ->imageEditor()
                        ->maxSize(PropertyResource::MAX_FOTO_KB)
                        ->imageEditorAspectRatios([null, '16:9', '4:3', '3:2', '1:1'])
                        ->helperText('Es la portada que se muestra en la vista pública. Podés recortarla libremente o elegir una proporción fija.'),
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->label('Fotografías del proyecto')
                        ->collection('gallery')
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->image()
                        ->imageEditor()
                        ->panelLayout('grid')
                        ->panelAspectRatio('1:1')
                        ->maxSize(PropertyResource::MAX_FOTO_KB)
                        ->imageEditorAspectRatios([null, '16:9', '4:3', '3:2', '1:1'])
                        ->maxFiles(30),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('portada')
                    ->label('')
                    ->collection('cover')
                    ->conversion('thumb')
                    ->square(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('projectType.label')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (Project $record): string => $record->projectType?->color ?? 'gray'),
                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Destacado'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('projects.manage') ?? false;
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
        return self::canViewAny();
    }
}

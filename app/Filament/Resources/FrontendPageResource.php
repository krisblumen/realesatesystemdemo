<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrontendPageResource\Pages;
use App\Filament\Resources\FrontendPageResource\RelationManagers\HeroRelationManager;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner-only editor for institutional pages (RFC-075). The owner edits section
 * drafts (through the content service) and publishes a page atomically. Pages
 * are the five canonical rows — never created or deleted here.
 */
class FrontendPageResource extends Resource
{
    protected static ?string $model = FrontendPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Frontend';

    protected static ?string $navigationLabel = 'Contenido de páginas';

    protected static ?string $modelLabel = 'página del sitio';

    protected static ?string $pluralModelLabel = 'páginas del sitio';

    protected static ?string $slug = 'frontend/paginas';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Cómo se nombra una página en migas de pan, buscador y títulos.
     *
     * Sin esto Filament cae al nombre del MODELO —«página del sitio»— y las
     * cinco se llaman igual: al entrar a editar no había forma de saber cuál
     * estabas tocando.
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof FrontendPage ? $record->label() : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Página')->schema([
                Toggle::make('is_enabled')->label('Página habilitada')
                    ->helperText('Una página deshabilitada usa el contenido por defecto en el sitio.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // El nombre que el owner conoce, con la clave técnica debajo:
                // la clave sola —«home», «inversionistas»— obliga a traducirla
                // mentalmente cada vez.
                TextColumn::make('key')->label('Página')
                    ->formatStateUsing(fn (FrontendPage $record): string => $record->label())
                    ->description(fn (FrontendPage $record): string => $record->key)
                    ->weight('medium'),
                // De un vistazo: qué páginas tienen trabajo guardado que el
                // sitio todavía no muestra. Sin esto, quien edita y se va cree
                // que su cambio ya está publicado.
                TextColumn::make('estado_publicacion')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (FrontendPage $record): string => $record->hasUnpublishedChanges()
                        ? 'Cambios sin publicar'
                        : 'Publicada')
                    ->color(fn (FrontendPage $record): string => $record->hasUnpublishedChanges() ? 'warning' : 'success')
                    ->icon(fn (FrontendPage $record): string => $record->hasUnpublishedChanges()
                        ? 'heroicon-o-exclamation-triangle'
                        : 'heroicon-o-check-circle'),
                IconColumn::make('is_enabled')->label('Habilitada')->boolean(),
                TextColumn::make('revision')->label('Publicaciones'),
                TextColumn::make('published_at')->label('Última publicación')->dateTime()->placeholder('Sin publicar'),
            ])
            ->actions([
                // Publishing lives on the edit page, which holds the
                // draft_revision it loaded (M-E1); a click-time table action
                // could not carry a stale revision.
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        // La portada primero y aparte: es fija, no se acomoda con las demás.
        //
        // Van dentro de UN grupo y no sueltos porque Filament convierte en
        // pestañas cualquier página con más de un relation manager, y eso
        // dejaba el listado de secciones escondido detrás de un clic. Un grupo
        // cuenta como uno solo y renderiza sus bloques apilados, en orden.
        return [
            RelationGroup::make('Contenido', [
                HeroRelationManager::class,
                SectionsRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrontendPages::route('/'),
            'edit' => Pages\EditFrontendPage::route('/{record}/edit'),
        ];
    }
}

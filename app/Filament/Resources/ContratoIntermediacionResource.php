<?php

namespace App\Filament\Resources;

use App\Enums\EstadoContrato;
use App\Enums\TipoOperacionContrato;
use App\Enums\UserStatus;
use App\Filament\Resources\ContratoIntermediacionResource\Pages;
use App\Filament\Resources\ContratoIntermediacionResource\RelationManagers;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use App\Services\Contratos\ContratoAccesoService;
use App\Services\Contratos\ContratoEnvioService;
use App\Services\Contratos\ContratoEventoService;
use App\Services\Contratos\ContratoRetencionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ContratoIntermediacionResource extends Resource
{
    protected static ?string $model = ContratoIntermediacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Operación';

    protected static ?string $navigationLabel = 'Contratos';

    protected static ?string $modelLabel = 'contrato de intermediación';

    protected static ?string $pluralModelLabel = 'contratos de intermediación';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cliente')
                ->description('El propietario/oferente que autoriza la intermediación. Todos los datos son obligatorios.')
                ->schema([
                    Forms\Components\TextInput::make('cliente_nombre')->label('Nombre completo')->required()->maxLength(255),
                    Forms\Components\TextInput::make('cliente_telefono')->label('Teléfono')->tel()->required()->maxLength(30),
                    Forms\Components\TextInput::make('cliente_email')->label('Email')->email()->required()->maxLength(255),
                    Forms\Components\TextInput::make('cliente_direccion')->label('Dirección')->required()->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make('Inmueble a promover')
                ->description('Datos propios del contrato: no referencian el catálogo de inmuebles.')
                ->schema([
                    Forms\Components\TextInput::make('inmueble_tipo')->label('Tipo de inmueble')->required()->maxLength(100),
                    Forms\Components\Select::make('tipo_operacion')
                        ->label('Tipo de operación')
                        ->options(self::tipoOperacionOptions())
                        ->required(),
                    Forms\Components\TextInput::make('inmueble_direccion')->label('Dirección del inmueble')->required()->maxLength(255),
                    Forms\Components\TextInput::make('precio_autorizado')
                        ->label('Precio / Renta autorizado')
                        ->helperText('Monto de venta, o renta mensual, según la operación.')
                        ->numeric()->minValue(0)->prefix('$')->required(),
                    Forms\Components\TextInput::make('comision_porcentaje')
                        ->label('Comisión (%)')
                        ->numeric()->minValue(0)->maxValue(100)->step(0.01)->required(),
                ])->columns(2),

            Forms\Components\Section::make('Condiciones')
                ->schema([
                    Forms\Components\DatePicker::make('vigencia_inicio')->label('Vigencia — inicio')->required(),
                    Forms\Components\DatePicker::make('vigencia_fin')->label('Vigencia — fin')->required()
                        ->afterOrEqual('vigencia_inicio'),
                    Forms\Components\Toggle::make('exclusividad')->label('Exclusividad')->inline(false),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('folio')->label('Folio')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('cliente_nombre')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('inmueble_tipo')->label('Inmueble')->toggleable(),
                Tables\Columns\TextColumn::make('tipo_operacion')
                    ->label('Operación')
                    ->badge()
                    ->formatStateUsing(fn (TipoOperacionContrato $state): string => $state->label()),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoContrato $state): string => $state->label())
                    ->color(fn (EstadoContrato $state): string => $state->color()),
                Tables\Columns\TextColumn::make('agente.name')->label('Agente')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Generado')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(self::estadoOptions()),
                SelectFilter::make('tipo_operacion')->label('Operación')->options(self::tipoOperacionOptions()),
                SelectFilter::make('agente_id')->label('Agente')->options(fn (): array => self::agentOptions())->searchable(),
                TernaryFilter::make('exclusividad')->label('Exclusividad'),
            ])
            ->actions([
                Tables\Actions\Action::make('enviar')
                    ->label('Enviar')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (ContratoIntermediacion $record): bool => $record->estado === EstadoContrato::Generado
                        && auth()->user()?->can('enviar', $record))
                    ->requiresConfirmation()
                    ->action(fn (ContratoIntermediacion $record) => self::runEnviar($record)),

                Tables\Actions\Action::make('reenviar')
                    ->label('Reenviar')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (ContratoIntermediacion $record): bool => in_array($record->estado, [EstadoContrato::Rechazado, EstadoContrato::Expirado], true)
                        && auth()->user()?->can('enviar', $record))
                    ->requiresConfirmation()
                    ->action(fn (ContratoIntermediacion $record) => self::runReenviar($record)),

                Tables\Actions\Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ContratoIntermediacion $record): bool => auth()->user()?->can('cancel', $record) ?? false)
                    ->requiresConfirmation()
                    ->action(fn (ContratoIntermediacion $record) => self::runCancelar($record)),

                Tables\Actions\Action::make('confirmarEliminacion')
                    ->label('Confirmar eliminación')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (ContratoIntermediacion $record): bool => $record->eliminacion_pendiente
                        && (auth()->user()?->can('confirmarEliminacion', $record) ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar eliminación del expediente')
                    ->modalDescription('Se purgarán la identificación y la firma del cliente y el expediente se archivará. El PDF y su hash se conservan para verificación. Esta acción no se puede deshacer.')
                    ->action(fn (ContratoIntermediacion $record) => self::runConfirmarEliminacion($record)),

                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Contrato')
                ->columns(3)
                ->schema([
                    TextEntry::make('folio')->label('Folio')->copyable(),
                    TextEntry::make('estado')->label('Estado')->badge()
                        ->formatStateUsing(fn (EstadoContrato $state): string => $state->label())
                        ->color(fn (EstadoContrato $state): string => $state->color()),
                    TextEntry::make('agente.name')->label('Agente'),
                    TextEntry::make('cliente_nombre')->label('Cliente'),
                    TextEntry::make('cliente_telefono')->label('Teléfono')->placeholder('—'),
                    TextEntry::make('cliente_email')->label('Email')->placeholder('—'),
                    TextEntry::make('inmueble_tipo')->label('Inmueble'),
                    TextEntry::make('tipo_operacion')->label('Operación')
                        ->formatStateUsing(fn (TipoOperacionContrato $state): string => $state->label()),
                    TextEntry::make('precio')->label('Precio / Renta')->state(fn (ContratoIntermediacion $r): string => $r->precioFormateado()),
                    TextEntry::make('vigencia_inicio')->label('Vigencia inicio')->date('d/m/Y'),
                    TextEntry::make('vigencia_fin')->label('Vigencia fin')->date('d/m/Y'),
                    TextEntry::make('firmado_at')->label('Firmado')->dateTime('d/m/Y H:i')->placeholder('—'),
                ]),

            // Evidencia de identidad: solo Owner, y solo una vez firmado. Muestra el FRENTE
            // de la credencial para corroborar que quien firmó es la persona de la ID.
            InfoSection::make('Identificación del firmante (frente)')
                ->description('Comparar la foto de la credencial con la firma registrada.')
                ->visible(fn (ContratoIntermediacion $r): bool => $r->estado === EstadoContrato::Firmado
                    && $r->tieneIdentificacion()
                    && (auth()->user()?->can('verIdentificacion', $r) ?? false))
                ->schema([
                    ViewEntry::make('identificacion')
                        ->hiddenLabel()
                        ->view('filament.infolists.identificacion-anverso')
                        ->state(fn (ContratoIntermediacion $r): string => route('contratos.media', [
                            'contrato' => $r,
                            'coleccion' => 'identificacion-anverso',
                        ])),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return $user instanceof User
            ? ContratoIntermediacion::query()->visibleTo($user)
            : ContratoIntermediacion::query()->whereRaw('1 = 0');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EventosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContratos::route('/'),
            'create' => Pages\CreateContrato::route('/create'),
            'view' => Pages\ViewContrato::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', ContratoIntermediacion::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', ContratoIntermediacion::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        // El contrato no se edita tras generarse; sus acciones son enviar/reenviar/cancelar.
        return false;
    }

    public static function runEnviar(ContratoIntermediacion $record): void
    {
        try {
            app(ContratoEnvioService::class)->enviar($record, auth()->user());
            Notification::make()->title('Contrato enviado al cliente.')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function runReenviar(ContratoIntermediacion $record): void
    {
        try {
            app(ContratoAccesoService::class)->reenviar($record, auth()->user());
            Notification::make()->title('Contrato reenviado (mismo folio).')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function runCancelar(ContratoIntermediacion $record): void
    {
        $record->transicionarA(
            EstadoContrato::Cancelado,
            auth()->user(),
            app(ContratoEventoService::class)->contextoHttp(),
        );
        Notification::make()->title('Contrato cancelado.')->success()->send();
    }

    private static function runConfirmarEliminacion(ContratoIntermediacion $record): void
    {
        app(ContratoRetencionService::class)->confirmarEliminacion($record, auth()->user());
        Notification::make()->title('Expediente eliminado. Se conservó el PDF para verificación.')->success()->send();
    }

    /** @return array<string, string> */
    private static function tipoOperacionOptions(): array
    {
        return collect(TipoOperacionContrato::cases())
            ->mapWithKeys(fn (TipoOperacionContrato $t): array => [$t->value => $t->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function estadoOptions(): array
    {
        return collect(EstadoContrato::cases())
            ->mapWithKeys(fn (EstadoContrato $e): array => [$e->value => $e->label()])
            ->all();
    }

    /** @return array<int, string> */
    private static function agentOptions(): array
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}

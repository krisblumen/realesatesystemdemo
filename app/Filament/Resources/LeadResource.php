<?php

namespace App\Filament\Resources;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\LeadResource\Pages;
use App\Filament\Resources\LeadResource\RelationManagers;
use App\Models\Lead;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\LeadAssignmentService;
use App\Services\LeadStatusService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Operación';

    protected static ?string $modelLabel = 'lead';

    protected static ?string $pluralModelLabel = 'leads';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del prospecto')
                // El agente captura el lead al crearlo, pero los datos de contacto
                // de un lead existente sólo los corrige owner/admin (integridad del
                // registro original). El agente sigue trabajando la sección Gestión.
                ->disabled(fn (string $operation): bool => $operation === 'edit' && ! self::userIsOwnerOrAdmin())
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(40)
                        ->regex('/^\+?[0-9\s\-()]{7,20}$/'),
                    Forms\Components\Select::make('source')
                        ->label('Origen')
                        ->options(self::sourceOptions())
                        ->required(),
                    Forms\Components\Textarea::make('message')
                        ->label('Mensaje')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Gestión')
                ->columns(2)
                ->schema([
                    Forms\Components\Radio::make('service_type')
                        ->label('Tipo de servicio')
                        ->options(fn (): array => self::serviceTypeOptions())
                        ->required()
                        ->inline()
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options(fn (?Lead $record): array => self::statusFormOptions($record))
                        ->default(LeadStatus::Nuevo->value)
                        ->required()
                        ->helperText('En edición solo se ofrecen transiciones válidas; usa "Cambiar estado" para mover el lead.'),
                    Forms\Components\Select::make('property_id')
                        ->label('Inmueble')
                        ->relationship('property', 'title', function (Builder $query): Builder {
                            // El agente sólo puede elegir entre sus inmuebles;
                            // owner/admin ven todos.
                            $user = auth()->user();

                            return $user instanceof User ? $query->visibleTo($user) : $query;
                        })
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get): bool => $get('service_type') === 'comercializacion'),
                    Forms\Components\Select::make('agent_id')
                        ->label('Agente')
                        ->relationship('agent', 'name', fn (Builder $query): Builder => $query
                            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente'))
                            ->where('status', UserStatus::Active->value))
                        ->searchable()
                        ->preload()
                        ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
                    Forms\Components\DateTimePicker::make('assigned_at')
                        ->label('Asignado el')
                        ->seconds(false)
                        ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->searchable()
                    ->placeholder('Sin teléfono'),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (LeadSource $state): string => $state->label())
                    ->color(fn (LeadSource $state): string => $state->color()),
                Tables\Columns\TextColumn::make('service_type')
                    ->label('Servicio')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ServiceType::query()->find($state)?->label ?? $state)
                    ->color(fn (string $state): string => ServiceType::query()->find($state)?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (LeadStatus $state): string => $state->label())
                    ->color(fn (LeadStatus $state): string => $state->color())
                    ->sortable(),
                Tables\Columns\TextColumn::make('property.title')
                    ->label('Inmueble')
                    ->searchable()
                    ->placeholder('Sin inmueble'),
                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agente')
                    ->searchable()
                    ->placeholder('Sin asignar'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (Lead $record): ?string => $record->isNew()
                ? 'nh-lead-row-new'
                : null)
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(self::statusOptions()),
                SelectFilter::make('source')
                    ->label('Origen')
                    ->options(self::sourceOptions()),
                SelectFilter::make('service_type')
                    ->label('Servicio')
                    ->options(fn (): array => self::serviceTypeOptions()),
                SelectFilter::make('agent_id')
                    ->label('Agente')
                    ->relationship('agent', 'name')
                    ->preload()
                    ->searchable()
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false),
            ])
            ->headerActions([
                Tables\Actions\Action::make('reassignAgentLeads')
                    ->label('Reasignar leads de un agente')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false)
                    ->form([
                        Forms\Components\Select::make('from_agent_id')
                            ->label('Agente origen (sale / de vacaciones)')
                            ->options(fn (): array => self::agentOptions())
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('to_agent_id')
                            ->label('Agente destino (cobertura)')
                            ->options(fn (): array => self::activeAgentOptions())
                            ->searchable()
                            ->required()
                            ->different('from_agent_id'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (array $data): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            return;
                        }

                        $from = User::query()->findOrFail($data['from_agent_id']);
                        $to = User::query()->findOrFail($data['to_agent_id']);

                        $count = app(LeadAssignmentService::class)
                            ->reassignOpenLeads($from, $to, $actor, $data['reason']);

                        Notification::make()
                            ->success()
                            ->title("{$count} leads abiertos reasignados a {$to->name}.")
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('changeStatus')
                    ->label('Cambiar estado')
                    ->icon('heroicon-o-arrow-path')
                    ->form(fn (Lead $record): array => [
                        Forms\Components\Select::make('status')
                            ->label('Nuevo estado')
                            ->options(self::transitionOptions($record))
                            ->required(),
                    ])
                    ->visible(fn (Lead $record): bool => (auth()->user()?->can('update', $record) ?? false)
                        && $record->status->allowedTransitions() !== [])
                    ->action(function (Lead $record, array $data): void {
                        app(LeadStatusService::class)->transition($record, LeadStatus::from($data['status']));
                    }),
                Tables\Actions\Action::make('reassign')
                    ->label('Reasignar')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('agent_id')
                            ->label('Nuevo agente')
                            ->options(fn (): array => self::activeAgentOptions())
                            ->searchable()
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->visible(function (Lead $record): bool {
                        $user = auth()->user();

                        return $user instanceof User
                            && $user->hasAnyRole(['owner', 'admin'])
                            && $user->can('update', $record);
                    })
                    ->action(function (Lead $record, array $data): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            return;
                        }

                        $agent = User::query()->findOrFail($data['agent_id']);

                        app(LeadAssignmentService::class)->reassign($record, $agent, $actor, $data['reason']);
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Lead $record): bool => auth()->user()?->can('update', $record) ?? false),
                // Los leads no se eliminan: son historial comercial para auditoría.
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('reassignSelected')
                    ->label('Reasignar seleccionados')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false)
                    ->form([
                        Forms\Components\Select::make('agent_id')
                            ->label('Nuevo agente')
                            ->options(fn (): array => self::activeAgentOptions())
                            ->searchable()
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            return;
                        }

                        $agent = User::query()->findOrFail($data['agent_id']);
                        $service = app(LeadAssignmentService::class);

                        foreach ($records as $record) {
                            $service->reassign($record, $agent, $actor, $data['reason']);
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = Lead::query();

        return $user instanceof User ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user() instanceof User) {
            return null;
        }

        // Owner/admin ven los leads abiertos sin asignar (esperan revisión
        // manual). El agente ve sus propios leads abiertos: getEloquentQuery()
        // ya scopea a "sólo mis leads" vía visibleTo(), así que alcanza con
        // filtrar los abiertos.
        $query = static::getEloquentQuery()->open();

        if (self::userIsOwnerOrAdmin()) {
            $query->unassigned();
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    private static function userIsOwnerOrAdmin(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LeadNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Lead::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Lead::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        // Los leads son historial comercial: nadie los elimina.
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    /** @return array<int, string> */
    private static function activeAgentOptions(): array
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Todos los agentes (incluye suspendidos): el agente "origen" de una
     * reasignación masiva puede haber renunciado y estar inactivo.
     *
     * @return array<int, string>
     */
    private static function agentOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('roles.name', 'agente'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, string> */
    private static function sourceOptions(): array
    {
        return collect(LeadSource::cases())
            ->mapWithKeys(fn (LeadSource $source): array => [$source->value => $source->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(LeadStatus::cases())
            ->mapWithKeys(fn (LeadStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function serviceTypeOptions(): array
    {
        return ServiceType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'code')
            ->all();
    }

    /**
     * Opciones de estado para el formulario, respetando la máquina de estados:
     * en alta solo "nuevo"; en edición, el estado actual + sus transiciones válidas.
     *
     * @return array<string, string>
     */
    private static function statusFormOptions(?Lead $lead): array
    {
        if (! $lead instanceof Lead) {
            return [LeadStatus::Nuevo->value => LeadStatus::Nuevo->label()];
        }

        return collect([$lead->status, ...$lead->status->allowedTransitions()])
            ->unique()
            ->mapWithKeys(fn (LeadStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function transitionOptions(Lead $lead): array
    {
        return collect($lead->status->allowedTransitions())
            ->mapWithKeys(fn (LeadStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}

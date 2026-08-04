<?php

namespace App\Filament\Resources;

use App\Enums\UserStatus;
use App\Events\UserRegistered;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Services\UserStatusService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Seguridad';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de acceso')
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
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Placeholder::make('password_notice')
                            ->label('Contraseña')
                            ->content('Se le manda un mail de invitación para que el usuario elija su propia contraseña.')
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Forms\Components\TextInput::make('password')
                            ->label('Nueva contraseña')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        Forms\Components\Select::make('roles')
                            ->label('Roles')
                            ->helperText('Un usuario puede tener uno o más roles (ej. arquitectura + agente).')
                            ->multiple()
                            ->options(fn (): array => static::assignableRoleOptions())
                            ->required()
                            ->rules([
                                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                    $roles = is_array($value) ? $value : [];

                                    foreach ($roles as $role) {
                                        if (! is_string($role) || ! Gate::allows('assignRole', [User::class, $role])) {
                                            $fail('No tienes permiso para asignar uno de los roles seleccionados.');

                                            return;
                                        }
                                    }
                                },
                            ])
                            ->visible(fn (): bool => auth()->user()?->can('users.create') || auth()->user()?->can('users.update')),
                    ]),
                Forms\Components\Section::make('Perfil')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255)
                            ->regex('/^\+?[0-9\s\-()]{7,20}$/'),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->tel()
                            ->maxLength(255)
                            ->regex('/^\+?[0-9\s\-()]{7,20}$/'),
                        Forms\Components\FileUpload::make('avatar')
                            ->label('Avatar')
                            ->image()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->imageEditor()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(static::statusOptions())
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('El estado se gestiona desde las acciones Suspender/Reactivar.')
                            ->visible(fn (): bool => auth()->user()?->can('users.create') || auth()->user()?->can('users.update')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->disk('public')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->icon('heroicon-m-envelope')
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->separator(', '),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus|string|null $state): ?string => self::statusLabel($state))
                    ->color(fn (UserStatus|string|null $state): string => self::statusColor($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Último login')
                    ->icon('heroicon-m-clock')
                    ->color('gray')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin acceso')
                    ->sortable(),
                Tables\Columns\TextColumn::make('zones.name')
                    ->label('Zonas asignadas')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-map-pin')
                    ->listWithLineBreaks()
                    ->limitList(4)
                    ->expandableLimitedList(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(static::statusOptions()),
                TrashedFilter::make()
                    ->label('Papelera')
                    ->visible(fn (): bool => auth()->user()?->hasRole('owner') ?? false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->visible(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false),
                Tables\Actions\Action::make('resendInvitation')
                    ->label('Reenviar invitación')
                    ->icon('heroicon-o-paper-airplane')
                    ->iconButton()
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (User $record): mixed => event(new UserRegistered($record)))
                    ->visible(fn (User $record): bool => ($record->isPending() && (auth()->user()?->can('update', $record) ?? false))),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-lock-closed')
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(fn (User $record, array $data): mixed => app(UserStatusService::class)
                        ->suspend($record, auth()->user(), $data['reason']))
                    ->visible(fn (User $record): bool => ($record->isActive() && (auth()->user()?->can('suspend', $record) ?? false))),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivar')
                    ->icon('heroicon-o-lock-open')
                    ->iconButton()
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (User $record): mixed => app(UserStatusService::class)
                        ->reactivate($record, auth()->user(), 'Reactivación desde panel administrativo.'))
                    ->visible(fn (User $record): bool => ($record->isSuspended() && (auth()->user()?->can('reactivate', $record) ?? false))),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (User $record): bool => auth()->user()?->can('delete', $record) ?? false),
                Tables\Actions\RestoreAction::make()
                    ->iconButton()
                    ->visible(fn (User $record): bool => auth()->user()?->can('restore', $record) ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('users.delete') ?? false),
                    Tables\Actions\RestoreBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole('owner') ?? false),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ZonesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('users.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    /**
     * @return array<string, string>
     */
    public static function assignableRoleOptions(): array
    {
        $actor = auth()->user();

        return Role::query()
            ->when(! $actor?->hasRole('owner'), fn (Builder $query) => $query->where('name', '!=', 'owner'))
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(UserStatus::cases())
            ->mapWithKeys(fn (UserStatus $status): array => [$status->value => self::statusLabel($status)])
            ->all();
    }

    private static function statusLabel(UserStatus|string|null $status): ?string
    {
        $status = is_string($status) ? UserStatus::tryFrom($status) : $status;

        return match ($status) {
            UserStatus::Active => 'Activo',
            UserStatus::Suspended => 'Suspendido',
            UserStatus::Pending => 'Pendiente de activación',
            null => null,
        };
    }

    private static function statusColor(UserStatus|string|null $status): string
    {
        $status = is_string($status) ? UserStatus::tryFrom($status) : $status;

        return match ($status) {
            UserStatus::Active => 'success',
            UserStatus::Suspended => 'danger',
            UserStatus::Pending => 'warning',
            null => 'gray',
        };
    }
}

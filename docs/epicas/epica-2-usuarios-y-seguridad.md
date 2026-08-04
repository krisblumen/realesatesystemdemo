# Épica 2 — Usuarios y Seguridad

**Proyecto:** New Hauz — Plataforma Inmobiliaria  
**Estado:** ✅ APROBADO PARA IMPLEMENTACIÓN  
**Rama base:** `develop`  
**Rama de trabajo:** `feature/epica-2-usuarios-y-seguridad`  
**Arquitecto:** Edgar  
**QA:** Sebastián  
**Revisión:** Kristian  
**Diseño generado:** 16 de Junio, 2026  
**Auditoría aplicada:** 16 de Junio, 2026 (Gemini CLI)  
**Cierre técnico:** 16 de Junio, 2026

---

## 1. Contexto y Dependencias

Esta épica consume directamente los contratos entregados por la Épica 1:

| Contrato consumido | RFC origen | Estado |
| :--- | :--- | :--- |
| Laravel 13.x + PHP 8.3 | RFC-001 | ✅ Activo |
| PostgreSQL 18 + PostGIS 3.6 | RFC-002 / RFC-003 | ✅ Activo |
| Filament v3.3.54 + panel `/admin` | RFC-004 | ✅ Activo |
| Livewire 3.8.1 | RFC-005 | ✅ Activo |
| `spatie/laravel-permission` 8.0.0 | RFC-006 | ✅ Activo |
| `spatie/laravel-medialibrary` 11.23.0 | RFC-007 | ✅ Activo |
| `App\Models\User` con `HasRoles` | RFC-006 | ✅ Activo |
| Roles base: `owner`, `admin`, `agente` | RFC-006 | ✅ Activo (sin permisos asignados aún) |

**No se toca ningún archivo de la Épica 1.** Toda extensión es aditiva (migraciones `alter`, traits, policies nuevas).

---

## 2. Objetivos

### Lo que esta épica entrega

- Enum `UserStatus` y enum `SuspensionAction` como tipos de dominio.
- Modelo `User` extendido con campos de agente, estados y soft delete.
- Matriz de roles y permisos implementada con seeder verificable.
- `UserPolicy` con reglas de acceso aplicadas en capa de dominio (no solo UI).
- `UserResource` en Filament con CRUD completo, filtros, validaciones y control de permisos diferenciado por rol.
- Máquina de estados `activo ↔ suspendido` con bloqueo de login y auditoría.
- Tabla `user_suspensions` como registro inmutable de acciones de suspensión/reactivación.
- Middleware `EnsureUserIsActive` como defensa principal de acceso.
- Listener `InvalidateSuspendedLogin` como defensa secundaria con logging de seguridad.

### Lo que esta épica NO entrega

- Tablas `properties` o `leads` (se declaran como contrato diferido en el modelo).
- Autenticación externa (OAuth, SSO, LDAP).
- Paquetes de pago o SaaS de permisos.
- API REST de usuarios (monolito; Filament es la interfaz).
- Frontend público (Épica 6).
- Recuperación de contraseña por email (Épica 8).
- Definición de si `admin` puede crear otros `admin` (decisión de negocio diferida — ver Sección 16).

---

## 3. Alcance Funcional

| # | Funcionalidad | Actor |
| :--- | :--- | :--- |
| F-1 | Crear usuario con rol asignado | owner, admin |
| F-2 | Editar datos de usuario | owner, admin |
| F-3 | Ver listado y detalle de usuarios | owner, admin |
| F-4 | Soft-delete de usuario | owner |
| F-5 | Restaurar usuario eliminado | owner |
| F-6 | Asignar/cambiar rol a usuario | owner (cualquier rol), admin (solo admin y agente) |
| F-7 | Suspender usuario con motivo | owner, admin (no a owner) |
| F-8 | Reactivar usuario suspendido | owner, admin |
| F-9 | Bloqueo automático de login para suspendidos | sistema |
| F-10 | Historial de suspensiones y reactivaciones | owner, admin |

---

## 4. Alcance Técnico

```
app/
├── Enums/
│   ├── UserStatus.php                  ← Nuevo
│   └── SuspensionAction.php            ← Nuevo (corrección auditoría 4.1)
├── Models/
│   ├── User.php                        ← EXTENDER (no recrear)
│   └── UserSuspension.php              ← Nuevo
├── Policies/
│   └── UserPolicy.php                  ← Nuevo
├── Http/Middleware/
│   └── EnsureUserIsActive.php          ← Nuevo
├── Services/
│   └── UserSuspensionService.php       ← Nuevo
├── Listeners/
│   └── InvalidateSuspendedLogin.php    ← Nuevo
├── Filament/Resources/
│   └── UserResource/
│       ├── UserResource.php            ← Nuevo
│       └── Pages/
│           ├── ListUsers.php
│           ├── CreateUser.php
│           └── EditUser.php
database/
├── migrations/
│   ├── xxxx_add_agent_fields_to_users_table.php   ← ALTER
│   └── xxxx_create_user_suspensions_table.php     ← CREATE
├── seeders/
│   └── PermissionSeeder.php            ← Nuevo
```

---

## 5. RFC-011 — Modelo Usuario

### 5.1 Decisiones de arquitectura (CERRADAS)

| Decisión | Resolución |
| :--- | :--- |
| ¿Extender o recrear User? | **Extender.** La migración `0001_01_01_000000_create_users_table.php` no se modifica. |
| Tipo de campo `status` | **String con cast a `UserStatus` enum.** Más flexible que `$table->enum()` para migraciones futuras. |
| Tipo de campo `action` en suspensiones | **String con cast a `SuspensionAction` enum.** Consistente con `UserStatus`. |
| Gestión de avatar | **Campo string** (ruta relativa). Spatie MediaLibrary queda disponible para conversiones de imagen en Épica 4. |
| Relaciones `properties` / `leads` | **Contratos diferidos.** Los métodos existen en el modelo, devuelven colección vacía segura hasta que las tablas de Épicas 3 y 4 existan. |
| Soft delete | **`SoftDeletes` trait.** Campo `deleted_at` vía migración de alteración. |
| Actualización de `last_login_at` | **Listener `Login`** de Laravel. Sin tocar el flujo de autenticación de Filament. |

### 5.2 Enum `UserStatus`

```php
// app/Enums/UserStatus.php
namespace App\Enums;

enum UserStatus: string
{
    case Activo     = 'activo';
    case Suspendido = 'suspendido';

    public function label(): string
    {
        return match($this) {
            self::Activo     => 'Activo',
            self::Suspendido => 'Suspendido',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Activo     => 'success',
            self::Suspendido => 'danger',
        };
    }
}
```

### 5.3 Enum `SuspensionAction` *(corrección auditoría 4.1)*

```php
// app/Enums/SuspensionAction.php
namespace App\Enums;

enum SuspensionAction: string
{
    case Suspendido  = 'suspendido';
    case Reactivado  = 'reactivado';

    public function label(): string
    {
        return match($this) {
            self::Suspendido => 'Suspendido',
            self::Reactivado => 'Reactivado',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Suspendido => 'danger',
            self::Reactivado => 'success',
        };
    }
}
```

### 5.4 Migración de extensión

```php
// database/migrations/xxxx_add_agent_fields_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable()->after('email');
    $table->string('whatsapp')->nullable()->after('phone');
    $table->string('avatar')->nullable()->after('whatsapp');
    $table->string('status')->default('activo')->after('avatar');
    $table->timestamp('last_login_at')->nullable()->after('status');
    $table->softDeletes();
});
```

**Rollback:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn(['phone', 'whatsapp', 'avatar', 'status', 'last_login_at', 'deleted_at']);
});
```

### 5.5 Modelo `User` extendido

```php
// app/Models/User.php — cambios respecto al estado de Épica 1
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

// Traits a agregar:
use SoftDeletes;

// Fillable:
#[Fillable(['name', 'email', 'password', 'phone', 'whatsapp', 'avatar', 'status'])]

// Casts:
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'last_login_at'     => 'datetime',
        'status'            => UserStatus::class,
    ];
}

// Scopes:
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', UserStatus::Activo->value);
}

public function scopeSuspended(Builder $query): Builder
{
    return $query->where('status', UserStatus::Suspendido->value);
}

// Helpers de estado:
public function isActive(): bool
{
    return $this->status === UserStatus::Activo;
}

public function isSuspended(): bool
{
    return $this->status === UserStatus::Suspendido;
}

// Relaciones reales (Épica 2):
public function suspensions(): HasMany
{
    return $this->hasMany(UserSuspension::class);
}

public function latestSuspension(): HasOne
{
    return $this->hasOne(UserSuspension::class)->latestOfMany();
}

// Contratos diferidos — se activan en Épica 3 y Épica 4.
// IMPORTANTE: descomentar las líneas reales cuando la tabla exista.
// No eliminar los métodos; son el contrato con el resto del sistema.
public function properties(): HasMany
{
    // return $this->hasMany(\App\Models\Property::class); // Épica 3
    return $this->hasMany(User::class, 'id', 'id')->whereRaw('1=0');
}

public function leads(): HasMany
{
    // return $this->hasMany(\App\Models\Lead::class); // Épica 4
    return $this->hasMany(User::class, 'id', 'id')->whereRaw('1=0');
}
```

### 5.6 Modelo `UserSuspension`

```php
// app/Models/UserSuspension.php
namespace App\Models;

use App\Enums\SuspensionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSuspension extends Model
{
    protected $fillable = ['user_id', 'suspended_by', 'action', 'reason'];

    protected function casts(): array
    {
        return [
            'action'     => SuspensionAction::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }
}
```

---

## 6. RFC-012 — Roles y Permisos

### 6.1 Matriz rol → permiso (CERRADA)

| Permiso | `owner` | `admin` | `agente` |
| :--- | :---: | :---: | :---: |
| `users.view` | ✅ | ✅ | ❌ |
| `users.create` | ✅ | ✅ | ❌ |
| `users.update` | ✅ | ✅ | ❌ |
| `users.delete` | ✅ | ❌ | ❌ |
| `properties.manage` | ✅ | ✅ | ✅ |
| `leads.manage` | ✅ | ✅ | ✅ |
| `zones.manage` | ✅ | ✅ | ❌ |

**Reglas de negocio superpuestas a la matriz (aplicadas en Policy):**

- `admin` tiene `users.update` pero **no puede cambiar el rol de un `owner`**.
- `admin` tiene `users.update` pero **no puede suspender a un `owner`**.
- `admin` tiene `users.create` pero **no puede asignar el rol `owner`** al crear/editar.
- `admin` **no tiene** `users.delete` — nunca puede hacer soft-delete.
- Nadie puede eliminarse ni suspenderse a sí mismo.
- Un `owner` puede operar sobre cualquier usuario, incluyendo otros `owner`s.

### 6.2 `PermissionSeeder`

```php
// database/seeders/PermissionSeeder.php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché antes de cualquier operación (R-1)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'properties.manage', 'leads.manage', 'zones.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $owner  = Role::firstOrCreate(['name' => 'owner',  'guard_name' => 'web']);
        $admin  = Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
        $agente = Role::firstOrCreate(['name' => 'agente', 'guard_name' => 'web']);

        $owner->syncPermissions($permissions);

        $admin->syncPermissions([
            'users.view', 'users.create', 'users.update',
            'properties.manage', 'leads.manage', 'zones.manage',
        ]);

        $agente->syncPermissions([
            'properties.manage', 'leads.manage',
        ]);

        // Limpiar caché después de asignar permisos
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

> `firstOrCreate` + `syncPermissions` hacen el seeder **idempotente** — se puede re-ejecutar sin duplicados.

### 6.3 `UserPolicy`

```php
// app/Policies/UserPolicy.php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('users.view');
    }

    public function view(User $auth, User $target): bool
    {
        return $auth->can('users.view');
    }

    public function create(User $auth): bool
    {
        return $auth->can('users.create');
    }

    public function update(User $auth, User $target): bool
    {
        if (! $auth->can('users.update')) {
            return false;
        }
        // Admin no puede modificar a un owner
        if ($auth->hasRole('admin') && $target->hasRole('owner')) {
            return false;
        }
        return true;
    }

    public function delete(User $auth, User $target): bool
    {
        if (! $auth->can('users.delete')) {
            return false;
        }
        if ($auth->id === $target->id) {
            return false;
        }
        return true;
    }

    public function restore(User $auth, User $target): bool
    {
        return $auth->hasRole('owner');
    }

    public function forceDelete(User $auth, User $target): bool
    {
        return false;
    }

    public function suspend(User $auth, User $target): bool
    {
        if ($auth->id === $target->id) {
            return false;
        }
        // Nadie puede suspender a un owner (decisión cerrada)
        if ($target->hasRole('owner')) {
            return false;
        }
        return $auth->hasRole('owner') || $auth->hasRole('admin');
    }

    public function reactivate(User $auth, User $target): bool
    {
        return $auth->hasRole('owner') || $auth->hasRole('admin');
    }

    /**
     * Determina si el actor puede asignar un rol específico al crear/editar.
     * Owner puede asignar cualquier rol. Admin solo puede asignar admin y agente.
     */
    public function assignRole(User $auth, string $roleName): bool
    {
        if ($auth->hasRole('owner')) {
            return true;
        }
        if ($auth->hasRole('admin')) {
            return in_array($roleName, ['admin', 'agente'], true);
        }
        return false;
    }
}
```

**Registro en `AppServiceProvider`:**

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Policies\UserPolicy;

public function boot(): void
{
    Gate::policy(User::class, UserPolicy::class);
}
```

---

## 7. RFC-013 — CRUD Usuarios en Filament

### 7.1 Estructura del Resource

```
app/Filament/Resources/UserResource/
├── UserResource.php
└── Pages/
    ├── ListUsers.php
    ├── CreateUser.php
    └── EditUser.php
```

### 7.2 Form (campos)

```php
Forms\Components\TextInput::make('name')
    ->required()->maxLength(255),

Forms\Components\TextInput::make('email')
    ->email()->required()->unique(ignoreRecord: true),

Forms\Components\TextInput::make('password')
    ->password()
    ->required(fn ($operation) => $operation === 'create')
    ->minLength(8)
    ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
    ->dehydrated(fn ($state) => filled($state)),

Forms\Components\TextInput::make('phone')->nullable()->tel(),

Forms\Components\TextInput::make('whatsapp')->nullable()->tel(),

Forms\Components\FileUpload::make('avatar')
    ->image()->directory('avatars')->nullable(),

// CORRECCIÓN auditoría 3.1 y recomendación 8.3:
// visible para owner Y admin; opciones filtradas según rol del actor.
Forms\Components\Select::make('roles')
    ->label('Rol')
    ->options(function () {
        $actor = auth()->user();
        if ($actor->hasRole('owner')) {
            return \Spatie\Permission\Models\Role::pluck('name', 'name');
        }
        // Admin no puede ver ni asignar el rol owner
        return \Spatie\Permission\Models\Role::whereNotIn('name', ['owner'])->pluck('name', 'name');
    })
    ->required()
    ->visible(fn () => auth()->user()->can('users.create') || auth()->user()->can('users.update')),

// status visible para owner y admin; admin puede cambiar status de admin/agente
// (el intento de cambiar status de owner es bloqueado por la Policy en el servicio)
Forms\Components\Select::make('status')
    ->options(\App\Enums\UserStatus::class)
    ->required()
    ->visible(fn () => auth()->user()->can('users.update') || auth()->user()->can('users.create')),
```

> **Principio aplicado:** la visibilidad se basa en el **permiso** (`users.create` / `users.update`), no en el rol. Esto hace que el campo sea visible para owner y admin por igual. La restricción de qué roles puede asignar el admin se aplica en las `options()` del Select (UI) y en `UserPolicy::assignRole()` (dominio).

### 7.3 Table (columnas y acciones)

```php
// Columnas
Tables\Columns\ImageColumn::make('avatar')->circular(),
Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
Tables\Columns\TextColumn::make('email')->searchable(),
Tables\Columns\TextColumn::make('roles.name')->badge(),
Tables\Columns\TextColumn::make('status')
    ->badge()
    ->color(fn ($state) => $state?->color()),
Tables\Columns\TextColumn::make('last_login_at')->dateTime()->sortable()->toggleable(),

// Acciones de fila
Tables\Actions\EditAction::make()
    ->visible(fn ($record) => auth()->user()->can('update', $record)),

Tables\Actions\Action::make('suspend')
    ->label('Suspender')
    ->icon('heroicon-o-lock-closed')
    ->color('danger')
    ->requiresConfirmation()
    ->form([
        Forms\Components\Textarea::make('reason')
            ->label('Motivo de suspensión')
            ->required()
            ->maxLength(500),
    ])
    ->action(fn ($record, array $data) =>
        app(\App\Services\UserSuspensionService::class)
            ->suspend($record, auth()->user(), $data['reason'])
    )
    ->visible(fn ($record) =>
        auth()->user()->can('suspend', $record) && $record->isActive()
    ),

Tables\Actions\Action::make('reactivate')
    ->label('Reactivar')
    ->icon('heroicon-o-lock-open')
    ->color('success')
    ->requiresConfirmation()
    ->action(fn ($record) =>
        app(\App\Services\UserSuspensionService::class)
            ->reactivate($record, auth()->user())
    )
    ->visible(fn ($record) =>
        auth()->user()->can('reactivate', $record) && $record->isSuspended()
    ),

Tables\Actions\DeleteAction::make()
    ->visible(fn ($record) => auth()->user()->can('delete', $record)),
```

### 7.4 Filtros

```php
Tables\Filters\SelectFilter::make('status')
    ->options(\App\Enums\UserStatus::class),

Tables\Filters\SelectFilter::make('roles')
    ->relationship('roles', 'name'),

Tables\Filters\TrashedFilter::make()
    ->visible(fn () => auth()->user()->hasRole('owner')),
```

### 7.5 Validaciones clave

| Campo | Regla |
| :--- | :--- |
| `email` | `required\|email\|unique:users,email,{id}` |
| `password` | `required` en create, `nullable` en update, mínimo 8 caracteres |
| `roles` | `required\|exists:roles,name` + Policy `assignRole` |
| `status` | `required\|in:activo,suspendido` |
| `phone` / `whatsapp` | `nullable\|regex:/^\+?[0-9\s\-()]{7,20}$/` |

### 7.6 Control de acceso en Resource

```php
public static function canViewAny(): bool
{
    return auth()->user()->can('users.view');
}

public static function canCreate(): bool
{
    return auth()->user()->can('users.create');
}
```

---

## 8. RFC-014 — Suspensión y Reactivación

### 8.1 Tabla de auditoría

```php
// database/migrations/xxxx_create_user_suspensions_table.php
Schema::create('user_suspensions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')
        ->constrained('users')
        ->cascadeOnDelete();
    $table->foreignId('suspended_by')
        ->constrained('users');
    $table->string('action');           // cast a SuspensionAction enum
    $table->text('reason')->nullable();
    $table->timestamps();

    $table->index('user_id');
    $table->index(['created_at']);
});
```

> La tabla es **append-only** por diseño. Ningún flujo del sistema hace UPDATE ni DELETE sobre esta tabla. El historial es inmutable.

### 8.2 Máquina de estados (CERRADA)

```
          suspend()              reactivate()
 activo ─────────────► suspendido ─────────────► activo
   ▲                                                │
   └────────────────────────────────────────────────┘
                   (ciclo permitido)

 Restricciones absolutas (Policy):
   - owner no puede ser suspendido por nadie
   - nadie puede suspenderse a sí mismo
   - admin puede suspender a admin y agente
   - owner puede suspender a admin y agente
```

### 8.3 `UserSuspensionService`

```php
// app/Services/UserSuspensionService.php
namespace App\Services;

use App\Enums\SuspensionAction;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserSuspension;
use Illuminate\Auth\Access\AuthorizationException;

class UserSuspensionService
{
    public function suspend(User $target, User $responsible, ?string $reason = null): void
    {
        throw_if(
            $responsible->cannot('suspend', $target),
            AuthorizationException::class,
            'No tiene permisos para suspender este usuario.'
        );

        throw_if(
            $target->isSuspended(),
            \LogicException::class,
            'El usuario ya está suspendido.'
        );

        $target->update(['status' => UserStatus::Suspendido]);

        UserSuspension::create([
            'user_id'      => $target->id,
            'suspended_by' => $responsible->id,
            'action'       => SuspensionAction::Suspendido,
            'reason'       => $reason,
        ]);
    }

    public function reactivate(User $target, User $responsible): void
    {
        throw_if(
            $responsible->cannot('reactivate', $target),
            AuthorizationException::class,
            'No tiene permisos para reactivar este usuario.'
        );

        throw_if(
            $target->isActive(),
            \LogicException::class,
            'El usuario ya está activo.'
        );

        $target->update(['status' => UserStatus::Activo]);

        UserSuspension::create([
            'user_id'      => $target->id,
            'suspended_by' => $responsible->id,
            'action'       => SuspensionAction::Reactivado,
            'reason'       => null,
        ]);
    }
}
```

### 8.4 Bloqueo de login — estrategia de doble defensa

El bloqueo se aplica en dos puntos complementarios. La auditoría validó este enfoque como correcto.

**Punto 1 — Middleware `EnsureUserIsActive` (defensa principal)**

Actúa en cada request autenticado. Cubre el caso en que un usuario ya logueado es suspendido mientras su sesión está activa.

```php
// app/Http/Middleware/EnsureUserIsActive.php
namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if (auth()->check() && auth()->user()->status === UserStatus::Suspendido) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => 'Tu cuenta ha sido suspendida. Contacta al administrador.']);
        }

        return $next($request);
    }
}
```

Registrar en `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);
})
```

**Punto 2 — Listener `InvalidateSuspendedLogin` (defensa secundaria + logging)**

Actúa en el evento `Login`. Invalida la sesión inmediatamente si el usuario que acaba de autenticarse está suspendido. Incluye logging de seguridad para detectar intentos de acceso por usuarios suspendidos.

```php
// app/Listeners/InvalidateSuspendedLogin.php
namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class InvalidateSuspendedLogin
{
    public function handle(Login $event): void
    {
        if ($event->user->isSuspended()) {
            Log::warning('Intento de login por usuario suspendido', [
                'user_id' => $event->user->id,
                'email'   => $event->user->email,
                'ip'      => request()->ip(),
                'agent'   => request()->userAgent(),
            ]);

            auth()->logout();
        }
    }
}
```

Registrar el listener (en `AppServiceProvider::boot()` o `EventServiceProvider`):
```php
\Illuminate\Support\Facades\Event::listen(
    \Illuminate\Auth\Events\Login::class,
    \App\Listeners\InvalidateSuspendedLogin::class,
);
```

### 8.5 Protección del Owner (CERRADA)

La protección reside exclusivamente en la Policy, no en la UI. Cualquier llamada al servicio — desde Filament, Artisan, tests o una futura API — pasa por `UserPolicy::suspend()`, que rechaza sin excepción cualquier intento de suspender a un owner.

```php
// UserPolicy::suspend() — línea que cierra la protección:
if ($target->hasRole('owner')) {
    return false;   // absolutamente nadie puede suspender a un owner
}
```

---

## 9. Modelo de Datos

### 9.1 Alteración de `users`

```
users (estado final post-Épica 2)
├── id                  bigserial PK
├── name                varchar(255) NOT NULL
├── email               varchar(255) UNIQUE NOT NULL
├── email_verified_at   timestamp NULL
├── password            varchar(255) NOT NULL
├── remember_token      varchar(100) NULL
├── phone               varchar(255) NULL          ← Épica 2
├── whatsapp            varchar(255) NULL          ← Épica 2
├── avatar              varchar(255) NULL          ← Épica 2
├── status              varchar(255) DEFAULT 'activo'  ← Épica 2
├── last_login_at       timestamp NULL             ← Épica 2
├── created_at          timestamp
├── updated_at          timestamp
└── deleted_at          timestamp NULL             ← Épica 2 (soft delete)
```

### 9.2 Nueva tabla `user_suspensions`

```
user_suspensions
├── id              bigserial PK
├── user_id         bigint FK → users.id CASCADE DELETE
├── suspended_by    bigint FK → users.id
├── action          varchar(255)   -- cast SuspensionAction enum
├── reason          text NULL
├── created_at      timestamp
└── updated_at      timestamp

Índices:
  idx_user_suspensions_user_id   ON (user_id)
  idx_user_suspensions_created   ON (created_at DESC)
```

### 9.3 Relaciones del modelo User

```
User ──(hasRoles)──► roles               [Spatie — Épica 1 — activo]
     ──(hasMany)──► user_suspensions     [Épica 2 — activo]
     ──(hasMany)──► properties           [Épica 3 — DIFERIDO]
     ──(hasMany)──► leads                [Épica 4 — DIFERIDO]
```

---

## 10. Seguridad — Mapa de controles

| Control | Capa | Mecanismo |
| :--- | :--- | :--- |
| Bloqueo login suspendido (sesión activa) | Middleware | `EnsureUserIsActive` en grupo `web` |
| Bloqueo login suspendido (nuevo intento) | Listener | `InvalidateSuspendedLogin` + log de seguridad |
| Ver usuarios | Policy | `UserPolicy::viewAny()` → permiso `users.view` |
| Crear usuarios | Policy | `UserPolicy::create()` → permiso `users.create` |
| Editar usuario | Policy | `UserPolicy::update()` + regla admin-no-owner |
| Asignar rol `owner` | Policy + UI | `UserPolicy::assignRole()` + opciones filtradas en Select |
| Soft-delete | Policy | `UserPolicy::delete()` → solo owner |
| Restaurar | Policy | `UserPolicy::restore()` → solo owner |
| Suspender | Service + Policy | `UserSuspensionService` llama a `cannot()` antes de actuar |
| Reactivar | Service + Policy | `UserSuspensionService` llama a `cannot()` antes de actuar |
| Protección owner | Policy | `UserPolicy::suspend()` rechaza incondicionalmente |
| Resource Filament visible | Resource | `canViewAny()` → permiso `users.view` |

**Principio rector:** La Policy es la única fuente de verdad para autorización. La UI de Filament la consume, no la reemplaza.

---

## 11. Estrategia de Testing

### 11.1 Tests unitarios — Modelo y Enums

```php
// tests/Unit/UserStatusTest.php
it('casts status to UserStatus enum correctly')
it('isActive returns true when status is activo')
it('isSuspended returns true when status is suspendido')
it('soft delete sets deleted_at and hides from default queries')
it('scopeActive excludes suspended users')
it('scopeSuspended excludes active users')

// tests/Unit/SuspensionActionTest.php
it('SuspensionAction enum has suspendido and reactivado cases')
it('UserSuspension casts action to SuspensionAction enum')
```

### 11.2 Tests de política — UserPolicy

```php
// tests/Unit/UserPolicyTest.php
it('owner can view any user')
it('admin can view any user')
it('agente cannot view users')
it('owner can delete user')
it('admin cannot delete user')
it('admin cannot update owner')
it('nobody can suspend an owner')
it('admin can suspend an agente')
it('owner can suspend an admin')
it('nobody can self-suspend')
it('owner can assign any role')
it('admin cannot assign owner role')
it('admin can assign admin role')
it('admin can assign agente role')
```

### 11.3 Tests de Feature — CRUD

```php
// tests/Feature/UserCrudTest.php
it('owner can create a user with any role')
it('admin can create a user with admin or agente role')
it('admin cannot create a user with owner role')
it('agente cannot access user list')
it('owner can soft delete a user')
it('admin cannot soft delete a user')
it('owner can restore a soft-deleted user')
it('form shows status field to admin')
it('form shows all roles to owner')
it('form hides owner role from admin')
```

### 11.4 Tests de Feature — Suspensión

```php
// tests/Feature/UserSuspensionTest.php
it('suspending a user records SuspensionAction::Suspendido in user_suspensions')
it('reactivating a user records SuspensionAction::Reactivado in user_suspensions')
it('suspended user cannot login')
it('middleware invalidates session of suspended user on next request')
it('listener logs warning when suspended user attempts login')
it('admin cannot suspend an owner via service')
it('service throws AuthorizationException when admin tries to suspend owner')
it('suspension history is append-only: no updates or deletes')
```

### 11.5 Tests de regresión — Épica 1

```php
// tests/Feature/Regression/Epica1RegressionTest.php
it('filament panel loads at /admin')
it('roles owner admin agente exist in database')
it('media table exists')
it('postgis extension is active')
```

### 11.6 Configuración de tests

Tests de Feature usan `RefreshDatabase` sobre `inmo_test` (PostgreSQL — `phpunit.xml` de Épica 1). Sin SQLite in-memory. El `UserFactory` debe extenderse con los nuevos campos:

```php
// database/factories/UserFactory.php — extensión
public function definition(): array
{
    return [
        // campos existentes...
        'phone'         => fake()->phoneNumber(),
        'whatsapp'      => fake()->phoneNumber(),
        'status'        => \App\Enums\UserStatus::Activo,
        'last_login_at' => null,
    ];
}

public function suspended(): static
{
    return $this->state(['status' => \App\Enums\UserStatus::Suspendido]);
}
```

---

## 12. Riesgos Técnicos

| # | Riesgo | Probabilidad | Impacto | Mitigación |
| :--- | :--- | :---: | :---: | :--- |
| R-1 | Caché Spatie Permission desactualizado | Alta | Medio | `forgetCachedPermissions()` al inicio Y al final del `PermissionSeeder` |
| R-2 | Soft delete + cascade en tablas futuras | Media | Alto | Épicas 3/4 usarán `nullOnDelete` en FK hacia `users`. `cascadeOnDelete` solo en `user_suspensions` (historial ligado al user) |
| R-3 | Filament BulkActions sin Policy | Media | Alto | Cada `BulkAction` debe llamar `authorize()` explícito |
| R-4 | Owner único en producción | Baja | Crítico | La Policy lo protege. Verificar en deploy inicial que exista al menos 1 owner activo antes de salir a producción |
| R-5 | Middleware no cubre rutas API futuras | Media | Medio | En Épica 8 (API), aplicar `EnsureUserIsActive` al grupo `api` |
| R-6 | Contratos diferidos `1=0` olvidados | Baja | Medio | Los métodos comentados en el modelo son la señal para las Épicas 3/4. Incluir en checklist de esas épicas |

---

## 13. Criterios de Aceptación (QA-011 → QA-020)

### Criterios originales (QA-011 → QA-017)

| ID | Caso | Verificación |
| :--- | :--- | :--- |
| QA-011 | Crear usuario | Owner/admin crea usuario con rol → aparece en listado con datos correctos |
| QA-012 | Asignar rol | Owner cambia rol de agente a admin → permisos actualizados inmediatamente |
| QA-013 | Editar usuario | Admin edita teléfono de agente → cambio persiste; admin no puede editar owner |
| QA-014 | Suspender usuario | Admin suspende agente con motivo → status = suspendido, registro en `user_suspensions` con `SuspensionAction::Suspendido` |
| QA-015 | Reactivar usuario | Owner reactiva usuario suspendido → status = activo, nuevo registro con `SuspensionAction::Reactivado` |
| QA-016 | Validar permisos | Agente intenta acceder a `/admin/users` → 403, sin acceso |
| QA-017 | Bloqueo de acceso | Usuario suspendido intenta login → error "cuenta suspendida", sin sesión activa |

### Criterios adicionales de arquitectura (QA-018 → QA-020)

| ID | Caso | Verificación |
| :--- | :--- | :--- |
| QA-018 | Protección de owner | Admin intenta suspender owner: botón no visible en UI; llamada directa al servicio → `AuthorizationException` |
| QA-019 | Soft delete y restauración | Owner elimina usuario → no aparece en listado; owner restaura → vuelve a aparecer con datos intactos |
| QA-020 | Historial inmutable | Auditoría: verificar que no existe ningún UPDATE/DELETE sobre `user_suspensions` en ningún flujo de la aplicación |

### Criterio adicional post-auditoría (QA-021)

| ID | Caso | Verificación |
| :--- | :--- | :--- |
| QA-021 | Admin no puede asignar rol owner | Admin intenta crear/editar usuario y seleccionar rol `owner` → la opción no existe en el Select; intento por servicio → Policy rechaza |

---

## 14. Plan de Implementación por Lotes (A → E)

Los lotes son **estrictamente incrementales**: cada lote tiene su DoD antes de comenzar el siguiente.

```
Lote A → Lote B → Lote C → Lote D → Lote E
 Modelo    Roles    CRUD    Suspensión  Tests
```

---

### Lote A — Modelo y Migraciones

**Archivos:**
1. `app/Enums/UserStatus.php`
2. `app/Enums/SuspensionAction.php`
3. `database/migrations/xxxx_add_agent_fields_to_users_table.php`
4. `database/migrations/xxxx_create_user_suspensions_table.php`
5. `app/Models/User.php` — agregar `SoftDeletes`, fillable, casts, scopes, relaciones, contratos diferidos
6. `app/Models/UserSuspension.php`
7. `database/factories/UserFactory.php` — extender con nuevos campos

**Verificación:**
```bash
php artisan migrate
php artisan tinker --execute="echo App\Models\User::first()->status->label();"
# Debe imprimir: Activo
php artisan tinker --execute="echo App\Models\User::withTrashed()->count();"
# Debe imprimir: número de usuarios (soft delete activo)
```

**DoD:** Migraciones ejecutadas sin error. `User::first()->status` devuelve `UserStatus::Activo`. `User::withTrashed()` no lanza excepción. `UserSuspension::create([...])` persiste correctamente.

---

### Lote B — Roles, Permisos y Policy

**Archivos:**
1. `database/seeders/PermissionSeeder.php`
2. `app/Policies/UserPolicy.php`
3. `app/Providers/AppServiceProvider.php` — registro `Gate::policy`
4. `database/seeders/DatabaseSeeder.php` — llamar `PermissionSeeder`

**Verificación:**
```bash
php artisan db:seed --class=PermissionSeeder
php artisan tinker --execute="
\$owner = App\Models\User::first()->assignRole('owner');
echo \$owner->can('users.delete') ? 'owner:delete=OK' : 'FAIL'; echo PHP_EOL;
echo \$owner->can('suspend', \$owner) ? 'FAIL' : 'owner:self-suspend=blocked:OK'; echo PHP_EOL;
"
```

**DoD:** 7 permisos en BD. Cada rol tiene exactamente los permisos de la matriz. `UserPolicy::suspend()` rechaza suspender a un owner. `UserPolicy::assignRole()` rechaza que admin asigne rol `owner`.

---

### Lote C — UserResource en Filament

**Archivos:**
1. `app/Filament/Resources/UserResource.php`
2. `app/Filament/Resources/UserResource/Pages/ListUsers.php`
3. `app/Filament/Resources/UserResource/Pages/CreateUser.php`
4. `app/Filament/Resources/UserResource/Pages/EditUser.php`

**Puntos críticos (correcciones de auditoría aplicadas):**
- Campo `roles`: visible para owner y admin; opciones filtradas — admin no ve ni puede asignar `owner`.
- Campo `status`: visible para owner y admin.
- Acciones de suspensión/reactivación: controladas por Policy vía `can()`.
- Soft delete: `DeleteAction` visible solo para owner.
- `TrashedFilter`: visible solo para owner.

**Verificación:**
```bash
php artisan serve
# Como owner: crear usuario con rol owner → funciona
# Como admin: crear usuario con rol admin → funciona; opción owner no aparece
# Como agente: intentar /admin/users → 403
```

**DoD:** CRUD completo funcional. Rol `owner` no aparece en Select para admin. Filtros operativos. Badges de status con color correcto.

---

### Lote D — Suspensión, Auditoría y Middleware

**Archivos:**
1. `app/Services/UserSuspensionService.php`
2. `app/Http/Middleware/EnsureUserIsActive.php`
3. `app/Listeners/InvalidateSuspendedLogin.php`
4. `bootstrap/app.php` — registrar middleware en grupo `web`
5. `app/Providers/AppServiceProvider.php` — registrar listener `Login`

**Verificación:**
```bash
# 1. Crear usuario agente de prueba
# 2. Suspenderlo desde Filament (Admin lo suspende)
# 3. Intentar login con ese usuario
# Resultado: redirección al login con "cuenta suspendida"
# 4. Verificar log de seguridad:
tail -f storage/logs/laravel.log | grep "suspendido"
# 5. Verificar historial:
php artisan tinker --execute="
echo App\Models\UserSuspension::latest()->first()->action->label();
# Debe imprimir: Suspendido
"
```

**DoD:** Usuario suspendido no puede hacer login. Middleware invalida sesión activa. Log de seguridad registra el intento. Historial en `user_suspensions` con enum correcto. Admin no puede suspender owner.

---

### Lote E — Tests y Cierre

**Archivos:**
1. `tests/Unit/UserStatusTest.php`
2. `tests/Unit/SuspensionActionTest.php`
3. `tests/Unit/UserPolicyTest.php`
4. `tests/Feature/UserCrudTest.php`
5. `tests/Feature/UserSuspensionTest.php`
6. `tests/Feature/Regression/Epica1RegressionTest.php`

**Verificación:**
```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
# Resultado esperado: todos en verde, sin regresiones de Épica 1
```

**DoD:** Suite completa en verde. QA-011 → QA-021 cubiertos por tests automatizados. Sebastián valida los casos manuales.

---

## 15. Checklist de Cierre Técnico

| Elemento | Estado |
| :--- | :--- |
| Enum `UserStatus` creado y casteado en modelo | Pendiente |
| Enum `SuspensionAction` creado y casteado en `UserSuspension` | Pendiente |
| Migración ALTER users ejecutada | Pendiente |
| Migración CREATE user_suspensions ejecutada (con índices) | Pendiente |
| Modelo `User` extendido (SoftDeletes, casts, scopes, contratos diferidos) | Pendiente |
| Modelo `UserSuspension` creado | Pendiente |
| `UserFactory` extendida con nuevos campos + estado `suspended()` | Pendiente |
| `PermissionSeeder` con `forgetCachedPermissions()` al inicio y al final | Pendiente |
| `UserPolicy` registrada en Gate con regla `assignRole` | Pendiente |
| `UserResource` operativo: campos `roles` y `status` visibles para owner y admin | Pendiente |
| `UserResource`: admin no ve ni puede asignar rol `owner` | Pendiente |
| Acciones de suspensión/reactivación en Filament controladas por Policy | Pendiente |
| `UserSuspensionService` con validaciones de negocio y enum | Pendiente |
| `EnsureUserIsActive` middleware registrado en grupo `web` | Pendiente |
| `InvalidateSuspendedLogin` listener registrado con logging | Pendiente |
| Login de usuario suspendido bloqueado y verificado manualmente | Pendiente |
| Admin no puede suspender a owner — verificado por test y manualmente | Pendiente |
| Suite de tests completa en verde | Pendiente |
| Tests de regresión Épica 1 en verde | Pendiente |
| QA-011 → QA-021 validados por Sebastián | Pendiente |

---

## 16. Decisiones Diferidas / Fuera de Alcance

Estas decisiones están documentadas pero **no se resuelven en esta épica**. Requieren input del cliente o se activan en épicas posteriores.

| # | Tema | Estado | Épica destino |
| :--- | :--- | :--- | :--- |
| D-1 | ¿Puede un admin crear otro admin? | **Diferido** — actualmente sí, según la matriz. Confirmar con Kristian si debe restringirse. | Épica 2 revisión o Épica 8 |
| D-2 | Avatar con Spatie MediaLibrary vs campo string | **Diferido** — campo string en esta épica. Migrar a MediaLibrary cuando se requieran conversiones de imagen. | Épica 4 |
| D-3 | RelationManager de suspensiones en `EditUser` vs página separada | **Diferido** — se recomienda RelationManager. Implementar al desarrollar Lote C. | Lote C |
| D-4 | Contratos diferidos `properties`/`leads` en User | **Diferido** — código temporal, activar al crear las tablas respectivas. | Épicas 3 y 4 |
| D-5 | Middleware `EnsureUserIsActive` en grupo API | **Diferido** — fuera de alcance hasta que exista capa API. | Épica 8 |

---

## 17. Registro de Cambios desde la Auditoría

Los siguientes cambios fueron aplicados al documento tras la auditoría de Gemini CLI (16/06/2026):

| # | Hallazgo auditoría | Cambio aplicado |
| :--- | :--- | :--- |
| 3.1 (medio) | Campos `roles` y `status` con `visible(owner)` bloqueaban al admin | Visibilidad cambiada a `can('users.create') \|\| can('users.update')` — visible para owner y admin |
| 3.1 + Rec.8.3 | Admin podía ver/asignar rol `owner` en el Select | Opciones del Select filtradas dinámicamente; admin no ve ni puede asignar `owner`. Método `UserPolicy::assignRole()` agregado |
| 4.1 (menor) | Campo `action` en `user_suspensions` era string sin tipo | Creado enum `SuspensionAction`, casteado en `UserSuspension`, usado en `UserSuspensionService` |
| Rec.8.1 | `PermissionSeeder` sin garantía de caché limpio | `forgetCachedPermissions()` agregado al inicio **y** al final del seeder |
| Rec.9.1 | Listener sin logging de seguridad | `InvalidateSuspendedLogin` ahora registra `Log::warning()` con user_id, email, IP y user-agent |
| — | Pregunta abierta: ¿admin crea admins? | Documentada como Decisión Diferida D-1. No modifica la matriz de permisos actual |

**Hallazgos no aplicados:**

| # | Hallazgo | Razón |
| :--- | :--- | :--- |
| 4.2 Redundancia middleware + listener | Middleware es suficiente | Se mantienen ambos: el listener agrega logging de seguridad que es valioso en producción. La "redundancia" es intencionada (defensa en profundidad) |
| Sobreingeniería contratos diferidos `1=0` | Eliminar | Se mantienen como contratos diferidos documentados. Son la solución correcta para no inventar tablas de épicas futuras. Documentados con comentario de activación |

---

## 18. Cierre Técnico del Diseño

**Fecha de cierre:** 16 de Junio, 2026  
**Basado en auditoría:** `docs/audits/epica-2-auditoria-diseno.md` — Gemini CLI  

### Confirmaciones de arquitectura

| Punto | Confirmado |
| :--- | :--- |
| User extendido (no recreado) — migración ALTER sobre tabla existente | ✅ |
| Roles/permisos base: owner, admin, agente con matriz cerrada | ✅ |
| Autorización en Policies (no solo UI) — Policy es fuente única de verdad | ✅ |
| Soft delete vía `SoftDeletes` trait + campo `deleted_at` | ✅ |
| Bloqueo de login de usuario suspendido (middleware + listener) | ✅ |
| Auditoría completa en `user_suspensions` (append-only, enum tipado) | ✅ |
| Protección de Owner a nivel de Policy — ningún rol puede suspenderlo | ✅ |
| Relaciones `properties`/`leads` como contratos diferidos | ✅ |
| Criterios de aceptación verificables QA-011→QA-021 | ✅ |
| Plan por lotes A→E estrictamente incremental | ✅ |

### Veredicto

> **APROBADO PARA IMPLEMENTACIÓN**
>
> El diseño es técnicamente sólido, consume correctamente los contratos de la Épica 1 y aplica las observaciones válidas de la auditoría. Los hallazgos críticos son inexistentes. Los hallazgos medios y menores han sido resueltos. Las decisiones diferidas están documentadas con su épica destino. El plan por lotes garantiza implementación incremental y verificable.
>
> Se puede iniciar el **Lote A** inmediatamente.

---

*Documento revisado y cerrado el 16 de Junio, 2026*  
*Rama de destino: `feature/epica-2-usuarios-y-seguridad` desde `develop`*

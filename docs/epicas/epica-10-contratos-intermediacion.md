# Épica 10 — Contratos de Intermediación — Diseño Técnico

**Proyecto:** New Hauz — Plataforma Inmobiliaria (monolito Laravel)
**Épica:** Épica 10 — Contratos de Intermediación (RFC-063 → RFC-070)
**Rama base:** `develop` · **Rama de trabajo:** `feature/epica-10-contratos-intermediacion`
**Arquitecto:** Claude · **Auditor / Cierre técnico:** Codex
**Estado:** ✅ APROBADO PARA IMPLEMENTACIÓN — correcciones de auditoría aplicadas (Prompt 3)

> Este documento es el diseño técnico que se implementará en el Prompt 4. Fue
> auditado por Codex (`docs/audits/epica-10-auditoria-diseno.md`, veredicto
> inicial: Rechazado hasta corregir críticos) y corregido en este Prompt 3. Los
> hallazgos aplicados se listan en la sección **Registro de Cambios desde la
> Auditoría** y el veredicto final está en **Cierre Técnico del Diseño**. Los
> snippets son de referencia idiomática, confirmados contra los patrones reales
> del repositorio.

---

## 1. Contexto y Dependencias

La Épica 10 digitaliza el contrato de intermediación (autorización de promoción/venta/renta): el agente lo genera desde Filament, el sistema emite folio + QR/enlace, el cliente lo abre sin login, completa datos, revisa el clausulado y firma o rechaza. El documento firmado queda con sello digital, hash de integridad y expediente trazable.

Todos los contratos previos fueron **verificados en el código real** (no asumidos):

| Contrato previo | Origen | Estado real verificado | Cómo lo consume la Épica 10 |
| :---- | :---- | :---- | :---- |
| Modelo `User` con `phone`, `whatsapp`, `isActive()`, `hasRole()` | RFC-011 | ✅ `app/Models/User.php:26-52` — campos `phone`/`whatsapp` fillables, `isActive()`, trait `HasRoles` | `belongsTo` agente; datos de contacto del agente para el envío |
| Roles y permisos (Spatie) | RFC-006/012 | ✅ `database/seeders/PermissionSeeder.php` — convención `modulo.accion`, roles `owner/admin/agente` | Se añaden 3 permisos nuevos (ver §14) |
| Media Library ^11.23 | RFC-007 | ✅ instalado; patrón `addMediaFromString()->toMediaCollection()` en `LonaBatchApprovalService` | Colecciones `identificacion` (owner-only) y `documento-final` (PDF) |
| `barryvdh/laravel-dompdf` ^3.1 | — | ✅ instalado; patrón `Pdf::loadView()` en `PropertyPdfController` y Lonas | PDF final del contrato |
| `endroid/qr-code` ^6.0 | — | ✅ **ya instalado** (usado por RFC-062 Lonas); patrón `Builder(...)->build()->getDataUri()` | QR del enlace + mini-QR del sello |
| Modelo `Property` (catálogo) | RFC-019 | ✅ existe | **NO se referencia** — ver Regla de Oro abajo |
| Tracking WhatsApp | RFC-044 | ⚠️ **solo click-tracking** ("Medir interacciones"), **NO es un canal de envío** | Ver §11 y Riesgo R-6: el envío automático por WhatsApp **no tiene infraestructura** |
| Notificaciones avanzadas | RFC-053 | ⚠️ parcial — patrón `Notification` con canales `['database','mail']` (`LonaRequest*Notification`) | Notificaciones por `mail` + `database`; WhatsApp diferido |
| Automatizaciones | RFC-054 | ✅ patrón `Schedule::command()` en `routes/console.php` | 3 comandos programados (§11) |
| Auditoría y trazabilidad | RFC-057 | ⚠️ **stub** ("Trazabilidad implementada"); no hay paquete global — patrón real: tablas log por-entidad (`user_status_logs`, `lead_assignment_logs`) | Tabla `contrato_eventos` por-entidad, anticipando RFC-057 |
| Preparación multisucursal | RFC-058 | 📄 documento | El folio es único **global**, no por sucursal |

### Regla de Oro — extensión ADITIVA

> El contrato de intermediación es una entidad **independiente** del catálogo de
> Property. **No** lleva FK a `properties`; los datos del inmueble a promover
> (tipo, operación, dirección, comisión) son **columnas propias** del contrato.
> No se modifica ninguna migración existente de `users`, `properties`, `zones` ni
> `media`. Firmar un contrato **no** crea un registro en el catálogo.

---

## 2. Objetivos

### Qué entrega la Épica 10
- Entidad `ContratoIntermediacion` con 8 estados y máquina de transiciones validada.
- Folio único global de 8 caracteres + token de acceso de un solo uso, con expiración de 72h.
- QR/enlace público y vista imprimible desde el panel interno.
- Formulario interno (Filament) para que el agente genere el contrato.
- Formulario público mobile-first (sin login) con clausulado dinámico y aviso de privacidad.
- Firma electrónica simple en canvas + evidencia reforzada (IP, user-agent, timestamp de servidor, hash).
- PDF final con sello digital SVG, hash SHA-256 y vista pública de verificación por folio.
- Notificaciones por email + registro en base de datos; jobs de expiración, vencimiento y retención de 2 años.
- Panel de seguimiento con filtros y control de acceso a la identificación oficial (solo Owner).

### Qué NO entrega (fuera de alcance fase 1)
- ❌ Firma electrónica avanzada certificada **NOM-151** (Mifiel, Weetrust, DocuSign). Es firma simple con evidencia; queda como fase futura.
- ❌ **Envío automático por WhatsApp Business API** (Meta Cloud API / Twilio). RFC-044 es solo tracking; no hay proveedor de envío configurado. Fase 1 entrega enlace `wa.me` asistido por el agente (ver §11, R-6, §20).
- ❌ Cobro de comisiones / módulo financiero.
- ❌ Alta automática del inmueble en el catálogo público a partir del contrato.
- ❌ OTP / verificación adicional del firmante (decisión cerrada en EPICA-10: el token es la única barrera).

---

## 3. Alcance Funcional (funcionalidad ↔ actor)

| Funcionalidad | Agente | Admin | Owner | Cliente | Sistema |
| :---- | :---: | :---: | :---: | :---: | :---: |
| Generar contrato y capturar datos previos | ✅ | ✅ | ✅ | — | — |
| Enviar / reenviar enlace (email + wa.me) | ✅ | ✅ | ✅ | — | dispara |
| Cancelar contrato (antes de firma) | ❌ | ✅ | ✅ | — | — |
| Ver listado de contratos | solo propios | todos | todos | — | — |
| Ver identificación oficial adjunta | ❌ | ❌ | ✅ | — | — |
| Descargar PDF firmado | ✅ propios | ✅ | ✅ | recibe copia | genera |
| Abrir formulario, completar datos, firmar/rechazar | — | — | — | ✅ (token) | registra |
| Generar folio, QR, token; calcular hash; sellar PDF | — | — | — | — | ✅ |
| Marcar Expirado / Vencido; mover a eliminación pendiente | — | — | confirma borrado | — | ✅ (jobs) |
| Verificar integridad de un PDF por folio | público | público | público | público | compara hash |

---

## 4. Alcance Técnico (árbol de archivos a crear)

```
app/
  Enums/
    EstadoContrato.php                         (nuevo — 8 estados + transicionarA)
    TipoOperacionContrato.php                  (nuevo — venta/renta/renta_opcion_compra)
    OrigenAccesoContrato.php                   (nuevo — inicial/reenvio — Mn-3)
  Models/
    ContratoIntermediacion.php                 (nuevo)
    ContratoAcceso.php                         (nuevo — tokens)
    ContratoFirmaEvidencia.php                 (nuevo)
    ContratoEvento.php                         (nuevo — auditoría per-entidad)
  Policies/
    ContratoIntermediacionPolicy.php           (nueva)
  Services/
    Contratos/
      FolioGenerator.php                       (nuevo — folio único global)
      ContratoCreacionService.php              (nuevo — folio pre-insert + retry unique — C-3)
      ContratoEstadoService.php                (nuevo — API única de transición — M-1)
      ContratoEventoService.php                (nuevo — resuelve contexto HTTP/CLI — Mn-2)
      ContratoAccesoService.php                (nuevo — emitir/validar/invalidar token)
      ContratoFirmaService.php                 (nuevo — firma + evidencia + estado)
      ContratoPdfService.php                   (nuevo — PDF + sello + hash)
      ContratoEnvioService.php                 (nuevo — email + wa.me link)
  Http/
    Controllers/
      Public/
        ContratoPublicoController.php          (nuevo — form público por token)
        ContratoFirmaController.php            (nuevo — recibe firma/rechazo)
        ContratoVerificacionController.php     (nuevo — /verificar/{folio})
      ContratoQrController.php                 (nuevo — QR/imprimible interno)
      ContratoMediaController.php              (nuevo — stream autorizado de media privada — M-3/C-2)
  Filament/
    Resources/
      ContratoIntermediacionResource.php       (nuevo — panel + form interno)
      ContratoIntermediacionResource/Pages/    (List, Create, View)
      ContratoIntermediacionResource/RelationManagers/
        EventosRelationManager.php             (historial de auditoría)
  Console/
    Commands/
      ContratosExpirarCommand.php              (nuevo)
      ContratosVencerCommand.php               (nuevo)
      ContratosRetencionCommand.php            (nuevo)
  Notifications/
    ContratoEnlaceEnviado.php                  (nuevo — al cliente)
    ContratoRecordatorioFirma.php              (nuevo — al cliente)
    ContratoFirmado.php                        (nuevo — al agente + copia cliente)
    ContratoRechazado.php                      (nuevo — al agente)
    ContratoPorExpirar.php                     (nuevo — al agente)
    ContratoRetencionPendiente.php             (nuevo — al owner)
database/
  migrations/
    xxxx_create_contratos_intermediacion_table.php
    xxxx_create_contrato_accesos_table.php
    xxxx_create_contrato_firma_evidencias_table.php
    xxxx_create_contrato_eventos_table.php
  factories/
    ContratoIntermediacionFactory.php
resources/
  views/
    public/contratos/
      show.blade.php                           (form público mobile-first)
      firmar.blade.php                         (canvas de firma)
      invalido.blade.php                       (token expirado/usado)
      verificar.blade.php                      (verificación por folio)
    pdf/
      contrato-intermediacion.blade.php        (plantilla PDF + clausulado dinámico)
    filament/
      contrato-qr-imprimible.blade.php         (vista imprimible interna)
  js/
    contrato-firma.js                          (canvas vanilla, sin librerías nuevas)
routes/
  web.php                                       (rutas públicas — aditivo)
  console.php                                   (3 Schedule::command — aditivo)
```

---

## 5. RFC-063 — Modelo `ContratoIntermediacion`

### 5.1 Decisiones de arquitectura cerradas

| # | Decisión | Justificación |
| :-- | :---- | :---- |
| D-1 | Enum **nuevo** `TipoOperacionContrato`, NO reutilizar `OperationType` | `OperationType` solo tiene `Venta`/`Renta` (`app/Enums/OperationType.php`). El contrato requiere además `renta con opción a compra`. Extender `OperationType` rompería Lonas y Property (regla aditiva). Enum propio y aislado. |
| D-2 | Datos del inmueble como **columnas del contrato**, sin FK a `properties` | Regla de Oro / decisión 3 de EPICA-10. El contrato no depende del catálogo. |
| D-3 | Tokens en tabla aparte `contrato_accesos` (no columna en el contrato) | El folio es permanente; el token es efímero, renovable y con historial (RFC-064). Un `hasMany` modela reenvíos. |
| D-4 | Evidencia de firma en tabla `hasOne` `contrato_firma_evidencias` | Separa datos sensibles de la firma del registro principal; un contrato tiene a lo sumo una firma. |
| D-5 | Auditoría en tabla per-entidad `contrato_eventos` | El repo NO tiene paquete de auditoría global; patrón real = tablas log por-entidad (`lead_assignment_logs`). Anticipa RFC-057 sin bloquearse en él. |
| D-6 | `softDeletes` en el contrato | La retención de 2 años + confirmación de borrado por Owner exige borrado lógico previo al físico. |
| D-7 | Identificación oficial y PDF en Media Library, no columnas | Reutiliza RFC-007; colecciones separadas con visibilidad distinta. |

### 5.2 Enums de dominio

```php
// app/Enums/TipoOperacionContrato.php
enum TipoOperacionContrato: string
{
    case Venta = 'venta';
    case Renta = 'renta';
    case RentaOpcionCompra = 'renta_opcion_compra';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Renta => 'Renta',
            self::RentaOpcionCompra => 'Renta con opción a compra',
        };
    }
}
```

```php
// app/Enums/EstadoContrato.php
enum EstadoContrato: string
{
    case Generado  = 'generado';
    case Enviado   = 'enviado';
    case Leido     = 'leido';
    case Firmado   = 'firmado';
    case Rechazado = 'rechazado';
    case Expirado  = 'expirado';
    case Cancelado = 'cancelado';
    case Vencido   = 'vencido';

    public function label(): string
    {
        return match ($this) {
            self::Generado  => 'Generado',
            self::Enviado   => 'Enviado',
            self::Leido     => 'Leído / Visto',
            self::Firmado   => 'Firmado',
            self::Rechazado => 'Rechazado',
            self::Expirado  => 'Expirado',
            self::Cancelado => 'Cancelado',
            self::Vencido   => 'Vencido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Generado  => 'gray',
            self::Enviado   => 'info',
            self::Leido     => 'warning',
            self::Firmado   => 'success',
            self::Rechazado => 'danger',
            self::Expirado  => 'danger',
            self::Cancelado => 'gray',
            self::Vencido   => 'gray',
        };
    }

    /**
     * "Terminal de negocio": el contrato ya no admite cancelación ni reenvío.
     * OJO (Mn-1): NO significa "estado terminal del flujo público" — Rechazado
     * y Expirado están fuera de esta lista justamente porque SÍ admiten reenvío.
     */
    public function esTerminal(): bool
    {
        return in_array($this, [self::Firmado, self::Cancelado, self::Vencido], true);
    }

    /**
     * Transiciones válidas. Fuente única de la máquina de estados.
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Generado  => [self::Enviado, self::Cancelado],
            self::Enviado   => [self::Leido, self::Firmado, self::Rechazado, self::Expirado, self::Cancelado],
            self::Leido     => [self::Firmado, self::Rechazado, self::Expirado, self::Cancelado],
            self::Rechazado => [self::Enviado],  // reenvío, mismo folio
            self::Expirado  => [self::Enviado],  // reenvío, mismo folio
            self::Firmado   => [self::Vencido],  // solo por fin de vigencia
            self::Cancelado => [],
            self::Vencido   => [],
        };
    }

    public function puedeTransicionarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), true);
    }
}
```

### 5.3 Migración `contratos_intermediacion`

```php
Schema::create('contratos_intermediacion', function (Blueprint $table) {
    $table->id();

    // Identificación
    $table->string('folio', 8)->unique();                 // único GLOBAL (RFC-058)
    $table->string('estado')->default(EstadoContrato::Generado->value)->index();

    // Cliente (datos propios del contrato)
    $table->string('cliente_nombre');
    $table->string('cliente_telefono')->nullable();
    $table->string('cliente_email')->nullable();
    $table->string('cliente_direccion')->nullable();
    // Identificación oficial: NO columna — va a Media Library en disco privado
    // (colecciones 'identificacion-anverso' e 'identificacion-reverso', ver §5.4)

    // Inmueble a promover (SIN FK a properties — D-2)
    $table->string('inmueble_tipo');
    $table->string('tipo_operacion');                     // TipoOperacionContrato
    $table->string('inmueble_direccion');
    $table->decimal('comision_porcentaje', 5, 2);

    // Condiciones
    $table->date('vigencia_inicio')->nullable();
    $table->date('vigencia_fin')->nullable();
    $table->boolean('exclusividad')->default(false);
    $table->string('plantilla_version')->default('v1');

    // Trazabilidad
    $table->foreignId('agente_id')->constrained('users');
    $table->timestamp('enviado_at')->nullable();
    $table->timestamp('leido_at')->nullable();
    $table->timestamp('firmado_at')->nullable();
    $table->timestamp('rechazado_at')->nullable();
    $table->timestamp('cancelado_at')->nullable();
    $table->timestamp('expirado_at')->nullable();
    $table->timestamp('vencido_at')->nullable();
    $table->text('motivo_rechazo')->nullable();

    // Documento final (RFC-068)
    $table->string('documento_hash', 64)->nullable();     // SHA-256 hex del PDF final
    $table->timestamp('retencion_revisar_at')->nullable(); // firmado_at + 2 años
    $table->boolean('eliminacion_pendiente')->default(false)->index();

    $table->timestamps();
    $table->softDeletes();

    $table->index('agente_id');
    $table->index('tipo_operacion');
});
```

> **Nota (cliente contacto):** al menos uno de `cliente_telefono` / `cliente_email`
> es obligatorio a nivel de aplicación (regla de RFC-065), no a nivel de columna,
> porque ambos son individualmente nullable.

### 5.4 Modelo `ContratoIntermediacion`

```php
class ContratoIntermediacion extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $table = 'contratos_intermediacion';

    protected $fillable = [
        'folio', 'estado',
        'cliente_nombre', 'cliente_telefono', 'cliente_email', 'cliente_direccion',
        'inmueble_tipo', 'tipo_operacion', 'inmueble_direccion', 'comision_porcentaje',
        'vigencia_inicio', 'vigencia_fin', 'exclusividad', 'plantilla_version',
        'agente_id', 'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoContrato::class,
            'tipo_operacion' => TipoOperacionContrato::class,
            'exclusividad' => 'boolean',
            'comision_porcentaje' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fin' => 'date',
            'enviado_at' => 'datetime', 'leido_at' => 'datetime',
            'firmado_at' => 'datetime', 'rechazado_at' => 'datetime',
            'cancelado_at' => 'datetime', 'expirado_at' => 'datetime',
            'vencido_at' => 'datetime', 'retencion_revisar_at' => 'datetime',
            'eliminacion_pendiente' => 'boolean',
        ];
    }

    // Relaciones
    public function agente(): BelongsTo { return $this->belongsTo(User::class, 'agente_id'); }
    public function accesos(): HasMany { return $this->hasMany(ContratoAcceso::class); }
    public function evidenciaFirma(): HasOne { return $this->hasOne(ContratoFirmaEvidencia::class); }
    public function eventos(): HasMany { return $this->hasMany(ContratoEvento::class); }

    public function accesoVigente(): ?ContratoAcceso
    {
        return $this->accesos()
            ->whereNull('usado_at')
            ->where('expira_at', '>', now())
            ->latest('id')->first();
    }

    /**
     * API ÚNICA de transición de estado (M-1). Ningún servicio/comando debe
     * hacer `update(['estado' => ...])` directo: siempre pasa por aquí (o por
     * ContratoEstadoService, que delega en este método). Valida la transición,
     * fija el timestamp del evento y registra auditoría de forma atómica.
     */
    public function transicionarA(EstadoContrato $destino, ?User $actor = null, array $contexto = []): void
    {
        if (! $this->estado->puedeTransicionarA($destino)) {
            throw new \DomainException("Transición inválida: {$this->estado->value} → {$destino->value}");
        }

        $columnaTimestamp = match ($destino) {
            EstadoContrato::Enviado => 'enviado_at',
            EstadoContrato::Leido => 'leido_at',
            EstadoContrato::Firmado => 'firmado_at',
            EstadoContrato::Rechazado => 'rechazado_at',
            EstadoContrato::Cancelado => 'cancelado_at',
            EstadoContrato::Expirado => 'expirado_at',
            EstadoContrato::Vencido => 'vencido_at',
            default => null,
        };

        $this->estado = $destino;
        if ($columnaTimestamp !== null) {
            $this->{$columnaTimestamp} = now();
        }
        $this->save();

        $this->registrarEvento($destino->value, $actor, $contexto);
    }

    /**
     * Registra un evento de auditoría (Mn-2: NO usa request() internamente).
     * El contexto (ip, user_agent) lo resuelve ContratoEventoService según
     * HTTP o CLI y lo pasa explícito; así los comandos de consola no guardan
     * IP/UA vacíos o incorrectos.
     *
     * @param  array{ip?: ?string, user_agent?: ?string, meta?: array<string,mixed>}  $contexto
     */
    public function registrarEvento(string $tipo, ?User $actor = null, array $contexto = []): ContratoEvento
    {
        return $this->eventos()->create([
            'tipo' => $tipo,
            'actor_id' => $actor?->id,
            'ip' => $contexto['ip'] ?? null,
            'user_agent' => isset($contexto['user_agent'])
                ? substr((string) $contexto['user_agent'], 0, 500)
                : null,
            'meta' => $contexto['meta'] ?? [],
        ]);
    }

    // Media Library — TODAS las colecciones en disco PRIVADO 'local' (C-2).
    // Nunca en 'public' (default de config/media-library.php): contienen datos
    // personales/sensibles y una URL /storage/... saltaría la Policy.
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('identificacion-anverso')->singleFile()->useDisk('local'); // M-6
        $this->addMediaCollection('identificacion-reverso')->singleFile()->useDisk('local'); // M-6
        $this->addMediaCollection('firma')->singleFile()->useDisk('local');
        $this->addMediaCollection('documento-final')->singleFile()->useDisk('local');
    }

    /** ¿Tiene ambos lados de la identificación oficial? (regla de firma, §7/§8). */
    public function tieneIdentificacionCompleta(): bool
    {
        return $this->hasMedia('identificacion-anverso') && $this->hasMedia('identificacion-reverso');
    }

    protected static function newFactory(): ContratoIntermediacionFactory
    {
        return ContratoIntermediacionFactory::new();
    }
}
```

### 5.5 Máquina de estados (diagrama)

```
                 cancelar (admin/owner)
        ┌───────────────────────────────────────────┐
        │                                            ▼
   ┌──────────┐  enviar   ┌─────────┐  abrir   ┌────────┐
   │ Generado │──────────▶│ Enviado │─────────▶│ Leído  │
   └──────────┘           └─────────┘          └────────┘
                            │  │  │                │  │
                    firmar  │  │  │ expira(72h)    │  │ firmar
                            ▼  │  ▼                ▼  │
                       ┌─────────┐  ┌──────────┐     │
                       │ Firmado │  │ Expirado │◀────┘ expira
                       └─────────┘  └──────────┘
                            │            │
                fin vigencia│            │ reenviar (mismo folio) ──▶ Enviado
                            ▼            │
                       ┌─────────┐   rechazar ──▶ ┌───────────┐
                       │ Vencido │                │ Rechazado │──▶ reenviar ──▶ Enviado
                       └─────────┘                └───────────┘
```

> El cambio de estado se realiza **siempre** por un método guardado que valida
> `EstadoContrato::puedeTransicionarA()` antes de escribir; nunca por `update(['estado' => ...])`
> directo. Ver `ContratoFirmaService` y los comandos de consola.

---

## 6. RFC-064 — Folio y QR

### 6.1 Generación de folio (`FolioGenerator`)

- 8 caracteres de un **alfabeto sin ambiguos** (sin `0/O/1/I/L`) → `23456789ABCDEFGHJKMNPQRSTUVWXYZ` (30 símbolos ≈ 30⁸ ≈ 6.5×10¹¹ combinaciones).
- Reintento en colisión + **índice `unique` en BD** como red de seguridad real (no solo validación PHP).

```php
class FolioGenerator
{
    private const ALFABETO = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    private const LONGITUD = 8;
    private const MAX_INTENTOS = 10;

    public function generar(): string
    {
        for ($i = 0; $i < self::MAX_INTENTOS; $i++) {
            $folio = $this->aleatorio();
            if (! ContratoIntermediacion::withTrashed()->where('folio', $folio)->exists()) {
                return $folio;
            }
        }
        throw new \RuntimeException('No se pudo generar un folio único tras '.self::MAX_INTENTOS.' intentos.');
    }

    private function aleatorio(): string
    {
        $out = '';
        for ($i = 0; $i < self::LONGITUD; $i++) {
            $out .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }
        return $out;
    }
}
```

> La verificación de unicidad incluye `withTrashed()` para que un folio de un
> contrato soft-deleted no se reutilice mientras el expediente aún exista.
> Ante el improbable caso de carrera (dos folios iguales entre el `exists()` y el
> `insert()`), el índice `unique` provoca `QueryException`; el servicio de creación
> reintenta una vez capturando esa excepción.

### 6.2 Tabla y modelo de accesos (`contrato_accesos`)

```php
Schema::create('contrato_accesos', function (Blueprint $table) {
    $table->id();
    // C-1: la tabla se llama 'contratos_intermediacion' (plural irregular), pero
    // Laravel inferiría 'contrato_intermediacions' desde la columna. Tabla explícita.
    $table->foreignId('contrato_intermediacion_id')
        ->constrained('contratos_intermediacion')
        ->cascadeOnDelete();
    $table->string('token_hash', 64)->unique();   // SHA-256 del token; el claro nunca se guarda
    $table->timestamp('expira_at');
    $table->timestamp('usado_at')->nullable();     // sella el "un solo uso"
    $table->string('emitido_por');                 // OrigenAccesoContrato (Mn-3): inicial|reenvio
    $table->timestamps();

    $table->index(['contrato_intermediacion_id', 'usado_at']);
});
```

```php
// app/Enums/OrigenAccesoContrato.php (Mn-3)
enum OrigenAccesoContrato: string
{
    case Inicial = 'inicial';
    case Reenvio = 'reenvio';
}
```

**Decisiones de seguridad del token:**
- El token claro (`Str::random(48)`) se entrega en la URL; en BD solo se guarda `hash('sha256', $token)`. Esto evita que una fuga de BD exponga enlaces activos y bloquea enumeración.
- La URL pública es `/{token}` opaco, **no** `/{folio}` — el folio es adivinable (8 chars), el token no. Previene IDOR (ver §14).
- Expiración 72h configurable vía `config('contratos.token_ttl_horas', 72)`.

### 6.3 Servicio de acceso (`ContratoAccesoService`)

```php
class ContratoAccesoService
{
    public function emitir(ContratoIntermediacion $contrato, OrigenAccesoContrato $origen = OrigenAccesoContrato::Inicial): string
    {
        // Invalida cualquier token activo anterior (reenvío).
        $contrato->accesos()->whereNull('usado_at')->update(['usado_at' => now()]);

        $token = Str::random(48);
        $contrato->accesos()->create([
            'token_hash' => hash('sha256', $token),
            'expira_at' => now()->addHours((int) config('contratos.token_ttl_horas', 72)),
            'emitido_por' => $origen->value,
        ]);

        return $token; // claro — solo para construir la URL, nunca se persiste
    }

    public function resolver(string $token): ?ContratoAcceso
    {
        return ContratoAcceso::where('token_hash', hash('sha256', $token))
            ->whereNull('usado_at')
            ->where('expira_at', '>', now())
            ->first();
    }

    /** Consumo atómico del "un solo uso" — guardado contra doble pestaña (R-2). */
    public function consumir(ContratoAcceso $acceso): bool
    {
        return ContratoAcceso::whereKey($acceso->id)
            ->whereNull('usado_at')
            ->update(['usado_at' => now()]) === 1;
    }
}
```

### 6.4 QR (endroid v6 — dependencia ya instalada)

```php
$url = route('contratos.publico.show', ['token' => $token]);
$qrDataUri = (new Builder(
    data: $url,
    encoding: new Encoding('UTF-8'),
    errorCorrectionLevel: ErrorCorrectionLevel::High,
    size: 600,
))->build()->getDataUri();
```

Vista imprimible interna (`contrato-qr-imprimible.blade.php`): folio en texto + QR, servida por `ContratoQrController` bajo `/admin`, autorizada por Policy. Queda asociada al historial (evento `qr_impreso`) aunque el token expire.

### 6.5 Reenvío
- Solo válido si el contrato está en `Rechazado` o `Expirado`.
- `ContratoAccesoService::emitir($contrato, 'reenvio')` invalida el token previo y emite uno nuevo, **mismo folio**.
- Transición `→ Enviado`; se registra evento `reenviado`; reinicia el ciclo de notificación (§11).

---

## 7. RFC-065 — Formulario Interno (Filament)

`ContratoIntermediacionResource` con formulario seccionado:

1. **Cliente** — nombre (req), teléfono, email, dirección. Validación: teléfono **o** email obligatorio.
2. **Identificación oficial** — dos `SpatieMediaLibraryFileUpload` (colecciones `identificacion-anverso` e `identificacion-reverso`, disco privado `local`), con validación `->image()->maxSize(config('contratos.id_max_kb', 4096))->acceptedFileTypes(['image/jpeg','image/png'])`. **Opcional aquí** (RFC-065): el agente los sube si ya los tiene; si no, los carga el cliente en el formulario público. **Regla de firma (M-6):** ambos lados deben existir antes de poder firmar (ver §8); si el agente no los cargó, el público los exige.
3. **Inmueble a promover** — tipo (req), tipo de operación (`TipoOperacionContrato`, req), dirección (req), comisión % (req).
4. **Condiciones** — vigencia inicio/fin, exclusividad (toggle).
5. **Resumen** (solo lectura) — folio y QR tras crear.

**Al crear — `ContratoCreacionService`, NO `afterCreate` (C-3):** el folio es
obligatorio y `unique`; generarlo en `afterCreate` implicaría insertar sin folio
(imposible con la columna `NOT NULL unique`) o marcar la columna nullable
(degrada el contrato). Por eso el folio se genera **antes del insert**, y el retry
por colisión envuelve el `create` completo, no solo el `exists()` del generador:

```php
// app/Services/Contratos/ContratoCreacionService.php
class ContratoCreacionService
{
    public function __construct(
        private readonly FolioGenerator $folios,
        private readonly ContratoEventoService $eventos,
    ) {}

    /** @param array<string,mixed> $datos */
    public function crear(array $datos, User $actor): ContratoIntermediacion
    {
        for ($i = 0; $i < 3; $i++) {
            try {
                // Cada intento en su propio SAVEPOINT (transacción anidada). Descubierto en
                // implementación (Lote B): en PostgreSQL, un INSERT que viola el índice UNIQUE
                // aborta la transacción entera (25P02); sin savepoint, el reintento hereda una
                // transacción envenenada y falla — tanto bajo RefreshDatabase como si crear()
                // corre dentro de un DB::transaction externo.
                return DB::transaction(function () use ($datos, $actor) {
                    $contrato = ContratoIntermediacion::create([
                        ...$datos,
                        'folio' => $this->folios->generar(),
                        'agente_id' => $actor->id,
                        'estado' => EstadoContrato::Generado,
                    ]);
                    $this->eventos->registrar($contrato, 'generado', $actor);

                    return $contrato;
                });
            } catch (QueryException $e) {
                if (! $this->esColisionFolio($e)) {
                    throw $e;   // otro error de BD: no lo tragamos
                }
                // Colisión de folio en la ventana exists()→insert(): reintenta.
            }
        }
        throw new \RuntimeException('No se pudo crear el contrato: colisión de folio persistente.');
    }

    private function esColisionFolio(QueryException $e): bool
    {
        // 23505 = unique_violation en PostgreSQL; además chequea que sea el índice del folio.
        return $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'folio');
    }
}
```

En el Resource, `CreateContrato::handleRecordCreation()` delega en este servicio
(no usa `afterCreate` para el folio):

```php
protected function handleRecordCreation(array $data): Model
{
    return app(ContratoCreacionService::class)->crear($data, auth()->user());
}
```

**Decisión — generar ≠ enviar (acciones explícitas):** crear el contrato lo deja en `Generado`. El envío es una **acción explícita** ("Enviar al cliente") en la tabla/vista, no un efecto secundario de guardar. Justificación: el agente puede querer revisar/imprimir el QR antes de disparar el envío; además evita reenvíos accidentales al editar. La acción "Enviar" llama a `ContratoEnvioService`, emite token, pasa a `Enviado` y registra evento.

**Permisos:** la creación exige `contratos.manage`; la acción "Enviar/Reenviar" exige `contratos.manage`; "Cancelar" exige `contratos.cancel`. Todo resuelto por `ContratoIntermediacionPolicy` (§14), no por la UI.

**Listado propio del agente:** el `EloquentQuery` del Resource se limita con la Policy scope (`agente` ve solo `where('agente_id', auth id)`); owner/admin ven todos. (Cumple RFC-065 "mis contratos" y RFC-070 con una sola implementación.)

---

## 8. RFC-066 — Formulario Público del Cliente

### 8.1 Rutas públicas (aditivo en `web.php`)

```php
// Throttling obligatorio en TODO el canal público (Mn-4): endpoints sin sesión.
Route::prefix('contrato')->name('contratos.publico.')->middleware('throttle:contratos-publico')->group(function () {
    Route::get('/{token}', [ContratoPublicoController::class, 'show'])->name('show');
    Route::post('/{token}/firmar', [ContratoFirmaController::class, 'firmar'])->name('firmar');
    Route::post('/{token}/rechazar', [ContratoFirmaController::class, 'rechazar'])->name('rechazar');
});
Route::middleware('throttle:contratos-verificar')->group(function () {
    Route::get('/verificar/{folio}', [ContratoVerificacionController::class, 'show'])->name('contratos.verificar');
    Route::post('/verificar/{folio}', [ContratoVerificacionController::class, 'comparar'])->name('contratos.verificar.comparar');
});
```

Limitadores registrados en `AppServiceProvider::boot()` (o `bootstrap/app.php`):
```php
RateLimiter::for('contratos-publico', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
RateLimiter::for('contratos-verificar', fn (Request $r) => Limit::perMinute(10)->by($r->ip())); // anti-enumeración (M-5)
```

### 8.2 Resolución y comportamiento por estado

```php
public function show(string $token, ContratoAccesoService $accesos)
{
    $acceso = $accesos->resolver($token);
    if ($acceso === null) {
        return response()->view('public.contratos.invalido', [], 410); // Gone
    }
    $contrato = $acceso->contrato;
    if ($contrato->estado === EstadoContrato::Cancelado) {
        return response()->view('public.contratos.invalido', ['motivo' => 'cancelado'], 410);
    }
    // Primera apertura: Enviado → Leído (vía API única de transición — M-1).
    if ($contrato->estado === EstadoContrato::Enviado) {
        $contrato->transicionarA(EstadoContrato::Leido, null, app(ContratoEventoService::class)->contextoHttp());
        // notifica al agente "cliente vio el contrato" (§11)
    }
    return view('public.contratos.show', compact('contrato', 'token'));
}
```

- **Token expirado/usado** → `invalido.blade.php`, HTTP 410, **sin exponer datos** del contrato.
- **Cancelado** → mensaje "contrato no disponible".
- **Aviso de privacidad**: checkbox obligatorio antes de habilitar la captura de datos/ID y el botón Firmar. Se registra su aceptación (evento `privacidad_aceptada`, con timestamp).

### 8.3 Clausulado dinámico (una plantilla, variantes de texto)

Una sola plantilla Blade parcial (`resources/views/public/contratos/_clausulado.blade.php`) con bloques condicionales por `exclusividad` × `tipo_operacion`. La misma parcial la consume el PDF (§10) para garantizar que cliente y documento final vean **idéntico** clausulado.

```blade
@if ($contrato->exclusividad)
    {{-- cláusula de exclusividad --}}
@else
    {{-- cláusula sin exclusividad --}}
@endif

@switch($contrato->tipo_operacion)
    @case(\App\Enums\TipoOperacionContrato::Venta) ... @break
    @case(\App\Enums\TipoOperacionContrato::Renta) ... @break
    @case(\App\Enums\TipoOperacionContrato::RentaOpcionCompra) ... @break
@endswitch
```

- **Mobile-first** (acceso principal por QR desde celular): layout de una columna, canvas de firma a ancho completo, inputs grandes.
- **Validación**: no se puede firmar sin aviso aceptado ni sin los campos obligatorios de RFC-063.

---

## 9. RFC-067 — Firma Electrónica y Evidencia

### 9.1 Captura en canvas (JS vanilla, sin librerías nuevas)

`resources/js/contrato-firma.js`: canvas HTML5 con eventos `pointerdown/move/up` (cubre mouse y touch), botón "Limpiar", y al confirmar exporta `canvas.toDataURL('image/png')` a un input hidden que viaja en el POST. Sin dependencias npm nuevas.

### 9.2 Tabla `contrato_firma_evidencias`

```php
Schema::create('contrato_firma_evidencias', function (Blueprint $table) {
    $table->id();
    $table->foreignId('contrato_intermediacion_id')
        ->constrained('contratos_intermediacion')   // C-1: tabla explícita
        ->cascadeOnDelete();
    $table->string('ip', 45);                 // IPv6-safe
    $table->string('user_agent', 500);
    $table->timestamp('firmado_at');          // hora de SERVIDOR (no del cliente)
    $table->string('firma_hash', 64);         // SHA-256 del PNG del trazo
    $table->timestamps();
    // La imagen del trazo se guarda en Media Library, colección 'firma'
});
```

### 9.3 Servicio de firma (`ContratoFirmaService`) — transacción + un solo uso

**Orden corregido (M-2): validar TODO el payload ANTES de consumir el token.**
Un POST con firma corrupta o datos incompletos **no** debe quemar el enlace. El
consumo atómico del token ocurre recién dentro de la transacción, después de que
el input pasó validación.

```php
public function firmar(ContratoAcceso $acceso, string $firmaPngBase64, array $datosCliente): ContratoIntermediacion
{
    // 0. VALIDACIÓN PREVIA (fuera de la transacción, antes de tocar el token — M-2):
    //    - decodifica y valida el PNG (prefijo MIME image/png|jpeg, tamaño máx).
    //    - valida datos obligatorios de RFC-063.
    //    - exige identificación completa (anverso + reverso — M-6).
    //    Si algo falla → ValidationException y el token sigue vivo.
    $png = $this->validarFirma($firmaPngBase64);          // lanza si inválido
    $this->validarDatosObligatorios($datosCliente);        // lanza si faltan
    $contratoPreview = $acceso->contrato;
    if (! $contratoPreview->tieneIdentificacionCompleta() && ! $this->traeAmbosLados($datosCliente)) {
        throw ValidationException::withMessages(['identificacion' => 'Falta anverso o reverso de la identificación.']);
    }

    return DB::transaction(function () use ($acceso, $png, $datosCliente) {
        // 1. Lock del acceso y del contrato antes de mutar (R-2).
        $accesoLock = ContratoAcceso::whereKey($acceso->id)->lockForUpdate()->first();
        $contrato = $accesoLock->contrato()->lockForUpdate()->first();

        // 2. Consumo atómico del token — si otra pestaña ya firmó, aborta (R-2).
        if (! app(ContratoAccesoService::class)->consumir($accesoLock)) {
            throw ValidationException::withMessages(['token' => 'Este enlace ya fue utilizado.']);
        }

        // 3. Transición validada por la API única (M-1).
        $contrato->fill($datosCliente);
        $contrato->retencion_revisar_at = now()->addYears(2);   // decisión 10 EPICA-10
        $contrato->save();
        $contrato->transicionarA(EstadoContrato::Firmado, null, app(ContratoEventoService::class)->contextoHttp());

        // 4. Guarda el trazo en Media Library (disco privado) + evidencia.
        $contrato->addMediaFromString($png)->usingFileName("firma-{$contrato->folio}.png")
            ->toMediaCollection('firma');   // colección en disco 'local' (C-2)

        $contrato->evidenciaFirma()->create([
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'firmado_at' => now(),                 // hora de SERVIDOR
            'firma_hash' => hash('sha256', $png),
        ]);

        // 5. Genera PDF + sello + hash (RFC-068).
        app(ContratoPdfService::class)->generarYSellar($contrato);

        return $contrato;
    });
}
```

- **Sin OTP** — el token de un solo uso es la única barrera (decisión cerrada).
- El `rechazar()` es análogo: valida input, luego (en transacción) consume el token, `transicionarA(EstadoContrato::Rechazado)`, guarda `motivo_rechazo`; **no** genera PDF.
- `transicionarA()` ya registra el evento de auditoría; no se llama `registrarEvento` por separado.

---

## 10. RFC-068 — PDF, Sello Digital y Verificación

### 10.1 Generación (dompdf, ya instalado)

```php
class ContratoPdfService
{
    public function generarYSellar(ContratoIntermediacion $contrato): void
    {
        // 1. Mini-QR de verificación (endroid) → /verificar/{folio}
        $qrVerif = (new Builder(
            data: route('contratos.verificar', ['folio' => $contrato->folio]),
            errorCorrectionLevel: ErrorCorrectionLevel::High, size: 300,
        ))->build()->getDataUri();

        // 2. Render del PDF con datos + firma + evidencia + sello SVG.
        //    La firma se embebe como data URI leyendo los BYTES del disco privado
        //    (opcional-5 / riesgo-3): getFirstMediaUrl('firma') apuntaría a /storage
        //    (URL pública que ni existe en disco 'local') → dompdf fallaría o expondría media.
        $firmaMedia = $contrato->getFirstMedia('firma');
        $firmaDataUri = 'data:image/png;base64,'.base64_encode(file_get_contents($firmaMedia->getPath()));

        $pdf = Pdf::loadView('pdf.contrato-intermediacion', [
            'contrato' => $contrato,
            'evidencia' => $contrato->evidenciaFirma,
            'firmaDataUri' => $firmaDataUri,
            'selloSvg' => $this->selloSvg(),   // placeholder documentado si el arte real no llegó (R-4)
            'qrVerificacion' => $qrVerif,
        ])->setPaper('letter');

        $bytes = $pdf->output();

        // 3. Hash SHA-256 del PDF FINAL (post-sello) → BD + expediente.
        $contrato->update(['documento_hash' => hash('sha256', $bytes)]);
        $contrato->addMediaFromString($bytes)
            ->usingFileName("contrato-{$contrato->folio}.pdf")
            ->toMediaCollection('documento-final');
    }
}
```

### 10.2 Contradicción resuelta del sello (hallazgo de diseño)

> RFC-068 dice que el sello "codifica folio, **hash del documento final** y fecha".
> Pero el hash es del PDF **que contiene el sello** → circularidad: no se puede
> imprimir dentro de un documento el hash de ese mismo documento ya renderizado.

**Resolución (D-8):** el sello impreso muestra **folio + fecha/hora de emisión + mini-QR**. El **hash SHA-256 del PDF final se calcula sobre los bytes ya renderizados y se guarda en BD**, y se expone en la vista `/verificar/{folio}` — no se imprime literalmente dentro del PDF. La verificación de integridad es: el visitante sube el PDF, el sistema recomputa `sha256` de esos bytes y lo compara contra `documento_hash`. Esto conserva la garantía de integridad de RFC-068 sin la imposibilidad técnica. Diverge de la letra del RFC (el sello no "codifica el hash" impreso) → **marcar para auditoría**.

### 10.3 Vista pública de verificación (`/verificar/{folio}`)

- Accesible por **folio** (no requiere token — RFC-068).
- Muestra únicamente: **folio, estatus (firmado), fecha de firma**. **Cero datos personales** del cliente o del inmueble.
- `comparar()`: recibe un PDF subido, calcula `sha256` y responde "íntegro" / "no coincide" comparando con `documento_hash`. No almacena el archivo subido.

**Anti-enumeración (M-5):** el folio de 8 chars no es secreto; probar folios no debe
revelar barato qué contratos existen. Controles:
- **Rate limiting dedicado** `throttle:contratos-verificar` (10/min por IP, §8).
- **Respuesta uniforme por defecto**: sin subir un PDF, la vista NO confirma
  existencia/estatus de forma distinta para folio inexistente vs. no-firmado — muestra
  una pantalla neutra "Verificá un documento por folio" y solo entrega el resultado
  íntegro/no-coincide **tras** subir un PDF cuyo hash coincida con un contrato firmado.
  Un folio inexistente y uno existente-pero-no-firmado responden igual.
- **Decisión cerrada (P-3):** el responsable eligió **modo uniforme**. No se implementa
  un modo "mostrar estatus por folio" en fase 1.

---

## 11. RFC-069 — Estatus, Notificaciones y Automatizaciones

### 11.1 Notificaciones (patrón real `['database','mail']`)

Seis notificaciones siguiendo el patrón de `LonaRequest*Notification` (canales `database` + `mail`):

| Notificación | Destinatario | Disparador |
| :---- | :---- | :---- |
| `ContratoEnlaceEnviado` | Cliente | envío inicial / reenvío |
| `ContratoRecordatorioFirma` | Cliente | job recordatorio (48h) |
| `ContratoFirmado` (adjunta PDF) | Agente + copia al cliente | firma |
| `ContratoRechazado` | Agente | rechazo |
| `ContratoPorExpirar` | Agente | job (previo a 72h) |
| `ContratoRetencionPendiente` | Owner | job retención (2 años) |

### 11.2 WhatsApp — realidad de la infraestructura (Riesgo R-6)

> **No existe canal de envío de WhatsApp en el código.** RFC-044 es solo
> click-tracking. No hay WhatsApp Business API (Meta Cloud / Twilio) configurada.

**Decisión fase 1 (D-9):** el **email** es una notificación automática real (canal `mail`). El **WhatsApp** se entrega como **enlace `wa.me` prellenado** (folio + URL del token) que el agente dispara/copia desde el panel — envío **asistido**, no automático. El envío automático por WhatsApp Business API queda como **contrato diferido** que requiere su propio RFC de integración de proveedor.

```php
// ContratoEnvioService — enlace wa.me asistido
public function whatsappLink(ContratoIntermediacion $contrato, string $tokenUrl): string
{
    $texto = rawurlencode("Hola {$contrato->cliente_nombre}, aquí está tu contrato New Hauz (folio {$contrato->folio}): {$tokenUrl}");
    $tel = preg_replace('/\D/', '', (string) $contrato->cliente_telefono);
    return "https://wa.me/{$tel}?text={$texto}";
}
```

Esto cumple la intención de EPICA-10 (ambos canales desde el primer envío) **sin fingir** una integración inexistente. Marcado para auditoría y §20.

### 11.3 Jobs programados (patrón `Schedule::command`)

Aditivo en `routes/console.php`:
```php
Schedule::command('contratos:expirar')->hourly()->withoutOverlapping();
Schedule::command('contratos:vencer')->dailyAt('01:00');
Schedule::command('contratos:retencion')->dailyAt('02:00');
```

- **`contratos:expirar`** — contratos en `Enviado`/`Leído` con token vencido (>72h) → `Expirado` (transición validada). Además el recordatorio a 48h se resuelve dentro de este comando comparando `enviado_at`.
- **`contratos:vencer`** — contratos `Firmado` con `vigencia_fin < hoy` → `Vencido`.
- **`contratos:retencion`** — `Firmado`/`Vencido` con `retencion_revisar_at <= now()` → set `eliminacion_pendiente = true` + notifica al Owner. **NO borra.** Test dedicado verifica que el comando **no** ejecuta ningún `delete()`/`forceDelete()`.

### 11.4 Confirmación de eliminación por el Owner (M-4)

El job solo **marca** `eliminacion_pendiente`. El flujo de borrado se cierra con una
acción explícita en el panel, diseñada así:

- **Acción Filament** "Confirmar eliminación de expediente", visible **solo si**
  `eliminacion_pendiente = true` y autorizada por `ContratoIntermediacionPolicy::confirmarEliminacion`
  (**solo Owner**), con `requiresConfirmation()` (modal de doble confirmación).
- **Efecto (política de borrado, decisión D-10):**
  1. **Purga de media sensible**: elimina físicamente las colecciones
     `identificacion-anverso`, `identificacion-reverso` y `firma` (son los datos
     personales que la retención de 2 años busca no conservar).
  2. **Conserva el `documento-final` (PDF) + el registro** por defecto, y aplica
     **soft delete** al contrato (`deleted_at`), preservando folio/hash para que la
     verificación pública siga sirviendo integridad sin exponer datos personales.
  3. Registra evento `eliminacion_confirmada` (actor = Owner) **antes** de purgar,
     para que la auditoría quede aunque el expediente se degrade.
- La duración legal de conservación del PDF firmado (¿más de 2 años?) es de negocio
  → **Pregunta escalada P-4**. Por defecto: PDF conservado, media personal purgada.
- Test: Owner confirma → media personal borrada + evento `eliminacion_confirmada` +
  contrato soft-deleted; admin/agente no ven ni pueden ejecutar la acción (403).

---

## 12. RFC-070 — Panel de Seguimiento

`ContratoIntermediacionResource` (tabla):
- **Columnas:** folio, cliente, inmueble (tipo+operación), estado (badge con `EstadoContrato::color()`), agente, fechas clave.
- **Filtros:** estado, agente, tipo de operación, exclusividad.
- **Scope por rol:** agente → solo `agente_id = auth id`; admin/owner → todos (vía Policy).
- **Acciones:** Enviar/Reenviar (`contratos.manage`), Cancelar (`contratos.cancel`), Descargar PDF, Ver QR imprimible.
- **RelationManager `Eventos`** — historial de auditoría de solo lectura (RFC-057).
- **Identificación / firma / PDF** viven en disco privado `local`: **no** hay URL
  `/storage/...`. El panel enlaza a `ContratoMediaController` (ruta autorizada, abajo),
  y el enlace se renderiza **solo si** la Policy lo permite — no ocultamiento cosmético.
- **Acción "Confirmar eliminación"** (solo Owner, §11.4) visible si `eliminacion_pendiente`.

### 12.1 `ContratoMediaController` — stream autorizado (M-3 / C-2)

Ruta interna que sirve los bytes desde el disco privado tras pasar por la Policy. Es
el **único** camino para ver media sensible; no existe URL pública equivalente.

```php
// routes/web.php (grupo admin autenticado)
Route::get('/admin/contratos/{contrato}/media/{coleccion}', ContratoMediaController::class)
    ->name('contratos.media')
    ->middleware(['auth']);

// app/Http/Controllers/ContratoMediaController.php
public function __invoke(ContratoIntermediacion $contrato, string $coleccion)
{
    // Mapea cada colección a su método de Policy (M-3).
    $ability = match ($coleccion) {
        'identificacion-anverso', 'identificacion-reverso' => 'verIdentificacion',
        'firma' => 'verFirma',
        'documento-final' => 'verDocumentoFinal',
        default => abort(404),
    };
    Gate::authorize($ability, $contrato);

    $media = $contrato->getFirstMedia($coleccion) ?? abort(404);

    return response()->file($media->getPath());   // bytes desde disco 'local', nunca URL pública
}
```

Indicadores sugeridos (widgets, opcionales dentro del alcance): contratos por estatus, próximos a expirar, próximos a revisión de retención.

---

## 13. Modelo de Datos (esquema final)

| Tabla | Propósito | Claves / índices |
| :---- | :---- | :---- |
| `contratos_intermediacion` | Registro maestro del contrato | `folio` UNIQUE, `estado` idx, `agente_id` FK+idx, `tipo_operacion` idx, `eliminacion_pendiente` idx, softDeletes |
| `contrato_accesos` | Tokens de acceso (histórico, reenvíos) | `token_hash` UNIQUE, FK `contrato_intermediacion_id` cascade → **`constrained('contratos_intermediacion')`** (C-1), idx (`contrato_id`,`usado_at`) |
| `contrato_firma_evidencias` | Evidencia de la firma (hasOne) | FK **`constrained('contratos_intermediacion')`** (C-1) cascade |
| `contrato_eventos` | Auditoría per-entidad (RFC-057) | FK **`constrained('contratos_intermediacion')`** (C-1) cascade, `tipo`, `actor_id` FK nullable, `ip`, `user_agent`, `meta` json, idx (`contrato_id`,`created_at`) |
| Media (`media`) | ID oficial (`identificacion-anverso`/`-reverso`), firma (`firma`), PDF (`documento-final`) — **todas en disco privado `local`** (C-2) | Reutiliza tabla `media` existente — sin migración nueva |

```php
// contrato_eventos
Schema::create('contrato_eventos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('contrato_intermediacion_id')
        ->constrained('contratos_intermediacion')   // C-1: tabla explícita
        ->cascadeOnDelete();
    $table->string('tipo');                 // generado|enviado|leido|firmado|rechazado|cancelado|reenviado|qr_impreso|privacidad_aceptada|eliminacion_confirmada
    $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('ip', 45)->nullable();
    $table->string('user_agent', 500)->nullable();
    $table->json('meta')->nullable();
    $table->timestamps();
    $table->index(['contrato_intermediacion_id', 'created_at']);
});
```

---

## 14. Seguridad — Mapa de Controles

### 14.1 Permisos nuevos (convención `modulo.accion`)

```php
// Añadir a PermissionSeeder::PERMISSIONS
'contratos.manage',              // generar / enviar / reenviar / ver listado (scoped)
'contratos.cancel',              // cancelar (admin/owner)
'contratos.ver-identificacion',  // ver ID/firma + confirmar eliminación (SOLO owner)
```

Asignación en `ROLE_PERMISSIONS`:
- `owner` → los tres (hereda todo).
- `admin` → `contratos.manage`, `contratos.cancel`.
- `agente` → `contratos.manage`.

> **Nota de coherencia con EPICA-10 (evaluación de decisiones, tabla §10 de la auditoría):**
> el "Flujo del proceso" del RFC de épica (paso 10) menciona que "el agente/administrador
> puede Cancelar", pero la sección **Decisiones** (13) y **Permisos** restringen cancelar a
> **Admin/Owner**. Este diseño sigue la sección de Decisiones (autoritativa): **el agente NO
> cancela**. Discrepancia de wording del RFC de épica registrada como **Pregunta escalada P-5**.

> **`PermissionSeederTest`**: debe actualizarse por los 3 permisos nuevos (el repo tiene la
> matriz exacta con conteos); es parte de la regresión de Épica 2 (riesgo de implementación 6).

### 14.2 `ContratoIntermediacionPolicy` (fuente única)

```php
public function viewAny(User $u): bool { return $u->can('contratos.manage'); }

public function view(User $u, ContratoIntermediacion $c): bool
{
    if ($u->can('contratos.cancel')) return true;          // admin/owner: todos
    return $u->can('contratos.manage') && $c->agente_id === $u->id; // agente: solo propios
}

public function create(User $u): bool { return $u->can('contratos.manage'); }

public function cancel(User $u, ContratoIntermediacion $c): bool
{
    return $u->can('contratos.cancel') && ! $c->estado->esTerminal();   // Mn-1
}

public function enviar(User $u, ContratoIntermediacion $c): bool
{
    return $this->view($u, $c) && $u->can('contratos.manage');
}

// --- Media privada: un método por colección (M-3) ---

/** SOLO owner ve la identificación oficial — ni admin. */
public function verIdentificacion(User $u, ContratoIntermediacion $c): bool
{
    return $u->can('contratos.ver-identificacion');
}

/** La firma (trazo) es tan sensible como la ID → SOLO owner. */
public function verFirma(User $u, ContratoIntermediacion $c): bool
{
    return $u->can('contratos.ver-identificacion');
}

/** El PDF final lo ve quien puede ver el contrato (agente propio / admin / owner) — P-1. */
public function verDocumentoFinal(User $u, ContratoIntermediacion $c): bool
{
    return $this->view($u, $c);
}

/** Confirmar eliminación tras retención: SOLO owner y solo si está pendiente (M-4). */
public function confirmarEliminacion(User $u, ContratoIntermediacion $c): bool
{
    return $u->can('contratos.ver-identificacion') && $c->eliminacion_pendiente;
}
```

> Nota: `confirmarEliminacion` reutiliza `contratos.ver-identificacion` como
> marcador de "capacidad Owner" en vez de crear un cuarto permiso — Owner es el
> único rol que lo tiene. Si en el futuro se desacopla, se añade
> `contratos.eliminar` sin tocar la lógica.

### 14.3 Controles del canal público
- **Anti-IDOR:** la URL usa el **token opaco** (`Str::random(48)`), no el folio. El folio (8 chars) nunca aparece en la ruta de llenado.
- **Token en BD como hash** (`sha256`): fuga de BD no revela enlaces activos.
- **Un solo uso atómico** (`consumir()` con `update ... where usado_at is null`) → sin doble firma por doble pestaña.
- **Validación ANTES de consumir el token (M-2):** el payload (PNG + datos + ID completa) se valida antes de tocar `usado_at`; un POST inválido no quema el enlace.
- **Sin exponer datos** en pantallas de token inválido/expirado (HTTP 410, vista genérica).
- **Rate limiting** (Mn-4): `throttle:contratos-publico` (20/min) y `throttle:contratos-verificar` (10/min), por IP.
- **Validación del PNG de firma:** decodificar base64, verificar prefijo MIME `image/png|jpeg`, tamaño máximo (`config('contratos.firma_max_kb', 512)`), rechazar payloads no-imagen antes de `addMediaFromString`.
- **Identificación oficial:** también valida MIME (`image/jpeg|png`) y tamaño (`config('contratos.id_max_kb', 4096)`) tanto en el form interno como en el público (opcional-6).
- **Verificación pública:** `/verificar/{folio}` no filtra PII; respuesta uniforme anti-enumeración (M-5); el PDF subido para comparar hash no se almacena.

### 14.4 Identificación oficial y media sensible (disco privado — C-2)
- **Regla obligatoria, no "considerar":** las colecciones `identificacion-anverso`,
  `identificacion-reverso`, `firma` y `documento-final` se declaran con `->useDisk('local')`
  (disco privado). El default de `config/media-library.php` es `public` (servido por
  `/storage`), que expondría estos datos por URL directa saltándose la Policy — **prohibido**.
- **Único acceso**: `ContratoMediaController` (§12.1), que hace `Gate::authorize(...)` y
  streamea bytes del disco privado. No existe URL pública para estas colecciones.
- **Tests obligatorios**: admin (no owner) → 403 en `identificacion-*` y `firma`; owner → 200;
  no debe existir ninguna ruta `/storage/...` que sirva estas colecciones.

---

## 15. Estrategia de Testing (PostgreSQL de test, sin SQLite)

**Unit:**
- `EstadoContrato::siguientes()` / `puedeTransicionarA()` — matriz completa de transiciones válidas e inválidas.
- `ContratoIntermediacion::transicionarA()` — transición inválida lanza `DomainException`; válida fija el timestamp correcto + evento (M-1).
- `TipoOperacionContrato::label()`.
- `FolioGenerator` — formato (8 chars, alfabeto), unicidad con colisión forzada (sembrar folio y verificar reintento).
- `ContratoCreacionService` — retry sobre `QueryException` unique del folio, no sobre otros errores (C-3).
- Cálculo de hash del PDF estable.

**Feature (con `RefreshDatabase` sobre `inmo_test`):**
- **Migración (C-1):** `migrate:fresh` corre limpio; las FK de `contrato_accesos`/`contrato_firma_evidencias`/`contrato_eventos` apuntan realmente a `contratos_intermediacion` (insertar hijo con `contrato_intermediacion_id` válido funciona; con id inexistente falla por FK).
- Generación por rol: agente/admin/owner pueden crear; usuario sin permiso → 403.
- Flujo completo: generar → enviar (token emitido) → abrir público (Enviado→Leído) → firmar → PDF con hash → estado Firmado + token invalidado.
- Rechazo con motivo → Rechazado, sin PDF.
- **Un solo uso:** segundo POST con el mismo token → rechazado.
- **Payload antes de consumir (M-2):** POST con firma corrupta → 422 y el token **sigue vivo** (se puede firmar después con payload válido).
- **Identificación completa (M-6):** intentar firmar sin anverso+reverso → 422; con ambos → firma.
- **IDOR:** GET con token ajeno/aleatorio → 410 sin datos.
- **Reenvío:** desde Rechazado/Expirado conserva folio, emite token nuevo, invalida el previo, vuelve a Enviado.
- **Media privada (C-2/M-3):** admin (no owner) → 403 en `identificacion-anverso`/`-reverso` y `firma`; owner → 200; el PDF (`documento-final`) lo baja el agente propio/admin/owner; **no existe** ruta `/storage/...` para esas colecciones.
- **Job expiración:** contrato Enviado con token vencido → Expirado.
- **Job retención:** a los 2 años marca `eliminacion_pendiente` y notifica Owner; **no** borra (assert que el conteo de contratos no cambia y no hay `forceDelete`).
- **Confirmar eliminación (M-4):** Owner confirma → media personal (`identificacion-*`, `firma`) borrada + evento `eliminacion_confirmada` + contrato soft-deleted; admin/agente → 403.
- **Verificación (M-5):** subir el PDF correcto → íntegro; PDF alterado → no coincide; folio inexistente y folio no-firmado responden **uniforme** (sin filtrar existencia/estatus); sin PII.
- **Rate limiting (Mn-4):** superar el límite en `/contrato/{token}` y `/verificar/{folio}` → 429.

**Regresión Épicas 1/2/4:** `PermissionSeederTest` actualizado por los 3 permisos nuevos; Property/roles/Media siguen verdes.

---

## 16. Riesgos Técnicos

| ID | Riesgo | Prob | Impacto | Mitigación |
| :-- | :---- | :--: | :--: | :---- |
| R-1 | Colisión de folio a escala | Baja | Medio | Alfabeto sin ambiguos (30⁸), reintento + índice `unique` como red real; captura de `QueryException` en la creación |
| R-2 | Doble firma por dos pestañas (carrera "un solo uso") | Media | Alto | Consumo atómico del token (`update where usado_at is null`) + `lockForUpdate` en el contrato, todo en transacción |
| R-3 | Fuerza probatoria real de la firma simple | — | Legal | Documentada como firma simple con evidencia (principio de prueba); NOM-151 diferido; evidencia reforzada (IP, UA, hora servidor, hash) |
| R-4 | Arte del sello SVG no entregado por el equipo | Media | Bajo | Placeholder SVG documentado; el contrato de datos (folio+fecha+QR) es independiente del arte final |
| R-5 | PDF grande/ilegible con evidencia e imágenes | Baja | Medio | Firma en PNG comprimido, ID en páginas anexas, `letter`; test de tamaño de salida |
| R-6 | **Envío automático WhatsApp inexistente** (RFC-044 solo trackea) | Alta | Medio | Fase 1: enlace `wa.me` asistido + email automático; WhatsApp Business API diferido a RFC de integración (§11.2, §20) |
| R-7 | Borrado accidental en el job de retención | Baja | Alto | El job **solo marca** `eliminacion_pendiente`; borrado físico exige confirmación manual del Owner; test que veta cualquier delete automático |
| R-8 | Circularidad hash-en-sello (RFC-068) | — | Bajo | Resuelto D-8: hash del PDF final en BD + `/verificar`, no impreso dentro del PDF |

---

## 17. Criterios de Aceptación (QA-151 → QA-180)

| QA | Caso | Verificación |
| :-- | :---- | :---- |
| QA-151 | Crear contrato genera folio de 8 chars único | Unit + Feature |
| QA-152 | Folio no colisiona (reintento) | Unit colisión forzada |
| QA-153 | Solo agente/admin/owner pueden generar | Feature 403 |
| QA-154 | Enviar emite token y pasa a Enviado | Feature |
| QA-155 | Email de enlace se encola al enviar | `Notification::fake` |
| QA-156 | Enlace `wa.me` se genera con folio+URL | Unit |
| QA-157 | Abrir enlace válido pasa Enviado→Leído | Feature |
| QA-158 | Token expirado → 410 sin datos | Feature |
| QA-159 | Token usado → 410 | Feature |
| QA-160 | Contrato cancelado → mensaje no disponible | Feature |
| QA-161 | Aviso de privacidad obligatorio antes de firmar | Feature |
| QA-162 | Clausulado cambia por exclusividad | Feature (assertSee) |
| QA-163 | Clausulado cambia por tipo de operación | Feature |
| QA-164 | Firma captura trazo + IP + UA + hora servidor | Feature |
| QA-165 | Firmar pasa a Firmado e invalida token | Feature |
| QA-166 | Doble firma (2ª petición) rechazada | Feature carrera |
| QA-167 | Rechazo registra motivo, pasa a Rechazado, sin PDF | Feature |
| QA-168 | PDF final se genera y adjunta al firmar | Feature |
| QA-169 | Hash SHA-256 persistido = recomputado | Feature |
| QA-170 | Sello contiene folio + fecha + mini-QR | Feature (render) |
| QA-171 | `/verificar/{folio}` no expone PII | Feature |
| QA-172 | Subir PDF correcto → íntegro; alterado → no coincide | Feature |
| QA-173 | Reenvío conserva folio, nuevo token, vuelve a Enviado | Feature |
| QA-174 | Reenvío solo desde Rechazado/Expirado | Feature |
| QA-175 | Solo Owner ve identificación oficial (admin 403) | Feature |
| QA-176 | Job expiración marca Expirado tras 72h | Feature (viaje en el tiempo) |
| QA-177 | Recordatorio a 48h se encola | Feature |
| QA-178 | Job vencimiento marca Vencido por fin de vigencia | Feature |
| QA-179 | Job retención marca eliminación pendiente + notifica Owner, sin borrar | Feature |
| QA-180 | Transición inválida (p.ej. Firmado→Enviado) es rechazada | Unit |

### Casos QA adicionales solicitados por la auditoría (post-auditoría)

| QA | Caso | Verificación | Hallazgo |
| :-- | :---- | :---- | :---- |
| QA-181 | `migrate:fresh` limpio; FKs apuntan a `contratos_intermediacion` (hijo con id inexistente falla) | Feature | C-1 |
| QA-182 | Media sensible en disco privado: sin ruta `/storage/...`; `identificacion-*` y `firma` solo por controlador autorizado | Feature | C-2 |
| QA-183 | Crear contrato sin folio es imposible; `ContratoCreacionService` reintenta solo ante colisión de folio | Unit + Feature | C-3 |
| QA-184 | Toda mutación de estado pasa por `transicionarA()`; no hay `update(['estado'])` directo en servicios/comandos | Unit + revisión | M-1 |
| QA-185 | POST de firma inválido no consume el token (se puede firmar luego) | Feature | M-2 |
| QA-186 | `ContratoMediaController`: admin 403 / owner 200 en ID y firma; PDF por agente propio/admin/owner | Feature | M-3 |
| QA-187 | Owner confirma eliminación → purga media personal + evento + soft delete; admin/agente 403 | Feature | M-4 |
| QA-188 | `/verificar/{folio}` respuesta uniforme (inexistente ≡ no-firmado); rate limit 429 | Feature | M-5, Mn-4 |
| QA-189 | Firmar exige anverso + reverso de identificación | Feature | M-6 |
| QA-190 | Rate limiting en `/contrato/{token}` (429 al exceder) | Feature | Mn-4 |

---

## 18. Plan de Implementación por Lotes (A → H)

| Lote | Contenido | DoD | Verificación |
| :-- | :---- | :---- | :---- |
| **A** (RFC-063) | Enums `EstadoContrato`/`TipoOperacionContrato`/`OrigenAccesoContrato`, migración `contratos_intermediacion`, modelo (con `transicionarA` M-1 y colecciones disco privado C-2), factory, tabla `contrato_eventos` (FK explícita C-1) | Migra limpio; factory persiste; `transicionarA` inválida lanza excepción; válida fija estado+timestamp+evento | `php artisan migrate:fresh`; Unit enums + `transicionarA`; QA-181 |
| **B** (RFC-064) | `FolioGenerator`, `ContratoCreacionService` (C-3), `contrato_accesos` (FK explícita), `ContratoAccesoService`, `ContratoEventoService`, QR endroid, vista imprimible, endpoint reenvío | Folio único (colisión forzada); create con retry unique; token expira; consumir atómico; reenvío conserva folio | Unit folio/creación + Feature token; QA-183 |
| **C** (RFC-065) | `ContratoIntermediacionResource` (form seccionado, ID anverso/reverso disco privado), `ContratoIntermediacionPolicy` (métodos media M-3), 3 permisos, `ContratoMediaController`, acción "Enviar" | CRUD en `/admin` por rol; crear vía servicio genera folio; enviar emite token→Enviado; media solo por controlador autorizado | Feature CRUD + media 403/200; QA-182, QA-186 |
| **D** (RFC-066) | Rutas públicas con `throttle`, `ContratoPublicoController`, clausulado dinámico parcial, aviso privacidad, vistas mobile, limitadores `RateLimiter::for` | Token válido abre (Enviado→Leído vía `transicionarA`); expirado/usado→410; clausulado varía; rate limit 429 | Feature público; QA-190 |
| **E** (RFC-067) | `contrato_firma_evidencias` (FK explícita), `contrato-firma.js`, `ContratoFirmaService` (validar→consumir M-2, ID completa M-6), `ContratoFirmaController` | Firma persiste trazo+evidencia (disco privado); token invalidado; payload inválido no quema token; exige anverso+reverso; rechazo con motivo | Feature firma + carrera; QA-185, QA-189 |
| **F** (RFC-068) | `ContratoPdfService` (firma por bytes, no URL), plantilla PDF, sello SVG (placeholder), hash, `/verificar/{folio}` con respuesta uniforme | PDF adjunto (disco privado); hash BD=recomputado; verificación sin PII y uniforme | Feature PDF + verificación; QA-188 |
| **G** (RFC-069) | 6 notificaciones, `ContratoEnvioService` (email+wa.me), 3 comandos, `Schedule::command`, acción Owner "Confirmar eliminación" (§11.4) | Notificaciones en eventos; jobs expiración/vencimiento/retención (no borra); Owner confirma → purga media personal + soft delete | Feature jobs + `Notification::fake`; QA-187 |
| **H** (RFC-070) | Tabla/filtros del panel, RelationManager Eventos, enlaces media autorizados, widgets; **tests + regresión** | `php artisan test` verde; QA-151→**190**; `PermissionSeederTest` actualizado; sin regresión Épicas 1/2/4 | Suite completa + Pint |

> Orden estricto: ningún lote empieza sin cerrar la DoD del anterior. Commits atómicos `feat:/test:/fix:`.

---

## 19. Checklist de Cierre Técnico

- [ ] Enums, migraciones y modelo operativos (Lote A)
- [ ] Folio único + token de un solo uso + reenvío (Lote B)
- [ ] Formulario interno + Policy + permisos (Lote C)
- [ ] Formulario público + clausulado dinámico + privacidad (Lote D)
- [ ] Firma + evidencia + hash de firma (Lote E)
- [ ] PDF + sello + hash + verificación pública (Lote F)
- [ ] Notificaciones + jobs (expiración/vencimiento/retención) + acción Owner confirmar eliminación (Lote G)
- [ ] Panel + media privada autorizada (ID/firma solo Owner, PDF scoped) (Lote H)
- [ ] Media sensible en disco privado `local`; sin ruta `/storage/...` (C-2)
- [ ] Estado siempre vía `transicionarA()`; sin `update(['estado'])` directo (M-1)
- [ ] Suite completa verde sobre PostgreSQL; QA-151→**190** cubiertos
- [ ] `PermissionSeederTest` actualizado; sin regresión de épicas previas
- [ ] `./vendor/bin/pint --test` limpio en archivos de la Épica 10

---

## 20. Decisiones Diferidas / Fuera de Alcance

| ID | Diferido | Destino |
| :-- | :---- | :---- |
| DIF-1 | Firma certificada **NOM-151** (Mifiel/Weetrust/DocuSign) | RFC de integración de PSC, fase futura |
| DIF-2 | **Envío automático por WhatsApp Business API** (Meta Cloud/Twilio) | RFC de integración de proveedor de mensajería. Fase 1 usa `wa.me` asistido + email automático (R-6, §11.2) |
| DIF-3 | Cobro de comisiones / módulo financiero | Módulo financiero aparte |
| DIF-4 | Alta automática del inmueble en catálogo al firmar | No previsto — el contrato es independiente de Property |
| DIF-5 | Sistema de auditoría global RFC-057 | La tabla `contrato_eventos` per-entidad lo anticipa; migración futura a un sistema compartido |
| DIF-6 | Arte gráfico final del sello SVG | Lo entrega el equipo de diseño; placeholder mientras tanto (R-4) |

---

## Registro de Cambios desde la Auditoría

Auditoría: `docs/audits/epica-10-auditoria-diseno.md` (Codex, 2026-07-14). Veredicto
inicial: **Rechazado hasta corregir críticos**. Los tres críticos (C-1, C-2) fueron
**verificados en código real** antes de aplicar (pluralización de Laravel y disco default
de media-library). Todos los hallazgos fueron aplicados.

### Hallazgos aplicados

| # | Hallazgo | Cambio aplicado | Sección |
| :-- | :---- | :---- | :---- |
| **C-1** | `constrained()` inferiría `contrato_intermediacions` (tabla real: `contratos_intermediacion`) | `->constrained('contratos_intermediacion')` explícito en accesos, evidencias y eventos | §6.2, §9.2, §13 |
| **C-2** | Media sensible quedaría pública (disco default `public`) | `->useDisk('local')` obligatorio en `identificacion-*`, `firma`, `documento-final`; único acceso vía `ContratoMediaController` | §5.4, §12.1, §14.4 |
| **C-3** | Folio en `afterCreate` con columna `NOT NULL unique` | `ContratoCreacionService`: folio pre-insert + retry `QueryException` unique alrededor del `create` completo | §4, §7 |
| **M-1** | Falta API única de transición | Método `ContratoIntermediacion::transicionarA()` + `ContratoEstadoService`; prohibido `update(['estado'])` directo (se corrigió también §8.2) | §5.4, §8.2 |
| **M-2** | Token se consumía antes de validar payload | Validación completa (PNG/datos/ID) **antes** de consumir; consumo atómico dentro de la transacción con `lockForUpdate` | §9.3, §14.3 |
| **M-3** | Falta ruta/controlador real para media sensible | `ContratoMediaController` + métodos de Policy `verIdentificacion`/`verFirma`/`verDocumentoFinal` | §4, §12.1, §14.2 |
| **M-4** | Falta acción Owner de confirmación de eliminación | §11.4: acción Filament solo Owner, purga media personal + soft delete + evento `eliminacion_confirmada` | §11.4, §12, §14.2 |
| **M-5** | `/verificar/{folio}` permite enumeración | Respuesta uniforme (inexistente ≡ no-firmado) + rate limit dedicado | §10.3, §8.1 |
| **M-6** | Una sola colección de ID (falta anverso/reverso) | Dos colecciones `identificacion-anverso`/`-reverso`; regla `tieneIdentificacionCompleta()` exigida antes de firmar | §5.4, §7, §9.3 |
| **Mn-1** | `esFinal()` confunde | Renombrado a `esTerminal()` con doc: "no admite cancelación ni reenvío" | §5.2, §14.2 |
| **Mn-2** | `registrarEvento()` usa `request()` en el modelo | Firma cambiada a contexto explícito (`ip`/`user_agent`); `ContratoEventoService` resuelve HTTP/CLI | §5.4, §4 |
| **Mn-3** | `emitido_por` string libre | Enum `OrigenAccesoContrato` (inicial/reenvio) | §6.2 |
| **Mn-4** | Falta throttling en rutas públicas | `throttle:contratos-publico` (20/min) y `throttle:contratos-verificar` (10/min) + `RateLimiter::for` | §8.1, §14.3 |
| Opc-5 | Firma para dompdf desde URL pública | Se embebe la firma por **bytes** (`file_get_contents` del disco privado) → data URI | §10.1 |
| Opc-6 | MIME/tamaño de identificación oficial | Validación `image/jpeg|png` + `id_max_kb` en form interno y público | §7, §14.3 |

### Hallazgos NO aplicados (con justificación)

Ninguno rechazado. Todos los hallazgos críticos, medios, menores y las recomendaciones
opcionales relevantes se incorporaron. Las 5 preguntas abiertas de la auditoría se
resolvieron técnicamente o se escalaron al responsable (abajo).

### Decisiones cerradas de EPICA-10 — ¿alguna se reabrió?

**Ninguna se reabrió.** La auditoría (su §10) confirmó que las 16 decisiones son sólidas.
La única "reinterpretación" es de wording, no de negocio: **email + WhatsApp** se cumple con
email automático + `wa.me` asistido, porque no existe WhatsApp Business API (R-6/DIF-2). No es
reabrir una decisión de negocio: es ajustar la implementación a la infraestructura real. La
discrepancia de wording del RFC de épica (agente cancela vs. no) se escala como P-5, sin
decidir unilateralmente.

---

## Preguntas Escaladas al Responsable de la Épica — RESUELTAS

Estas eran de **negocio/legal**, no técnicas. Fueron **resueltas por el responsable de la
épica** (2026-07-14). Todas las respuestas coinciden con el default que ya implementaba el
diseño → **sin cambios de código pendientes**, solo se fijan como contrato cerrado.

| ID | Pregunta | ✅ Decisión del responsable |
| :-- | :---- | :---- |
| P-1 | ¿El PDF final lo ve el agente siempre o solo si es propio? | **Agente solo los propios**; admin/owner todos — `verDocumentoFinal` = `view`. |
| P-2 | ¿La identificación es obligatoria para firmar? | **Obligatoria, ambos lados (anverso + reverso)**. Máximo peso probatorio; `tieneIdentificacionCompleta()` bloquea la firma sin ambos. |
| P-3 | ¿`/verificar/{folio}` muestra estatus o responde uniforme? | **Respuesta uniforme** anti-enumeración: folio inexistente ≡ no-firmado; solo confirma integridad tras subir un PDF válido. |
| P-4 | Al confirmar eliminación, ¿qué se borra? | **Purga datos personales** (identificación + firma) + soft delete del contrato; **conserva el PDF firmado + hash** para verificación pública. |
| P-5 | ¿`wa.me` cuenta para el DoD? ¿El agente puede cancelar? | QA valida **generación del enlace** `wa.me`, no envío automático. **Cancelar: solo Admin/Owner** (el agente NO cancela). |

> Con P-1…P-5 cerradas, el diseño no tiene decisiones de negocio abiertas. Cualquier cambio
> futuro sobre estas (p.ej. plazo de retención legal del PDF > 2 años) se trata como nueva
> solicitud, no como deuda de este diseño.

---

## Cierre Técnico del Diseño

### Confirmaciones de arquitectura

- ✅ **Aditividad**: cero cambios a migraciones de `users`/`properties`/`zones`/`media`. Sin FK a `properties`.
- ✅ **Integridad de migraciones (C-1)**: todas las FK usan `constrained('contratos_intermediacion')`; verificado que la inferencia por defecto (`contrato_intermediacions`) habría fallado.
- ✅ **Datos sensibles (C-2)**: identificación, firma y PDF en disco privado `local`, servidos solo por controlador autorizado. Sin URL pública.
- ✅ **Creación robusta (C-3)**: folio pre-insert + retry unique en `ContratoCreacionService`, no `afterCreate`.
- ✅ **Máquina de estados (M-1)**: `transicionarA()` como única API de transición; transiciones inválidas lanzan excepción.
- ✅ **Un solo uso seguro (M-2/R-2)**: validación antes de consumir + consumo atómico + `lockForUpdate`.
- ✅ **Autorización (M-3)**: Policy como fuente única, con métodos por colección de media; ID y firma solo Owner.
- ✅ **Retención (M-4/R-7)**: el job solo marca; el Owner confirma; purga auditada.
- ✅ **Anti-enumeración e IDOR (M-5)**: token opaco hasheado + verificación uniforme + rate limiting.
- ✅ **Honestidad de infraestructura (R-6)**: WhatsApp asistido, no fingido; API automática diferida (DIF-2).
- ✅ **Cobertura QA**: QA-151→190 (30 base + 10 post-auditoría), cada hallazgo con su test.

### Veredicto final

> **✅ APROBADO PARA IMPLEMENTACIÓN.**
> Todos los hallazgos críticos, medios y menores de la auditoría de Codex fueron
> aplicados y verificados contra el código real. Las decisiones de negocio pendientes
> están escaladas (P-1…P-5) con defaults reversibles que no bloquean el desarrollo.
> El diseño está listo para iniciar el **Lote A** (Prompt 4).

---

*Diseño técnico — Épica 10 — Contratos de Intermediación · New Hauz · rama `feature/epica-10-contratos-intermediacion`*
*Corregido en Prompt 3 tras la auditoría de diseño de Codex (`docs/audits/epica-10-auditoria-diseno.md`).*

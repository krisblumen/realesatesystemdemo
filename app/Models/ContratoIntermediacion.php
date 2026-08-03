<?php

namespace App\Models;

use App\Enums\EstadoContrato;
use App\Enums\TipoOperacionContrato;
use App\Support\NumeroALetras;
use Database\Factories\ContratoIntermediacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Contrato de intermediación (Épica 10, RFC-063). Entidad INDEPENDIENTE del catálogo
 * de Property: los datos del inmueble a promover son columnas propias, sin FK a
 * properties (D-2). El estado se cambia SIEMPRE vía transicionarA() (M-1).
 *
 * @property EstadoContrato $estado
 * @property TipoOperacionContrato $tipo_operacion
 */
class ContratoIntermediacion extends Model implements HasMedia
{
    /** @use HasFactory<ContratoIntermediacionFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $table = 'contratos_intermediacion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'estado',
        'cliente_nombre',
        'cliente_telefono',
        'cliente_email',
        'cliente_direccion',
        'inmueble_tipo',
        'tipo_operacion',
        'inmueble_direccion',
        'comision_porcentaje',
        'precio_autorizado',
        'vigencia_inicio',
        'vigencia_fin',
        'exclusividad',
        'plantilla_version',
        'agente_id',
        'motivo_rechazo',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'estado' => EstadoContrato::Generado->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoContrato::class,
            'tipo_operacion' => TipoOperacionContrato::class,
            'exclusividad' => 'boolean',
            'comision_porcentaje' => 'decimal:2',
            'precio_autorizado' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fin' => 'date',
            'enviado_at' => 'datetime',
            'leido_at' => 'datetime',
            'firmado_at' => 'datetime',
            'rechazado_at' => 'datetime',
            'cancelado_at' => 'datetime',
            'expirado_at' => 'datetime',
            'vencido_at' => 'datetime',
            'retencion_revisar_at' => 'datetime',
            'eliminacion_pendiente' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function agente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agente_id');
    }

    /**
     * Scope de visibilidad por rol: owner/admin ven todos; el agente solo los propios.
     * Consumido por el panel (RFC-070) y la Policy (fuente única de autorización).
     *
     * @param  Builder<ContratoIntermediacion>  $query
     * @return Builder<ContratoIntermediacion>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['owner', 'admin'])) {
            return $query;
        }

        return $query->where('agente_id', $user->id);
    }

    /** @return HasMany<ContratoEvento, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(ContratoEvento::class);
    }

    /** @return HasMany<ContratoAcceso, $this> */
    public function accesos(): HasMany
    {
        return $this->hasMany(ContratoAcceso::class);
    }

    /** @return HasOne<ContratoFirmaEvidencia, $this> */
    public function evidenciaFirma(): HasOne
    {
        return $this->hasOne(ContratoFirmaEvidencia::class);
    }

    /** Último token de acceso vigente (no usado y no expirado), si existe. */
    public function accesoVigente(): ?ContratoAcceso
    {
        return $this->accesos()
            ->whereNull('usado_at')
            ->where('expira_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * API ÚNICA de transición de estado (M-1). Ningún servicio/comando debe hacer
     * update(['estado' => ...]) directo: siempre pasa por aquí. Valida la transición,
     * fija el timestamp del evento y registra auditoría.
     *
     * @param  array{ip?: ?string, user_agent?: ?string, meta?: array<string,mixed>}  $contexto
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
     * Registra un evento de auditoría (Mn-2: NO usa request() internamente). El contexto
     * (ip, user_agent) lo resuelve quien llama según HTTP o CLI y lo pasa explícito.
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

    /**
     * Media Library — TODAS las colecciones en disco PRIVADO 'local' (C-2). Nunca en
     * 'public' (default de config/media-library.php): contienen datos personales y una
     * URL /storage/... saltaría la Policy.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('identificacion-anverso')->singleFile()->useDisk('local');
        $this->addMediaCollection('identificacion-reverso')->singleFile()->useDisk('local');
        $this->addMediaCollection('firma')->singleFile()->useDisk('local');
        $this->addMediaCollection('documento-final')->singleFile()->useDisk('local');
    }

    /**
     * ¿Tiene ambas caras (frente + reverso) de la identificación oficial? Requisito para
     * firmar (P-2/M-6): se capturan las dos caras como evidencia del expediente.
     */
    public function tieneIdentificacionCompleta(): bool
    {
        return $this->hasMedia('identificacion-anverso') && $this->hasMedia('identificacion-reverso');
    }

    /**
     * ¿Tiene la foto del FRENTE? La evidencia que el Owner consulta en el panel muestra solo
     * el frente, cuyo propósito es corroborar que quien firmó es la persona de la credencial.
     */
    public function tieneIdentificacion(): bool
    {
        return $this->hasMedia('identificacion-anverso');
    }

    /**
     * Precio/renta autorizado en número y letra, formato legal mexicano:
     * "$1,000,000.00 (un millón de pesos 00/100 M.N.)". Fuente única para vistas y PDF.
     */
    public function precioFormateado(): string
    {
        if ($this->precio_autorizado === null) {
            return '—';
        }

        $monto = (float) $this->precio_autorizado;

        return '$'.number_format($monto, 2).' ('.NumeroALetras::pesos($monto).')';
    }

    protected static function newFactory(): ContratoIntermediacionFactory
    {
        return ContratoIntermediacionFactory::new();
    }
}

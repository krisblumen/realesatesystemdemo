# RFC-062 CONTROL DE LONAS ASIGNADAS

## Proyecto

NEW HAUZ

## RFC

RFC-062

## Estado

✅ IMPLEMENTADO

## Rama base

`develop`

## Rama de trabajo

`feature/control-lonas-asignadas` _(ya creada; no sigue el patrón `feature/rfc-0XX-slug` de RFCs anteriores — se mantiene el nombre existente)_

## Responsable Principal

Kristian

## Participantes

### Arquitectura

_(a definir)_

### QA

_(a definir)_

## Fecha

2026-07-13

---

# Seguimiento del Pipeline Multimodelo

| Etapa | Agente | Estado | Fecha |
| :---- | :---- | :---- | :---- |
| 1\. Generación del RFC | Claude (Arquitecto) | ✅ Completado | 2026-07-13 |
| 2\. Auditoría de diseño | Antigravity | ✅ Completado | 2026-07-13 |
| 3\. Aplicación de correcciones | Claude (Arquitecto) | ✅ Completado | 2026-07-13 |
| 4\. Implementación | Claude | ✅ Completado — Lotes A–E | 2026-07-13 |
| 5\. Auditoría de implementación | Codex | ✅ Completado — aprobación condicionada; correcciones aplicadas | 2026-07-13 |
| 6\. Cierre técnico | Codex | ✅ Completado | 2026-07-13 |

---

# Objetivo

Controlar el ciclo de vida de las **lonas publicitarias** (carteles de venta/renta) que se entregan a los agentes para colocar en propiedades:

1. **Asignación** — owner/admin define cuántas lonas de **venta** y cuántas de **renta** recibe cada agente.
2. **Justificación con evidencia** — el agente sólo puede marcar una lona como colocada si aporta una foto tomada **en el momento, desde la cámara del dispositivo**, nunca desde galería.
3. **Reposición controlada** — el agente no puede solicitar más lonas de un tipo (venta o renta) mientras tenga lonas de ese mismo tipo sin colocar.
4. **Armado automático del material** — cuando se aprueba una entrega (inicial o por solicitud), el sistema genera el PDF de la lona: plantilla base de NewHauz + datos del agente (nombre, teléfono, correo) + QR opcional hacia el detalle público de un inmueble publicado.

Este RFC pertenece a la **Épica 9 — Operación de Campo** (`docs/rfc/EPICA-9-OPERACION-DE-CAMPO.md`, nueva), decisión del Product Owner (CD-1 cerrada) tras evaluar la recomendación de la auditoría de diseño. Es adyacente a Épica 2 (Usuarios y Seguridad, de la que consume roles/permisos) y Épica 4 (Inmuebles, de la que consume el modelo `Property` para el QR), pero no encajaba en ninguna de las 8 épicas existentes.

---

# Contexto y Dependencias

## Consume de Épica 2 (Usuarios y Seguridad)

| Contrato | Origen | Uso en este RFC |
| :---- | :---- | :---- |
| Roles `owner`, `admin`, `agente` (spatie/laravel-permission) | Épica 2 | Gate de acceso a los nuevos Resources/Páginas Filament |
| `User::$phone`, `User::email` (columnas ya existentes, `app/Models/User.php`) | Épica 2 | Datos de contacto impresos en el PDF de la lona — **no se agregan columnas nuevas a `users`** |
| Patrón de permisos vía `PermissionSeeder` | Épica 2 (confirmado en auditoría de RFC-061) | Se agregan dos permisos nuevos: `lonas.manage` (owner/admin) y `lonas.place` (agente) |

## Consume de Épica 4 (Inmuebles)

| Contrato | Origen | Uso en este RFC |
| :---- | :---- | :---- |
| Modelo `Property` (`app/Models/Property.php`), tabla `properties` | Épica 4 | Selección opcional del inmueble a enlazar en el QR |
| `Property::scopePublished()` | Épica 4 | El selector de inmueble en el formulario de aprobación **sólo** ofrece propiedades publicadas |
| `Property::canonical()` (`app/Models/Property.php:245-247`) | Épica 4 | URL destino del QR: `$property->canonical()` |
| `Spatie\MediaLibrary` — colecciones `cover`/`gallery` en `Property` | Épica 4 | No se consume directamente; referencia de patrón para las colecciones nuevas (`evidencia`, `diseno-pdf`) |
| `App\Http\Controllers\PropertyPdfController` (`Pdf::loadView(...)->download(...)`) | Épica 4 | Contrato de referencia — el `LonaPdfController` nuevo replica exactamente esta forma (Gate → load relaciones → `Pdf::loadView` → `download`) |

## Consume de patrones ya operativos (no ligados a una Épica numerada)

| Contrato | Origen | Uso en este RFC |
| :---- | :---- | :---- |
| `App\Notifications\LeadAssignedNotification` (`ShouldQueue`, `via() => ['database','mail']`) | Módulo Leads | Plantilla exacta para `LonaRequestSubmittedNotification` / `LonaRequestApprovedNotification` |
| Patrón `App\Filament\Pages\AgentDashboard` + widget `canView()` por rol (RFC-061) | RFC-061 | Plantilla para la landing del agente (`AgentLonas`) y el aislamiento de widgets por rol |
| `barryvdh/laravel-dompdf` (`^3.1`, ya instalado) | `composer.json:10` | Motor de generación del PDF de la lona |

## Dependencia nueva a instalar

**No existe ningún paquete de generación de códigos QR en el proyecto** (`composer.json`/`composer.lock` sin coincidencias de "qr", confirmado). Se propone `endroid/qr-code` (^5.x): activamente mantenido, sin dependencias pesadas, exporta PNG/SVG que dompdf embebe como `data:image/png;base64,...`. Alternativa descartada: `simplesoftwareio/simple-qrcode` (wrapper delgado, sin actividad reciente). Ver CD-2.

---

# Alcance

## Lo que entrega este RFC

- Modelos y migraciones: `LonaBatch` (entrega/lote), `LonaUnit` (unidad física individual), `LonaRequest` (solicitud del agente).
- Enums: `LonaUnitStatus` (PendienteColocacion/Colocada), `LonaRequestStatus` (Pendiente/Aprobada/Rechazada). **El tipo venta/renta reutiliza el enum existente `App\Enums\OperationType`** — no se crea `LonaTipo` (ver decisión de implementación I-1).
- Asignación manual inicial por owner/admin (sin solicitud previa) vía `LonaBatchResource`.
- Solicitud de reposición por el agente, bloqueada mientras existan unidades `PendienteColocacion` de ese mismo tipo.
- Aprobación de solicitudes por owner/admin vía `LonaRequestResource`, con selección opcional de inmueble publicado para el QR.
- Armado automático del PDF (plantilla NewHauz + datos del agente + QR opcional) al aprobar una entrega (manual o por solicitud).
- Captura de evidencia fotográfica **sólo por cámara en vivo** (sin `<input type=file>`, sin selector de galería posible) vía componente Livewire con `getUserMedia` + `<canvas>`.
- Página del agente (`AgentLonas`, patrón RFC-061) para ver sus lonas pendientes/colocadas, registrar evidencia y solicitar reposición.
- Notificaciones a owner/admin al recibir una solicitud, y al agente al ser aprobada/rechazada.
- Permisos nuevos `lonas.manage` / `lonas.place`.

## Lo que NO entrega este RFC

- Georreferenciación (GPS) de la colocación — sólo se exige foto. Ver CD-3 (decisión abierta).
- Inventario físico/serializado de lonas reutilizables — el modelo asume material impreso **de un solo uso** por lote (ver sección 5.1 para el razonamiento).
- Métricas o reportes de consumo de lonas por zona/periodo — se difiere a un RFC de reportería (posible Épica 7).
- Integración con proveedores de impresión — el PDF se descarga desde el panel; el envío a imprenta queda fuera de alcance.
- Renombrar la rama de trabajo al patrón `feature/rfc-0XX-*`.

---

# Diseño Técnico

## 5.1 Modelo de dominio (CERRADO)

Tres entidades, no dos, porque "lona" mezcla dos conceptos que deben modelarse por separado: el **lote autorizado** (cuántas se entregan y cuándo) y la **unidad física individual** (cada lona debe justificarse una por una con su propia foto).

```
LonaRequest (solicitud, 0..1 por lote)
        │ aprueba →
        ▼
   LonaBatch (lote entregado: agente, tipo, cantidad, quién autorizó)
        │ genera N →
        ▼
   LonaUnit × cantidad (unidad física: estado, evidencia, inmueble/ubicación)
```

**Por qué es de un solo uso (no reutilizable):** el enunciado exige que el PDF se arme "en automático" cada vez que se autoriza una entrega, con los datos del agente y, opcionalmente, un QR a un inmueble distinto en cada ocasión. Un modelo de lona reutilizable (con código serial que se reimprime) no encaja con "armado automático de las lonas" en cada solicitud — se modela como material impreso desechable por lote. Si el negocio en verdad reutiliza lonas físicas, el ajuste es aditivo (una tabla `lona_devoluciones`), no rediseño.

**Corrección de la auditoría de diseño (M-1) — dos inmuebles distintos, no uno:** la versión original de este RFC copiaba el `property_id` de la solicitud/lote hacia cada `LonaUnit` al crearla, confundiendo dos conceptos independientes:

1. **Inmueble del QR** — el que owner/admin elige al aprobar el lote, para imprimir en el PDF. Es **uno solo por lote** y se fija en el momento de la aprobación.
2. **Inmueble/ubicación de colocación real** — dónde el agente terminó colgando *esa* unidad física en particular. Puede ser **distinto por cada unidad** del mismo lote (un agente con 5 lonas de venta puede colgarlas en 5 propiedades distintas), y sólo se conoce cuando el agente coloca la lona, no cuando se aprueba el lote.

Por eso `LonaUnit.property_id`/`ubicacion_referencia` **nunca se copian del lote** — nacen `null` y el agente los completa recién en `CapturePlacementEvidence` (sección 5.4). El PDF no se regenera si luego la colocación real difiere del inmueble del QR: son datos desacoplados a propósito.

## 5.2 Migraciones (CERRADO)

```php
// database/migrations/2026_07_13_000001_create_lona_batches_table.php
Schema::create('lona_batches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('lona_request_id')->nullable()->constrained('lona_requests')->nullOnDelete();
    ->string('operation_type'); // OperationType (venta/renta)
    $table->unsignedInteger('cantidad');
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});

// database/migrations/2026_07_13_000002_create_lona_units_table.php
Schema::create('lona_units', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lona_batch_id')->constrained('lona_batches')->cascadeOnDelete();
    $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete(); // denormalizado, evita join en la validación "todas colocadas"
    ->string('operation_type'); // OperationType, denormalizado del batch
    $table->string('status')->default('pendiente_colocacion'); // LonaUnitStatus
    $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
    $table->string('ubicacion_referencia')->nullable(); // texto libre si no hay Property en sistema
    $table->timestamp('placed_at')->nullable();
    $table->timestamps();
});

// database/migrations/2026_07_13_000003_create_lona_requests_table.php
Schema::create('lona_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
    ->string('operation_type'); // OperationType (venta/renta)
    $table->unsignedInteger('cantidad_solicitada');
    $table->string('estado')->default('pendiente'); // LonaRequestStatus
    $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete(); // opcional, para el QR
    $table->foreignId('reviewed_by')->nullable()->constrained('users');
    $table->timestamp('reviewed_at')->nullable();
    $table->string('motivo_rechazo')->nullable();
    $table->timestamps();

    // Corrección de auditoría (C-1): un exists() a nivel de aplicación tiene ventana de
    // carrera bajo solicitudes concurrentes. El índice único parcial (PostgreSQL) es la
    // garantía real — a lo sumo una solicitud "pendiente" por agente+tipo, sin importar
    // cuántas peticiones lleguen al mismo tiempo.
});

DB::statement(<<<'SQL'
    CREATE UNIQUE INDEX lona_requests_agent_tipo_pendiente_unique
    ON lona_requests (agent_id, operation_type)
    WHERE estado = 'pendiente' AND deleted_at IS NULL
SQL);
```

`lona_batches.lona_request_id` es nullable porque la asignación **inicial** la hace owner/admin directamente, sin solicitud previa del agente (primer requisito del enunciado: "el admin o el owner deben poner... cuántas lonas... se le están dando al agente").

## 5.3 Regla de bloqueo de solicitudes (CERRADO — corregido por auditoría C-1)

```php
// App\Services\Lonas\LonaEligibilityService
public function canRequestMore(User $agent, OperationType $type): bool
{
    $tienePendientesDeColocar = LonaUnit::query()
        ->where('agent_id', $agent->id)
        ->where('operation_type', $type->value)
        ->where('status', LonaUnitStatus::PendienteColocacion->value)
        ->exists();

    $tieneSolicitudPendiente = LonaRequest::query()
        ->where('agent_id', $agent->id)
        ->where('operation_type', $type->value)
        ->where('estado', LonaRequestStatus::Pendiente->value)
        ->exists();

    return ! $tienePendientesDeColocar && ! $tieneSolicitudPendiente;
}
```

Venta y renta son contadores **independientes** — el enunciado lo dice explícitamente ("hasta que tenga colocadas todas sus lonas de venta puede solicitar más lonas de venta; lo mismo para renta"). El agente puede tener venta bloqueada y renta disponible al mismo tiempo.

**Hallazgo crítico de auditoría (C-1) corregido:** la versión original sólo miraba `LonaUnit` pendientes — un agente con 0 unidades pendientes podía enviar múltiples `LonaRequest` redundantes del mismo tipo antes de que la primera se revisara. Se agrega el segundo chequeo (`$tieneSolicitudPendiente`) como validación de UX (falla rápido con un mensaje claro), respaldado por el índice único parcial de 5.2 como garantía real a nivel de base de datos — el chequeo en PHP por sí solo no cierra la ventana de carrera bajo concurrencia real.

## 5.4 Captura de evidencia — sólo cámara en vivo, sin selector de archivos (CERRADO — corregido por auditoría M-1/M-2)

**Decisión de diseño central de este RFC.** Un `<input type="file" accept="image/*" capture="environment">` sólo es una *sugerencia* al navegador — en Android/iOS suele abrir la cámara, pero el usuario puede tener la opción "Archivos" disponible según el navegador/OS, y en desktop el atributo `capture` se ignora casi siempre. No es una garantía, es una pista.

La forma de **eliminar por completo** la posibilidad de elegir una foto de galería es no ofrecer ningún selector de archivos: capturar el frame directamente con `getUserMedia` + `<canvas>` y subir el resultado como base64. No existe UI para "elegir archivo" porque no existe ningún `<input type=file>` en el DOM.

```php
// app/Livewire/Lonas/CapturePlacementEvidence.php
namespace App\Livewire\Lonas;

use App\Models\{LonaUnit, Property};
use Illuminate\Validation\Rule;
use Livewire\Component;

class CapturePlacementEvidence extends Component
{
    public LonaUnit $lonaUnit;
    public ?string $photoData = null; // data:image/jpeg;base64,... o data:image/png;base64,...
    public ?int $propertyId = null;   // dónde se coloca ESTA unidad — independiente del inmueble del QR (ver 5.1)
    public ?string $ubicacionReferencia = null;

    public function mount(LonaUnit $lonaUnit): void
    {
        $this->authorize('place', $lonaUnit);
        $this->lonaUnit = $lonaUnit;
    }

    public function confirmPlacement(): void
    {
        $this->authorize('place', $this->lonaUnit);

        $this->validate([
            'photoData' => [
                'required',
                'string',
                'max:7000000', // ~5MB de binario en base64 — evita agotar memoria en addMediaFromBase64()
                function ($attribute, $value, $fail) {
                    if (! str_starts_with($value, 'data:image/jpeg;base64,') && ! str_starts_with($value, 'data:image/png;base64,')) {
                        $fail('La evidencia debe ser una foto JPEG o PNG capturada desde la cámara.');
                    }
                },
            ],
            'propertyId' => ['nullable', 'integer', Rule::exists(Property::class, 'id')->where('status', \App\Enums\PropertyStatus::Publicado->value)],
            'ubicacionReferencia' => ['nullable', 'string', 'max:255', 'required_without:propertyId'],
        ]);

        $this->lonaUnit
            ->addMediaFromBase64($this->photoData)
            ->toMediaCollection('evidencia');

        $this->lonaUnit->update([
            'status' => \App\Enums\LonaUnitStatus::Colocada,
            'placed_at' => now(),
            'property_id' => $this->propertyId,
            'ubicacion_referencia' => $this->ubicacionReferencia,
        ]);

        $this->dispatch('lona-placed');
    }

    public function render()
    {
        return view('livewire.lonas.capture-placement-evidence', [
            'properties' => Property::published()->where('agent_id', $this->lonaUnit->agent_id)->orderBy('title')->get(),
        ]);
    }
}
```

**Corrección de auditoría (M-2) — validación de Base64:** la regla `starts_with:data:image/jpeg;base64,data:image/png;base64,` propuesta inicialmente en la revisión tiene un defecto: como los prefijos de un data-URI ya contienen una coma propia (`data:image/jpeg;base64,`), y la regla `starts_with` de Laravel separa alternativas por coma, el último fragmento tras explotar la cadena queda vacío (`""`) — y **todo string empieza con la cadena vacía**, por lo que esa regla no habría bloqueado nada. Se reemplaza por un **closure inline** en el array de reglas (`function ($attribute, $value, $fail) { ... }`) con comparación directa vía `str_starts_with()` — la forma nativa de Laravel para reglas ad-hoc; no existe `Rule::make()` para closures en `Illuminate\Validation\Rule` (verificado contra `vendor/laravel/framework` del proyecto), así que se evita esa API inexistente.

**Corrección de auditoría (M-1) — inmueble/ubicación en el momento de colocar:** se agregan `propertyId` (opcional, sólo inmuebles publicados **del agente autenticado**) y `ubicacionReferencia` (texto libre, obligatorio si no se eligió inmueble) al propio flujo de captura — ver razonamiento en 5.1.

```blade
{{-- resources/views/livewire/lonas/capture-placement-evidence.blade.php --}}
<div x-data="lonaCapture()" x-init="startCamera()">
    <video x-ref="video" autoplay playsinline muted class="w-full rounded"></video>
    <canvas x-ref="canvas" class="hidden"></canvas>

    <button type="button" x-show="!captured" @click="capture()">Capturar foto</button>
    <img x-show="captured" :src="dataUrl" class="w-full rounded">

    <button type="button" x-show="captured" @click="retake()">Repetir</button>

    <div x-show="captured">
        <label>Inmueble (opcional, sólo publicados)</label>
        <select wire:model="propertyId">
            <option value="">— Sin inmueble asociado —</option>
            @foreach($properties as $property)
                <option value="{{ $property->id }}">{{ $property->title }}</option>
            @endforeach
        </select>

        <label>Referencia de ubicación (si no eliges inmueble)</label>
        <input type="text" wire:model="ubicacionReferencia" placeholder="Ej. Av. Reforma 123, esquina con...">
    </div>

    <button type="button" x-show="captured" @click="$wire.set('photoData', dataUrl); $wire.confirmPlacement()">
        Confirmar colocación
    </button>
</div>

<script>
function lonaCapture() {
    return {
        stream: null, captured: false, dataUrl: null,
        async startCamera() {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }, audio: false,
            });
            this.$refs.video.srcObject = this.stream;
        },
        capture() {
            const v = this.$refs.video, c = this.$refs.canvas;
            c.width = v.videoWidth; c.height = v.videoHeight;
            c.getContext('2d').drawImage(v, 0, 0);
            this.dataUrl = c.toDataURL('image/jpeg', 0.85);
            this.captured = true;
        },
        retake() { this.captured = false; this.dataUrl = null; },
    };
}
</script>
```

**Límite honesto de esta garantía (ver R-2):** no hay forma 100% infalsificable en un navegador — un usuario con hardware/software de "cámara virtual" a nivel de sistema operativo podría inyectar un feed falso a `getUserMedia`. Ese ataque requiere herramientas fuera del alcance normal de un agente inmobiliario y queda documentado como riesgo residual aceptado, no resuelto.

## 5.5 Armado automático del PDF (CERRADO — corregido por auditoría M-3)

Mismo patrón que `PropertyPdfController` (Épica 4): Gate → cargar relaciones → `Pdf::loadView()` → guardar/descargar.

```php
// App\Services\Lonas\LonaBatchApprovalService
namespace App\Services\Lonas;

use App\Models\{LonaBatch, LonaRequest, LonaUnit, User, Property};
use App\Enums\{LonaUnitStatus, OperationType};
use App\Notifications\LonaRequestApprovedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Illuminate\Validation\ValidationException;

class LonaBatchApprovalService
{
    // El tipo venta/renta reutiliza OperationType (I-1). El PDF+QR es endroid v6 (CD-2).
    public function grant(User $agent, OperationType $type, int $cantidad, User $authorizedBy, ?Property $property = null, ?LonaRequest $request = null): LonaBatch
    {
        // Corrección de auditoría (M-3): sin esto, un admin podía asignar un lote a un
        // usuario sin rol agente o suspendido.
        if (! $agent->hasRole('agente') || ! $agent->isActive()) {
            throw ValidationException::withMessages([
                'agent' => 'Sólo se pueden asignar lonas a un agente activo.',
            ]);
        }

        $batch = LonaBatch::create([
            'agent_id' => $agent->id,
            'lona_request_id' => $request?->id,
            'operation_type' => $type->value,
            'cantidad' => $cantidad,
            'created_by' => $authorizedBy->id,
        ]);

        // Corrección de auditoría (M-1): ya NO se copia property_id del lote a la unidad.
        // Cada LonaUnit nace sin inmueble/ubicación — el agente la fija al colocarla
        // (5.4). El inmueble aquí abajo ($property) es sólo el destino del QR del PDF.
        LonaUnit::insert(array_fill(0, $cantidad, [
            'lona_batch_id' => $batch->id,
            'agent_id' => $agent->id,
            'operation_type' => $type->value,
            'status' => LonaUnitStatus::PendienteColocacion->value,
            'created_at' => now(), 'updated_at' => now(),
        ]));

        $qrDataUri = $property
            ? (new Builder(
                data: $property->canonical(),
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 600,
            ))->build()->getDataUri()
            : null;

        $pdf = Pdf::loadView('pdf.lona-design', [
            'agent' => $agent,
            'operationType' => $type,
            'property' => $property,
            'qrDataUri' => $qrDataUri,
        ])->setPaper([0, 0, 2551, 3402]); // 90cm × 120cm en puntos (1cm ≈ 28.3465pt) — tamaño físico de lona (CD-6 cerrada); diseño gráfico del contenido pendiente (R-1)

        $batch->addMediaFromString($pdf->output())
            ->usingFileName("lona-{$tipo->value}-{$batch->id}.pdf")
            ->toMediaCollection('diseno-pdf');

        if ($request) {
            $request->update(['estado' => \App\Enums\LonaRequestStatus::Aprobada->value, 'reviewed_by' => $authorizedBy->id, 'reviewed_at' => now()]);
            $agent->notify(new LonaRequestApprovedNotification($batch));
        }

        return $batch;
    }
}
```

`resources/views/pdf/lona-design.blade.php` recibe fondo base (`public/images/lona-base-{venta|renta}.png`, pendiente de Diseño — R-1), nombre/teléfono/correo del agente superpuestos, y el QR (`$qrDataUri`) sólo si `$property` no es null.

## 5.6 Página del agente — `AgentLonas` (CERRADO, mismo patrón que RFC-061)

```php
// app/Filament/Pages/AgentLonas.php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class AgentLonas extends Page
{
    protected static ?string $navigationLabel = 'Mis Lonas';
    protected static ?string $slug = 'mis-lonas';
    protected static string $view = 'filament.pages.agent-lonas';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('agente') ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Widgets\AgentLonaUnitsWidget::class];
    }
}
```

`AgentLonaUnitsWidget` lista las `LonaUnit` del agente agrupadas por tipo y estado, con botón "Registrar evidencia" (abre `CapturePlacementEvidence`) por unidad pendiente, y botón "Solicitar más lonas" por tipo — deshabilitado si `LonaEligibilityService::canRequestMore()` es `false`, con el texto explicando por qué (mismo criterio de estado-vacío-explícito que `AgentZonesWidget` en RFC-061).

## 5.7 Resources Filament del lado admin/owner (CERRADO — corregido por auditoría C-2/Mn-1)

- `LonaBatchResource` — asignación manual inicial. Formulario: agente (select, sólo usuarios con rol `agente` y activos — refuerza 5.5), tipo, cantidad (`required`, `integer`, **`max:50`** — cierra CD-5/R-4), inmueble opcional (select filtrado a `Property::published()`). Al guardar, invoca `LonaBatchApprovalService::grant()` sin `$request`.
- `LonaRequestResource` — bandeja de solicitudes de los agentes. Tabla con badges por `estado`. Acciones: "Aprobar" (mismo formulario que arriba, precargado con agente/tipo/cantidad de la solicitud, invoca `grant()` con `$request`), "Rechazar" (motivo obligatorio, `estado = rechazada`).

Ambos gateados por el permiso `lonas.manage` (owner/admin) vía **`LonaBatchPolicy`** y **`LonaRequestPolicy`** respectivamente, navigationGroup `'Operación'` — mismo grupo que `PropertyResource`.

**Hallazgo crítico de auditoría (C-2) corregido:** el RFC original omitía `LonaBatchPolicy` — Filament resuelve la autorización de un Resource a partir de la Policy del modelo asociado, así que sin ella `LonaBatchResource` quedaba con autorización indefinida. Se agrega al árbol de archivos (sección "Alcance Técnico").

**Recomendación opcional de auditoría aplicada:** `LonaUnitPolicy`, `LonaRequestPolicy` y `LonaBatchPolicy` retornan explícitamente `false` en `forceDelete()` — sólo soft delete, para preservar la trazabilidad histórica de auditoría operacional.

---

# Alcance Técnico

## Árbol de archivos

```
Crear:
  database/migrations/2026_07_13_000001_create_lona_batches_table.php
  database/migrations/2026_07_13_000002_create_lona_units_table.php
  database/migrations/2026_07_13_000003_create_lona_requests_table.php
  app/Models/LonaBatch.php
  app/Models/LonaUnit.php
  app/Models/LonaRequest.php
  app/Enums/LonaTipo.php
  app/Enums/LonaUnitStatus.php
  app/Enums/LonaRequestStatus.php
  app/Services/Lonas/LonaEligibilityService.php
  app/Services/Lonas/LonaRequestService.php (I-5)
  app/Services/Lonas/LonaBatchApprovalService.php
  app/Policies/LonaBatchPolicy.php
  app/Policies/LonaUnitPolicy.php
  app/Policies/LonaRequestPolicy.php
  app/Livewire/Lonas/CapturePlacementEvidence.php
  resources/views/livewire/lonas/capture-placement-evidence.blade.php
  app/Filament/Pages/AgentLonas.php
  app/Filament/Widgets/AgentLonaUnitsWidget.php
  resources/views/filament/pages/agent-lonas.blade.php
  resources/views/filament/widgets/agent-lona-units.blade.php
  app/Filament/Resources/LonaBatchResource.php (+ Pages)
  app/Filament/Resources/LonaRequestResource.php (+ Pages)
  app/Notifications/LonaRequestSubmittedNotification.php
  app/Notifications/LonaRequestApprovedNotification.php
  app/Notifications/LonaRequestRejectedNotification.php
  resources/views/pdf/lona-design.blade.php
  tests/Feature/Lonas/LonaEligibilityTest.php
  tests/Feature/Lonas/LonaRequestApprovalTest.php
  tests/Feature/Lonas/LonaEvidenceCaptureTest.php

Modificar:
  composer.json (agregar endroid/qr-code)
  database/seeders/PermissionSeeder.php (agregar lonas.manage, lonas.place)
```

## Archivos que NO se tocan

```
app/Models/User.php                          ← se lee phone/email, no se agregan columnas
app/Models/Property.php                       ← se consume canonical()/scopePublished(), sin cambios
app/Http/Controllers/PropertyPdfController.php ← sólo referencia de patrón, no se modifica
app/Filament/Pages/AgentDashboard.php         ← RFC-061, sin relación funcional con este RFC
```

---

# Plan de Implementación por Lotes

```
Lote A → Lote B → Lote C → Lote D → Lote E
Datos     Evidencia  Aprobación  Admin UI   Tests
```

## Lote A — Modelos, migraciones, enums, permisos, policies

**Archivos:** migraciones (incluye el índice único parcial de 5.2), `LonaBatch`/`LonaUnit`/`LonaRequest`, enums, `PermissionSeeder`, `LonaBatchPolicy`/`LonaUnitPolicy`/`LonaRequestPolicy` (con `forceDelete()` en `false`).
**DoD:** migraciones corren limpio contra `inmo_test`; el índice único parcial rechaza una segunda `LonaRequest` "pendiente" del mismo agente+tipo a nivel de base de datos; `php artisan permission:cache-reset` refleja `lonas.manage`/`lonas.place`.

## Lote B — Captura de evidencia (agente)

**Archivos:** `CapturePlacementEvidence` + vista (con selector de inmueble/ubicación), `LonaUnitPolicy`.
**Puntos críticos:** ningún `<input type=file>` en el DOM; `getUserMedia` con `facingMode: environment`; validación de `photoData` con closure inline (no `starts_with:` con comas) y `max:7000000`; `addMediaFromBase64` guarda en colección `evidencia`; `property_id`/`ubicacion_referencia` se graban recién aquí, nunca antes.
**DoD:** marcar una unidad como colocada exige foto capturada en vivo; sin foto, el botón "Confirmar" no habilita envío; sin inmueble ni ubicación de referencia, tampoco.

## Lote C — Solicitud y aprobación

**Archivos:** `LonaEligibilityService`, `LonaBatchApprovalService`, `LonaRequestPolicy`, notificaciones.
**DoD:** solicitud rechazada por validación si hay unidades pendientes del mismo tipo; aprobación genera batch + N unidades + PDF adjunto en Media Library.

## Lote D — UI Filament (admin/owner + agente)

**Archivos:** `LonaBatchResource`, `LonaRequestResource`, `AgentLonas` + widget.
**DoD:** owner/admin asigna lonas iniciales y aprueba solicitudes desde el panel; agente ve su estado y solicita reposición sólo cuando aplica.

## Lote E — Tests y regresión

**Archivos:** los tres archivos de test listados en Alcance Técnico.
**DoD:** suite verde sobre PostgreSQL+PostGIS real (`inmo_test`), sin afectar RFC-061 ni Épica 4.

---

# Criterios de Aceptación / Casos QA

_(Rango oficial `QA-151` a `QA-167` — Épica 9, Operación de Campo. CD-4 cerrada.)_

| ID | Caso | Verificación |
| :---- | :---- | :---- |
| QA-151 | Asignación inicial | Owner/admin asigna N lonas de venta y M de renta a un agente; se crean N+M `LonaUnit` en `pendiente_colocacion` |
| QA-152 | Bloqueo de solicitud | Agente con al menos 1 unidad `pendiente_colocacion` de tipo venta no puede crear una `LonaRequest` de venta |
| QA-153 | Independencia venta/renta | Agente con venta bloqueada puede solicitar renta si toda su renta está colocada |
| QA-154 | Evidencia obligatoria | No se puede marcar una unidad como `colocada` sin `photoData` válido |
| QA-155 | Sin selector de archivos | La vista de captura no renderiza ningún `<input type=file>` |
| QA-156 | Aprobación genera PDF | Al aprobar una solicitud, se genera un PDF y queda adjunto en la colección `diseno-pdf` del batch |
| QA-157 | QR opcional | Si la solicitud/asignación incluye un inmueble publicado, el PDF contiene un QR hacia `canonical()`; si no, el PDF se genera sin QR |
| QA-158 | Sólo inmuebles publicados | El selector de inmueble en el formulario de aprobación no ofrece propiedades no publicadas |
| QA-159 | Notificación al admin | Al crear una `LonaRequest`, owner/admin reciben notificación |
| QA-160 | Notificación al agente | Al aprobar/rechazar, el agente recibe notificación con el resultado |
| QA-161 | Aislamiento de acceso | Un agente no puede acceder a `LonaBatchResource`/`LonaRequestResource` (403) |
| QA-162 | Rechazo con motivo | Rechazar una solicitud exige `motivo_rechazo`; el agente lo ve en su notificación |
| QA-163 | Bloqueo de solicitudes redundantes | Un agente con una `LonaRequest` en `pendiente` no puede crear otra del mismo tipo (validación de servicio + índice único parcial) |
| QA-164 | Colocación con inmueble o ubicación propios | Al registrar evidencia, el agente asocia un inmueble publicado propio o una referencia de texto; esos datos son independientes del inmueble usado en el QR del PDF |
| QA-165 | Rechazo de payload inválido | `photoData` que no empiece con `data:image/jpeg;base64,` o `data:image/png;base64,`, o que exceda el tamaño máximo, es rechazado |
| QA-166 | Sólo agentes activos reciben lonas | `LonaBatchApprovalService::grant()` rechaza asignar un lote a un usuario sin rol `agente` o suspendido |
| QA-167 | Autorización de `LonaBatchResource` | Un agente no puede acceder a `LonaBatchResource` (403, vía `LonaBatchPolicy`) |

## Tests de referencia (PHPUnit 12 nativo)

```php
// tests/Feature/Lonas/LonaEligibilityTest.php
public function test_agent_cannot_request_more_venta_with_pending_units(): void
public function test_agent_can_request_renta_while_venta_is_blocked(): void
public function test_agent_cannot_create_second_pending_request_of_same_type(): void
public function test_concurrent_requests_of_same_type_are_blocked_by_unique_index(): void

// tests/Feature/Lonas/LonaRequestApprovalTest.php
public function test_approving_request_creates_batch_and_units(): void
public function test_approving_request_with_published_property_embeds_qr(): void
public function test_approving_request_without_property_generates_pdf_without_qr(): void
public function test_property_selector_excludes_unpublished_properties(): void
public function test_rejecting_request_requires_a_reason(): void
public function test_grant_rejects_user_without_agente_role(): void
public function test_grant_rejects_suspended_agent(): void
public function test_grant_does_not_copy_qr_property_into_unit_property(): void

// tests/Feature/Lonas/LonaEvidenceCaptureTest.php
public function test_cannot_mark_unit_placed_without_photo_data(): void
public function test_marking_unit_placed_stores_media_and_updates_status(): void
public function test_non_owner_agent_cannot_place_evidence_on_others_unit(): void
public function test_rejects_photo_data_with_invalid_mime_prefix(): void
public function test_rejects_photo_data_larger_than_max_size(): void
public function test_placement_requires_property_or_ubicacion_referencia(): void
```

---

# Riesgos Técnicos y Mitigaciones

## R-1 — Plantilla base de la lona ✅ CERRADA

**Riesgo original:** el diseño gráfico de fondo (imagen NewHauz) era un entregable de Diseño, no de ingeniería; sin él, el PDF real no podía validarse visualmente. **Resolución (2026-07-13):** Diseño entregó los assets finales — `public/images/brand/fondo_lonas.jpg` (fondo, exportado 1:1 a 2551×3402px, exactamente el tamaño de página en puntos) y `public/images/brand/Logo_lonas.svg` (logo, colores `#f4960e`/`#fff`) — más un PDF de referencia del layout esperado. `resources/views/pdf/lona-design.blade.php` se reescribió con ese layout real (marco redondeado naranja, logo centrado, tipo VENTA/RENTA a página completa, datos del agente, caja de QR montando la esquina inferior derecha del marco, leyenda). Ver decisión I-9. La mitigación original (pipeline aislado del asset) se cumplió: el reemplazo fue sólo cambiar la vista y los assets estáticos, sin tocar `LonaBatchApprovalService` ni el resto del pipeline.

## R-2 — Evidencia por cámara no es criptográficamente infalsificable

**Riesgo:** `getUserMedia` elimina el selector de archivos, pero no impide un feed de cámara virtual a nivel de sistema operativo. **Mitigación:** aceptado como riesgo residual — el objetivo del RFC es impedir la falsificación *trivial* (elegir foto de galería), no un ataque con herramientas de suplantación de hardware. Si se requiere más adelante, evaluar sello de tiempo/marca de agua quemada en el canvas antes de `toDataURL` (no forma parte de este RFC).

## R-3 — `endroid/qr-code` es una dependencia nueva

**Riesgo:** agregar un paquete no evaluado antes en el proyecto. **Mitigación:** paquete sin dependencias de sistema adicionales (usa GD/Imagick, ya presentes para Media Library), licencia MIT, mantenimiento activo. Confirmar en Lote A antes de avanzar a Lote C.

## R-4 — Tamaño de lote no acotado ✅ CERRADA

**Riesgo original:** nada impedía que owner/admin asignara una `cantidad` desproporcionada (ej. 500 lonas) generando igual número de `LonaUnit` e infladas consultas. **Resolución (auditoría de diseño):** cerrado en `max:50` por lote (sección 5.7). Cierra también CD-5.

## R-5 — Condición de carrera en solicitudes concurrentes ✅ CERRADA

**Riesgo original (hallazgo crítico C-1 de la auditoría):** `canRequestMore()` sólo evaluaba `LonaUnit` pendientes; un agente podía enviar varias `LonaRequest` redundantes del mismo tipo antes de que la primera se revisara. **Resolución:** doble capa — chequeo de servicio (`LonaEligibilityService`, sección 5.3) para UX, y el índice único parcial `lona_requests_agent_tipo_pendiente_unique` (sección 5.2) como garantía real a nivel de PostgreSQL, inmune a condiciones de carrera bajo concurrencia.

## R-6 — Falta de `LonaBatchPolicy` ✅ CERRADA

**Riesgo original (hallazgo crítico C-2 de la auditoría):** `LonaBatchResource` no tenía Policy asociada; Filament requiere una para resolver autorización. **Resolución:** se agrega `app/Policies/LonaBatchPolicy.php` al árbol de archivos y al Lote A (sección 5.7).

---

# Decisiones Diferidas / Abiertas

| # | Tema | Estado | Destino |
| :---- | :---- | :---- | :---- |
| CD-1 | ¿Épica 9 nueva o anexar a Épica 7? | **✅ Cerrada** — Épica 9 (Operación de Campo), decisión del Product Owner. Ver `docs/rfc/EPICA-9-OPERACION-DE-CAMPO.md` | — |
| CD-2 | Confirmar `endroid/qr-code` vs. alternativa | **✅ Cerrada en Lote C** — instalado `endroid/qr-code ^6.0`, sin dependencias de sistema extra (usa GD, ya presente por medialibrary). API v6 confirmada contra el `vendor/` real: `new Builder(data:, encoding:, errorCorrectionLevel: ErrorCorrectionLevel::High)->build()->getDataUri()` (distinta a la `Builder::create()` que asumía el diseño). QR embebido en el PDF de dompdf como data URI, verificado por test. | — |
| CD-3 | ¿Se exige georreferenciación (GPS) además de la foto? | Abierta — no incluida en este RFC | Aditivo a `lona_units` si se aprueba |
| CD-4 | Rango oficial de IDs QA | **✅ Cerrada** — `QA-151` a `QA-167` (Épica 9; Épica 8 llega hasta `QA-150`) | — |
| CD-5 | Cantidad máxima de lonas por lote/asignación | **✅ Cerrada** — `max:50` por lote (sección 5.7, R-4 cerrada) | — |
| CD-6 | Tamaño físico real de la lona (para `setPaper()` del PDF) | **✅ Cerrada** — 90cm × 120cm (`[0,0,2551,3402]` en puntos). El *diseño gráfico del contenido* sigue en R-1 | — |

---

# Checklist de Cierre Técnico

## Pre-commit

- [ ] Migraciones corren contra `inmo_test` sin error, incluido el índice único parcial de `lona_requests`
- [ ] `LonaEligibilityService` bloquea solicitudes con unidades pendientes Y con solicitudes pendientes del mismo tipo (R-5)
- [ ] Captura de evidencia sin `<input type=file>` en el DOM (QA-155)
- [ ] Validación de `photoData` usa un closure inline con `str_starts_with`, no `starts_with:` con comas
- [ ] `LonaUnit.property_id`/`ubicacion_referencia` sólo se escriben desde `CapturePlacementEvidence`, nunca desde `grant()`
- [ ] `LonaBatchApprovalService::grant()` rechaza agentes sin rol `agente` o suspendidos (M-3)
- [ ] `LonaBatchPolicy`, `LonaUnitPolicy`, `LonaRequestPolicy` existen y `forceDelete()` retorna `false`
- [ ] `LonaBatchApprovalService` genera PDF con y sin QR según haya inmueble
- [ ] Permisos `lonas.manage`/`lonas.place` seedeados y aplicados en Policies
- [ ] `php artisan test --filter=Lonas` en verde

## Pre-merge (QA)

- [ ] QA-151 al QA-167 verificados manualmente

## Post-merge

- [ ] Merge `feature/control-lonas-asignadas` → `develop`
- [x] CD-1 resuelta — Épica 9 (Operación de Campo)

---

# Estimación

Arquitectura: Kristian

Duración estimada: 1.5–2 Sprints (tres modelos nuevos, componente de cámara sin precedente en el proyecto, generación de PDF+QR nueva).

Complejidad: Media-Alta. El mayor riesgo no es técnico sino de dependencia externa (R-1, plantilla gráfica) y decisiones de negocio abiertas (CD-3, CD-6) que conviene cerrar antes de Lote D.

---

# Registro de Cambios desde la Auditoría

| # | Hallazgo | Tipo | Cambio aplicado |
| :---- | :---- | :---- | :---- |
| C-1 | `canRequestMore()` no evita solicitudes concurrentes/redundantes del mismo tipo | Crítico | Agregado chequeo de `LonaRequest` pendiente en el servicio (5.3) **+** índice único parcial `lona_requests_agent_tipo_pendiente_unique` en PostgreSQL (5.2) como garantía real ante condiciones de carrera — el chequeo en PHP solo no la cierra |
| C-2 | Falta `LonaBatchPolicy` | Crítico | Agregada al árbol de archivos, al Lote A y a la sección 5.7 |
| M-1 | `LonaUnit.property_id` no se puede fijar por unidad al colocarla; se confundía con el inmueble del QR | Medio | Separados los dos conceptos (5.1): el inmueble del QR se decide al aprobar el lote; `property_id`/`ubicacion_referencia` de cada `LonaUnit` se fijan sólo en `CapturePlacementEvidence` (5.4), nunca copiados desde el lote |
| M-2 | Validación de `photoData` con `starts_with:` y comas — la regla propuesta en la revisión inicial no bloqueaba nada por un tercer prefijo vacío | Medio | Reemplazada por un closure inline con `str_starts_with()` explícito + `max:7000000` (5.4). **Nota:** la corrección aplicada difiere de la que propuso literalmente la auditoría, tanto por el defecto de sintaxis detectado en su propia regla como porque `Rule::make()` (mi primer intento de corrección) tampoco existe en `Illuminate\Validation\Rule` — verificado contra el `vendor/` real del proyecto antes de cerrar esta sección |
| M-3 | `LonaBatchApprovalService::grant()` no valida rol/estado del agente | Medio | Agregada validación `hasRole('agente')` + `isActive()` al inicio de `grant()` (5.5) |
| Mn-1 | Sin tope máximo de `cantidad` por lote | Menor | `max:50` en el formulario de Filament (5.7); cierra CD-5 y R-4 |
| Op-1 | Deshabilitar `forceDelete()` en las Policies (recomendación opcional) | Opcional | Aplicada — las tres Policies (`LonaBatchPolicy`, `LonaUnitPolicy`, `LonaRequestPolicy`) retornan `false` en `forceDelete()` |

# Hallazgos No Aplicados / Divergencias con la Auditoría

| # | Hallazgo de la auditoría | Decisión |
| :---- | :---- | :---- |
| Ar-1 | CD-2 marcada "cerrada técnicamente" (confirmación de `endroid/qr-code`) | **No aceptada tal cual.** La auditoría de diseño no instaló ni ejecutó el paquete — es una evaluación en el papel, sin evidencia de ejecución. Se mantiene CD-2 abierta hasta que el Lote A la confirme contra código real, siguiendo el mismo criterio que ya tenía este RFC antes de la auditoría |

Todas las demás recomendaciones obligatorias y la recomendación opcional fueron aplicadas sin objeción.

# Decisiones de Implementación (Etapa 4 · Lote A)

Ajustes al diseño surgidos de leer el código real del proyecto durante la implementación. Ninguno cambia el comportamiento especificado; alinean el RFC con las convenciones vigentes.

| # | Decisión | Motivo |
| :---- | :---- | :---- |
| I-1 | **No se crea `LonaTipo`.** El tipo venta/renta reutiliza `App\Enums\OperationType` (ya existente, usado en `Property.operation_type`). Las columnas se llaman `operation_type`, no `tipo`. | Evita un enum gemelo idéntico y calca la convención de nombres de `properties`. |
| I-2 | **Orden de migraciones corregido:** `lona_requests` (000001) → `lona_batches` (000002) → `lona_units` (000003). | `lona_batches.lona_request_id` es FK a `lona_requests`; la tabla referenciada debe existir antes. El orden original del RFC (batches primero) fallaba al migrar. |
| I-3 | **Las 3 policies se registran explícitamente en `app/Providers/AppServiceProvider.php`** vía `Gate::policy()`. | Este proyecto no usa auto-descubrimiento de policies (todas las existentes se registran ahí). `AppServiceProvider.php` se agrega a "Modificar". |
| I-4 | Las tres tablas usan `softDeletes()`; el índice único parcial incluye `AND deleted_at IS NULL`. | Coherente con `Lead`/`Property` (soft delete) y con la recomendación opcional Op-1; evita que una solicitud borrada bloquee el slot único. |
| I-5 | **Se agrega `app/Services/Lonas/LonaRequestService.php`** (`submit()`), no previsto en el árbol original. La creación de solicitudes del lado del agente (elegibilidad + notificación a owner/admin + traducción de la colisión del índice único a `ValidationException`) es un dominio propio, separado de `LonaEligibilityService` (sólo consulta) y de `LonaBatchApprovalService` (lado admin). | Mantiene los servicios con una responsabilidad clara cada uno. |
| I-6 | El PDF de la lona (placeholder R-1) fija la caja de diseño en `height: 4536px` para llenar los 3402pt de alto de la página (1pt ≈ 1.333px a 96dpi). | Que el marco enmarque toda la lona 90×120; el arte final de Diseño reemplaza el layout sin tocar el pipeline. |
| I-7 | `AgentLonaUnitsWidget` se excluye del auto-descubrimiento (`$isDiscovered = false`) y se registra sólo como header widget de `AgentLonas`. Se actualizó `tests/Feature/Auth/PermissionSeederTest.php` (9 → 11 permisos) por los dos permisos nuevos. | Evita que la tabla del agente aparezca en el dashboard general; el test del seeder afirma la matriz exacta de permisos y debía reflejar los nuevos. |
| I-8 | El inmueble del QR en `grant()` puede ser **cualquier inmueble publicado del sistema** (no se restringe al agente receptor), pero **debe estar publicado**. El inmueble sugerido por el agente en `submit()` sí debe ser **publicado y propio**. | El admin puede querer promocionar un desarrollo destacado ajeno al agente; el agente sólo sugiere sobre su propia cartera. |
| I-9 | **Diseño real de la lona (cierra R-1).** `resources/views/pdf/lona-design.blade.php` reescrito con los assets finales de Diseño: `public/images/brand/fondo_lonas.jpg` (fondo full-bleed, 2551×3402px — 1:1 con la página en puntos, sin reescalado) y `public/images/brand/Logo_lonas.svg` (dompdf v3.1.5 renderiza SVG embebido correctamente, verificado con un render aislado antes de integrarlo). Posiciones absolutas calculadas en puntos sobre el lienzo 2551×3402 (no `%`/`transform`, poco fiables en dompdf). Tipografía: `Helvetica`/`Arial` bold (igual que `property-sheet.blade.php`) — no `Montserrat` (la fuente que visualmente más se parece al diseño de referencia) porque el proyecto sólo tiene `.woff2` compilados por Vite y no había herramienta de conversión a `.ttf`/`.otf` disponible; swap trivial a futuro si se agregan los archivos de fuente reales. **Cambio de comportamiento respecto al placeholder:** el marco ya no cambia de color por tipo (verde/azul) — el diseño real usa un único marco naranja de marca para venta y renta por igual; sólo cambia la palabra ("VENTA"/"RENTA"). La caja de QR se dimensionó para enmarcar exactamente la esquina inferior-derecha del marco (mismo criterio visual que la referencia); sin inmueble, se omiten la caja y su leyenda, no queda un hueco vacío. | Assets y layout final entregados el 2026-07-13; verificado visualmente contra el PDF de referencia con Venta+QR, Renta sin teléfono ni QR, y nombre/email largos, sin overflow. |

---

# Correcciones desde la Auditoría de Implementación (Etapa 5)

Auditoría en `docs/audits/RFC-062-auditoria-implementacion.md` — veredicto: aprobación condicionada. Hallazgos aplicados:

| # | Hallazgo | Severidad | Corrección aplicada |
| :---- | :---- | :---- | :---- |
| M-IMP-1 | El `property_id` de la solicitud del agente sólo se filtraba en las opciones del select de Filament; un payload Livewire manipulado podía colar el inmueble de otro agente o uno no publicado. | Medio (bloqueante) | Invariante movido al dominio: `LonaRequestService::submit()` exige que el inmueble sugerido sea **publicado y del propio agente**, sino `ValidationException`. Tests negativos: propiedad ajena, no publicada, y bypass vía widget (`test_agent_cannot_smuggle_another_agents_property_via_the_widget`). |
| Mn-IMP-1 | `grant()` no revalidaba que el inmueble del QR estuviera publicado. | Menor | `grant()` exige `PropertyStatus::Publicado` si hay inmueble (decisión I-8). Test `test_grant_rejects_an_unpublished_qr_property`. |
| — (recomendación) | El tope `cantidad ≤ 50` vivía sólo en los forms de Filament. | Recomendado | Invariante `1..50` agregado a `submit()` y `grant()` en el servicio. Tests `..._rejects_cantidad_over_the_maximum`. |
| Mn-IMP-2 | `migrate:fresh --seed` roto en `ZoneSeeder` (Polygon vs MultiPolygon). | Menor, **fuera de alcance** | Preexistente, no de RFC-062. No se toca aquí; queda para un fix aparte del seed geográfico. |

Riesgo residual documentado (aceptado): la garantía "sólo cámara" es de UI (no hay `<input type=file>`, la fuente es `getUserMedia`), **no** una prueba forense — un feed de cámara virtual o un payload Livewire manipulado pueden inyectar una imagen. Ya estaba en R-2.

---

# Hallazgos Post-Cierre (Producción)

Detectados después del cierre técnico, al desplegar en producción.

## H-PROD-1 — `Class "Endroid\QrCode\Builder\Builder" not found` en producción

**Síntoma:** al asignar un lote desde `LonaBatchResource`, 500 en `/livewire/update`. **Causa:** `endroid/qr-code` está en `composer.json`/`composer.lock` desde el merge de este RFC, pero el deploy de producción no corrió `composer install` después de actualizar el código — `vendor/endroid` nunca se instaló ahí. **No es un bug de código.** Acción operativa: correr `composer install --no-dev -o` (o el comando de deploy equivalente) en el servidor de producción.

## H-PROD-2 — `grant()` podía dejar un lote huérfano sin PDF si la generación fallaba ✅ CORREGIDO

**Hallazgo real, encontrado al diagnosticar H-PROD-1.** `LonaBatchApprovalService::grant()` creaba el `LonaBatch` y sus `LonaUnit` dentro de una transacción (`DB::transaction`), pero `attachDesignPdf()` (que arma el PDF con el QR) se llamaba **después**, fuera de esa transacción. El incidente de H-PROD-1 lo confirmó en producción: el `INSERT` de `lona_batches` y de `lona_units` habían corrido y comprometido antes de que la clase del QR fallara — quedó un lote con unidades `pendiente_colocacion` reales, pero sin ningún PDF adjunto, indistinguible en la UI de un lote entregado correctamente.

**Corrección:** `attachDesignPdf()` se movió **adentro** de la misma `DB::transaction()` (sección 5.5 / `app/Services/Lonas/LonaBatchApprovalService.php`). Ahora, si la generación del PDF falla por cualquier motivo, toda la operación (lote + unidades + actualización de la solicitud) se revierte — no puede quedar un lote a medias. Regresión completa (128 tests) verde tras el cambio.

**Recuperación necesaria en producción (dato existente, no se corrige con el fix de código):** el lote huérfano que ya se creó durante el incidente (agente id 3, `venta`, cantidad 1, sin PDF) sigue en la base y su unidad sigue contando como "pendiente de colocación" para ese agente — hay que decidir si se borra (lote + su unidad) o se le regenera el PDF manualmente una vez actualizado el código en el servidor. **Resuelto:** confirmado por soft-delete en producción (`LonaBatch::find(2)` sin media, `units()->delete()` + `delete()`), verificado con `withTrashed()->find(2)->getFirstMedia('diseno-pdf')` → `null`.

## H-PROD-3 — Ajuste de diseño de la lona a partir del PDF real generado en producción

Feedback sobre `lona-venta-3.pdf` (primer PDF real generado en producción tras H-PROD-1/H-PROD-2): la palabra VENTA/RENTA y el teléfono debían ser mucho más grandes (casi al ancho del marco), los datos del agente debían quedar anclados cerca del borde inferior del marco (no colgando a media altura), y faltaba un degradado navy de arriba hacia abajo hasta aprox. la mitad de la lona.

- **Tipografía:** `.tipo` (VENTA/RENTA) de `380pt` a `600pt`; `.phone` de `140pt` a `300pt`.
- **Bloque de datos del agente** (teléfono/nombre/email/sitio) reposicionado en bloque, ahora terminando a ~120pt del borde inferior del marco en vez de quedar pegado justo debajo del logo con un vacío grande abajo.
- **Degradado navy:** se intentó primero con CSS `linear-gradient()` — **dompdf no lo soporta** (verificado con un render aislado: la página salió en blanco antes de integrarlo a la vista real). Se resolvió generando `public/images/brand/lona-gradient-overlay.png` con GD: un PNG con canal alfa, color `#050f38` (navy de marca, `--color-navy-900` en `resources/css/app.css`), opaco arriba y transparente lineal hacia abajo, alto = mitad de la página (1701pt de 3402pt). Se superpone como `<img>` entre el fondo y el marco. Primera iteración con una curva `pow(t, 1.4)` se desvanecía demasiado rápido (visualmente perdía fuerza a los ~25-30% de la altura); se cambió a una curva **lineal**, que sí sostiene la oscuridad hasta cerca de la mitad real de la lona, como se pidió.

| # | Hallazgo | Tipo | Cambio aplicado |
| :---- | :---- | :---- | :---- |
| H-PROD-3 | Tipografía chica, datos del agente sin anclar al marco, sin degradado navy | Ajuste de diseño (feedback directo sobre PDF real) | `resources/views/pdf/lona-design.blade.php` reescrito con las medidas de arriba; nuevo asset `public/images/brand/lona-gradient-overlay.png` |

Verificado visualmente: Venta con QR y teléfono, Renta sin teléfono/QR y nombre largo — sin overflow, degradado visible hasta la mitad de la lona en ambos. Regresión: 17 tests (`LonaRequestApprovalTest` + `LonaResourcesTest`) verde, Pint limpio.

## H-PROD-4 — Formato de teléfono mexicano en la lona

Último ajuste de diseño (cierra la lona al 100%): el teléfono debía imprimirse formateado, no como dígitos corridos — `442-119-09-59` (grupos 3-3-2-2) para números normales, y `55-10-10-10-10` (grupos de 2) para Ciudad de México (LADA 55).

**Implementación:** `App\Support\MexicanPhoneFormatter::format()` (nueva clase, mismo patrón que `App\Support\PropertySlugGenerator`) — limpia el valor a dígitos, quita el `52`/`+52` de país si viene incluido, y aplica el agrupamiento según si el número de 10 dígitos empieza con `55`. Si el resultado no tiene exactamente 10 dígitos reconocibles, devuelve el valor original sin tocar (evita cortar mal un número con formato no estándar, ej. 01800). Consumida sólo en `resources/views/pdf/lona-design.blade.php`; no cambia cómo se guarda `User::$phone`, sólo cómo se imprime en la lona.

**Tests:** `tests/Unit/MexicanPhoneFormatterTest.php` (6 casos: formato regular, CDMX, entrada con formato previo, con código de país, valor no reconocible, `null`). Verificado también con render real: `442-119-09-59` y `55-10-10-10-10` renderizan exactos, sin desbordar el marco.

## H-PROD-5 — Cambio de regla de negocio: tope de 5 sin colocar por tipo, con reposición

**Cambio de negocio pedido en producción.** La regla original de elegibilidad era binaria: un agente no podía pedir más lonas de un tipo mientras tuviera **cualquier** unidad de ese tipo sin colocar. Se reemplaza por un **tope de 5 lonas sin colocar por tipo, con reposición**: colocar una lona con evidencia libera un cupo. Es decir, `disponible = 5 − (unidades sin colocar de ese tipo)`; las colocadas ya no cuentan. Ejemplo pedido: si el agente ya justificó 4 con evidencia (le queda 1 sin colocar), puede pedir 4; si justificó 1, puede pedir 1. Venta y renta se cuentan por separado; sigue habiendo una sola solicitud pendiente por tipo a la vez.

- **`LonaEligibilityService`** (reescrito): `CAP_PER_TYPE = 5`, `uncolocatedCount()`, `availableToRequest()` (0 si hay solicitud pendiente; si no, `max(0, CAP − sin colocar)`), y `canRequestMore()` = `availableToRequest > 0`.
- **`LonaRequestService::submit()`**: valida `cantidad` contra el cupo disponible del agente (reemplaza el viejo tope fijo de 50).
- **`LonaBatchApprovalService::grant()`**: valida que `sin colocar + cantidad ≤ CAP` (aplica tanto a aprobar una solicitud como a la asignación directa del admin). Inyecta `LonaEligibilityService`.
- **UI:** el form "Solicitar más" del agente y el de aprobación del admin muestran el cupo disponible en `helperText` y lo aplican como `maxValue` dinámico; `LonaBatchResource` limita `cantidad` a `CAP_PER_TYPE`.

Deja obsoleta la vieja formulación de CD-5/R-4 ("tope por lote de 50"): el tope real de dominio ahora es 5 sin colocar por tipo. Tests reescritos en `LonaEligibilityTest` (incluye el escenario exacto de reposición) y ampliados en `LonaRequestApprovalTest` (tope en `grant()`).

## H-PROD-6 — Botón "Confirmar colocación" invisible en el modal de evidencia ✅ CORREGIDO

**Síntoma reportado (con captura):** al registrar evidencia, sólo se veía el botón "Cerrar"; no aparecía el de guardar/confirmar. **Causa:** el botón usaba utilidades Tailwind arbitrarias (`bg-green-600 text-white`) en un blade que se renderiza **dentro de un modal de Filament**, cuyo bundle de CSS no compila `bg-green-600`. Resultado: fondo transparente + texto blanco = botón invisible sobre el fondo blanco del modal (el botón "Repetir", con `border`, sí se veía porque `border` sí está compilado). **Corrección:** los botones del componente usan ahora **estilos inline** (`background-color`/`color`/`padding`…), garantizando visibilidad en cualquier contexto sin depender del CSS compilado. Verificado en vivo: el botón "Confirmar colocación" renderiza verde, ancho completo, con texto blanco.

**Bonus (robustez del guardado):** de paso, `confirmPlacement()` ahora recibe la foto **como argumento** (`$wire.confirmPlacement(dataUrl)`) en vez de depender de un `$wire.set('photoData', ...)` previo — así la foto viaja en la misma petición que la llamada, sin la carrera en que el método podía ejecutarse antes de sincronizar la propiedad. El botón muestra estado "Guardando…" y se deshabilita durante el envío. Tests de `LonaEvidenceCaptureTest` actualizados al nuevo contrato (foto por argumento).

## H-PROD-7 — Bandeja de evidencias para owner/admin

**Necesidad detectada en producción:** el agente registraba evidencia, pero owner/admin no tenían dónde verla para verificarla visualmente. La idea inicial era un "tab" dentro de Lonas asignadas; se resolvió con un **recurso de navegación propio** ("Evidencias", grupo Operación) porque los tabs de Filament filtran registros del mismo recurso (serían lotes), mientras que la evidencia vive a nivel de `LonaUnit` colocada — un recurso dedicado da grilla + filtros que un tab no.

- **`LonaEvidenceResource`** (sólo lectura, model `LonaUnit`): `getEloquentQuery()` acotado a unidades `colocada`. Columnas: miniatura de la foto (`SpatieMediaLibraryImageColumn` sobre la colección `evidencia`), agente, tipo (badge), inmueble/`ubicacion_referencia`, `placed_at`. Filtros por agente y por tipo. Acción "Ver evidencia" → modal con la foto a tamaño completo + datos (`resources/views/filament/lonas/evidence-view-modal.blade.php`). Gateado por `lonas.manage` (el agente ve las suyas en "Mis Lonas", no esta bandeja global); sin create/edit/delete.
- **Nota de rendimiento:** la columna usa la imagen original (sin conversión `thumb`), para que funcione con toda la evidencia ya existente sin depender de `media-library:regenerate`. Si el volumen crece, se puede agregar una conversión `thumb` a `LonaUnit` y regenerar.

Tests: `tests/Feature/Filament/LonaEvidenceResourceTest.php` (acceso owner/admin sí, agente 403; sólo lista colocadas, no pendientes; filtro por agente).

---

Estado: ✅ IMPLEMENTADO, auditado y cerrado técnicamente. Ver `docs/cierres/RFC-062-cierre-tecnico.md`. Hallazgos post-cierre en producción documentados arriba (H-PROD-1 a H-PROD-7).

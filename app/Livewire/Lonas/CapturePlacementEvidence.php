<?php

namespace App\Livewire\Lonas;

use App\Enums\LonaUnitStatus;
use App\Enums\PropertyStatus;
use App\Models\LonaUnit;
use App\Models\Property;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Captura de evidencia de colocación de una lona.
 *
 * Diseño central (RFC-062 5.4): la foto se obtiene SÓLO de la cámara en vivo
 * (getUserMedia + canvas → base64). No existe ningún <input type=file> en la vista,
 * así que no hay forma de elegir una imagen de galería. El `property_id`/
 * `ubicacion_referencia` se fijan aquí — dónde se colocó ESTA unidad — y son
 * independientes del inmueble usado en el QR del PDF del lote.
 */
class CapturePlacementEvidence extends Component
{
    public LonaUnit $lonaUnit;

    /** data:image/jpeg;base64,... o data:image/png;base64,... */
    public ?string $photoData = null;

    /** Inmueble donde se coloca esta unidad (opcional, sólo publicados del agente). */
    public ?int $propertyId = null;

    /** Referencia de texto si no se asocia un inmueble del sistema. */
    public ?string $ubicacionReferencia = null;

    public bool $placed = false;

    public function mount(LonaUnit $lonaUnit): void
    {
        $this->authorize('place', $lonaUnit);
        $this->lonaUnit = $lonaUnit;
    }

    public function confirmPlacement(string $photoData = ''): void
    {
        $this->authorize('place', $this->lonaUnit);

        // La foto llega como argumento desde el canvas (ver vista): así viaja en la misma
        // petición que la llamada al método, sin depender de un $wire.set previo que
        // podría no haberse sincronizado todavía.
        $this->photoData = $photoData;

        // Idempotencia: una unidad ya colocada no se re-coloca (evita pisar la
        // evidencia y el placed_at originales).
        if ($this->lonaUnit->isPlaced()) {
            $this->addError('lonaUnit', 'Esta lona ya fue registrada como colocada.');

            return;
        }

        $validated = $this->validate();

        // allowedMimeTypes: segunda capa — addMediaFromBase64 rechaza si el binario
        // decodificado no es realmente JPEG/PNG, no sólo por el prefijo del data-URI.
        $this->lonaUnit
            ->addMediaFromBase64($validated['photoData'], 'image/jpeg', 'image/png')
            ->usingFileName('evidencia-'.$this->lonaUnit->id.'.jpg')
            ->toMediaCollection('evidencia');

        $this->lonaUnit->update([
            'status' => LonaUnitStatus::Colocada,
            'placed_at' => now(),
            'property_id' => $this->propertyId,
            'ubicacion_referencia' => $this->ubicacionReferencia,
        ]);

        $this->placed = true;
        $this->dispatch('lona-placed');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'photoData' => [
                'required',
                'string',
                'max:7000000', // ~5MB de binario en base64 — evita agotar memoria al decodificar
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! str_starts_with((string) $value, 'data:image/jpeg;base64,')
                        && ! str_starts_with((string) $value, 'data:image/png;base64,')) {
                        $fail('La evidencia debe ser una foto JPEG o PNG capturada desde la cámara.');
                    }
                },
            ],
            'propertyId' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')
                    ->where('status', PropertyStatus::Publicado->value)
                    ->where('agent_id', $this->lonaUnit->agent_id),
            ],
            'ubicacionReferencia' => ['nullable', 'string', 'max:255', 'required_without:propertyId'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'photoData' => 'evidencia fotográfica',
            'propertyId' => 'inmueble',
            'ubicacionReferencia' => 'referencia de ubicación',
        ];
    }

    public function render(): mixed
    {
        return view('livewire.lonas.capture-placement-evidence', [
            'properties' => Property::published()
                ->where('agent_id', $this->lonaUnit->agent_id)
                ->orderBy('title')
                ->get(),
        ]);
    }
}

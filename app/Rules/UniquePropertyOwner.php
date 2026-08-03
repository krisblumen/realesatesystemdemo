<?php

namespace App\Rules;

use App\Models\PropertyOwner;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class UniquePropertyOwner implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(private ?int $ignoreId = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Alcanza con UNO de los dos: un cliente cargado sólo con teléfono
        // sigue siendo el mismo cliente, y exigir ambos dejaría pasar
        // justamente el caso más común.
        $duplicate = PropertyOwner::findDuplicate(
            $this->data['phone'] ?? null,
            $this->data['email'] ?? null,
            $this->ignoreId,
        );

        if (! $duplicate) {
            return;
        }

        $fail($this->mensajeDe($duplicate));
    }

    /**
     * El aviso dice QUIÉN lo tiene y CÓMO ubicarlo.
     *
     * Es público porque lo usa también `EnforcesClientOwnership`, que valida en
     * el guardado de la página. Los dos caminos tienen que decir exactamente lo
     * mismo: si divergen, el agente ve un texto distinto según por dónde
     * entró el rechazo.
     *
     * Antes, al agente se le devolvía un mensaje neutro «contacta a un
     * administrador», a propósito, para no exponer datos del otro agente. Se
     * revierte por pedido del owner: el objetivo del aviso es que los dos
     * asesores se pongan de acuerdo, y mandarlos a un tercero para averiguar un
     * nombre agrega un paso sin proteger nada que el equipo no comparta.
     */
    public function mensajeDe(PropertyOwner $duplicate): string
    {
        $agente = $duplicate->agent;

        if ($agente === null) {
            return 'Ese cliente ya está registrado y todavía no tiene asesor asignado. Contacta a un administrador para que te lo asigne.';
        }

        // El teléfono es opcional en el perfil del usuario, así que puede
        // faltar. Nombrar al asesor igual sirve —ya sabe a quién buscar— y
        // decir «teléfono: —» sería ruido.
        $contacto = filled($agente->phone)
            ? " Puedes comunicarte al {$agente->phone}."
            : ' Búscalo en el directorio del equipo para coordinar.';

        return "Ese cliente ya está registrado por {$agente->name}.{$contacto}";
    }
}

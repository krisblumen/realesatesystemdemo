<?php

namespace App\Filament\Resources\PropertyOwnerResource\Pages\Concerns;

use App\Models\PropertyOwner;
use App\Models\User;
use App\Rules\UniquePropertyOwner;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;

trait EnforcesClientOwnership
{
    /**
     * El aviso que muestra el modal. Público porque Livewire tiene que
     * conservarlo entre el momento en que se detecta el duplicado y el momento
     * en que se dibuja el modal.
     */
    public string $avisoClienteDuplicado = '';

    /**
     * El modal del cliente ya registrado.
     *
     * Se dibuja con el mismo componente que las confirmaciones del panel —el de
     * publicar una página, por ejemplo— para que el agente lo reconozca. Pero
     * NO es una confirmación: no hay nada que aceptar ni forma de seguir
     * adelante, así que lleva un solo botón. Dejar un «Confirmar» prometería
     * que se puede registrar igual.
     */
    public function clienteDuplicadoAction(): Action
    {
        return Action::make('clienteDuplicado')
            ->modalHeading('Ese cliente ya está registrado')
            ->modalDescription(fn (): string => $this->avisoClienteDuplicado)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Entendido');
    }

    /**
     * Fuerza que el cliente sea del agente que lo está creando (owner y admin sí
     * eligen) y rechaza duplicados —mismo teléfono o mismo email— entre todos
     * los agentes.
     *
     * El rechazo se cuenta DOS veces a propósito: un modal, que es imposible de
     * pasar por alto, y el error en los dos campos que identifican al cliente,
     * que deja marcado dónde está el dato repetido cuando el modal se cierra.
     *
     * LAS CLAVES DEL ERROR VAN CON PREFIJO `data.`, y no es un detalle:
     * Filament nombra así los campos del formulario, y un mensaje emitido como
     * `phone` no encuentra dónde mostrarse. El guardado se frenaba igual —el
     * duplicado nunca se creó— pero en pantalla no pasaba NADA: el agente veía
     * que el botón no respondía, sin forma de saber que el cliente ya estaba
     * tomado.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enforceClientOwnership(array $data, ?int $ignoreId = null): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'data.agent_id' => 'Debes iniciar sesión para gestionar propietarios.',
            ]);
        }

        if (! $user->hasAnyRole(['owner', 'admin'])) {
            $data['agent_id'] = $user->id;
        }

        $duplicado = PropertyOwner::findDuplicate(
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $ignoreId,
        );

        if ($duplicado !== null) {
            $mensaje = (new UniquePropertyOwner($ignoreId))->mensajeDe($duplicado);

            $this->avisoClienteDuplicado = $mensaje;
            $this->mountAction('clienteDuplicado');

            throw ValidationException::withMessages([
                'data.phone' => $mensaje,
                'data.email' => $mensaje,
            ]);
        }

        return $data;
    }
}

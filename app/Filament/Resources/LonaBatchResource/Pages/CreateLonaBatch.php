<?php

namespace App\Filament\Resources\LonaBatchResource\Pages;

use App\Enums\OperationType;
use App\Filament\Resources\LonaBatchResource;
use App\Models\Property;
use App\Models\User;
use App\Services\Lonas\LonaBatchApprovalService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLonaBatch extends CreateRecord
{
    protected static string $resource = LonaBatchResource::class;

    /**
     * La asignación no es un simple insert: delega en el servicio que crea el lote,
     * sus N unidades físicas y arma el PDF (con QR si hay inmueble). El servicio
     * valida que el destinatario sea un agente activo (M-3).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $agent = User::query()->findOrFail($data['agent_id']);
        $property = ! empty($data['property_id'])
            ? Property::query()->find($data['property_id'])
            : null;

        return app(LonaBatchApprovalService::class)->grant(
            agent: $agent,
            type: OperationType::from($data['operation_type']),
            cantidad: (int) $data['cantidad'],
            authorizedBy: auth()->user(),
            property: $property,
        );
    }
}

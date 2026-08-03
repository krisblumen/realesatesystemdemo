<?php

namespace App\Filament\Resources\ContratoIntermediacionResource\Pages;

use App\Filament\Resources\ContratoIntermediacionResource;
use App\Models\User;
use App\Services\Contratos\ContratoCreacionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContrato extends CreateRecord
{
    protected static string $resource = ContratoIntermediacionResource::class;

    /**
     * El folio y el estado los fija el servicio ANTES del insert, con retry ante colisión
     * del índice UNIQUE (C-3). No se usa afterCreate para el folio.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(ContratoCreacionService::class)->crear($data, $actor);
    }
}

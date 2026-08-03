<?php

namespace App\Filament\Resources\ContratoIntermediacionResource\Pages;

use App\Enums\EstadoContrato;
use App\Filament\Resources\ContratoIntermediacionResource;
use App\Models\ContratoIntermediacion;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewContrato extends ViewRecord
{
    protected static string $resource = ContratoIntermediacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Ver el contrato ANTES de mandarlo: para revisar los datos y para
            // mostrárselo al cliente en pantalla. Hasta ahora la única forma de
            // verlo era enviarlo, que es justo lo que uno quiere hacer último.
            Action::make('vistaPrevia')
                ->label('Vista previa')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->url(fn (): string => route('contratos.borrador', ['contrato' => $this->getRecord()]))
                ->openUrlInNewTab()
                // Deja de ofrecerse una vez firmado: ahí existe el documento
                // real y sellado, y mostrar un borrador sin firma al lado del
                // definitivo es una invitación a confundirlos.
                ->visible(fn (): bool => $this->getRecord()->estado !== EstadoContrato::Firmado
                    && (auth()->user()?->can('view', $this->getRecord()) ?? false)),

            // Enviar inmediatamente desde la pantalla de detalle (tras crear el contrato).
            Action::make('enviar')
                ->label('Enviar contrato')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->estado === EstadoContrato::Generado
                    && (auth()->user()?->can('enviar', $this->getRecord()) ?? false))
                ->action(fn () => $this->ejecutar(fn (ContratoIntermediacion $r) => ContratoIntermediacionResource::runEnviar($r))),

            Action::make('reenviar')
                ->label('Reenviar')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->getRecord()->estado, [EstadoContrato::Rechazado, EstadoContrato::Expirado], true)
                    && (auth()->user()?->can('enviar', $this->getRecord()) ?? false))
                ->action(fn () => $this->ejecutar(fn (ContratoIntermediacion $r) => ContratoIntermediacionResource::runReenviar($r))),

            Action::make('cancelar')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => auth()->user()?->can('cancel', $this->getRecord()) ?? false)
                ->action(fn () => $this->ejecutar(fn (ContratoIntermediacion $r) => ContratoIntermediacionResource::runCancelar($r))),
        ];
    }

    /** Ejecuta la acción sobre el registro y refresca la vista para reflejar el nuevo estado. */
    private function ejecutar(callable $callback): void
    {
        /** @var ContratoIntermediacion $record */
        $record = $this->getRecord();
        $callback($record);
        $record->refresh();
    }
}

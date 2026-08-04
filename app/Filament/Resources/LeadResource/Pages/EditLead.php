<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Models\User;
use App\Services\LeadAssignmentService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    /**
     * Publica a proposito: Livewire solo persiste propiedades publicas
     * entre requests (cada fillForm()/call() es un request simulado
     * distinto) -- una privada se pierde entre mount() y save().
     */
    public ?int $agentIdBeforeSave = null;

    private ?int $pendingAgentId = null;

    /**
     * Cambiar el agente desde el form de edicion (en vez del boton
     * "Reasignar") tiene que pasar por el mismo servicio que el boton,
     * asi ambos caminos dejan auditoria y notifican al agente. El agente
     * "anterior" se captura aca, al montar -- agent_id es un campo de
     * relacion (->relationship('agent', ...)) y Filament lo escribe en
     * $this->record apenas se llena el form, antes de guardar, asi que
     * leerlo en mutateFormDataBeforeSave() ya seria tarde.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->agentIdBeforeSave = $this->record->agent_id;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingAgentId = $data['agent_id'] ?? null;

        if ($this->pendingAgentId !== $this->agentIdBeforeSave) {
            // El campo agent_id ya quedo "sucio" en el modelo en memoria
            // (relacion BelongsTo) apenas se lleno el form -- sacarlo de
            // $data no alcanza, porque update() igual persiste atributos
            // sucios del modelo. Se revierte el atributo para que el
            // guardado normal no lo toque; reassign() en afterSave() hace
            // el cambio real, con auditoria y notificacion.
            $this->record->agent_id = $this->agentIdBeforeSave;
            unset($data['agent_id'], $data['assigned_at']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingAgentId === $this->agentIdBeforeSave || $this->pendingAgentId === null) {
            return;
        }

        $agent = User::find($this->pendingAgentId);

        if (! $agent) {
            return;
        }

        app(LeadAssignmentService::class)->reassign(
            $this->record,
            $agent,
            auth()->user(),
            'Asignación desde edición del lead.',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}

<div>
    {{-- Componente de captura por cámara en vivo (RFC-062 5.4). Tiene su propio botón
         de confirmación, por eso el modal no lleva submit action. --}}
    @livewire(\App\Livewire\Lonas\CapturePlacementEvidence::class, ['lonaUnit' => $unit], key('lona-evidence-'.$unit->id))
</div>

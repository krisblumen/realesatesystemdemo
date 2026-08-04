<div class="space-y-3">
    @php($url = $unit->getFirstMediaUrl('evidencia'))

    @if ($url)
        <img src="{{ $url }}" alt="Evidencia de colocación" style="width:100%;border-radius:0.5rem;">
    @else
        <p class="text-sm text-gray-500">Esta unidad no tiene evidencia registrada.</p>
    @endif

    <dl class="text-sm">
        <div class="flex justify-between border-b border-gray-100 py-1 dark:border-gray-700">
            <dt class="font-medium">Agente</dt>
            <dd>{{ $unit->agent?->name ?? '—' }}</dd>
        </div>
        <div class="flex justify-between border-b border-gray-100 py-1 dark:border-gray-700">
            <dt class="font-medium">Tipo</dt>
            <dd>{{ $unit->operation_type->label() }}</dd>
        </div>
        <div class="flex justify-between border-b border-gray-100 py-1 dark:border-gray-700">
            <dt class="font-medium">Colocada en</dt>
            <dd>{{ $unit->property?->title ?? $unit->ubicacion_referencia ?? '—' }}</dd>
        </div>
        <div class="flex justify-between py-1">
            <dt class="font-medium">Fecha</dt>
            <dd>{{ $unit->placed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        </div>
    </dl>
</div>

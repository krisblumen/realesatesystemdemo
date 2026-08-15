<?php

namespace App\Filament\Widgets;

use App\Enums\ZoneStatus;
use App\Models\Zone;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Las zonas que trabajan, con cuántos inmuebles tiene cada una.
 *
 * POR QUÉ ES UNA LISTA Y NO TARJETAS. `ZonesOverviewWidget` ya dice CUÁNTAS
 * zonas hay; eso es un número y no dice nada sobre el negocio. Lo que una
 * inmobiliaria mira es DÓNDE está su inventario: tres zonas con 38, 27 y 21
 * inmuebles cuentan una historia que «12 zonas activas» no cuenta.
 *
 * Los dos widgets conviven a propósito: responden preguntas distintas.
 *
 * SE ORDENAN POR INVENTARIO y se cortan en cinco. Con veinte zonas la lista se
 * vuelve un directorio, y para eso está la página de Zonas.
 */
class ZonasActivasWidget extends Widget
{
    protected static string $view = 'filament.widgets.zonas-activas';

    protected static ?int $sort = 13;

    private const CUANTAS = 5;

    /**
     * @return Collection<int, array{nombre: string, inmuebles: int}>
     */
    public function getZonas(): Collection
    {
        return Zone::query()
            ->where('status', ZoneStatus::Active->value)
            ->withCount('properties')
            ->orderByDesc('properties_count')
            ->orderBy('name')
            ->limit(self::CUANTAS)
            ->get()
            ->map(fn (Zone $zona): array => [
                'nombre' => $zona->name,
                'inmuebles' => (int) $zona->properties_count,
            ]);
    }

    public function getTotalDeZonas(): int
    {
        return Zone::where('status', ZoneStatus::Active->value)->count();
    }
}

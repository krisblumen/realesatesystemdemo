<?php

namespace App\Http\Controllers;

use App\Enums\OperationType;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Catálogo público: sólo inmuebles publicados, con filtros y paginación.
     */
    public function index(Request $request): View
    {
        $query = Property::query()->published()->with('zone');

        if ($operation = $request->string('operacion')->toString()) {
            $query->where('operation_type', $operation);
        }

        if ($type = $request->string('tipo')->toString()) {
            $query->where('property_type', $type);
        }

        if ($zone = $request->integer('zona')) {
            $query->where('zone_id', $zone);
        }

        if ($beds = $request->integer('recamaras')) {
            $query->where('bedrooms', '>=', $beds);
        }

        // Sólo oportunidades de inversión. Es el filtro al que lleva el botón de
        // esa sección del home: sin él, «ver todo» mandaba al catálogo entero y
        // la promesa de la sección se perdía en el camino.
        if ($request->string('oportunidad')->toString() === '1') {
            $query->where('is_opportunity', true);
        }

        // Rango de precio: "0-1500000", "1500000-3000000", "6000000+"
        if ($precio = $request->string('precio')->toString()) {
            if (str_ends_with($precio, '+')) {
                $query->where('price', '>=', (int) rtrim($precio, '+'));
            } elseif (str_contains($precio, '-')) {
                [$min, $max] = explode('-', $precio, 2);
                $query->whereBetween('price', [(int) $min, (int) $max]);
            }
        }

        $this->applySort($query, $request->string('orden')->toString());

        $properties = $query->paginate(9)->withQueryString();

        $zones = Zone::query()
            ->whereHas('properties', fn (Builder $q): Builder => $q->published())
            ->orderBy('name')
            ->get();

        // Últimas 3 publicadas con portada — carrusel de fondo del hero.
        $heroProperties = Property::query()
            ->published()
            ->whereHas('media', fn (Builder $q): Builder => $q->where('collection_name', 'cover'))
            ->latest()
            ->take(3)
            ->get();

        return view('inmuebles.index', [
            'properties' => $properties,
            'zones' => $zones,
            'operationOptions' => OperationType::cases(),
            'typeOptions' => PropertyType::cases(),
            'filters' => $request->only(['operacion', 'tipo', 'zona', 'recamaras', 'precio', 'orden', 'oportunidad']),
            'heroProperties' => $heroProperties,
        ]);
    }

    /**
     * Ficha pública del inmueble. Sólo publicados; sólo se muestra la zona.
     */
    public function show(Property $property): View
    {
        abort_unless($property->isPublished(), 404);

        $property->load('zone', 'agent');

        $related = Property::query()
            ->published()
            ->where('id', '!=', $property->id)
            ->where('zone_id', $property->zone_id)
            ->latest()
            ->take(3)
            ->with('zone')
            ->get();

        return view('inmuebles.show', [
            'property' => $property,
            'related' => $related,
        ]);
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'precio_asc' => $query->orderBy('price'),
            'precio_desc' => $query->orderByDesc('price'),
            'superficie' => $query->orderByDesc('construction_area'),
            default => $query->latest(),
        };
    }
}

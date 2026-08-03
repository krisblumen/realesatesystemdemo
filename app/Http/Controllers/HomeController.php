<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Project;
use App\Models\Property;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class HomeController extends Controller
{
    public function index(): View
    {
        $featured = Property::query()
            ->published()->featured()->with('zone')->latest()->take(3)->get();

        $opportunities = Property::query()
            ->published()->opportunity()->with('zone')->latest()->take(3)->get();

        // Zonas con inmuebles publicados — para el buscador del home.
        $searchZones = Zone::query()
            ->whereHas('properties', fn (Builder $q): Builder => $q->published())
            ->orderBy('name')
            ->get();

        // Proyectos destacados (A-74) para la sección de proyectos del home.
        $featuredProjects = Project::query()
            ->where('is_featured', true)
            ->with('projectType')
            ->latest()
            ->take(4)
            ->get();

        return view('welcome', [
            'featured' => $featured,
            'opportunities' => $opportunities,
            'searchZones' => $searchZones,
            'typeOptions' => PropertyType::cases(),
            'featuredProjects' => $featuredProjects,
        ]);
    }
}

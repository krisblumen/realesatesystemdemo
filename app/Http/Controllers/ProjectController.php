<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;

class ProjectController extends Controller
{
    /**
     * Listado público de proyectos (A-74 Arquitectura).
     */
    public function index(): View
    {
        $projects = Project::query()
            ->with('projectType')
            ->latest()
            ->get();

        return view('site.proyectos', [
            'projects' => $projects,
        ]);
    }

    /**
     * Ficha pública de un proyecto.
     */
    public function show(Project $project): View
    {
        $project->load('projectType');

        // Otros proyectos del mismo tipo para el bloque "relacionados".
        $related = Project::query()
            ->where('id', '!=', $project->id)
            ->when($project->project_type, fn ($q) => $q->where('project_type', $project->project_type))
            ->with('projectType')
            ->latest()
            ->take(3)
            ->get();

        return view('site.proyecto', [
            'project' => $project,
            'related' => $related,
        ]);
    }
}

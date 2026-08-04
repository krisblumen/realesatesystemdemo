<?php

namespace Database\Seeders;

use App\Models\ProjectType;
use Illuminate\Database\Seeder;

class ProjectTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'obra-nueva',               'label' => 'Obra nueva',               'color' => 'success'],
            ['code' => 'remodelacion',             'label' => 'Remodelación',             'color' => 'warning'],
            ['code' => 'urbanizacion',             'label' => 'Urbanización',             'color' => 'info'],
            ['code' => 'proyecto-arquitectonico',  'label' => 'Proyecto arquitectónico',  'color' => 'primary'],
            ['code' => 'renders',                  'label' => 'Renders',                  'color' => 'gray'],
            ['code' => 'desarrollo-habitacional',  'label' => 'Desarrollo habitacional',  'color' => 'success'],
            ['code' => 'desarrollo-comercial',     'label' => 'Desarrollo comercial',     'color' => 'info'],
            ['code' => 'desarrollo-industrial',    'label' => 'Desarrollo industrial',    'color' => 'gray'],
            ['code' => 'construccion-industrial',  'label' => 'Construcción industrial',  'color' => 'gray'],
            ['code' => 'construccion-residencial', 'label' => 'Construcción residencial', 'color' => 'success'],
            ['code' => 'remodelacion-residencial', 'label' => 'Remodelación residencial', 'color' => 'warning'],
            // Sugerencias adicionales — alineadas a los proyectos del sitio.
            ['code' => 'restauracion-patrimonial', 'label' => 'Restauración patrimonial', 'color' => 'danger'],
            ['code' => 'diseno-interiores',        'label' => 'Diseño de interiores',     'color' => 'primary'],
            ['code' => 'paisajismo',               'label' => 'Paisajismo',               'color' => 'success'],
        ];

        foreach ($types as $i => $type) {
            ProjectType::query()->firstOrCreate(
                ['code' => $type['code']],
                [...$type, 'sort_order' => $i + 1, 'active' => true],
            );
        }
    }
}

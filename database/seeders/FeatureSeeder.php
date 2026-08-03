<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            'Alberca',
            'Jardín',
            'Roof garden',
            'Seguridad 24/7',
            'Elevador',
            'Estacionamiento techado',
            'Cocina integral',
            'Cuarto de servicio',
            'Bodega',
            'Amueblado',
            'Aire acondicionado',
            'Calentador solar',
            'Cisterna',
            'Acceso controlado',
            'Área de juegos',
            'Gimnasio',
        ];

        foreach ($features as $name) {
            Feature::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}

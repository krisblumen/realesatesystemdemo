<?php

namespace Database\Seeders;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\UserStatus;
use App\Enums\ZoneStatus;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * El contenido de muestra con el que nace cada inquilino.
 *
 * NO USA FACTORIES NI FAKER, y son dos razones distintas.
 *
 * La técnica: `fakerphp/faker` es dependencia de desarrollo, así que en un
 * servidor instalado con `--no-dev` no existe. Este sembrador corre al
 * construir la plantilla, o sea EN PRODUCCIÓN, y ahí `fake()` no está.
 *
 * La de fondo, que importa más: esto es lo primero que ve la persona invitada.
 * Faker produce nombres de calle en inglés y párrafos de relleno; un demo con
 * eso adentro enseña un sistema que parece de prueba. Y siendo aleatorio,
 * cambia en cada reconstrucción de la plantilla — así que dos inquilinos creados
 * en semanas distintas ven cosas distintas y nadie puede dar soporte hablando
 * del mismo inmueble.
 *
 * Datos fijos: mismo contenido siempre, en español, y con la forma que el panel
 * produciría de verdad.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Los agentes de muestra comparten una contraseña conocida A PROPÓSITO.
     *
     * Sirven para que la persona invitada pueda entrar como agente y ver ese
     * lado del sistema —la vista por zona, los leads propios, los permisos
     * recortados— que desde el `owner` no se ve. Son contenido, no cuentas.
     *
     * Es aceptable porque cada inquilino vive en su propia base y el demo es un
     * entorno cerrado. El día que el demo se abra al público, esto se revisa.
     */
    private const PASSWORD_AGENTES = 'demo-agente';

    private const AGENTES = [
        ['Lucía Bermúdez', 'lucia@demo.local'],
        ['Marcos Iturbe', 'marcos@demo.local'],
        ['Renata Salgado', 'renata@demo.local'],
        ['Emiliano Cordero', 'emiliano@demo.local'],
        ['Paulina Reyes', 'paulina@demo.local'],
    ];

    private const CLIENTES = [
        ['Alfonso', 'Nájera', '4421100201', 'alfonso.najera@ejemplo.com'],
        ['Beatriz', 'Olmedo', '4421100202', 'beatriz.olmedo@ejemplo.com'],
        ['Carlos', 'Villaseñor', '4421100203', 'carlos.villasenor@ejemplo.com'],
        ['Daniela', 'Quiroga', '4421100204', 'daniela.quiroga@ejemplo.com'],
        ['Esteban', 'Marroquín', '4421100205', 'esteban.marroquin@ejemplo.com'],
        ['Fernanda', 'Lizárraga', '4421100206', 'fernanda.lizarraga@ejemplo.com'],
        ['Gonzalo', 'Peñaloza', '4421100207', 'gonzalo.penaloza@ejemplo.com'],
        ['Helena', 'Bustamante', '4421100208', 'helena.bustamante@ejemplo.com'],
        ['Ignacio', 'Ferreyra', '4421100209', 'ignacio.ferreyra@ejemplo.com'],
        ['Julieta', 'Sandoval', '4421100210', 'julieta.sandoval@ejemplo.com'],
    ];

    /**
     * @var array<int, array{0: string, 1: OperationType, 2: PropertyType, 3: int, 4: int, 5: float, 6: int, 7: int, 8: int}>
     */
    private const INMUEBLES = [
        ['Casa con jardín en privada', OperationType::Venta, PropertyType::Casa, 4_850_000, 3, 2.5, 2, 220, 185],
        ['Departamento con roof garden', OperationType::Venta, PropertyType::Departamento, 2_950_000, 2, 2, 1, 95, 95],
        ['Casa de una planta con patio', OperationType::Venta, PropertyType::Casa, 3_400_000, 3, 2, 2, 180, 140],
        ['Departamento amueblado céntrico', OperationType::Renta, PropertyType::Departamento, 18_000, 2, 1.5, 1, 78, 78],
        ['Residencia con alberca', OperationType::Venta, PropertyType::Casa, 9_800_000, 4, 3.5, 3, 420, 380],
        ['Loft en planta alta', OperationType::Renta, PropertyType::Departamento, 14_500, 1, 1, 1, 62, 62],
        ['Casa en esquina con estudio', OperationType::Venta, PropertyType::Casa, 5_600_000, 3, 3, 2, 260, 210],
        ['Terreno plano con servicios', OperationType::Venta, PropertyType::Terreno, 2_100_000, 0, 0, 0, 340, 0],
        ['Casa nueva lista para habitar', OperationType::Venta, PropertyType::Casa, 4_100_000, 3, 2.5, 2, 195, 165],
        ['Departamento con vista abierta', OperationType::Venta, PropertyType::Departamento, 3_250_000, 2, 2, 1, 102, 102],
        ['Casa con doble sala', OperationType::Venta, PropertyType::Casa, 6_900_000, 4, 3, 2, 310, 265],
        ['Departamento en renta amplio', OperationType::Renta, PropertyType::Departamento, 21_000, 3, 2, 2, 118, 118],
        ['Casa remodelada con terraza', OperationType::Venta, PropertyType::Casa, 5_200_000, 3, 2.5, 2, 240, 200],
        ['Local comercial a pie de calle', OperationType::Renta, PropertyType::Local, 32_000, 0, 1, 2, 150, 120],
        ['Casa con recámara en planta baja', OperationType::Venta, PropertyType::Casa, 7_450_000, 4, 3.5, 3, 330, 290],
        ['Departamento compacto bien ubicado', OperationType::Renta, PropertyType::Departamento, 12_800, 1, 1, 1, 55, 55],
        ['Terreno en zona en crecimiento', OperationType::Venta, PropertyType::Terreno, 1_650_000, 0, 0, 0, 280, 0],
        ['Casa familiar con jardín amplio', OperationType::Venta, PropertyType::Casa, 8_200_000, 4, 3, 3, 380, 320],
        ['Departamento con dos estacionamientos', OperationType::Venta, PropertyType::Departamento, 4_300_000, 3, 2, 2, 128, 128],
        ['Casa con oficina independiente', OperationType::Venta, PropertyType::Casa, 6_100_000, 3, 3, 2, 285, 245],
    ];

    public function run(): void
    {
        // Las zonas NO se fabrican acá: se usan las que ya sembró ZoneSeeder.
        //
        // Antes este sembrador creaba cuatro zonas propias y MORÍA en la primera
        // —`Zone.polygon` se castea a MultiPolygon y acá se pasaba un Polygon—
        // dejando 5 usuarios y cero inmuebles.
        //
        // El arreglo no fue envolver los polígonos, sino dejar de fabricar zonas
        // que la aplicación nunca fabricaría: las de ZoneSeeder llevan municipio
        // y código postal, como las que salen del panel.
        $zonas = Zone::query()->where('status', ZoneStatus::Active)->get();

        if ($zonas->isEmpty()) {
            $this->command?->error('No hay zonas activas. Corré ZoneSeeder antes que este sembrador.');

            return;
        }

        $agentes = collect(self::AGENTES)->map(fn (array $datos): User => tap(
            User::create([
                'name' => $datos[0],
                'email' => $datos[1],
                'password' => Hash::make(self::PASSWORD_AGENTES),
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
            ]),
            fn (User $agente) => $agente->assignRole('agente'),
        ));

        // Dos clientes por agente, repartidos en orden y no al azar: así el
        // reparto es el mismo en cada inquilino.
        $clientes = collect(self::CLIENTES)->map(fn (array $datos, int $i): PropertyOwner => PropertyOwner::create([
            'first_name' => $datos[0],
            'last_name' => $datos[1],
            'phone' => $datos[2],
            'email' => $datos[3],
            'agent_id' => $agentes[intdiv($i, 2)]->id,
        ]));

        foreach (self::INMUEBLES as $i => $inmueble) {
            [$titulo, $operacion, $tipo, $precio, $recamaras, $banos, $estacionamientos, $terreno, $construccion] = $inmueble;

            $cliente = $clientes[$i % $clientes->count()];

            Property::create([
                'title' => $titulo,
                'description' => 'Inmueble de demostración para conocer el sistema. '.
                    'Los datos son de muestra y no corresponden a una propiedad real.',
                'operation_type' => $operacion,
                'property_type' => $tipo,
                'status' => PropertyStatus::Borrador,
                'price' => $precio,
                'bedrooms' => $recamaras,
                'bathrooms' => $banos,
                'parking_spaces' => $estacionamientos,
                'land_area' => $terreno,
                'construction_area' => $construccion ?: null,
                'zone_id' => $zonas[$i % $zonas->count()]->id,
                'agent_id' => $cliente->agent_id,
                'owner_id' => $cliente->id,
                'commission_percentage' => 5.0,
            ]);
        }

        $this->command?->info(sprintf(
            'Demo: %d agentes, %d zona(s) existentes, %d clientes, %d inmuebles.',
            $agentes->count(),
            $zonas->count(),
            $clientes->count(),
            count(self::INMUEBLES),
        ));
    }
}

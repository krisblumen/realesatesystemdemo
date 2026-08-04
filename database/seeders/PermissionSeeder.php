<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public const GUARD = 'web';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'properties.manage',
        'owners.manage',
        'leads.manage',
        'zones.manage',
        'projects.manage',
        'lonas.manage',
        'lonas.place',
        'contratos.manage',              // generar / enviar / reenviar / ver listado (scoped)
        'contratos.cancel',              // cancelar (admin/owner)
        'contratos.ver-identificacion',  // ver ID/firma + confirmar eliminación (solo owner)
        'frontend.manage',               // CMS del frontend público (solo owner, Épica 12)
    ];

    /**
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'owner' => self::PERMISSIONS,
        'admin' => [
            'users.view',
            'users.create',
            'users.update',
            'properties.manage',
            'owners.manage',
            'leads.manage',
            'zones.manage',
            'projects.manage',
            'lonas.manage',
            'contratos.manage',
            'contratos.cancel',
        ],
        'agente' => [
            'properties.manage',
            'owners.manage',
            'leads.manage',
            'lonas.place',
            'contratos.manage',
        ],
        // Roles del área de proyectos (A-74): gestionan el módulo de Proyectos.
        'arquitectura' => [
            'projects.manage',
        ],
        'proyectos' => [
            'projects.manage',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => self::GUARD,
            ]);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => self::GUARD,
            ])->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

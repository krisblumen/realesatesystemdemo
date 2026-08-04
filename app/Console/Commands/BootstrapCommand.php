<?php

namespace App\Console\Commands;

use Database\Seeders\OwnerSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;

class BootstrapCommand extends Command
{
    protected $signature = 'app:bootstrap';

    protected $description = 'Migra la base, siembra roles/permisos y crea el owner inicial';

    public function handle(): int
    {
        $this->info('Migrando base de datos...');
        $this->call('migrate', ['--force' => true]);

        $this->info('Sembrando roles y permisos...');
        $this->call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

        $this->info('Creando owner inicial...');
        $this->call('db:seed', ['--class' => OwnerSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Bootstrap completo.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use App\Tenancy\AprovisionaInquilinos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Invita a alguien al demo: aprovisiona su inquilino e imprime el acceso.
 *
 * El acceso sale POR CONSOLA y no por correo. No es un atajo: quita el correo
 * como punto de falla. Un mensaje que cae en spam es una persona que quería
 * probar el producto y no pudo, con un inquilino aprovisionado ocupando lugar —
 * y en un demo eso es la mayor parte del embudo perdida en el último paso.
 *
 * De consola y no de la web a propósito: en fase 1 no hay registro público.
 */
class InvitarADemo extends Command
{
    protected $signature = 'demo:invitar
                            {email : Correo de la persona invitada}
                            {--dias= : Días de vida del inquilino (por defecto, el de configuración)}';

    protected $description = 'Da de alta un inquilino de demo y muestra su acceso';

    public function handle(AprovisionaInquilinos $alta): int
    {
        $email = (string) $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->components->error("«{$email}» no es un correo válido.");

            return self::FAILURE;
        }

        if ($this->yaTieneUnoActivo($email)) {
            $this->components->error("Ya hay un inquilino activo para «{$email}».");
            $this->line('  Si hace falta uno nuevo, hay que expirar el anterior primero.');

            return self::FAILURE;
        }

        if ($dias = $this->option('dias')) {
            config(['tenancy.dias_de_vida' => (int) $dias]);
        }

        $resultado = $alta->crear($email);

        $this->mostrarAcceso($resultado->tenant, $resultado->password);

        return self::SUCCESS;
    }

    private function yaTieneUnoActivo(string $email): bool
    {
        return Tenant::query()
            ->where('email', $email)
            ->whereIn('estado', [TenantEstado::Activo->value, TenantEstado::Aprovisionando->value])
            ->exists();
    }

    private function mostrarAcceso(Tenant $tenant, string $password): void
    {
        $dominio = config('tenancy.dominio_base', 'demo.localhost');

        $this->newLine();
        $this->components->info('Inquilino listo.');

        $this->table(['Dato', 'Valor'], [
            ['Dirección', "https://{$tenant->slug}.{$dominio}/admin"],
            ['Usuario', $tenant->email],
            ['Contraseña', $password],
            ['Vence', $tenant->expira_en->format('Y-m-d')],
        ]);

        // Se muestra UNA sola vez: en la base ya quedó hasheada. Si se pierde no
        // hay forma de recuperarla, sólo de regenerarla.
        $this->components->warn('La contraseña no se vuelve a mostrar. Copiala ahora.');

        $this->newLine();

        // El límite aceptado en RFC-14, trasladado a quien lo necesita saber. Un
        // límite conocido que no llega a quien sube los archivos no es un límite
        // aceptado: es un descuido con papeles.
        $this->components->warn('Aviso para la persona invitada:');
        $this->line('  No subir al demo nada que no pueda ser público. Las imágenes');
        $this->line('  publicadas se sirven sin pedir sesión, así que quien tenga la');
        $this->line('  URL puede verlas aunque el sitio esté cerrado.');
    }
}

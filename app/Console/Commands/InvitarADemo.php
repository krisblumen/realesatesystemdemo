<?php

namespace App\Console\Commands;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use App\Notifications\AltaDeDemoEntregada;
use App\Notifications\InvitacionAlDemo;
use App\Tenancy\AprovisionaInquilinos;
use App\Tenancy\LimiteAlcanzado;
use App\Tenancy\LimiteDeAltas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Throwable;

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

    public function handle(AprovisionaInquilinos $alta, LimiteDeAltas $limites): int
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

        // EL TOPE SE COMPRUEBA ACÁ Y NO ADENTRO DEL ALTA (RFC-10, regla 1).
        //
        // Cuando el registro público pase a la cola, `crear()` va a correr
        // dentro del trabajo — y ahí los límites no deben aplicarse: encolar
        // altas que van a fallar es acumular basura. El lugar del límite es el
        // registro, antes de encolar.
        //
        // La invitación no pasa origen: no lo tiene, y a quien invita lo limita
        // ser el operador. Lo que sí aplica es el tope duro, porque protege la
        // INSTANCIA —las 100 conexiones compartidas con producción— y eso no
        // depende de por dónde entró el alta.
        try {
            $limites->verificar();
        } catch (LimiteAlcanzado $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $resultado = $alta->crear($email);

        $this->mostrarAcceso($resultado->tenant, $resultado->password);

        $this->avisar($resultado->tenant, $resultado->password);

        return self::SUCCESS;
    }

    private function yaTieneUnoActivo(string $email): bool
    {
        return Tenant::query()
            ->where('email', $email)
            ->whereIn('estado', [TenantEstado::Activo->value, TenantEstado::Aprovisionando->value])
            ->exists();
    }

    /**
     * Los dos correos, DESPUÉS de mostrar el acceso en pantalla.
     *
     * El orden importa y viene de RFC-11: el correo va ADEMÁS, no en lugar de.
     * Es el eslabón que no controlamos —cae en spam, se demora, rebota— y si su
     * fallo tumbara el alta, cada problema de correo dejaría un inquilino
     * aprovisionado y a nadie adentro. Con el acceso ya impreso, quien invitó
     * puede entregarlo a mano.
     *
     * Por eso se atrapa y se avisa, en vez de dejar reventar.
     */
    private function avisar(Tenant $tenant, string $password): void
    {
        $operador = (string) config('tenancy.aviso_de_altas', '');

        try {
            Notification::route('mail', $tenant->email)
                ->notify(new InvitacionAlDemo($tenant, $password));

            if ($operador !== '') {
                Notification::route('mail', $operador)
                    ->notify(new AltaDeDemoEntregada($tenant));
            }
        } catch (Throwable $e) {
            $this->newLine();
            $this->components->warn('El inquilino se creó, pero el correo no salió: '.$e->getMessage());
            $this->line('  El acceso de arriba sigue siendo válido. Entregalo a mano.');
        }
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

            // DE QUÉ PLANTILLA NACIÓ, y no es un dato de curiosidad.
            //
            // Pasó en producción: se construyó una plantilla nueva, se apuntó el
            // `.env` sin limpiar la caché de configuración, y el alta siguió
            // usando la anterior. El comando dijo «inquilino listo» y el error se
            // descubrió recién al abrir el panel y ver contenido viejo.
            //
            // El dato ya existía —el padrón lo guarda— pero llegaba tarde:
            // después de haber invitado a alguien. Acá se ve en el momento.
            ['Plantilla', $tenant->template_version],
        ]);

        // Se muestra UNA sola vez: en la base ya quedó hasheada. Si se pierde no
        // hay forma de recuperarla, sólo de regenerarla.
        $this->components->warn('La contraseña no se vuelve a mostrar. Copiala ahora.');
        $this->line("  Si se pierde: `php artisan demo:reemitir-acceso {$tenant->slug}`.");

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

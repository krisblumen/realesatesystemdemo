<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SyncMailUnseenCountsCommand extends Command
{
    protected $signature = 'mail:sync-unseen';

    protected $description = 'Sincroniza el conteo de mails no leidos de los usuarios @newhauz.com.mx via doveadm.';

    public function handle(): int
    {
        $scriptPath = (string) config('mail_indicator.script_path');
        $sudoUser = (string) config('mail_indicator.sudo_user');
        $synced = 0;

        User::query()
            ->active()
            ->get()
            ->filter(fn (User $user): bool => $user->hasNewhauzMailbox())
            ->each(function (User $user) use ($scriptPath, $sudoUser, &$synced): void {
                // Corre como el usuario dueno de los Maildirs (vmail), no root:
                // ya tiene el permiso exacto que necesitamos y nada mas.
                $result = Process::run(['sudo', '-u', $sudoUser, $scriptPath, $user->email]);

                if (! $result->successful() || ! preg_match('/unseen=(\d+)/', $result->output(), $matches)) {
                    Log::warning('No se pudo sincronizar el buzon del usuario.', [
                        'user_id' => $user->id,
                        'error' => $result->errorOutput(),
                    ]);

                    return;
                }

                $user->forceFill([
                    'mail_unseen_count' => (int) $matches[1],
                    'mail_unseen_synced_at' => now(),
                ])->save();

                $synced++;
            });

        $this->info("{$synced} buzones sincronizados.");

        return self::SUCCESS;
    }
}

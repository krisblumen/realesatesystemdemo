<?php

namespace App\Tenancy;

use App\Models\EnlaceDeMuestra;
use Illuminate\Support\Str;

/**
 * Genera, revoca y canjea el enlace con el que se muestra el sitio.
 *
 * UNO SOLO A LA VEZ, y no es una limitación técnica sino una decisión: varios
 * enlaces activos son una lista que alguien tiene que administrar, y nadie lo va
 * a hacer en un demo de dos días. Generar uno nuevo revoca el anterior, que es
 * lo que alguien espera al decir «mandame otro».
 */
class CompartirElSitio
{
    /** La marca que queda en la sesión de quien canjeó un enlace válido. */
    public const CLAVE_DE_SESION = 'muestra_del_sitio';

    /**
     * @return string El token en claro, por única vez.
     */
    public function generar(): string
    {
        $this->revocar();

        $token = Str::random(40);

        EnlaceDeMuestra::create([
            'token_hash' => hash('sha256', $token),
            'expira_en' => now()->addDays((int) config('tenancy.dias_de_enlace', 7)),
        ]);

        return $token;
    }

    public function revocar(): void
    {
        EnlaceDeMuestra::query()->vigente()->update(['revocado_en' => now()]);
    }

    public function vigente(): ?EnlaceDeMuestra
    {
        return EnlaceDeMuestra::query()->vigente()->latest('id')->first();
    }

    /**
     * Si el token sirve. No dice POR QUÉ no sirve, a propósito: distinguir
     * «vencido» de «revocado» de «no existe» le diría a quien prueba tokens al
     * azar cuándo acertó la forma.
     */
    public function canjear(string $token): bool
    {
        return EnlaceDeMuestra::query()
            ->vigente()
            ->where('token_hash', hash('sha256', $token))
            ->exists();
    }
}

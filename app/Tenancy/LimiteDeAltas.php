<?php

namespace App\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Los topes que hacen seguro abrir el demo (RFC-10).
 *
 * EL RIESGO QUE CIERRAN. El demo comparte instancia de Postgres con la
 * producción de New Hauz y con el correo: las 100 conexiones son de todos. Un
 * registro abierto sin tope puede dejar sin conexiones al sitio que factura.
 * Ese es el riesgo de esta épica, y no depende de cuántos inquilinos haya sino
 * de cuántos existan a la vez.
 *
 * SE COMPRUEBAN ANTES DE ENCOLAR, nunca dentro del trabajo: encolar altas que
 * van a fallar es acumular basura (RFC-10, regla 1).
 *
 * Y CONTRA EL CONTEO REAL, no contra un contador que se pueda desincronizar
 * (regla 2). Cuesta una consulta y no miente nunca.
 */
class LimiteDeAltas
{
    /**
     * Convierte el origen en un hash con sal.
     *
     * Se guarda el hash y NO la dirección: alcanza para limitar altas repetidas
     * del mismo lugar, y no permite reconstruir el origen ni cruzarlo con otra
     * fuente. Es un dato personal menos que retener.
     */
    public function hashDe(string $origen): string
    {
        $sal = (string) Config::get('tenancy.limites.sal', '');

        if ($sal === '') {
            // SE NIEGA EN VEZ DE LIMITAR MAL. Hashear con sal vacía daría un
            // límite que parece funcionar y no protege de nada — y nadie se
            // entera. Es la misma regla del centinela: que falle fuerte.
            throw new RuntimeException(
                'Falta `TENANCY_SAL_DE_ORIGEN`. Sin sal, el límite por origen no protege: '.
                'el hash sería adivinable probando direcciones.',
            );
        }

        return hash('sha256', $sal.'|'.$origen);
    }

    /**
     * @throws LimiteAlcanzado
     */
    public function verificar(?string $origen = null): void
    {
        $this->verificarTopeDeLaInstancia();

        if ($origen !== null) {
            $this->verificarOrigen($origen);
        }
    }

    /**
     * El tope duro. Existe SIEMPRE, aunque el plazo de vida sea corto: es la
     * última red, y vale para todos los caminos de alta —incluida la invitación
     * por consola— porque lo que protege es la instancia, no al visitante.
     */
    private function verificarTopeDeLaInstancia(): void
    {
        $tope = (int) Config::get('tenancy.limites.tope_ocupados', 0);

        if ($tope <= 0) {
            return;
        }

        // OCUPADOS, no «activos». El recurso escaso son las BASES VIVAS: un
        // inquilino expirado ya no atiende a nadie pero su base sigue ahí hasta
        // que el barrido la borre, ocupando disco y conexiones igual. Contar
        // sólo `activo` daría un cupo que no existe.
        $ocupados = Tenant::query()
            ->where('estado', '!=', TenantEstado::Borrado->value)
            ->count();

        if ($ocupados >= $tope) {
            throw new LimiteAlcanzado(
                'El demo está lleno en este momento. Cada espacio se libera al vencer, '.
                'así que conviene reintentar en unas horas.',
            );
        }
    }

    private function verificarOrigen(string $origen): void
    {
        $porOrigen = (int) Config::get('tenancy.limites.por_origen', 0);
        $ventana = (int) Config::get('tenancy.limites.ventana_horas', 24);

        if ($porOrigen <= 0) {
            return;
        }

        $desde = now()->subHours($ventana);

        // Cuenta filas del padrón y NO bases vivas: la fila sobrevive al borrado
        // (RFC-09). Si contara lo vivo, bastaría esperar a que expire para
        // volver a empezar.
        $previas = Tenant::query()
            ->where('origen_hash', $this->hashDe($origen))
            ->where('created_at', '>=', $desde)
            ->orderBy('created_at')
            ->get(['created_at']);

        if ($previas->count() < $porOrigen) {
            return;
        }

        $reintentar = $previas->first()->created_at->copy()->addHours($ventana);

        throw new LimiteAlcanzado(
            'Ya se crearon varios demos desde este lugar. Se puede volver a intentar '.
            $reintentar->diffForHumans(),
            $reintentar,
        );
    }
}

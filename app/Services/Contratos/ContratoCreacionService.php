<?php

namespace App\Services\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Crea un contrato generando el folio ANTES del insert y reintentando ante colisión
 * del índice UNIQUE alrededor del create completo (hallazgo C-3). No se usa afterCreate
 * porque la columna folio es NOT NULL unique y no admite un insert sin folio.
 */
class ContratoCreacionService
{
    private const MAX_INTENTOS = 3;

    public function __construct(
        private readonly FolioGenerator $folios,
        private readonly ContratoEventoService $eventos,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, User $actor): ContratoIntermediacion
    {
        for ($i = 0; $i < self::MAX_INTENTOS; $i++) {
            try {
                // Cada intento va en su propio SAVEPOINT (transacción anidada). En PostgreSQL,
                // un INSERT que viola el índice UNIQUE aborta la transacción completa (25P02);
                // el savepoint aísla la colisión para que el reintento no herede una transacción
                // envenenada, incluso si crear() se llama dentro de un DB::transaction externo.
                return DB::transaction(function () use ($datos, $actor): ContratoIntermediacion {
                    $contrato = ContratoIntermediacion::create([
                        ...$datos,
                        'folio' => $this->folios->generar(),
                        'agente_id' => $actor->id,
                        'estado' => EstadoContrato::Generado,
                    ]);

                    $this->eventos->registrar($contrato, 'generado', $actor);

                    return $contrato;
                });
            } catch (QueryException $e) {
                if (! $this->esColisionFolio($e)) {
                    throw $e; // otro error de BD: no lo tragamos
                }
                // Colisión de folio en la ventana exists()→insert(): reintenta.
            }
        }

        throw new \RuntimeException('No se pudo crear el contrato: colisión de folio persistente.');
    }

    private function esColisionFolio(QueryException $e): bool
    {
        // 23505 = unique_violation en PostgreSQL; además confirma que es el índice del folio.
        return $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'folio');
    }
}

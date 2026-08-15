<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoContrato;
use App\Filament\Resources\ContratoIntermediacionResource;
use App\Models\ContratoIntermediacion;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Los contratos que piden atención, como en la maqueta de la landing.
 *
 * QUÉ ES «EN PROCESO», porque la palabra podría significar varias cosas. Son los
 * que ya salieron pero todavía no se resolvieron: generado, enviado y leído. Un
 * firmado no pide nada y un rechazado tampoco — están cerrados, cada uno a su
 * manera.
 *
 * Y SE SUMA «POR VENCER», que no estaba en ninguna pantalla: un contrato firmado
 * cuya vigencia se termina en menos de un mes. Es el aviso que evita que a una
 * inmobiliaria se le caiga una exclusiva sin darse cuenta, y es lo que convierte
 * este widget en algo útil en vez de un listado más.
 *
 * NO ES UNA TABLA de Filament a propósito: las tablas traen buscador, paginado y
 * encabezados, y esto son cuatro renglones para mirar de reojo. La chapa de una
 * tabla pesa más que el dato.
 */
class ContratosEnProcesoWidget extends Widget
{
    protected static string $view = 'filament.widgets.contratos-en-proceso';

    protected static ?int $sort = 9;

    /**
     * Cuántos días antes se avisa que una vigencia se termina.
     */
    private const AVISO_EN_DIAS = 30;

    /**
     * Cuántos renglones se muestran.
     */
    private const CUANTOS = 5;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false;
    }

    /**
     * @return Collection<int, array{folio: string, cliente: string, etiqueta: string, color: string, url: ?string}>
     */
    public function getContratos(): Collection
    {
        $enProceso = ContratoIntermediacion::query()
            ->whereIn('estado', [
                EstadoContrato::Generado->value,
                EstadoContrato::Enviado->value,
                EstadoContrato::Leido->value,
            ])
            ->latest('updated_at')
            ->limit(self::CUANTOS)
            ->get()
            ->map(fn (ContratoIntermediacion $c): array => $this->renglon($c, 'En proceso', 'info'));

        $porVencer = ContratoIntermediacion::query()
            ->where('estado', EstadoContrato::Firmado->value)
            ->whereNotNull('vigencia_fin')
            ->whereBetween('vigencia_fin', [now()->toDateString(), now()->addDays(self::AVISO_EN_DIAS)->toDateString()])
            ->orderBy('vigencia_fin')
            ->limit(self::CUANTOS)
            ->get()
            ->map(fn (ContratoIntermediacion $c): array => $this->renglon($c, 'Por vencer', 'warning'));

        // Los que vencen primero: un aviso que llega tarde no es un aviso.
        return $porVencer->concat($enProceso)->take(self::CUANTOS)->values();
    }

    /**
     * @return array{folio: string, cliente: string, etiqueta: string, color: string, url: ?string}
     */
    private function renglon(ContratoIntermediacion $contrato, string $etiqueta, string $color): array
    {
        return [
            'folio' => $contrato->folio,
            'cliente' => $contrato->cliente_nombre,
            'etiqueta' => $etiqueta,
            'color' => $color,

            // El enlace se arma sólo si el recurso existe: este widget no puede
            // ser el motivo de que el escritorio entero deje de dibujarse.
            'url' => class_exists(ContratoIntermediacionResource::class)
                ? ContratoIntermediacionResource::getUrl('view', ['record' => $contrato])
                : null,
        ];
    }
}

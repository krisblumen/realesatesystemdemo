<?php

namespace App\Models;

use App\Enums\TenantEstado;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un inquilino del demo: su espacio aislado, su base y su ciclo de vida.
 *
 * @property string $slug
 * @property string $database
 * @property TenantEstado $estado
 */
class Tenant extends Model
{
    /**
     * La conexión se DECLARA, no se hereda.
     *
     * La conexión por defecto va a apuntar al inquilino de cada petición. Un
     * `Tenant` que la heredara buscaría la tabla `tenants` dentro de la base del
     * propio inquilino —donde no existe— justo cuando hace falta para resolver
     * de quién es la petición.
     */
    protected $connection = 'central';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'estado' => TenantEstado::class,
            'expira_en' => 'datetime',
            'borrado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            $tenant->estado ??= TenantEstado::Aprovisionando;
        });
    }

    /**
     * El ÚNICO camino para cambiar de estado.
     *
     * Asignar `estado` a mano en cualquier otro lugar es un error de
     * implementación: sin un punto único, cada camino —alta, expiración,
     * borrado, padrón— inventa su secuencia y nadie puede decir cuáles son
     * válidas.
     */
    public function pasarA(TenantEstado $destino): void
    {
        if (! $this->estado->puedePasarA($destino)) {
            throw new DomainException(
                "Un inquilino en «{$this->estado->value}» no puede pasar a «{$destino->value}».",
            );
        }

        $this->estado = $destino;

        if ($destino === TenantEstado::Borrado) {
            $this->borrado_en = now();
        }

        $this->save();
    }

    /**
     * Los inquilinos que pueden haber dejado una base viva.
     *
     * Pregunta por `requiereBarridoDeBase()` y NO por «estado terminal», que es
     * la trampa: `fallido` es terminal y aun así puede tener base a medias si el
     * alta murió después de `CREATE DATABASE`. Un barrido que filtrara sólo
     * `expirado` la dejaría ahí para siempre, ocupando conexiones y disco, y el
     * padrón la mostraría como si no existiera.
     */
    public function scopeParaBarrer(Builder $query): Builder
    {
        $estados = array_values(array_filter(
            TenantEstado::cases(),
            fn (TenantEstado $estado): bool => $estado->requiereBarridoDeBase(),
        ));

        return $query->whereIn('estado', array_column($estados, 'value'));
    }
}

<?php

namespace App\Models;

use App\Support\Frontend\PublicRoutes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An institutional page (RFC-075): owns editable draft sections and a published
 * snapshot. The public render reads `published_revision`; the sections are the
 * owner's working draft.
 */
class FrontendPage extends Model
{
    protected $fillable = [
        'key',
        'is_enabled',
        'seo',
        'published_revision',
        'published_draft_revision',
        'published_at',
        'published_by',
        'revision',
        'draft_revision',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'seo' => 'array',
            'published_revision' => 'array',
            'published_at' => 'datetime',
            'revision' => 'integer',
            'published_draft_revision' => 'integer',
            'draft_revision' => 'integer',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FrontendSection::class);
    }

    /**
     * El nombre de la página como lo conoce el owner: «Inicio», «Nosotros».
     *
     * Sale del mismo allowlist que nombra los enlaces del sitio, no de una lista
     * nueva: dos listas paralelas terminarían llamando distinto a la misma
     * página en el panel y en el menú público.
     *
     * Si la clave no estuviera ahí —hoy las cinco están— se devuelve la clave
     * cruda: es fea, pero identifica la página, que es justo lo que hace falta
     * cuando algo no calza.
     */
    public function label(): string
    {
        return PublicRoutes::isKey($this->key)
            ? PublicRoutes::defaultLabel($this->key)
            : (string) $this->key;
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * ¿El borrador tiene cambios que el sitio todavía no muestra?
     *
     * `revision` cuenta PUBLICACIONES y `draft_revision` cuenta EDICIONES: son
     * contadores independientes y compararlos entre sí no dice nada. La única
     * respuesta correcta es contra `published_draft_revision`, que guarda qué
     * borrador quedó publicado.
     *
     * NULL significa «no se sabe» —una página publicada antes de que existiera
     * esa columna— y se responde que SÍ hay pendientes: avisar de más sólo
     * cuesta una publicación, avisar de menos deja al owner creyendo que su
     * cambio ya está en el sitio.
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->published_draft_revision === null
            || $this->draft_revision !== $this->published_draft_revision;
    }
}

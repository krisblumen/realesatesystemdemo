<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Marketing content + availability for a service (RFC-074, §16.6), 1:1 with a
 * ServiceType via `service_type_code`.
 *
 * SoftDeletes is mandatory: it stops Spatie from cascading a delete onto media
 * referenced by the content (InteractsWithMedia), and `forceDelete` is barred by
 * the policy. Uniqueness of the code is a partial unique index (live rows only),
 * declared in the migration — never a Blueprint unique.
 *
 * "Save is publishing" (Strategy A): the content columns are the single payload;
 * there is no draft/published split in v1.
 */
class FrontendService extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'service_type_code',
        'title',
        'short_description',
        'long_description',
        'bullets',
        'icon',
        'image_alt',
        'image_media_id',
        'show_in_home',
        'show_in_services',
        'allow_leads',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'bullets' => 'array',
            'show_in_home' => 'boolean',
            'show_in_services' => 'boolean',
            'allow_leads' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_type_code', 'code');
    }

    public function registerMediaCollections(): void
    {
        // NO singleFile()/onlyKeepLatest(): those call clearMediaCollectionExcept
        // and physically delete the previous file on replace, which v1 forbids
        // (§18.13, C-D1). The render resolves the CURRENT image through the
        // image_media_id column (§16.4), so superseded files simply stop being
        // referenced — they are never deleted.
        //
        // Disco PRIVADO desde la Épica 12.3: una imagen sólo llega al disco
        // público cuando una promoción la copia y la verifica. Sin esto, cambiar
        // la foto de un servicio dejaba la anterior accesible en /storage para
        // siempre, porque la colección no borra. Sólo afecta a las subidas
        // NUEVAS: el disco de una media vive en su fila, y las existentes ya
        // fueron reconocidas como `promoted` por su migración.
        $this->addMediaCollection('image')->useDisk('frontend-private');
    }
}

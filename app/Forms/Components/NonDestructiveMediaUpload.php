<?php

namespace App\Forms\Components;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Épica 12 (§16.4): the stock component has two behaviors incompatible with
 * "collections are storage, not truth":
 *
 * 1. deleteAbandonedFiles() runs on EVERY save and calls Media::delete() for
 *    any uuid missing from the form state (SpatieMediaLibraryFileUpload.php:
 *    125-128, 247-256) — physically destroying files that a published
 *    revision may still reference. SoftDeletes does not intercept it: it
 *    protects the owning model, not a direct Media::delete().
 * 2. loadStateFromRelationshipsUsing() re-reads the COLLECTION (take(1)) as
 *    the source of truth. Since v1 never deletes files, collections
 *    accumulate versions and that read is not deterministic — and, worse,
 *    form state re-loads resurrect references the owner just removed.
 *
 * This subclass neutralizes both: deleteAbandonedFiles() is a no-op, and the
 * form state loads from an EXPLICIT uuid column on the record
 * (->uuidColumn('logo_light_media_id')), the same column the render resolves.
 * Removing an image from the form only removes the editorial reference; the
 * file never leaves the disk through here (physical cleanup is out of v1
 * scope, §18.13). Collections must not use singleFile()/onlyKeepLatest()
 * either — the second automatic deletion route (FileAdder.php:645-651).
 */
class NonDestructiveMediaUpload extends SpatieMediaLibraryFileUpload
{
    protected ?string $uuidColumn = null;

    /** @var (\Closure(Media): ?string)|null */
    protected ?\Closure $previewUrlUsing = null;

    public function uuidColumn(string $column): static
    {
        $this->uuidColumn = $column;

        return $this;
    }

    public function getUuidColumn(): ?string
    {
        return $this->uuidColumn;
    }

    /**
     * De dónde sale la URL de vista previa de un archivo YA guardado.
     *
     * Hace falta para las colecciones que viven en un disco privado (Épica 12.3):
     * el componente base resuelve con `Media::getUrl()`, que sobre un disco sin
     * URL pública no devuelve nada servible, y el panel mostraría la imagen rota
     * justo después de subirla. Las colecciones que sí son públicas —los logos de
     * marca— no declaran nada y conservan el comportamiento original.
     *
     * @param  \Closure(Media): ?string  $callback
     */
    public function previewUrlUsing(\Closure $callback): static
    {
        $this->previewUrlUsing = $callback;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Truth = the uuid column, never "first file in the collection".
        $this->setUpPreviewUrl();

        $this->loadStateFromRelationshipsUsing(static function (NonDestructiveMediaUpload $component, HasMedia $record): void {
            $column = $component->getUuidColumn();

            if ($column === null) {
                $component->state([]);

                return;
            }

            $uuid = $record->getAttribute($column);

            $component->state($uuid ? [$uuid => $uuid] : []);
        });
    }

    public function deleteAbandonedFiles(): void
    {
        // Intentional no-op (§16.4): abandoning a file un-references it, never deletes it.
    }

    protected function setUpPreviewUrl(): void
    {
        $this->getUploadedFileUsing(function (NonDestructiveMediaUpload $component, string $file): ?array {
            $record = $component->getRecord();

            if ($record === null) {
                return null;
            }

            $media = $record->getRelationValue('media')->firstWhere('uuid', $file);

            if ($media === null) {
                return null;
            }

            $resolver = $component->previewUrlUsing;

            return [
                'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
                'size' => $media->getAttributeValue('size'),
                'type' => $media->getAttributeValue('mime_type'),
                'url' => $resolver !== null ? $resolver($media) : $media->getUrl(),
            ];
        });
    }
}

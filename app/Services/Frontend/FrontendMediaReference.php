<?php

namespace App\Services\Frontend;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Single boundary for validating editorial media references (§16.1, §16.4).
 *
 * A uuid is eligible only if it EXISTS, belongs to the owning record
 * (model_type + model_id) and to the expected collection. The FK alone only
 * proves existence, so without this a uuid from another collection — or from
 * another model entirely — would be accepted.
 *
 * There is no lock here on purpose: v1 has no physical media deletion
 * (§18.13), so a validated uuid cannot become dangling afterwards. (The media
 * LOCK that Épica 12.1 adds lives in the publisher and the promotion job, and
 * exists for the reference race, not for deletion — §18.18 punto 4.)
 *
 * SYNTAX IS PART OF THE BOUNDARY (§7.10). `media.uuid` is a native PostgreSQL
 * uuid column: querying it with a malformed string raises SQLSTATE 22P02 — an
 * exception, not a "not found". The format check therefore runs HERE, before any
 * query, so every caller is protected by construction. It used to live duplicated
 * in FrontendPageRenderer, which is precisely the drift this docblock warns
 * about: the renderer was safe and any new caller inherited the hole.
 *
 * Batches D/E must reuse this instead of re-implementing the check, which is
 * exactly where the batch A audit warned drift would appear.
 */
class FrontendMediaReference
{
    /** A well-formed uuid, or null — the single definition for the subsystem. */
    public function normalizeUuid(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    public function isEligible(?string $uuid, Model $owner, string $collection): bool
    {
        if ($this->normalizeUuid($uuid) === null) {
            return false;
        }

        return Media::query()
            ->where('uuid', $uuid)
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->where('collection_name', $collection)
            ->exists();
    }

    /**
     * Resolves an eligible uuid to its Media, or null when it is not eligible.
     * Used by the render boundary so an invalid reference degrades to fallback
     * instead of leaking somebody else's file.
     */
    public function resolve(?string $uuid, Model $owner, string $collection): ?Media
    {
        if ($this->normalizeUuid($uuid) === null) {
            return null;
        }

        return Media::query()
            ->where('uuid', $uuid)
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->where('collection_name', $collection)
            ->first();
    }
}

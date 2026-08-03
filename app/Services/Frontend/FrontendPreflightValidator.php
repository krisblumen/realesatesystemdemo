<?php

namespace App\Services\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use Illuminate\Support\Collection;

/**
 * Page-level preflight checks that run at PUBLISH time (RFC-077, Lote G), on top
 * of the per-section schema validation the publisher already runs. These are the
 * composition rules that span the WHOLE page, which the per-section schema cannot
 * express (§16 "Validaciones pre-publicación / Páginas"):
 *
 *   - one H1 per page: an ENABLED page must publish with its `hero` section
 *     enabled, so the rendered page has exactly one <h1>. The per-section schema
 *     already guarantees an enabled hero carries a title; this adds the
 *     cross-section rule it cannot see — that the hero is not turned off.
 *
 * A disabled page falls back (§16.7) and needs no H1, so it is exempt. Returns a
 * list of human errors ([] = ready to publish) so the publisher refuses a
 * structurally incomplete page before writing the snapshot.
 */
class FrontendPreflightValidator
{
    /**
     * @param  Collection<int, FrontendSection>  $sections  the page's canonical sections (draft rows)
     * @return list<string>
     */
    public function validatePage(FrontendPage $page, Collection $sections): array
    {
        // A disabled page serves the fallback; it does not need an H1.
        if (! $page->is_enabled) {
            return [];
        }

        $errors = [];

        // One H1 per page: the hero must be present and enabled. The schema
        // already requires a title on an enabled hero; this guarantees the hero
        // itself was not disabled, which would leave the page with no <h1>.
        $hero = $sections->firstWhere('section_key', 'hero');
        if ($hero === null || ! $hero->is_enabled) {
            $errors[] = 'La página habilitada debe tener su sección «hero» activa: es el H1 de la página.';
        }

        return $errors;
    }
}

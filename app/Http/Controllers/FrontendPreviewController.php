<?php

namespace App\Http\Controllers;

use App\Models\FrontendPage;
use App\Services\Frontend\FrontendPageRenderer;
use App\Support\Frontend\PublicRoutes;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Owner-only draft preview of an institutional page (RFC-077, Lote G).
 *
 * Security boundary, in order — the controller IS the gate (the route carries
 * only `web`, no `auth` middleware, because Filament owns the login route and
 * there is no named `login` route to redirect a guest to):
 *   1. Only the owner (role + `frontend.manage`) may preview — an unauthenticated
 *      or non-owner request is rejected with 403 BEFORE the pageKey is looked at,
 *      so a non-owner can never probe which keys exist.
 *   2. A pageKey outside the canonical allowlist returns a UNIFORM 404 — the same
 *      response whether the key is unknown or malformed (no enumeration).
 *
 * There is no reusable public token: the preview lives entirely behind the owner
 * session. It renders the WORKING DRAFT (never the published snapshot) in the
 * public layout, in preview mode (noindex,nofollow + banner). The full draft
 * state — SEO and is_enabled — travels too, so the preview matches what a publish
 * would produce (M-G-2).
 */
class FrontendPreviewController extends Controller
{
    /**
     * The human title per canonical page, for the preview <title> — derived
     * from the SAME registry the panel edits (discovery «tres trampas»,
     * hallazgo #1), instead of a second hand-written list that can silently
     * fall out of sync with it and degrade a real page's <title> to «Vista
     * previa».
     *
     * @return array<string, string>
     */
    private static function titles(): array
    {
        return collect(array_keys((array) config('frontend-sections.pages')))
            ->mapWithKeys(fn (string $key): array => [$key => PublicRoutes::defaultLabel($key)])
            ->all();
    }

    public function __invoke(string $pageKey): Response
    {
        // Owner gate first — a non-owner gets 403 regardless of the pageKey, so
        // the key allowlist is never revealed to them.
        $user = auth()->user();
        abort_unless($user !== null && $user->hasRole('owner') && $user->can('frontend.manage'), 403);

        // A non-canonical key is a uniform 404 (no distinction from a missing page).
        abort_unless(array_key_exists($pageKey, (array) config('frontend-sections.pages')), 404);
        abort_unless(FrontendPage::query()->where('key', $pageKey)->exists(), 404);

        $draft = app(FrontendPageRenderer::class)->renderDraft($pageKey);
        abort_if($draft === null, 404);

        // Observability (RFC-077): who previewed what — actor and entity only,
        // never the draft content.
        Log::info('frontend.previewed', ['actor' => $user->getKey(), 'entity' => "page:{$pageKey}"]);

        return response()->view('frontend.preview-shell', [
            'title' => self::titles()[$pageKey] ?? 'Vista previa',
            'seo' => $draft['seo'],
            'sections' => $draft['sections'],
            // A disabled page still previews (the owner is reviewing before
            // enabling), but the banner says it would not be public yet.
            'disabledNote' => $draft['enabled'] ? null : 'Esta página está deshabilitada: no se mostrará en el sitio hasta que la habilites y publiques.',
        ]);
    }
}

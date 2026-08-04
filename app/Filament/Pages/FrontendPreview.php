<?php

namespace App\Filament\Pages;

use App\Support\Frontend\PublicRoutes;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Owner-only entry to the draft preview (RFC-077, Lote G). It is the in-panel UX:
 * the owner picks one of the canonical pages and sees its WORKING DRAFT
 * rendered in the real public layout inside an iframe, pointing at the
 * owner-gated `frontend.preview` route (noindex,nofollow, no reusable token).
 *
 * Strategy B only: pages are the entities with a draft→published flow (§16.9).
 * Theme, contact, nav, footer and CTAs are strategy A (save = publish), so their
 * "preview" is the live site and they are not listed here.
 */
class FrontendPreview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationGroup = 'Frontend';

    protected static ?string $navigationLabel = 'Vista previa';

    protected static ?string $title = 'Vista previa del borrador';

    protected static ?string $slug = 'frontend/preview';

    protected static string $view = 'filament.pages.frontend-preview';

    /** The page key currently previewed; shared with the URL so it can be linked. */
    #[Url]
    public string $pageKey = 'home';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->hasRole('owner') && $user->can('frontend.manage')) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // Never trust the URL param: an unknown key falls back to home.
        if (! array_key_exists($this->pageKey, self::pages())) {
            $this->pageKey = 'home';
        }
    }

    /**
     * The canonical pages the owner can preview: key => label.
     *
     * Derived from `config('frontend-sections.pages')` — the SAME registry
     * `FrontendPage` reads — instead of a hand-written list (discovery «tres
     * trampas», hallazgo #1): a page added to the registry appears here for
     * free, and one that vanishes from it can't leave a stale, unpreviewable
     * entry behind either.
     */
    public static function pages(): array
    {
        return collect(array_keys((array) config('frontend-sections.pages')))
            ->mapWithKeys(fn (string $key): array => [$key => PublicRoutes::defaultLabel($key)])
            ->all();
    }

    /** The owner-gated preview URL for the current page. */
    public function getPreviewUrl(): string
    {
        return route('frontend.preview', ['pageKey' => $this->pageKey]);
    }
}

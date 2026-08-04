<?php

namespace App\Http\Controllers;

use App\Models\FrontendSection;
use App\Services\Frontend\FrontendMediaReference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The ONLY way to look at a section's DRAFT image (Épica 12.1 §7.7). The
 * `images` collection lives on `frontend-private`, outside the webroot: there is
 * no `/storage/...` equivalent, so this route is what the CMS and the preview
 * use until a publish promotes the file to the public disk.
 *
 * Two deliberate departures from the contratos precedent, both from the design
 * audit:
 *
 * - **No `auth` middleware.** It redirects an anonymous request to the login page
 *   (302 + HTML) instead of answering uniformly, which both breaks the contract
 *   for a binary endpoint and tells an anonymous caller that the route exists.
 *   Authentication is checked here and answered like every other failure.
 *
 * - **Authorization goes through the POLICY, not a permission middleware.**
 *   `can:frontend.manage` would let through a user who was granted the permission
 *   directly without the owner role; FrontendSectionPolicy requires BOTH
 *   (`hasRole('owner') && can('frontend.manage')`), and it is the same gate the
 *   Resource uses.
 *
 * Every failure answers **404, uniformly** — anonymous, authenticated but not
 * authorized, unknown section, foreign uuid and malformed uuid are
 * indistinguishable from outside. Nothing here reveals whether a section or a
 * file exists (anti-enumeration).
 */
class FrontendSectionMediaController extends Controller
{
    public function __construct(private readonly FrontendMediaReference $references) {}

    public function __invoke(FrontendSection $section, string $uuid): BinaryFileResponse
    {
        abort_unless(Auth::check(), 404);
        abort_unless(Gate::allows('view', $section), 404);

        // The uuid must be well-formed AND belong to THIS section's `images`
        // collection. resolve() rejects a malformed uuid before touching the
        // native uuid column (§7.10), so garbage in the URL is a 404, never a
        // SQLSTATE 22P02.
        $media = $this->references->resolve($uuid, $section, 'images');

        abort_if($media === null, 404);

        // Bytes served INLINE from the private disk — never a public URL, and not
        // an attachment: the CMS renders this in an <img> preview.
        return response()->file($media->getPath());
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Property;
use App\Support\Frontend\PublicRoutes;
use Illuminate\Http\Response;

/**
 * Dynamic sitemap.xml (RFC-076): the public marketing routes from the nav
 * allowlist plus the published property and project detail pages. Built from the
 * same canonical route source the navigation uses, so it can never list a route
 * that does not exist.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [];

        // Institutional pages — the canonical allowlist (RFC-073).
        foreach (array_keys(PublicRoutes::ALLOWLIST) as $key) {
            $urls[] = ['loc' => route(PublicRoutes::routeName($key)), 'changefreq' => 'weekly'];
        }

        // Published property and project detail pages.
        Property::query()->published()->pluck('slug')->each(function (string $slug) use (&$urls): void {
            $urls[] = ['loc' => route('inmuebles.show', $slug), 'changefreq' => 'daily'];
        });
        Project::query()->pluck('slug')->each(function (string $slug) use (&$urls): void {
            $urls[] = ['loc' => route('proyectos.show', $slug), 'changefreq' => 'weekly'];
        });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}

<?php

namespace App\Support\Frontend;

/**
 * The single allowlist of public destinations the frontend CMS may target
 * (RFC-073). v1 does not accept arbitrary routes: navigation keys and every
 * `route`-type CTA resolve through this table, so a persisted value can only
 * ever point at a real, existing public page.
 *
 * The URL and the active pattern are DERIVED from the key and never persisted
 * (RFC-073 schema, C-1): the owner may relabel or reorder a link, never repoint
 * it. Changing a route belongs in code, behind a deploy.
 */
final class PublicRoutes
{
    /**
     * key => [route name, default label, active pattern for request()->is()].
     *
     * The order here is the canonical fallback order of §16.7 — the exact nav
     * the public layout shipped before the CMS existed.
     *
     * @var array<string, array{route: string, label: string, pattern: string}>
     */
    public const ALLOWLIST = [
        'home' => ['route' => 'home', 'label' => 'Inicio', 'pattern' => '/'],
        'nosotros' => ['route' => 'nosotros', 'label' => 'Nosotros', 'pattern' => 'nosotros'],
        'servicios' => ['route' => 'servicios', 'label' => 'Servicios', 'pattern' => 'servicios'],
        'proyectos' => ['route' => 'proyectos', 'label' => 'Proyectos', 'pattern' => 'proyectos'],
        'inmuebles' => ['route' => 'inmuebles.index', 'label' => 'Inmobiliaria', 'pattern' => 'inmuebles*'],
        'inversionistas' => ['route' => 'inversionistas', 'label' => 'Inversionistas', 'pattern' => 'inversionistas'],
        'contacto' => ['route' => 'leads.create', 'label' => 'Contacto', 'pattern' => 'contacto'],
    ];

    public static function isKey(?string $key): bool
    {
        return is_string($key) && array_key_exists($key, self::ALLOWLIST);
    }

    public static function defaultLabel(string $key): string
    {
        return self::ALLOWLIST[$key]['label'];
    }

    public static function routeName(string $key): string
    {
        return self::ALLOWLIST[$key]['route'];
    }

    public static function pattern(string $key): string
    {
        return self::ALLOWLIST[$key]['pattern'];
    }
}

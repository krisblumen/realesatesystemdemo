<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendSetting;
use App\Models\Project;
use App\Models\Property;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Support\Frontend\ThemeContract;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Theme coverage of the public frontend.
 *
 * The previous version of this matrix certified a defect three ways, and each
 * weakness is answered structurally here:
 *
 * 1. It listed views BY HAND, so `site/partials/*` was invisible. Views are now
 *    DISCOVERED from disk: a new partial is covered the day it is created.
 * 2. It waved through every suffixed shade as decorative, so `bg-navy-900`
 *    survived as a CTA surface even though §16.5 bans it by name. Brand
 *    surfaces are now banned explicitly, shade or not.
 * 3. Its route assertions were satisfied by the global layout, so a page full
 *    of fixed roles still passed. Routes are now asserted by ABSENCE of fixed
 *    roles in the rendered HTML, which the layout cannot satisfy for it.
 *
 * And it never checked that a themed surface uses its guaranteed foreground,
 * which is how `bg-brand-accent` + `text-brand-primary` (1.15:1) shipped.
 */
class FrontendPublicThemeCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A published Property assigns an agent whose role must exist.
        $this->seed(PermissionSeeder::class);
    }

    /** Roles that must resolve the runtime theme, never a compiled constant. */
    private const BANNED_CLASSES = [
        'font-display', 'font-sans',
        'text-navy', 'bg-navy', 'text-orange', 'bg-orange',
        // Brand SURFACES, shade or not: §16.5 bans bg-navy-900 by name.
        'bg-navy-900', 'bg-navy-800', 'bg-navy-700',
        'from-navy-700', 'to-navy-900', 'via-navy-900',
        'ring-orange', 'border-orange', 'outline-orange', 'border-navy',
    ];

    /** Brand colours written as raw hex inside arbitrary Tailwind values. */
    private const BANNED_HEXES = ['#050F38', '#2e3842', '#14246E', '#f5a624', '#FFB829', '#E69500'];

    /** Surface utility => the ONLY foreground the contract guarantees over it. */
    private const SURFACE_FOREGROUND = [
        'bg-brand-primary' => 'on-brand-primary',
        'bg-brand-accent' => 'on-brand-accent',
    ];

    /**
     * Views that render in `x-layouts.public` but are DELIBERATELY out of the
     * theme's scope. Kept explicit so the boundary is auditable, not silent:
     * the guard test below proves every public-layout view is either covered
     * here or discovered as themed.
     *
     * - `styleguide`: a developer reference that documents the FIXED palette on
     *   purpose (its swatches ARE `bg-navy`, `text-orange`…); theming it would
     *   erase what it exists to show. Not linked from the public nav.
     * - `public/contratos/*` and `contratos/*`: the contract signing/verification
     *   flow, a separate functional area reached by token — not the marketing
     *   frontend the owner themes.
     *
     * @var list<string>
     */
    private const EXCLUDED_PUBLIC_VIEWS = [
        'styleguide.blade.php',
        'public/contratos/',
        'contratos/',
        // Owner-only draft preview shell (RFC-077, Lote G): noindex, never a
        // public marketing surface. It only wraps the section dispatcher, whose
        // partials the matrix already certifies through the live pages.
        'frontend/preview-shell',
    ];

    /**
     * Every view of the public MARKETING frontend — the surface the owner's
     * theme governs. Discovered recursively from its roots, not enumerated:
     * a new partial under any root is covered the day it is created.
     *
     * The roots ARE the scope: home, the institutional pages, the property
     * browsing funnel (`inmuebles`), the lead form (`livewire/leads`) and the
     * shared UI (`components`). A previous version rooted only at `site` and
     * `components`, which is exactly how `/inmuebles` and the Livewire form
     * escaped the contract (M-B2). The guard test keeps this list honest.
     *
     * @return list<string>
     */
    private static function publicViewFiles(): array
    {
        // Data providers run BEFORE the application boots, so resource_path()
        // is unavailable here; the path is derived from this file instead.
        $views = dirname(__DIR__, 3).'/resources/views';

        $roots = [
            $views.'/welcome.blade.php',
            $views.'/leads/create.blade.php',
        ];

        foreach (['/site', '/components', '/inmuebles', '/livewire/leads'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views.$dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $roots[] = $file->getPathname();
                }
            }
        }

        sort($roots);

        return $roots;
    }

    public static function publicViews(): array
    {
        $views = dirname(__DIR__, 3).'/resources/views/';

        return array_map(
            fn (string $path): array => [str_replace($views, '', $path)],
            self::publicViewFiles(),
        );
    }

    /**
     * `on_primary` / `accent_on_primary` are TEXT colours (the readable ink over
     * the primary surface), never a background. Using them as a SOLID surface —
     * `bg-on-brand-primary` — worked only while that colour defaulted to white;
     * once the owner themes it (e.g. #ebebeb), those "white" cards turn grey.
     * Translucent overlays on a dark surface (`bg-on-brand-primary/10`) stay
     * legitimate, so only the solid form (no `/opacity`) is banned.
     */
    #[DataProvider('publicViews')]
    public function test_no_public_view_uses_a_text_colour_as_a_solid_surface(string $view): void
    {
        $source = file_get_contents(resource_path("views/{$view}"));

        foreach (['bg-on-brand-primary', 'bg-accent-on-brand-primary'] as $class) {
            $this->assertDoesNotMatchRegularExpression(
                '/'.preg_quote($class, '/').'(?![-\/\w])/',
                $source,
                "{$view} uses the text colour `{$class}` as a solid surface; a card ".
                'must use a real surface (e.g. bg-white), or it turns the themed text colour.'
            );
        }
    }

    #[DataProvider('publicViews')]
    public function test_no_public_view_keeps_a_fixed_brand_role(string $view): void
    {
        $source = file_get_contents(resource_path("views/{$view}"));

        foreach (self::BANNED_CLASSES as $class) {
            // Word boundary so `text-navy` does not match `text-navy-50`, but the
            // surfaces listed above are matched explicitly with their suffix.
            $pattern = str_contains($class, '-9') || str_contains($class, '-8') || str_contains($class, '-7')
                ? '/'.preg_quote($class, '/').'/'
                : '/'.preg_quote($class, '/').'(?![-\w])/';

            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $source,
                "{$view} keeps the fixed brand role `{$class}` (RFC-072:148)."
            );
        }

        $upper = strtoupper($source);
        foreach (self::BANNED_HEXES as $hex) {
            $this->assertStringNotContainsString(
                $hex,
                $upper,
                "{$view} hardcodes the brand colour {$hex}."
            );
        }
    }

    public function test_every_public_layout_view_is_covered_or_explicitly_excluded(): void
    {
        // The structural fix for M-B2: the matrix used to CLAIM it covered
        // "every public Blade file" while its roots were hand-picked, so a new
        // public page could reintroduce a fixed role unseen. This walks the
        // whole tree, finds every view that renders in `x-layouts.public`, and
        // proves each one is either discovered as themed or listed as an
        // explicit, reasoned exclusion. A new public page fails here until a
        // human decides which bucket it belongs to.
        $viewsDir = resource_path('views');
        $covered = array_map(
            fn (string $p): string => str_replace($viewsDir.'/', '', $p),
            self::publicViewFiles(),
        );

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! str_contains($source, 'x-layouts.public') && ! str_contains($source, 'layouts.public')) {
                continue;
            }

            $relative = str_replace($viewsDir.'/', '', $file->getPathname());

            $excluded = false;
            foreach (self::EXCLUDED_PUBLIC_VIEWS as $prefix) {
                $excluded = $excluded || str_starts_with($relative, $prefix);
            }

            $this->assertTrue(
                in_array($file->getPathname(), self::publicViewFiles(), true) || $excluded,
                "{$relative} renders in the public layout but is neither covered by the theme matrix "
                .'nor in EXCLUDED_PUBLIC_VIEWS. Decide its scope explicitly (M-B2).'
            );
        }
    }

    #[DataProvider('publicViews')]
    public function test_no_public_control_keeps_a_fixed_focus_or_accent_role(string $view): void
    {
        // M-B3: `focus:ring-orange/20` ignores `--theme-focus`; the composited ring
        // reaches ~1.16:1 on white, below the 3:1 of RFC-072:138. Focus rings,
        // outlines and control accents must resolve the runtime token so the
        // service's guaranteed focus colour actually reaches the control.
        $source = file_get_contents(resource_path("views/{$view}"));

        foreach ([
            'ring-orange', 'border-orange', 'outline-orange', 'accent-orange',
        ] as $role) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?:(?:hover|focus|focus-visible|group-hover):)?'.preg_quote($role, '/').'(?:\/\d+)?(?![-\w])/',
                $source,
                "{$view} keeps the fixed control role `{$role}`; use the runtime `brand-focus`/`brand-accent` "
                .'token so `--theme-focus` reaches the control (M-B3).'
            );
        }
    }

    #[DataProvider('publicViews')]
    public function test_no_raw_brand_colour_is_used_as_a_foreground(string $view): void
    {
        // C-B2: `text-brand-primary`/`text-brand-accent` are surfaces used as
        // TEXT. Over the base background a pale brand colour reaches ~1.2:1, and
        // the contract cannot forbid a pale primary without also forbidding it
        // as a (valid) surface. So the raw colour is never a foreground: on a
        // themed surface it must be `on-brand-*`, over the base it must be the
        // guaranteed `-ink` token. Word boundary keeps `-ink` and `-on-*` out.
        $source = file_get_contents(resource_path("views/{$view}"));

        foreach (['brand-primary', 'brand-accent'] as $role) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?:(?:hover|focus|focus-visible|group-hover):)?text-'.preg_quote($role, '/').'(?![-\w])/',
                $source,
                "{$view} uses `text-{$role}` as a foreground; use `text-{$role}-ink` over the base "
                ."background or `text-on-{$role}` on the surface (C-B2)."
            );
        }
    }

    #[DataProvider('publicViews')]
    public function test_themed_surfaces_use_their_guaranteed_foreground(string $view): void
    {
        $source = file_get_contents(resource_path("views/{$view}"));

        // Every class attribute that paints a themed surface.
        preg_match_all('/class="([^"]*)"/', $source, $matches);

        foreach ($matches[1] as $classList) {
            // Base and hover are different states with different surfaces: an
            // outline button is transparent at rest and primary on hover, so
            // its resting text colour must not be judged against the hover
            // surface. Each state is analysed on its own.
            $states = ['' => [], 'hover:' => []];

            foreach (preg_split('/\s+/', $classList) as $class) {
                $prefix = str_starts_with($class, 'hover:') ? 'hover:' : '';
                $states[$prefix][] = substr($class, strlen($prefix));
            }

            foreach ($states as $prefix => $classes) {
                foreach (self::SURFACE_FOREGROUND as $surface => $foreground) {
                    // Only an OPAQUE (or near-opaque) surface governs the
                    // contrast of the text on it. `bg-brand-accent/10` is a
                    // tint: what the reader sees behind the text is still the
                    // page background, so the pairing rule does not apply.
                    $painted = preg_grep('/^'.preg_quote($surface, '/').'(?:\/(\d+))?$/', $classes);

                    $opaque = false;
                    foreach ($painted as $class) {
                        $opacity = str_contains($class, '/') ? (int) explode('/', $class)[1] : 100;
                        $opaque = $opaque || $opacity >= 50;
                    }

                    if (! $opaque) {
                        continue;
                    }

                    foreach ($classes as $class) {
                        // Only COLOUR utilities matter here. text-sm, text-center
                        // and text-[11px] are size and alignment: flagging them
                        // would turn this test into noise.
                        if (! self::isTextColour($class)) {
                            continue;
                        }

                        $this->assertStringContainsString(
                            $foreground,
                            $class,
                            "{$view}: `{$prefix}{$surface}` carries `{$prefix}{$class}`. Only `text-{$foreground}` is contrast-guaranteed over that surface (C-B1)."
                        );
                    }
                }
            }
        }

        $this->assertTrue(true);
    }

    /**
     * A `text-*` utility that actually paints a colour. Sizes (`text-sm`,
     * `text-[11px]`), alignment (`text-center`) and wrapping are excluded:
     * a pairing test that flags them stops being read.
     */
    private static function isTextColour(string $class): bool
    {
        if (! str_starts_with($class, 'text-')) {
            return false;
        }

        $value = substr($class, 5);

        // Arbitrary value: a colour only if it is a hex literal.
        if (str_starts_with($value, '[')) {
            return (bool) preg_match('/^\\[#[0-9a-fA-F]{3,8}\\]/', $value);
        }

        $name = explode('/', $value)[0];

        return in_array($name, [
            'white', 'black', 'transparent', 'current',
            'navy', 'orange', 'ink', 'graphite', 'stone', 'mist', 'cloud', 'fog', 'canvas',
            'brand-primary', 'brand-accent', 'on-brand-primary', 'on-brand-accent',
            'site-text', 'site-background', 'accent-on-brand-primary', 'brand-focus',
        ], true);
    }

    /**
     * Falla si la clase de marca FIJA aparece en el markup.
     *
     * No alcanza con buscar el texto suelto: desde que la página carga sus
     * tipografías, el `@font-face` que inyecta Vite trae `font-display: swap` —
     * una PROPIEDAD de CSS que se llama igual que la clase prohibida. Buscar la
     * cadena a secas daba nueve rutas en rojo por una regla que ninguna de ellas
     * rompía.
     *
     * Lo que se prohíbe sigue siendo lo mismo: el nombre usado como CLASE. Por
     * eso se descarta sólo cuando lo sigue un `:`, que es lo único que no puede
     * ser una clase.
     */
    private function assertNoFixedBrandRole(string $class, string $html, string $message): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/'.preg_quote($class, '/').'(?!\s*:)/',
            $html,
            $message,
        );
    }

    #[DataProvider('publicRoutes')]
    public function test_rendered_routes_contain_no_fixed_brand_roles(string $route): void
    {
        // Seeded so partials that only render with data — project card, nav,
        // carousel and the property hero/card — are actually exercised.
        $this->seedProjects();
        Property::factory()->published()->create();
        $this->applyDistinctiveTheme();

        $html = $this->get($route)->assertOk()->getContent();

        foreach (['font-display', 'bg-navy-900', 'bg-orange', 'from-navy-700', 'to-navy-900'] as $class) {
            $this->assertNoFixedBrandRole(
                $class,
                $html,
                "{$route} renders the fixed brand role `{$class}`; the layout cannot satisfy this away."
            );
        }

        $this->assertStringContainsString('--theme-primary: #0f766e', $html, $route);
    }

    public static function publicRoutes(): array
    {
        return [['/'], ['/nosotros'], ['/servicios'], ['/inversionistas'], ['/proyectos'], ['/contacto'], ['/inmuebles']];
    }

    public function test_the_property_detail_route_is_fully_themed(): void
    {
        // `/inmuebles/{slug}` is a public route the previous matrix never hit,
        // so its fixed navy/orange roles shipped green (M-B1).
        $property = Property::factory()->published()->create();
        $this->applyDistinctiveTheme();

        $html = $this->get("/inmuebles/{$property->slug}")->assertOk()->getContent();

        foreach (['font-display', 'bg-navy-900', 'bg-orange', 'ring-orange', 'text-navy', "\ntext-orange"] as $class) {
            $this->assertNoFixedBrandRole($class, $html, "property detail renders `{$class}`.");
        }

        $this->assertStringContainsString('--theme-primary: #0f766e', $html);
    }

    public function test_the_project_detail_route_is_fully_themed(): void
    {
        $projects = $this->seedProjects();
        $this->applyDistinctiveTheme();

        $html = $this->get("/proyectos/{$projects[0]->slug}")->assertOk()->getContent();

        foreach (['font-display', 'bg-navy-900', 'bg-orange', 'ring-orange'] as $class) {
            $this->assertNoFixedBrandRole($class, $html, "detail renders `{$class}`.");
        }

        $this->assertStringContainsString('--theme-accent: #be123c', $html);
    }

    public function test_the_guaranteed_foreground_actually_clears_aa_for_a_hostile_theme(): void
    {
        // The pairing rule only matters if the tokens really are safe. This is
        // the theme the audit used to prove `text-brand-primary` over
        // `bg-brand-accent` reaches just 1.15:1.
        $this->assertLessThan(
            ThemeContract::MIN_CONTRAST,
            ThemeContract::contrastRatio('#0f766e', '#be123c'),
            'Sanity: primary over accent is indeed unreadable for this theme.'
        );

        $this->assertTrue(ThemeContract::meetsAa('#ffffff', '#be123c'), 'on_accent over accent is safe.');
        $this->assertTrue(ThemeContract::meetsAa('#ffffff', '#0f766e'), 'on_primary over primary is safe.');
    }

    /** @return list<Project> */
    private function seedProjects(): array
    {
        return [
            Project::create(['title' => 'Proyecto Uno', 'slug' => 'proyecto-uno', 'description' => 'Uno.']),
            Project::create(['title' => 'Proyecto Dos', 'slug' => 'proyecto-dos', 'description' => 'Dos.']),
        ];
    }

    private function applyDistinctiveTheme(): void
    {
        $setting = FrontendSetting::current();
        $setting->theme = [
            'primary' => '#0f766e',
            'on_primary' => '#ffffff',
            'accent' => '#be123c',
            'on_accent' => '#ffffff',
            'radius' => 'rounded',
        ];
        $setting->save();

        app(FrontendCacheGeneration::class)->bump();
    }
}

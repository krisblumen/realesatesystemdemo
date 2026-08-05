<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendSetting;
use App\Models\Project;
use App\Models\Property;
use App\Services\Frontend\FrontendCacheGeneration;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The surface → foreground contract, checked on the RENDERED DOM TREE.
 *
 * The previous matrix compared `bg-brand-*` and `text-*` only when they shared
 * a class attribute. Real CTAs put the surface on a wrapper `<div>` and the
 * copy in child `<h2>`/`<p>`, so the dangerous relationship was never
 * evaluated and C-B1 survived a "green" suite twice.
 *
 * Walking descendants is the only way to state the rule the contract actually
 * makes: whatever text sits ON a themed surface must use the foreground that
 * §16.5 validates against it — text colour is inherited down the tree, not
 * declared next to the background.
 */
class FrontendSurfaceForegroundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A published Property assigns an agent whose role must exist.
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Opaque themed surface => the foregrounds the contract guarantees over it.
     *
     * `accent-on-brand-primary` belongs here because the service already
     * degrades it to `on_primary` when the accent is not legible over the
     * surface: it is a guaranteed token, not a raw accent.
     */
    private const SURFACES = [
        'bg-brand-primary' => ['on-brand-primary', 'accent-on-brand-primary'],
        'bg-brand-accent' => ['on-brand-accent'],
    ];

    /**
     * Foreground utilities allowed on ANY surface because they do not paint a
     * fixed colour: they inherit, or they are the guaranteed token itself.
     */
    private const NEUTRAL_FOREGROUNDS = ['text-current', 'text-transparent', 'text-inherit'];

    #[DataProvider('publicRoutes')]
    public function test_text_on_a_themed_surface_uses_the_guaranteed_foreground(string $route): void
    {
        $this->seedProjects();
        Property::factory()->published()->create();
        $this->applyTheme();

        $html = $this->get($route)->assertOk()->getContent();

        $violations = $this->violations($html);

        $this->assertSame(
            [],
            $violations,
            "{$route}: text on a themed surface must use its guaranteed foreground (C-B1).\n  ".
            implode("\n  ", array_slice($violations, 0, 12))
        );
    }

    public function test_the_project_detail_respects_the_contract(): void
    {
        $projects = $this->seedProjects();
        $this->applyTheme();

        $html = $this->get("/proyectos/{$projects[0]->slug}")->assertOk()->getContent();

        $this->assertSame([], $this->violations($html));
    }

    /**
     * Walks the tree: for every element painting an opaque themed surface,
     * every descendant that sets a colour must set the guaranteed one.
     *
     * @return list<string>
     */
    private function violations(string $html): array
    {
        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $violations = [];

        foreach ($xpath->query('//*[@class]') as $element) {
            foreach (self::SURFACES as $surface => $allowed) {
                if (! $this->paintsOpaqueSurface($element->getAttribute('class'), $surface)) {
                    continue;
                }

                foreach ($xpath->query('.//*[@class]', $element) as $descendant) {
                    foreach (preg_split('/\s+/', trim($descendant->getAttribute('class'))) as $class) {
                        // A hover colour is painted on the SAME surface as the
                        // resting one, so it is bound by the same guarantee.
                        $bare = preg_replace('/^(?:hover|focus|focus-visible|group-hover):/', '', $class);

                        $guaranteed = false;
                        foreach ($allowed as $token) {
                            $guaranteed = $guaranteed || str_contains($bare, $token);
                        }

                        if (! $this->isTextColour($bare) || $guaranteed) {
                            continue;
                        }

                        // A descendant may legitimately paint its OWN themed
                        // surface; its text is then judged against that one.
                        if ($this->paintsAnySurface($descendant->getAttribute('class'))) {
                            continue 2;
                        }

                        $violations[] = sprintf(
                            '<%s class="%s"> inside %s — expected text-%s',
                            $descendant->nodeName,
                            $class,
                            $surface,
                            $allowed[0],
                        );
                    }
                }

                // A foreground is not only a class: an inline `stroke`/`fill`
                // pins an SVG's colour just as hard (M-B4). Over a configurable
                // surface those must defer to the inherited colour — which a
                // class sets to the guaranteed token — never a fixed literal.
                foreach (['stroke', 'fill'] as $attribute) {
                    foreach ($xpath->query(".//*[@{$attribute}]", $element) as $painted) {
                        $value = strtolower(trim($painted->getAttribute($attribute)));

                        if (in_array($value, ['currentcolor', 'none', 'inherit', 'transparent', ''], true)
                            || str_starts_with($value, 'url(') || str_starts_with($value, 'var(')) {
                            continue;
                        }

                        $violations[] = sprintf(
                            '<%s %s="%s"> inside %s — use %s="currentColor" with text-%s',
                            $painted->nodeName,
                            $attribute,
                            $painted->getAttribute($attribute),
                            $surface,
                            $attribute,
                            $allowed[0],
                        );
                    }
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * Does this element paint the given themed surface densely enough to
     * govern the contrast of the text on it?
     *
     * Gradient stops count. The page heroes wash the brand primary over a
     * photograph with `from-brand-primary/[0.92]`, and 92% of the surface is
     * what the reader actually sees behind the headline — treating that as
     * "not a surface" is precisely how `text-white` survived in the heroes.
     */
    private function paintsOpaqueSurface(string $classList, string $surface): bool
    {
        $role = substr($surface, 3);
        $pattern = '/^(?:'.preg_quote($surface, '/').'|(?:from|via)-'.preg_quote($role, '/').')'
            .'(?:\/(?:(\d+)|\[0?\.(\d+)\]))?$/';

        foreach (preg_split('/\s+/', trim($classList)) as $class) {
            if (! preg_match($pattern, $class, $m)) {
                continue;
            }

            $opacity = match (true) {
                ($m[1] ?? '') !== '' => (int) $m[1],
                ($m[2] ?? '') !== '' => (int) round((float) ('0.'.$m[2]) * 100),
                default => 100,
            };

            // Below 50% the page background still governs the contrast.
            if ($opacity >= 50) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this element paint its OWN opaque background, whatever it is?
     *
     * The rule is about what sits BEHIND the text, so a descendant that paints
     * a fixed brand surface of its own — the WhatsApp green, a white card —
     * leaves the themed surface behind and is judged against that instead.
     * Only themed surfaces get the guaranteed-foreground requirement; fixed
     * ones are covered by the coverage matrix, which bans the brand roles.
     */
    private function paintsAnySurface(string $classList): bool
    {
        foreach (preg_split('/\s+/', trim($classList)) as $class) {
            if (! preg_match('/^bg-(.+?)(?:\/(\d+))?$/', $class, $m)) {
                continue;
            }

            if ((int) ($m[2] ?? 100) < 50 || in_array($m[1], ['transparent', 'current', 'inherit'], true)) {
                continue;
            }

            // Gradients and images do not, on their own, establish a background
            // colour: the surface underneath still shows through.
            if (! str_starts_with($m[1], 'gradient-') && ! str_starts_with($m[1], 'cover') && ! str_starts_with($m[1], 'center')) {
                return true;
            }
        }

        return false;
    }

    /** Only colour utilities; sizes and alignment are not foregrounds. */
    private function isTextColour(string $class): bool
    {
        if (! str_starts_with($class, 'text-') || in_array($class, self::NEUTRAL_FOREGROUNDS, true)) {
            return false;
        }

        $value = substr($class, 5);

        if (str_starts_with($value, '[')) {
            return (bool) preg_match('/^\[#[0-9a-fA-F]{3,8}\]/', $value);
        }

        return in_array(explode('/', $value)[0], [
            'white', 'black', 'navy', 'orange', 'ink', 'graphite', 'stone',
            'mist', 'cloud', 'fog', 'canvas', 'brand-primary', 'brand-accent',
            'site-text', 'accent-on-brand-primary',
        ], true);
    }

    public function test_the_property_detail_respects_the_contract(): void
    {
        $property = Property::factory()->published()->create();
        $this->applyTheme();

        $html = $this->get("/inmuebles/{$property->slug}")->assertOk()->getContent();

        $this->assertSame([], $this->violations($html));
    }

    public function test_the_detector_catches_a_fixed_svg_paint_on_a_themed_surface(): void
    {
        // Proves the M-B4 detector works, not merely that the views are clean:
        // a fixed `stroke`/`fill` over a themed surface must be reported, and
        // the `currentColor` + guaranteed-token form must pass.
        $bad = '<div class="bg-brand-accent"><svg stroke="#ffffff"><path fill="#fff"/></svg></div>';
        $this->assertNotEmpty($this->violations($bad), 'A fixed SVG paint over an accent surface must be flagged.');

        $good = '<div class="bg-brand-accent text-on-brand-accent"><svg stroke="currentColor"><path fill="none"/></svg></div>';
        $this->assertSame([], $this->violations($good), 'currentColor deferring to the guaranteed token is allowed.');
    }

    public static function publicRoutes(): array
    {
        return [['/'], ['/nosotros'], ['/servicios'], ['/inversionistas'], ['/proyectos'], ['/contacto'], ['/inmuebles']];
    }

    /** @return list<Project> */
    private function seedProjects(): array
    {
        return [
            Project::create(['title' => 'Proyecto Uno', 'slug' => 'proyecto-uno', 'description' => 'Uno.']),
            Project::create(['title' => 'Proyecto Dos', 'slug' => 'proyecto-dos', 'description' => 'Dos.']),
        ];
    }

    /**
     * A LIGHT primary with a DARK on_primary — the theme the audit used. It is
     * perfectly valid (16.2:1) and it makes every leftover `text-white`
     * illegible, which a navy-ish theme would have hidden.
     */
    private function applyTheme(): void
    {
        $setting = FrontendSetting::current();
        $setting->theme = [
            'primary' => '#fef08a',
            'on_primary' => '#171d23',
            'accent' => '#fde68a',
            'on_accent' => '#171d23',
            'background' => '#ffffff',
            'text' => '#171d23',
        ];
        $setting->save();

        app(FrontendCacheGeneration::class)->bump();
    }
}

<?php

namespace Tests\Unit\Frontend;

use App\Support\Frontend\CtaResolver;
use Tests\TestCase;

/**
 * The CTA value object `{label, type, target}` and its central resolver
 * (RFC-073). One resolver validates every CTA and every footer link, so a
 * dangerous target cannot slip in through a path the layout forgot to check.
 *
 * Runs against the app (needs route() for `route`-type targets) but touches no
 * database — the resolver is pure over its input.
 */
class CtaResolverTest extends TestCase
{
    private function resolve(mixed $cta): ?array
    {
        return app(CtaResolver::class)->resolve($cta);
    }

    public function test_a_route_target_in_the_allowlist_resolves_to_its_url(): void
    {
        $cta = $this->resolve(['label' => 'Ver proyectos', 'type' => 'route', 'target' => 'proyectos']);

        $this->assertSame('Ver proyectos', $cta['label']);
        $this->assertSame(route('proyectos'), $cta['url']);
        $this->assertFalse($cta['external']);
    }

    public function test_a_route_target_outside_the_allowlist_is_rejected(): void
    {
        // `filament.admin.pages.dashboard` is a real route, but not public.
        $this->assertNull($this->resolve(['label' => 'Panel', 'type' => 'route', 'target' => 'filament.admin.pages.dashboard']));
        $this->assertNull($this->resolve(['label' => 'X', 'type' => 'route', 'target' => 'route.that.does.not.exist']));
    }

    public function test_an_https_url_is_accepted_and_marked_external(): void
    {
        $cta = $this->resolve(['label' => 'Partner', 'type' => 'url', 'target' => 'https://partner.example.com/x']);

        $this->assertSame('https://partner.example.com/x', $cta['url']);
        $this->assertTrue($cta['external']);
    }

    public function test_non_https_and_dangerous_schemes_are_rejected(): void
    {
        foreach ([
            'http://insecure.example.com',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'file:///etc/passwd',
            'vbscript:msgbox(1)',
            '//protocol-relative.example.com',
            '/internal/relative',
        ] as $target) {
            $this->assertNull(
                $this->resolve(['label' => 'X', 'type' => 'url', 'target' => $target]),
                "`{$target}` must be rejected as a url target."
            );
        }
    }

    public function test_whatsapp_target_becomes_a_wa_me_link(): void
    {
        $cta = $this->resolve(['label' => 'WhatsApp', 'type' => 'whatsapp', 'target' => '+52 442 272 26 23']);

        $this->assertSame('https://wa.me/524422722623', $cta['url']);
        $this->assertTrue($cta['external']);
    }

    public function test_a_whatsapp_number_too_short_is_rejected(): void
    {
        $this->assertNull($this->resolve(['label' => 'X', 'type' => 'whatsapp', 'target' => '123']));
    }

    public function test_mailto_and_tel_are_validated(): void
    {
        $mail = $this->resolve(['label' => 'Correo', 'type' => 'mailto', 'target' => 'hola@newhauz.com.mx']);
        $this->assertSame('mailto:hola@newhauz.com.mx', $mail['url']);
        $this->assertFalse($mail['external']);

        $tel = $this->resolve(['label' => 'Llamar', 'type' => 'tel', 'target' => '+52 442 272 26 23']);
        $this->assertSame('tel:+524422722623', $tel['url']);

        $this->assertNull($this->resolve(['label' => 'X', 'type' => 'mailto', 'target' => 'not-an-email']));
        $this->assertNull($this->resolve(['label' => 'X', 'type' => 'tel', 'target' => '12']));
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->assertNull($this->resolve(['label' => 'X', 'type' => 'iframe', 'target' => 'whatever']));
        $this->assertNull($this->resolve(['label' => 'X', 'type' => 'route']));
        $this->assertNull($this->resolve(null));
        $this->assertNull($this->resolve('not-an-array'));
    }

    public function test_an_empty_or_html_label_is_rejected(): void
    {
        // The label is escaped in Blade, but a CTA with no readable label — or
        // one smuggling markup — is not a valid value object.
        $this->assertNull($this->resolve(['label' => '', 'type' => 'route', 'target' => 'home']));
        $this->assertNull($this->resolve(['label' => '   ', 'type' => 'route', 'target' => 'home']));
        $this->assertNull($this->resolve(['label' => '<b>hi</b>', 'type' => 'route', 'target' => 'home']));
    }
}

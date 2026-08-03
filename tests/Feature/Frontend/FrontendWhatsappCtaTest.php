<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Support\Frontend\CtaResolver;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Un CTA que lleva a WhatsApp se dibuja como WhatsApp.
 *
 * Verde y con su logo, sin importar qué variante haya pedido la sección: es la
 * señal de que ese botón abre una conversación y no otra página del sitio, y
 * quien la reconoce sabe qué va a pasar antes de tocarla.
 *
 * El `type` tiene que VIAJAR desde el resolver hasta la vista. Una vez que el
 * destino se resolvió a una URL, que sea de WhatsApp ya no se puede deducir sin
 * volver a mirar la URL — que es rehacer una decisión que el resolver ya tomó.
 */
class FrontendWhatsappCtaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    /** Publica el cierre de la home con este CTA y devuelve el HTML. */
    private function renderWithCta(array $cta): string
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();

        $page->sections()->where('section_key', 'final_cta')->firstOrFail()
            ->forceFill(['payload' => ['title' => 'Hablemos', 'primary_cta' => $cta]])->saveQuietly();

        app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $this->owner);

        return $this->get('/')->assertOk()->getContent();
    }

    // ------------------------------------------------------- el contrato ----

    public function test_the_resolver_hands_back_the_type(): void
    {
        $resuelto = app(CtaResolver::class)->resolve([
            'label' => 'Escríbenos', 'type' => 'whatsapp', 'target' => '524421190959',
        ]);

        $this->assertSame('whatsapp', $resuelto['type']);
        $this->assertStringStartsWith('https://wa.me/', $resuelto['url']);
    }

    #[DataProvider('otherTypes')]
    public function test_other_types_keep_their_own_type(string $type, string $target): void
    {
        $resuelto = app(CtaResolver::class)->resolve(['label' => 'Ir', 'type' => $type, 'target' => $target]);

        $this->assertSame($type, $resuelto['type']);
    }

    public static function otherTypes(): array
    {
        return [
            'página del sitio' => ['route', 'contacto'],
            'enlace externo' => ['url', 'https://ejemplo.test/algo'],
            'correo' => ['mailto', 'hola@ejemplo.test'],
            'teléfono' => ['tel', '4421190959'],
        ];
    }

    // ---------------------------------------------------------- el botón ----

    public function test_a_whatsapp_cta_is_green_and_carries_the_logo(): void
    {
        $html = $this->renderWithCta(['label' => 'Escríbenos', 'type' => 'whatsapp', 'target' => '524421190959']);

        $this->assertStringContainsString('bg-whatsapp', $html);
        $this->assertStringContainsString('text-on-whatsapp', $html);
        // El logo: el primer tramo del path, que alcanza para identificarlo.
        $this->assertStringContainsString('M.057 24l1.687-6.163', $html);
    }

    public function test_the_whatsapp_look_wins_over_the_variant_the_section_asked_for(): void
    {
        // El cierre pide `primary` (o `dark` sobre fondo claro) para su botón
        // principal. Un destino de WhatsApp lo pisa: si respetara la variante,
        // el botón se vería igual que el que lleva a otra página del sitio.
        $html = $this->renderWithCta(['label' => 'Escríbenos', 'type' => 'whatsapp', 'target' => '524421190959']);

        $this->assertStringContainsString('bg-whatsapp', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*bg-brand-accent[^>]*>\s*<svg[^>]*>.*?Escríbenos/s',
            $html,
            'El botón de WhatsApp salió con la variante de acento.',
        );
    }

    public function test_a_normal_cta_is_left_alone(): void
    {
        // LO QUE NO DEBE ROMPERSE: los demás destinos siguen usando la variante
        // que pidió la sección.
        $html = $this->renderWithCta(['label' => 'Contáctanos', 'type' => 'route', 'target' => 'contacto']);

        $this->assertStringContainsString('Contáctanos', $html);

        // Se CUENTA en vez de afirmar ausencia: el layout dibuja siempre el
        // botón flotante de WhatsApp, que usa el mismo verde. Una aserción de
        // «no aparece» fallaría por él y no por el CTA, que es lo que se prueba.
        $this->assertSame(1, substr_count($html, 'bg-whatsapp'), 'Sólo debería estar el flotante.');
    }

    public function test_the_whatsapp_cta_adds_a_second_green_button_to_the_page(): void
    {
        $html = $this->renderWithCta(['label' => 'Escríbenos', 'type' => 'whatsapp', 'target' => '524421190959']);

        // El flotante más el del cierre.
        $this->assertSame(2, substr_count($html, 'bg-whatsapp'));
    }

    // --------------------------------------------------------- el resplandor ----

    public function test_the_whatsapp_button_glows_green_and_not_amber(): void
    {
        // Un resplandor ámbar devolvería el botón a la marca del sitio y se
        // perdería la señal de que abre otro canal.
        $html = $this->renderWithCta(['label' => 'Escríbenos', 'type' => 'whatsapp', 'target' => '524421190959']);

        $this->assertStringContainsString('shadow-whatsapp', $html);
    }

    public function test_the_glows_follow_the_theme_instead_of_a_frozen_hex(): void
    {
        // `--shadow-cta` estaba clavado en `rgba(246, 163, 0, …)`, el ámbar por
        // defecto: un cliente con otro acento tenía botones que brillaban de un
        // color que no era el suyo. Ahora se deriva, así que cambiar la marca
        // cambia el resplandor sin recompilar.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/--shadow-cta:.*?var\(--nh-accent/s',
            $css,
            'El resplandor del CTA volvió a un color fijo.',
        );
        $this->assertMatchesRegularExpression('/--shadow-whatsapp:.*?var\(--color-whatsapp/s', $css);
    }

    // ------------------------------------------------------- la legibilidad ----

    public function test_the_white_on_green_pairing_is_a_deliberate_call(): void
    {
        // DEJADO POR ESCRITO para que no se lea como un descuido: el par es el
        // de la marca de WhatsApp, blanco sobre su verde, elegido por el dueño
        // del sitio para que el botón se reconozca al instante.
        //
        // El dato que estaba sobre la mesa al decidirlo: ese par da 1.98:1, por
        // debajo del 4.5:1 que WCAG pide para la etiqueta de un botón. Se
        // asumió a cambio del reconocimiento inmediato del canal. Si alguna vez
        // se prioriza la legibilidad, alcanza con oscurecer `--color-whatsapp`
        // (a #075E54 el blanco llega a 7.7:1) sin tocar una sola vista.
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match('/--color-whatsapp:\s*(#[0-9a-f]{6})/i', $css, $verde);
        preg_match('/--color-on-whatsapp:\s*(#[0-9a-f]{6})/i', $css, $tinta);

        $this->assertSame('#25d366', strtolower($verde[1] ?? ''), 'El verde dejó de ser el de WhatsApp.');
        $this->assertSame('#ffffff', strtolower($tinta[1] ?? ''));
    }

    public function test_the_button_component_still_bans_a_fixed_white(): void
    {
        // La regla §16.5 sigue entera: el blanco de WhatsApp entra por un ROL
        // (`text-on-whatsapp`), no como un `text-white` escrito en la vista. La
        // diferencia importa — un blanco suelto ahí se copiaría a la próxima
        // variante, donde el fondo sí es tematizable y sí quedaría ilegible.
        $this->assertStringNotContainsString(
            'text-white',
            file_get_contents(resource_path('views/components/button.blade.php')),
        );
    }
}

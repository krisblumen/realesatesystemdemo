<?php

namespace Tests\Feature\Frontend;

use App\Filament\Pages\FrontendSettingsPage;
use App\Models\FrontendSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Save-time validation of navigation, CTAs and footer (RFC-073) — the first
 * half of the boundary the navigation service re-checks at render. Unsafe
 * targets, HTML labels and an all-hidden menu must be rejected with an error
 * rather than stored and quietly repaired.
 */
class FrontendNavigationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    public function test_a_valid_navigation_is_saved_with_its_order(): void
    {
        Livewire::test(FrontendSettingsPage::class)
            ->set('data.navigation', [
                ['key' => 'home', 'label' => 'Portada', 'enabled' => true],
                ['key' => 'contacto', 'label' => 'Hablemos', 'enabled' => true],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $nav = FrontendSetting::current()->fresh()->navigation;

        $this->assertSame('home', $nav[0]['key']);
        $this->assertSame(0, $nav[0]['sort_order']);
        $this->assertSame(1, $nav[1]['sort_order']);
    }

    public function test_a_navigation_with_every_link_disabled_is_rejected(): void
    {
        Livewire::test(FrontendSettingsPage::class)
            ->set('data.navigation', [
                ['key' => 'home', 'label' => 'Inicio', 'enabled' => false],
                ['key' => 'contacto', 'label' => 'Contacto', 'enabled' => false],
            ])
            ->call('save')
            ->assertHasErrors('data.navigation');
    }

    public function test_html_in_a_navigation_label_is_rejected(): void
    {
        Livewire::test(FrontendSettingsPage::class)
            ->set('data.navigation', [
                ['key' => 'home', 'label' => '<script>alert(1)</script>', 'enabled' => true],
            ])
            ->call('save')
            ->assertHasErrors('data.navigation.0.label');
    }

    public function test_an_unsafe_primary_cta_target_is_rejected(): void
    {
        Livewire::test(FrontendSettingsPage::class)
            ->set('data.primary_cta', ['label' => 'Evil', 'type' => 'url', 'target' => 'javascript:alert(1)'])
            ->call('save')
            ->assertHasErrors('data.primary_cta.target');
    }

    public function test_a_valid_route_cta_is_saved(): void
    {
        Livewire::test(FrontendSettingsPage::class)
            ->set('data.primary_cta', ['label' => 'Ver proyectos', 'type' => 'route', 'target' => 'proyectos'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('proyectos', FrontendSetting::current()->fresh()->primary_cta['target']);
    }

    public function test_an_unsafe_footer_link_is_rejected(): void
    {
        Livewire::test(FrontendSettingsPage::class)
            ->set('data.footer', [
                'columns' => [[
                    'title' => 'Enlaces',
                    'links' => [
                        ['label' => 'Malo', 'type' => 'url', 'target' => 'data:text/html,x', 'enabled' => true],
                    ],
                ]],
            ])
            ->call('save')
            ->assertHasErrors('data.footer.columns.0.links.0.target');
    }
}

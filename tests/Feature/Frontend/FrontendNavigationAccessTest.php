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
 * Owner-only, for real (§16.2 / RFC-073): navigation, footer and CTAs are edited
 * on the frontend settings page, which is gated by role AND permission. Hiding
 * the menu is not enough — a non-owner must get a real 403 and must not be able
 * to persist a change.
 */
class FrontendNavigationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(PermissionSeeder::class);
    }

    public function test_only_the_owner_can_reach_the_navigation_editor(): void
    {
        $this->assertTrue($this->actingAs(User::factory()->withRole('owner')->create())::class
            && FrontendSettingsPage::canAccess());

        foreach (['admin', 'agente'] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create());
            $this->assertFalse(FrontendSettingsPage::canAccess(), "`{$role}` must not reach the editor.");
        }
    }

    public function test_a_non_owner_cannot_even_open_the_editor(): void
    {
        // mount() abort_unless(canAccess(), 403): a non-owner never reaches the
        // form, so there is no path to persist navigation from the UI.
        $this->actingAs(User::factory()->withRole('admin')->create());

        $this->get(FrontendSettingsPage::getUrl())->assertForbidden();

        $this->assertNull(FrontendSetting::current()->fresh()->navigation);
    }

    public function test_the_owner_can_save_navigation(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.navigation', [['key' => 'home', 'label' => 'Inicio', 'enabled' => true]])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(FrontendSetting::current()->fresh()->navigation);
    }
}

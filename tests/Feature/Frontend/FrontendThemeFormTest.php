<?php

namespace Tests\Feature\Frontend;

use App\Filament\Pages\FrontendSettingsPage;
use App\Models\FrontendSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T-8: hard validation on save — the first half of the §16.5 double boundary.
 * Low contrast on any of the three pairs, or a font outside the compiled
 * allowlist, must be rejected with a validation error instead of being stored
 * and quietly repaired later at render.
 */
class FrontendThemeFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function form(array $theme = []): Testable
    {
        $component = Livewire::test(FrontendSettingsPage::class);

        foreach ($theme as $field => $value) {
            $component->set("data.theme.{$field}", $value);
        }

        return $component;
    }

    public function test_a_valid_palette_is_saved(): void
    {
        $this->form([
            'primary' => '#123456',
            'on_primary' => '#ffffff',
            'accent' => '#f5a624',
            'on_accent' => '#171d23',
            'background' => '#ffffff',
            'text' => '#171d23',
            'heading_font' => 'Inter',
            'body_font' => 'Inter',
            'radius' => 'rounded',
        ])->call('save')->assertHasNoErrors();

        $theme = FrontendSetting::current()->fresh()->theme;

        $this->assertSame('#123456', $theme['primary']);
        $this->assertSame('Inter', $theme['heading_font']);
        $this->assertSame('rounded', $theme['radius']);
    }

    public function test_low_contrast_on_primary_is_rejected(): void
    {
        $this->form(['primary' => '#f2f4f6', 'on_primary' => '#ffffff'])
            ->call('save')
            ->assertHasErrors('data.theme.on_primary');
    }

    public function test_low_contrast_on_accent_is_rejected(): void
    {
        // White on the brand orange: the classic unreadable CTA.
        $this->form(['accent' => '#f5a624', 'on_accent' => '#ffffff'])
            ->call('save')
            ->assertHasErrors('data.theme.on_accent');
    }

    public function test_low_contrast_text_over_background_is_rejected(): void
    {
        $this->form(['background' => '#ffffff', 'text' => '#cccccc'])
            ->call('save')
            ->assertHasErrors('data.theme.text');
    }

    public function test_the_owner_override_allows_a_low_contrast_pair_to_be_saved(): void
    {
        // The owner explicitly accepts low contrast: the AA check is skipped and
        // the brand colour (orange body text on white, 2.26:1) is saved as-is.
        $this->form([
            'background' => '#ffffff',
            'text' => '#ff9100',
            'allow_low_contrast' => true,
        ])->call('save')->assertHasNoErrors();

        $theme = FrontendSetting::current()->fresh()->theme;
        $this->assertSame('#ff9100', $theme['text']);
        $this->assertTrue($theme['allow_low_contrast']);
    }

    public function test_a_font_outside_the_compiled_allowlist_is_rejected(): void
    {
        // Poppins was retired (B-8): the runtime toggle must never name a font
        // Vite did not compile, or the page silently falls back to a system one.
        $this->form(['heading_font' => 'Poppins'])
            ->call('save')
            ->assertHasErrors('data.theme.heading_font');
    }

    public function test_a_non_hex_colour_is_rejected(): void
    {
        $this->form(['primary' => '#000}</style><script>alert(1)</script>'])
            ->call('save')
            ->assertHasErrors('data.theme.primary');
    }

    public function test_a_rejected_theme_does_not_persist_anything(): void
    {
        $this->form(['site_name' => 'irrelevante', 'accent' => '#f5a624', 'on_accent' => '#ffffff'])
            ->set('data.site_name', 'No debe guardarse')
            ->call('save')
            ->assertHasErrors('data.theme.on_accent');

        $this->assertNotSame('No debe guardarse', FrontendSetting::current()->fresh()->site_name);
    }
}

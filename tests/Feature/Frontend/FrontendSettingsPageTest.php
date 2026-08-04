<?php

namespace Tests\Feature\Frontend;

use App\Filament\Pages\FrontendSettingsPage;
use App\Models\FrontendSetting;
use App\Models\User;
use App\Services\Frontend\FrontendCacheGeneration;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * T-1 (page): owner 200, every other role a REAL 403.
 * T-9d (Setting slice): saving the real Filament form with an upload removed
 * from its state must NOT delete the media row nor its file — the stock
 * SpatieMediaLibraryFileUpload would (deleteAbandonedFiles -> Media::delete()).
 */
class FrontendSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_reaches_the_page_and_other_roles_get_a_real_403(): void
    {
        $url = FrontendSettingsPage::getUrl();

        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get($url)->assertOk()
            // The always-visible floating save action renders on the page.
            ->assertSee('Guardar cambios');

        foreach (['admin', 'agente', 'arquitectura', 'proyectos'] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())
                ->get($url)->assertForbidden();
        }
    }

    public function test_the_brand_section_shows_a_preview_card_per_asset(): void
    {
        // UX: each brand asset renders as a card with its title and current
        // state, so a non-technical owner sees what is live and how to change it.
        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(FrontendSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('Logo — fondo claro')
            ->assertSee('Logo — fondo oscuro')
            ->assertSee('Favicon')
            ->assertSee('Imagen para redes')
            // With no custom media, every card shows the default-brand state.
            ->assertSee('Marca New Hauz por defecto')
            // Each card states the ideal format and dimensions.
            ->assertSee('~400×120 px')
            ->assertSee('512×512 px (mín. 32×32)')
            ->assertSee('1200×630 px (proporción 1.91:1)');
    }

    public function test_identity_fields_show_the_current_saved_value(): void
    {
        FrontendSetting::current()->update([
            'site_name' => 'INMOBILIARIA-DEMO',
            'tagline' => 'LEMA-DEMO',
            'legal_name' => 'RAZON-SOCIAL-DEMO',
        ]);

        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(FrontendSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('Nombre actual:')
            ->assertSee('INMOBILIARIA-DEMO')
            ->assertSee('Lema actual:')
            ->assertSee('LEMA-DEMO')
            ->assertSee('Razón social actual:')
            ->assertSee('RAZON-SOCIAL-DEMO');
    }

    public function test_an_empty_identity_field_reads_sin_definir(): void
    {
        FrontendSetting::current()->update(['tagline' => null]);

        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(FrontendSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('Lema actual:')
            ->assertSee('sin definir');
    }

    public function test_a_custom_brand_image_is_flagged_as_personalizada(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $setting = FrontendSetting::current();
        $media = $setting->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo-light');
        $setting->update(['logo_light_media_id' => $media->uuid]);

        $this->actingAs($owner)
            ->get(FrontendSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('Imagen personalizada');
    }

    public function test_the_theme_section_shows_the_saved_logos_and_color_picker(): void
    {
        // The brand logos + eyedropper appear above the colour pickers so the
        // owner can pull a colour straight from the logo.
        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(FrontendSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('Tu logotipo guardado')
            ->assertSee('Cuentagotas')
            ->assertSee('Logo fondo claro', false)
            ->assertSee('Logo fondo oscuro', false);
    }

    public function test_the_seo_section_renders_a_share_preview_with_the_saved_values(): void
    {
        FrontendSetting::current()->update([
            'default_og_title' => 'TITULO-REDES-DEMO',
            'default_meta_description' => 'DESCRIPCION-META-DEMO',
        ]);

        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(FrontendSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('Vista previa al compartir el enlace')
            ->assertSee('TITULO-REDES-DEMO')
            // The card uses the meta description as the OG fallback.
            ->assertSee('DESCRIPCION-META-DEMO');
    }

    public function test_the_share_preview_falls_back_from_meta_to_og_title(): void
    {
        FrontendSetting::current()->update([
            'default_og_title' => null,
            'default_meta_title' => 'TITULO-BUSCADORES-DEMO',
        ]);

        // With no OG title, the preview uses the search (meta) title.
        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(FrontendSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('TITULO-BUSCADORES-DEMO');
    }

    public function test_enabling_a_day_prefills_the_default_hours(): void
    {
        // Opening a day fills 9:00–18:00 so the pickers are never blank (which
        // was confusing). The owner can still change them.
        $this->actingAs(User::factory()->withRole('owner')->create());

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.hours_ui.lunes.enabled', true)
            ->assertSet('data.hours_ui.lunes.open', '09:00')
            ->assertSet('data.hours_ui.lunes.close', '18:00');
    }

    public function test_the_day_editor_compiles_into_the_business_hours_key_value(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.hours_ui.lunes.enabled', true)
            ->set('data.hours_ui.lunes.open', '09:00')
            ->set('data.hours_ui.lunes.close', '18:00')
            ->set('data.hours_ui.sabado.enabled', true)
            ->set('data.hours_ui.sabado.open', '10:00')
            ->set('data.hours_ui.sabado.close', '14:00')
            // Domingo stays closed and must not appear.
            ->call('save')
            ->assertHasNoErrors();

        $hours = FrontendSetting::current()->fresh()->business_hours;

        // Stored as the SAME key-value shape the render already consumes — the
        // owner's «bypass»: friendly editor in, key-value out. jsonb does not
        // preserve key order, so the CONTENTS (not the order) are asserted here;
        // week order is a render concern, covered by the footer test below.
        $this->assertEqualsCanonicalizing(['Lunes' => '09:00 – 18:00', 'Sábado' => '10:00 – 14:00'], $hours);
        $this->assertArrayNotHasKey('Domingo', $hours);
    }

    public function test_the_footer_renders_hours_in_week_order_despite_jsonb(): void
    {
        // jsonb reorders keys; the footer must re-sort by the week, so stored
        // out-of-order days still read Lunes → Domingo.
        FrontendSetting::current()->update([
            'business_hours' => ['Sábado' => '10:00 – 14:00', 'Miércoles' => '09:00 – 18:00', 'Lunes' => '09:00 – 18:00'],
        ]);
        app(FrontendCacheGeneration::class)->bump();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Horario', $html);
        // Lunes appears before Miércoles, and Miércoles before Sábado.
        $this->assertLessThan(strpos($html, 'Miércoles'), strpos($html, 'Lunes'));
        $this->assertLessThan(strpos($html, 'Sábado'), strpos($html, 'Miércoles'));
    }

    public function test_the_footer_shows_the_three_dedicated_social_fields(): void
    {
        // The owner gets three always-visible, network-specific fields instead of
        // a generic repeater, each labelled with its network name.
        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(FrontendSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('Redes sociales')
            ->assertSee('Instagram')
            ->assertSee('TikTok')
            ->assertSee('Facebook');
    }

    public function test_saving_a_social_url_stores_it_under_its_network_key(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.social_links.instagram', 'https://instagram.com/newhauz')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            'https://instagram.com/newhauz',
            FrontendSetting::current()->fresh()->social_links['instagram'] ?? null,
        );
    }

    public function test_saving_updates_identity_contact_and_seo(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.site_name', 'Inmobiliaria Prueba')
            ->set('data.public_email', 'contacto@prueba.mx')
            ->set('data.default_meta_title', 'Título SEO')
            ->call('save')
            ->assertHasNoErrors();

        $setting = FrontendSetting::current()->fresh();
        $this->assertSame('Inmobiliaria Prueba', $setting->site_name);
        $this->assertSame('contacto@prueba.mx', $setting->public_email);
        $this->assertSame('Título SEO', $setting->default_meta_title);
    }

    public function test_removing_an_upload_from_the_form_state_keeps_row_and_file(): void
    {
        $setting = FrontendSetting::current();
        $media = $setting->addMedia(UploadedFile::fake()->image('logo.png'))
            ->toMediaCollection('logo-light');
        $setting->update(['logo_light_media_id' => $media->uuid]);

        $this->actingAs(User::factory()->withRole('owner')->create());

        // Empty upload state = the owner removed the image from the form.
        // The stock component would run Media::delete() here.
        Livewire::test(FrontendSettingsPage::class)
            ->set('data.logo_light', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(Media::find($media->id), 'The media row must survive.');
        $this->assertTrue(
            Storage::disk('public')->exists($media->getPathRelativeToRoot()),
            'The file must survive: no system path deletes media in v1 (§16.4).'
        );

        // The editorial reference is gone -> render falls back to the default brand.
        $this->assertNull(FrontendSetting::current()->fresh()->logo_light_media_id);
    }

    public function test_selecting_an_existing_upload_syncs_the_brand_column(): void
    {
        $setting = FrontendSetting::current();
        $media = $setting->addMedia(UploadedFile::fake()->image('logo.png'))
            ->toMediaCollection('logo-light');

        $this->actingAs(User::factory()->withRole('owner')->create());

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.logo_light', [$media->uuid => $media->uuid])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($media->uuid, FrontendSetting::current()->fresh()->logo_light_media_id);
    }

    public function test_svg_is_rejected_by_brand_collections(): void
    {
        $this->expectException(FileUnacceptableForCollection::class);

        FrontendSetting::current()
            ->addMedia(UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->toMediaCollection('logo-light');
    }
}

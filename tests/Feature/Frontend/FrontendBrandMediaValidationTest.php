<?php

namespace Tests\Feature\Frontend;

use App\Filament\Pages\FrontendSettingsPage;
use App\Models\FrontendSetting;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M-1 (audit of batch A): §16.1 requires that every brand uuid be validated
 * server-side — it must EXIST, belong to the FrontendSetting singleton and to
 * the matching collection. The FK only proves existence, so a uuid from
 * another collection or another model used to be persisted happily.
 *
 * The render already defends itself (brandUrl re-checks), but invalid state
 * must never reach the database: rejecting late is not rejecting.
 */
class FrontendBrandMediaValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    public function test_rejects_a_uuid_from_another_collection_without_touching_any_column(): void
    {
        $setting = FrontendSetting::current();
        $favicon = $setting->addMedia(UploadedFile::fake()->image('fav.png'))
            ->toMediaCollection('favicon');

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.logo_light', [$favicon->uuid => $favicon->uuid])
            ->call('save')
            ->assertHasErrors('data.logo_light');

        $fresh = FrontendSetting::current()->fresh();
        $this->assertNull($fresh->logo_light_media_id, 'A cross-collection uuid must not be persisted.');
        $this->assertNull($fresh->favicon_media_id, 'No other column may be written on a rejected save.');
    }

    public function test_rejects_a_uuid_belonging_to_another_model(): void
    {
        $property = Property::factory()->create();
        $foreign = $property->addMedia(UploadedFile::fake()->image('cover.png'))
            ->toMediaCollection('cover');

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.logo_light', [$foreign->uuid => $foreign->uuid])
            ->call('save')
            ->assertHasErrors('data.logo_light');

        $this->assertNull(FrontendSetting::current()->fresh()->logo_light_media_id);
    }

    public function test_rejects_a_uuid_that_does_not_exist_at_all(): void
    {
        Livewire::test(FrontendSettingsPage::class)
            ->set('data.logo_light', ['00000000-0000-0000-0000-000000000009' => '00000000-0000-0000-0000-000000000009'])
            ->call('save')
            ->assertHasErrors('data.logo_light');

        $this->assertNull(FrontendSetting::current()->fresh()->logo_light_media_id);
    }

    public function test_a_rejected_save_does_not_persist_the_other_valid_fields_either(): void
    {
        // All-or-nothing: a rejected reference aborts the whole save so the
        // owner never ends up with half of the form applied.
        $setting = FrontendSetting::current();
        $favicon = $setting->addMedia(UploadedFile::fake()->image('fav.png'))
            ->toMediaCollection('favicon');

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.site_name', 'No debe guardarse')
            ->set('data.logo_light', [$favicon->uuid => $favicon->uuid])
            ->call('save')
            ->assertHasErrors('data.logo_light');

        $this->assertNotSame('No debe guardarse', FrontendSetting::current()->fresh()->site_name);
    }

    public function test_accepts_a_uuid_of_the_right_model_and_collection(): void
    {
        $setting = FrontendSetting::current();
        $logo = $setting->addMedia(UploadedFile::fake()->image('logo.png'))
            ->toMediaCollection('logo-light');

        Livewire::test(FrontendSettingsPage::class)
            ->set('data.logo_light', [$logo->uuid => $logo->uuid])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($logo->uuid, FrontendSetting::current()->fresh()->logo_light_media_id);
    }
}

<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\FeatureResource;
use App\Filament\Resources\FeatureResource\Pages\CreateFeature;
use App\Filament\Resources\FeatureResource\Pages\EditFeature;
use App\Filament\Resources\FeatureResource\Pages\ListFeatures;
use App\Models\Feature;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FeatureResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_and_admin_access_catalog_while_agent_is_forbidden(): void
    {
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agente');

        $this->actingAs($owner)->get(FeatureResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(FeatureResource::getUrl('index'))->assertOk();
        $this->actingAs($agent)->get(FeatureResource::getUrl('index'))->assertForbidden();
    }

    public function test_owner_can_create_edit_and_delete_feature(): void
    {
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(CreateFeature::class)
            ->fillForm([
                'name' => 'Paneles solares',
                'slug' => 'paneles-solares',
                'icon' => 'heroicon-o-sun',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $feature = Feature::where('slug', 'paneles-solares')->firstOrFail();

        Livewire::test(EditFeature::class, ['record' => $feature->getRouteKey()])
            ->fillForm([
                'name' => 'Energía solar',
                'slug' => 'energia-solar',
                'icon' => 'heroicon-o-sun',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(ListFeatures::class)->callTableAction('delete', $feature->refresh());

        $this->assertDatabaseMissing('features', ['id' => $feature->id]);
    }

    public function test_feature_form_validates_required_and_unique_fields(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        Feature::factory()->create(['slug' => 'alberca']);

        Livewire::test(CreateFeature::class)
            ->fillForm(['name' => '', 'slug' => 'alberca'])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'unique',
            ]);
    }

    public function test_admin_cannot_delete_feature(): void
    {
        $admin = $this->userWithRole('admin');
        $feature = Feature::factory()->create();
        $this->actingAs($admin);

        Livewire::test(ListFeatures::class)
            ->assertTableActionHidden('delete', $feature);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

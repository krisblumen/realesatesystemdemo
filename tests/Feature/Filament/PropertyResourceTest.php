<?php

namespace Tests\Feature\Filament;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Filament\Resources\PropertyResource;
use App\Filament\Resources\PropertyResource\Pages\Concerns\EnforcesAgentPropertyOwnership;
use App\Filament\Resources\PropertyResource\Pages\CreateProperty;
use App\Filament\Resources\PropertyResource\Pages\EditProperty;
use App\Filament\Resources\PropertyResource\Pages\ListProperties;
use App\Models\Feature;
use App\Models\PostalCode;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_roles_with_permission_can_access_index_but_agent_cannot_edit_another_record(): void
    {
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agente');
        $other = $this->userWithRole('agente');
        $property = Property::factory()->forAgent($other)->create();

        $this->actingAs($owner)->get(PropertyResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(PropertyResource::getUrl('index'))->assertOk();
        $this->actingAs($agent)->get(PropertyResource::getUrl('index'))->assertOk();
        $this->actingAs($agent)
            ->get(PropertyResource::getUrl('edit', ['record' => $property]))
            ->assertNotFound();
    }

    public function test_owner_can_create_property_with_agent_zone_and_features(): void
    {
        $owner = $this->userWithRole('owner');
        $agent = $this->userWithRole('agente');
        $zone = Zone::factory()->create();
        $features = Feature::factory()->count(2)->create();
        $this->actingAs($owner);

        Livewire::test(CreateProperty::class)
            ->fillForm(['estado_filter' => $zone->state_id])
            ->fillForm(['municipio_filter' => $zone->municipality_id])
            ->fillForm($this->formData([
                'title' => 'Casa de prueba',
                'zone_id' => $zone->id,
                'agent_id' => $agent->id,
                'features' => $features->modelKeys(),
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $property = Property::where('title', 'Casa de prueba')->firstOrFail();

        $this->assertSame(PropertyStatus::Borrador, $property->status);
        $this->assertSame('2.5', $property->bathrooms);
        $this->assertSame($agent->id, $property->agent_id);
        $this->assertSame($zone->id, $property->zone_id);
        $this->assertEqualsCanonicalizing($features->modelKeys(), $property->features()->pluck('features.id')->all());
    }

    public function test_agent_payload_forces_self_and_rejects_foreign_zone(): void
    {
        $agent = $this->userWithRole('agente');
        $other = $this->userWithRole('agente');
        $ownZone = Zone::factory()->create();
        $foreignZone = Zone::factory()->create();
        $agent->zones()->attach($ownZone);
        $this->actingAs($agent);

        Livewire::test(CreateProperty::class)
            ->fillForm(['estado_filter' => $ownZone->state_id])
            ->fillForm(['municipio_filter' => $ownZone->municipality_id])
            ->fillForm($this->formData([
                'title' => 'Propiedad propia',
                'zone_id' => $ownZone->id,
                'agent_id' => $other->id,
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $property = Property::where('title', 'Propiedad propia')->firstOrFail();

        $this->assertDatabaseHas('properties', [
            'title' => 'Propiedad propia',
            'agent_id' => $agent->id,
            'zone_id' => $ownZone->id,
        ]);

        Livewire::test(ListProperties::class)
            ->set('activeTab', 'borradores')
            ->assertCanSeeTableRecords([$property]);

        $guard = new class
        {
            use EnforcesAgentPropertyOwnership;

            /** @param array<string, mixed> $data
             * @return array<string, mixed>
             */
            public function enforce(array $data): array
            {
                return $this->enforceAgentOwnership($data);
            }
        };

        $this->expectException(ValidationException::class);
        $guard->enforce(['zone_id' => $foreignZone->id, 'agent_id' => $other->id]);
    }

    public function test_owner_can_edit_soft_delete_and_restore_as_draft(): void
    {
        $owner = $this->userWithRole('owner');
        $property = Property::factory()->published()->create();
        $zone = $property->zone;
        // CP↔zona coherente + colonia del catálogo para que el Select tenga su opción.
        $zone->update(['postal_code' => '76000']);
        PostalCode::create([
            'postal_code' => '76000',
            'colonia' => 'Centro',
            'municipality_id' => $zone->municipality_id,
            'state_id' => $zone->state_id,
        ]);
        $property->update(['postal_code' => '76000', 'colonia' => 'Centro']);
        $this->actingAs($owner);

        Livewire::test(EditProperty::class, ['record' => $property->getRouteKey()])
            ->fillForm(['title' => 'Título editado'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(ListProperties::class)->callTableAction('delete', $property->refresh());
        $this->assertSoftDeleted('properties', ['id' => $property->id]);

        $trashed = Property::withTrashed()->findOrFail($property->id);
        Livewire::test(ListProperties::class)
            ->filterTable('trashed', false)
            ->callTableAction('restore', $trashed);

        $this->assertSame(PropertyStatus::Borrador, $property->fresh()->status);
        $this->assertSame('Título editado', $property->fresh()->title);
    }

    public function test_postal_code_autocompletes_zone_and_colonia_is_filtered_by_cp(): void
    {
        $owner = $this->userWithRole('owner');
        $zone = Zone::factory()->create(['postal_code' => '76000']);
        // Misma colonia "Centro" en dos CP distintos: sólo debe ofrecerse la del CP de la zona.
        PostalCode::create(['postal_code' => '76000', 'colonia' => 'Centro', 'municipality_id' => $zone->municipality_id, 'state_id' => $zone->state_id]);
        PostalCode::create(['postal_code' => '76160', 'colonia' => 'Álamos 1a Sección', 'municipality_id' => $zone->municipality_id, 'state_id' => $zone->state_id]);
        $this->actingAs($owner);

        Livewire::test(CreateProperty::class)
            ->fillForm(['postal_code' => '76000'])
            // Fix 2: el CP autocompleta zona, estado y municipio.
            ->assertFormSet([
                'zone_id' => $zone->id,
                'estado_filter' => $zone->state_id,
                'municipio_filter' => $zone->municipality_id,
            ])
            // Fix 1: la colonia se ofrece SÓLO del CP de la zona (no del municipio).
            ->assertFormFieldExists(
                'colonia',
                fn (Select $field): bool => $field->getOptions() === ['Centro' => 'Centro'],
            );
    }

    public function test_edit_page_restore_also_degrades_published_property_to_draft(): void
    {
        $owner = $this->userWithRole('owner');
        $property = Property::factory()->published()->create();
        $property->delete();
        $this->actingAs($owner);

        Livewire::test(EditProperty::class, ['record' => $property->getRouteKey()])
            ->callAction('restore');

        $this->assertSame(PropertyStatus::Borrador, $property->fresh()->status);
    }

    public function test_state_actions_use_the_commercial_service(): void
    {
        $owner = $this->userWithRole('owner');
        $property = Property::factory()->create([
            'zone_id' => Zone::factory(),
            'operation_type' => OperationType::Venta,
            'owner_id' => PropertyOwner::factory(),
            'commission_percentage' => 5,
            'street' => 'Av. Constituyentes',
            'colonia' => 'Centro',
        ]);
        $this->addCover($property);
        $this->actingAs($owner);

        Livewire::test(ListProperties::class)->set('activeTab', 'borradores')->callTableAction('publish', $property);
        $this->assertSame(PropertyStatus::Publicado, $property->refresh()->status);

        Livewire::test(ListProperties::class)->set('activeTab', 'publicados')->callTableAction('markSold', $property);
        $this->assertSame(PropertyStatus::Vendido, $property->refresh()->status);

        Livewire::test(ListProperties::class)->set('activeTab', 'cerrados')->callTableAction('reopen', $property);
        $this->assertSame(PropertyStatus::Borrador, $property->refresh()->status);
    }

    public function test_agent_does_not_see_soft_deleted_properties(): void
    {
        $agent = $this->userWithRole('agente');
        $active = Property::factory()->forAgent($agent)->create();
        $trashed = Property::factory()->forAgent($agent)->create();
        $trashed->delete();
        $this->actingAs($agent);

        Livewire::test(ListProperties::class)
            ->set('activeTab', 'borradores')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$trashed]);
    }

    public function test_owner_can_filter_properties_by_agent(): void
    {
        $owner = $this->userWithRole('owner');
        $agentA = $this->userWithRole('agente');
        $agentB = $this->userWithRole('agente');
        $a = Property::factory()->forAgent($agentA)->create();
        $b = Property::factory()->forAgent($agentB)->create();
        $this->actingAs($owner);

        Livewire::test(ListProperties::class)
            ->set('activeTab', 'borradores')
            ->filterTable('agent', $agentA->id)
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }

    public function test_list_splits_properties_into_status_tabs(): void
    {
        $owner = $this->userWithRole('owner');
        $published = Property::factory()->published()->create();
        $draft = Property::factory()->create();
        $this->actingAs($owner);

        Livewire::test(ListProperties::class)
            ->set('activeTab', 'publicados')
            ->assertCanSeeTableRecords([$published])
            ->assertCanNotSeeTableRecords([$draft])
            ->set('activeTab', 'borradores')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$published]);
    }

    public function test_list_shows_most_recent_properties_first(): void
    {
        $owner = $this->userWithRole('owner');
        $older = Property::factory()->create(['created_at' => now()->subDays(3)]);
        $newer = Property::factory()->create(['created_at' => now()]);
        $this->actingAs($owner);

        Livewire::test(ListProperties::class)
            ->set('activeTab', 'borradores')
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_publish_action_notifies_the_reason_when_requirements_are_missing(): void
    {
        $owner = $this->userWithRole('owner');
        // Inmueble en borrador sin imagen principal: la publicación debe fallar.
        $property = Property::factory()->create(['zone_id' => Zone::factory()]);
        $this->actingAs($owner);

        Livewire::test(ListProperties::class)
            ->set('activeTab', 'borradores')
            ->callTableAction('publish', $property)
            ->assertNotified('No se pudo publicar el inmueble');

        $this->assertSame(PropertyStatus::Borrador, $property->refresh()->status);
    }

    /** @param array<string, mixed> $overrides */
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Inmueble de prueba',
            'description' => 'Descripción del inmueble.',
            'operation_type' => OperationType::Venta->value,
            'property_type' => PropertyType::Casa->value,
            'price' => 2_500_000,
            'bedrooms' => 3,
            'bathrooms' => 2.5,
            'parking_spaces' => 2,
            'land_area' => 180,
            'construction_area' => 140,
            'zone_id' => null,
            'agent_id' => null,
            'features' => [],
            'meta_title' => null,
            'meta_description' => null,
            'canonical_url' => null,
        ], $overrides);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    private function addCover(Property $property): void
    {
        $property->addMediaFromString((string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQYlWNgGAWkAwABNgABxYufBwAAAABJRU5ErkJggg==',
            true,
        ))
            ->usingFileName('cover.png')
            ->toMediaCollection('cover');
    }
}

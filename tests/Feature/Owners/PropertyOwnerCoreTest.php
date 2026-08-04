<?php

namespace Tests\Feature\Owners;

use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyOwnerCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_owner_persists_with_its_agent(): void
    {
        $agent = User::factory()->create();

        $owner = PropertyOwner::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'phone' => '4421234567',
            'email' => 'juan@example.com',
            'agent_id' => $agent->id,
        ]);

        $this->assertDatabaseHas('property_owners', [
            'id' => $owner->id,
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'phone' => '4421234567',
            'email' => 'juan@example.com',
            'agent_id' => $agent->id,
        ]);

        $this->assertTrue($owner->agent->is($agent));
    }

    public function test_property_owner_email_is_optional(): void
    {
        $owner = PropertyOwner::factory()->create(['email' => null]);

        $this->assertNull($owner->fresh()->email);
    }

    public function test_property_owner_supports_soft_deletes(): void
    {
        $owner = PropertyOwner::factory()->create();

        $owner->delete();

        $this->assertSoftDeleted('property_owners', ['id' => $owner->id]);
    }
}

<?php

namespace Tests\Feature\Properties;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use App\Services\UserStatusService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySuspensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_suspending_an_agent_keeps_their_published_property_published(): void
    {
        $property = Property::factory()->published()->create();
        $agent = $property->agent;
        $owner = User::factory()->withRole('owner')->create();

        app(UserStatusService::class)->suspend($agent, $owner, 'Prueba de suspensión.');

        $this->assertTrue($agent->fresh()->isSuspended());
        $this->assertSame(PropertyStatus::Publicado, $property->fresh()->status);
    }
}

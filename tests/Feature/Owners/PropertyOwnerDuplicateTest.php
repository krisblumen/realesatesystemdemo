<?php

namespace Tests\Feature\Owners;

use App\Models\PropertyOwner;
use App\Models\User;
use App\Rules\UniquePropertyOwner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PropertyOwnerDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_find_duplicate_matches_case_insensitive_name_and_normalized_phone(): void
    {
        $existing = PropertyOwner::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'phone' => '442 123 4567',
        ]);

        $match = PropertyOwner::findDuplicate('JUAN', 'perez', '4421234567');

        $this->assertNotNull($match);
        $this->assertSame($existing->id, $match->id);

        $this->assertNull(PropertyOwner::findDuplicate('Juan', 'Perez', '9999999999'));
        $this->assertNull(PropertyOwner::findDuplicate('Otro', 'Nombre', '4421234567'));
    }

    public function test_agent_gets_privacy_safe_message_for_another_agents_client(): void
    {
        $agentA = User::factory()->withRole('agente')->create();
        $agentB = User::factory()->withRole('agente')->create();
        PropertyOwner::factory()->create([
            'first_name' => 'Ana', 'last_name' => 'Lopez', 'phone' => '5551112222', 'agent_id' => $agentA->id,
        ]);

        $this->actingAs($agentB);

        $validator = Validator::make(
            ['first_name' => 'Ana', 'last_name' => 'Lopez', 'phone' => '5551112222'],
            ['phone' => [new UniquePropertyOwner]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('otro agente', $validator->errors()->first('phone'));
        $this->assertStringNotContainsString($agentA->name, $validator->errors()->first('phone'));
    }

    public function test_unique_rule_passes_when_no_duplicate(): void
    {
        $this->actingAs(User::factory()->withRole('agente')->create());

        $validator = Validator::make(
            ['first_name' => 'Nuevo', 'last_name' => 'Cliente', 'phone' => '5550000000'],
            ['phone' => [new UniquePropertyOwner]],
        );

        $this->assertFalse($validator->fails());
    }
}

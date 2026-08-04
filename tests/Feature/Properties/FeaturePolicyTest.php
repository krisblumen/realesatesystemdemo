<?php

namespace Tests\Feature\Properties;

use App\Models\Feature;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FeaturePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_and_admin_manage_catalog_while_agent_cannot(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->withRole('agente')->create();
        $feature = Feature::factory()->create();

        foreach ([$owner, $admin] as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Feature::class));
            $this->assertTrue(Gate::forUser($user)->allows('create', Feature::class));
            $this->assertTrue(Gate::forUser($user)->allows('update', $feature));
        }

        $this->assertTrue(Gate::forUser($owner)->allows('delete', $feature));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $feature));
        $this->assertFalse(Gate::forUser($agent)->allows('viewAny', Feature::class));
        $this->assertFalse(Gate::forUser($agent)->allows('update', $feature));
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_seeds_initial_owner_with_role_and_active_status(): void
    {
        putenv('OWNER_EMAIL=initial@newhauz.test');
        putenv('OWNER_PASSWORD=s3cret-pass');

        $this->seed(OwnerSeeder::class);

        $owner = User::where('email', 'initial@newhauz.test')->firstOrFail();

        $this->assertTrue($owner->hasRole('owner'));
        $this->assertSame(UserStatus::Active, $owner->status);
        $this->assertTrue(Hash::check('s3cret-pass', $owner->password));

        putenv('OWNER_EMAIL');
        putenv('OWNER_PASSWORD');
    }

    public function test_owner_seeder_is_idempotent(): void
    {
        putenv('OWNER_EMAIL=initial@newhauz.test');
        putenv('OWNER_PASSWORD=s3cret-pass');

        $this->seed(OwnerSeeder::class);
        $this->seed(OwnerSeeder::class);

        $this->assertSame(1, User::where('email', 'initial@newhauz.test')->count());

        putenv('OWNER_EMAIL');
        putenv('OWNER_PASSWORD');
    }

    public function test_falls_back_to_default_email_when_env_absent(): void
    {
        putenv('OWNER_EMAIL');
        putenv('OWNER_PASSWORD');

        $this->seed(OwnerSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'owner@newhauz.test']);
    }
}

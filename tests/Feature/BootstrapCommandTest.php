<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_command_seeds_roles_and_initial_owner(): void
    {
        putenv('OWNER_EMAIL=boot@newhauz.test');
        putenv('OWNER_PASSWORD=boot-pass');

        $this->artisan('app:bootstrap')
            ->assertSuccessful();

        $this->assertSame(5, Role::count());

        $owner = User::where('email', 'boot@newhauz.test')->firstOrFail();
        $this->assertTrue($owner->hasRole('owner'));

        putenv('OWNER_EMAIL');
        putenv('OWNER_PASSWORD');
    }
}

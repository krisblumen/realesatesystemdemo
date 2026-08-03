<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SidebarResponsiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_pages_inject_the_responsive_sidebar_script(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('syncSidebarToViewport', escape: false);
    }
}

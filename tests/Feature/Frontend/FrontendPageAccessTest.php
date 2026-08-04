<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource;
use App\Models\FrontendPage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner-only, for real (§16.2 / RFC-075): institutional page content is edited
 * only by owner; other roles get a real 403 and pages cannot be created or
 * force-deleted from the UI.
 */
class FrontendPageAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_only_the_owner_can_view_the_pages_module(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());
        $this->assertTrue(FrontendPageResource::canViewAny());

        foreach (['admin', 'agente', 'arquitectura', 'proyectos'] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create());
            $this->assertFalse(FrontendPageResource::canViewAny(), "`{$role}` must not reach the pages module.");
        }
    }

    public function test_a_non_owner_is_forbidden_from_the_index(): void
    {
        $this->actingAs(User::factory()->withRole('admin')->create());
        $this->get(FrontendPageResource::getUrl('index'))->assertForbidden();
    }

    public function test_the_owner_can_open_a_page_editor(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();

        $this->get(FrontendPageResource::getUrl('edit', ['record' => $page]))->assertOk();
    }

    public function test_pages_cannot_be_created_or_force_deleted(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();

        $this->assertFalse($owner->can('create', FrontendPage::class));
        $this->assertFalse($owner->can('delete', $page));
        $this->assertFalse($owner->can('forceDelete', $page));
    }
}

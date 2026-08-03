<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Widgets\AgentesSectionHeader;
use App\Filament\Widgets\InmueblesSectionHeader;
use App\Filament\Widgets\LeadsSectionHeader;
use App\Filament\Widgets\PropietariosSectionHeader;
use App\Filament\Widgets\ZonasSectionHeader;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardSectionHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, class-string> */
    private array $sharedHeaders = [
        InmueblesSectionHeader::class,
        LeadsSectionHeader::class,
        AgentesSectionHeader::class,
        PropietariosSectionHeader::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    public function test_shared_headers_are_visible_to_every_internal_role(): void
    {
        foreach (['owner', 'admin', 'agente'] as $role) {
            $this->actingAs($this->userWithRole($role));

            foreach ($this->sharedHeaders as $header) {
                $this->assertTrue($header::canView(), "{$header} should be visible to {$role}");
            }
        }
    }

    public function test_zones_header_is_for_owner_and_admin_only(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        $this->assertTrue(ZonasSectionHeader::canView());

        $this->actingAs($this->userWithRole('agente'));
        $this->assertFalse(ZonasSectionHeader::canView());
    }

    public function test_performance_header_title_changes_for_agents(): void
    {
        $this->actingAs($this->userWithRole('agente'));
        $this->assertSame('Mi rendimiento', (new AgentesSectionHeader)->getSectionTitle());

        $this->actingAs($this->userWithRole('owner'));
        $this->assertSame('Agentes', (new AgentesSectionHeader)->getSectionTitle());
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;
use App\Services\UserStatusService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_suspend_and_reactivate_agent_with_audit_logs(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agente');

        app(UserStatusService::class)->suspend($agent, $admin, 'Incumplimiento operativo');

        $this->assertSame(UserStatus::Suspended, $agent->refresh()->status);
        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $agent->id,
            'changed_by' => $admin->id,
            'from_status' => UserStatus::Active->value,
            'to_status' => UserStatus::Suspended->value,
            'reason' => 'Incumplimiento operativo',
        ]);

        app(UserStatusService::class)->reactivate($agent, $admin, 'Corrección validada');

        $this->assertSame(UserStatus::Active, $agent->refresh()->status);
        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $agent->id,
            'changed_by' => $admin->id,
            'from_status' => UserStatus::Suspended->value,
            'to_status' => UserStatus::Active->value,
            'reason' => 'Corrección validada',
        ]);
        $this->assertSame(2, UserStatusLog::where('user_id', $agent->id)->count());
    }

    public function test_admin_cannot_suspend_owner(): void
    {
        $admin = $this->userWithRole('admin');
        $owner = $this->userWithRole('owner');

        $this->expectException(AuthorizationException::class);

        app(UserStatusService::class)->suspend($owner, $admin, 'Intento inválido');
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

<?php

namespace Tests\Feature\Mail;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SyncMailUnseenCountsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_unseen_count_for_active_newhauz_users(): void
    {
        $user = User::factory()->create([
            'email' => 'kris@newhauz.com.mx',
            'status' => UserStatus::Active,
        ]);

        Process::fake([
            '*newhauz-mail-unseen.sh*' => Process::result(output: "INBOX unseen=5\n"),
        ]);

        $this->artisan('mail:sync-unseen')->assertSuccessful();

        $user->refresh();
        $this->assertSame(5, $user->mail_unseen_count);
        $this->assertNotNull($user->mail_unseen_synced_at);

        Process::assertRan(fn ($process): bool => in_array($user->email, (array) $process->command, true));
    }

    public function test_skips_users_outside_the_newhauz_domain(): void
    {
        $outsider = User::factory()->create([
            'email' => 'agente@gmail.com',
            'status' => UserStatus::Active,
        ]);

        Process::fake([
            '*newhauz-mail-unseen.sh*' => Process::result(output: "INBOX unseen=9\n"),
        ]);

        $this->artisan('mail:sync-unseen')->assertSuccessful();

        $this->assertNull($outsider->refresh()->mail_unseen_count);
        Process::assertNotRan(fn ($process): bool => in_array($outsider->email, (array) $process->command, true));
    }

    public function test_skips_suspended_newhauz_users(): void
    {
        $suspended = User::factory()->create([
            'email' => 'suspendido@newhauz.com.mx',
            'status' => UserStatus::Suspended,
        ]);

        Process::fake([
            '*newhauz-mail-unseen.sh*' => Process::result(output: "INBOX unseen=2\n"),
        ]);

        $this->artisan('mail:sync-unseen')->assertSuccessful();

        $this->assertNull($suspended->refresh()->mail_unseen_count);
        Process::assertNotRan(fn ($process): bool => in_array($suspended->email, (array) $process->command, true));
    }

    public function test_logs_and_continues_when_the_script_fails_for_one_user(): void
    {
        $broken = User::factory()->create([
            'email' => 'broken@newhauz.com.mx',
            'status' => UserStatus::Active,
        ]);
        $healthy = User::factory()->create([
            'email' => 'healthy@newhauz.com.mx',
            'status' => UserStatus::Active,
        ]);

        Process::fake([
            '*broken@newhauz.com.mx*' => Process::result(errorOutput: 'invalid mailbox', exitCode: 1),
            '*healthy@newhauz.com.mx*' => Process::result(output: "INBOX unseen=1\n"),
        ]);

        $this->artisan('mail:sync-unseen')->assertSuccessful();

        $this->assertNull($broken->refresh()->mail_unseen_count);
        $this->assertSame(1, $healthy->refresh()->mail_unseen_count);
    }
}

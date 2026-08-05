<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_shows_mail_icon_with_badge_for_landra_users_with_unseen_mail(): void
    {
        $user = User::factory()->withRole('owner')->create([
            'email' => 'kris@landracore.com',
            'mail_unseen_count' => 4,
        ]);

        $this->actingAs($user)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('fi-topbar-mail-btn', false)
            ->assertSee('webmail.landracore.com', false)
            ->assertSee('fi-icon-btn-badge-ctn', false);
    }

    public function test_hides_mail_icon_for_users_outside_the_landra_domain(): void
    {
        $user = User::factory()->withRole('owner')->create([
            'email' => 'owner@example.test',
            'mail_unseen_count' => 4,
        ]);

        $this->actingAs($user)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertDontSee('fi-topbar-mail-btn', false);
    }

    public function test_mail_icon_has_no_badge_when_there_is_no_unseen_mail(): void
    {
        $user = User::factory()->withRole('owner')->create([
            'email' => 'kris@landracore.com',
            'mail_unseen_count' => null,
        ]);

        $this->actingAs($user)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('fi-topbar-mail-btn', false)
            ->assertDontSee('fi-icon-btn-badge-ctn', false);
    }
}

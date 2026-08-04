<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Events\UserRegistered;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_notification_uses_mail_channel_with_an_activation_link_containing_the_token(): void
    {
        $user = User::factory()->pending()->create();
        $notification = new WelcomeNotification('un-token-de-prueba');

        $this->assertSame(['mail'], $notification->via($user));
        $this->assertInstanceOf(ShouldQueue::class, $notification);

        $mail = $notification->toMail($user);
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertStringContainsString('un-token-de-prueba', $mail->actionUrl);
        $this->assertStringContainsString(urlencode($user->email), $mail->actionUrl);
    }

    public function test_user_registered_event_notifies_the_new_user(): void
    {
        Notification::fake();
        $user = User::factory()->pending()->create();

        UserRegistered::dispatch($user);

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_creating_a_user_from_the_panel_dispatches_a_welcome_notification(): void
    {
        Notification::fake();
        $owner = User::factory()->active()->withRole('owner')->create();
        $this->actingAs($owner);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Agente Nuevo',
                'email' => 'agente-nuevo@example.test',
                'roles' => ['agente'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'agente-nuevo@example.test')->firstOrFail();

        $this->assertSame(UserStatus::Pending, $user->status);
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_full_activation_flow_sets_status_active_and_allows_login(): void
    {
        $user = User::factory()->withRole('agente')->pending()->create();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
            ->fillForm([
                'password' => 'contrasena-elegida-por-el-agente',
                'passwordConfirmation' => 'contrasena-elegida-por-el-agente',
            ])
            ->call('resetPassword');

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue(Hash::check('contrasena-elegida-por-el-agente', $user->password));

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'contrasena-elegida-por-el-agente',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_activation_link_signature_is_valid_over_real_http(): void
    {
        $user = User::factory()->withRole('agente')->pending()->create();
        $token = Password::createToken($user);

        $url = Filament::getResetPasswordUrl($token, $user);

        $this->get($url)->assertOk();
    }

    public function test_pending_user_cannot_log_in_before_activating(): void
    {
        $user = User::factory()->withRole('agente')->pending()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'cualquier-cosa',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_owner_can_resend_invitation_to_a_pending_user(): void
    {
        Notification::fake();
        $owner = User::factory()->active()->withRole('owner')->create();
        $pending = User::factory()->withRole('agente')->pending()->create();

        $this->actingAs($owner);

        Livewire::test(ListUsers::class)
            ->callTableAction('resendInvitation', $pending);

        Notification::assertSentTo($pending, WelcomeNotification::class);
    }

    public function test_resend_invitation_action_is_hidden_for_active_users(): void
    {
        $owner = User::factory()->active()->withRole('owner')->create();
        $active = User::factory()->active()->withRole('agente')->create();

        $this->actingAs($owner);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('resendInvitation', $active);
    }
}

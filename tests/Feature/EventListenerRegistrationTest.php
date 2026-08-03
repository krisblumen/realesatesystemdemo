<?php

namespace Tests\Feature;

use App\Events\LeadAssigned;
use App\Events\LeadCaptured;
use App\Events\UserRegistered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventListenerRegistrationTest extends TestCase
{
    /**
     * bootstrap/app.php debe mantener el auto-discovery de listeners
     * deshabilitado (withEvents(discover: false)). Application::configure()
     * lo activa por default, y como esta app registra sus listeners a mano
     * en AppServiceProvider::boot(), con el discovery prendido cada evento
     * queda enlazado dos veces mas de lo esperado: el listener corre de mas
     * por cada dispatch (ej. WelcomeNotification generaba un token nuevo que
     * pisaba al anterior en password_reset_tokens, invalidando el primer
     * mail enviado; SendLeadAssignedNotification le mandaba doble mail al
     * agente). LeadCaptured tiene 2 a proposito (AssignCapturedLead +
     * SendLeadConfirmationToClient, en ese orden); el resto tiene 1.
     *
     * @return array<string, array{class-string, int}>
     */
    public static function registeredEvents(): array
    {
        return [
            'Login' => [Login::class, 1],
            'LeadCaptured' => [LeadCaptured::class, 2],
            'LeadAssigned' => [LeadAssigned::class, 1],
            'UserRegistered' => [UserRegistered::class, 1],
            'PasswordReset' => [PasswordReset::class, 1],
        ];
    }

    #[DataProvider('registeredEvents')]
    public function test_event_has_the_expected_number_of_listeners_bound(string $event, int $expected): void
    {
        $listeners = app('events')->getListeners($event);

        $this->assertCount(
            $expected,
            $listeners,
            "{$event} tiene ".count($listeners)." listeners enlazados (deberia ser {$expected}) -- revisar auto-discovery en bootstrap/app.php o registros duplicados en AppServiceProvider.",
        );
    }
}

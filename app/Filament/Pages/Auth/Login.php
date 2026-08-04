<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\AgentDashboard;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if ($user?->isSuspended()) {
            Filament::auth()->logout();

            throw ValidationException::withMessages([
                'data.email' => 'Tu cuenta está suspendida. Contacta al administrador.',
            ]);
        }

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return $this->makeLoginResponse();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Iniciar sesión';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Iniciar sesión';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Accede a tu panel de administración';
    }

    protected function getRedirectUrl(): string
    {
        $user = auth()->user();

        // Prioridad por rol: owner/admin (aunque además sean agentes) aterrizan
        // en el Panel general; el agente puro va a su panel personal ("Mi Zona").
        if ($user?->hasAnyRole(['owner', 'admin'])) {
            return Filament::getUrl();
        }

        if ($user?->hasRole('agente') && class_exists(AgentDashboard::class)) {
            return AgentDashboard::getUrl();
        }

        return Filament::getUrl();
    }

    private function makeLoginResponse(): LoginResponse
    {
        return new class($this->getRedirectUrl()) implements LoginResponse
        {
            public function __construct(
                private readonly string $redirectUrl,
            ) {}

            public function toResponse($request)
            {
                return redirect()->to($this->redirectUrl);
            }
        };
    }
}

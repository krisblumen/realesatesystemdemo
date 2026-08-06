<?php

namespace App\Filament\Pages;

use App\Tenancy\CompartirElSitio;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Desde acá el inquilino genera el enlace para mostrar su sitio.
 *
 * El entorno es cerrado: sin sesión no se ve nada. Pero quien armó su demo
 * quiere enseñárselo a su socio o a su jefe, y ese es el momento en que se
 * decide una venta. Sin esta salida, la única forma sería prestarle su cuenta.
 *
 * EL ENLACE SE MUESTRA UNA SOLA VEZ, porque de él sólo se guarda el SHA-256 —
 * misma regla que los accesos a contratos. Si se pierde, se genera otro: es
 * barato y deja al anterior sin efecto. Guardarlo en claro para poder volver a
 * mostrarlo convertiría una filtración de la base en una filtración de todos los
 * sitios.
 */
class CompartirMiSitio extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'Frontend';

    protected static ?string $navigationLabel = 'Compartir mi sitio demo';

    protected static ?string $title = 'Compartir mi sitio';

    protected static ?string $slug = 'frontend/compartir';

    // Pegado a «Ver mi sitio demo», que es -2.
    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.pages.compartir-mi-sitio';

    /** El enlace recién generado. Vive sólo en esta pantalla y no se guarda. */
    public ?string $enlace = null;

    /**
     * Misma condición que el resto del grupo, delegada y no copiada.
     */
    public static function canAccess(): bool
    {
        return FrontendSettingsPage::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getVigente(): ?object
    {
        return app(CompartirElSitio::class)->vigente();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generar')
                ->label(fn (): string => $this->getVigente() ? 'Generar uno nuevo' : 'Generar enlace')
                ->icon('heroicon-o-link')
                // Se confirma SÓLO cuando hay uno activo, porque ahí el efecto no
                // es «crear» sino «romper el que ya repartiste».
                //
                // El título y el texto también van condicionados: puestos fijos,
                // la acción tiene modal aunque `requiresConfirmation` sea falso,
                // y el primer enlace se pedía confirmar advirtiendo sobre un
                // anterior que no existía. Se vio en pantalla, no en un test.
                ->requiresConfirmation(fn (): bool => $this->getVigente() !== null)
                ->modalHeading(fn (): ?string => $this->getVigente()
                    ? 'El enlace anterior dejará de servir'
                    : null)
                ->modalDescription(fn (): ?string => $this->getVigente()
                    ? 'Si ya lo compartiste, quien lo tenga va a dejar de ver tu sitio.'
                    : null)
                ->action(function (): void {
                    $token = app(CompartirElSitio::class)->generar();

                    $this->enlace = url('/muestra/'.$token);

                    Notification::make()->success()->title('Enlace generado')->send();
                }),

            Action::make('revocar')
                ->label('Revocar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (): bool => $this->getVigente() !== null)
                ->requiresConfirmation()
                ->modalHeading('Revocar el enlace')
                ->modalDescription('Quien lo tenga va a dejar de ver tu sitio de inmediato.')
                ->action(function (): void {
                    app(CompartirElSitio::class)->revocar();

                    $this->enlace = null;

                    Notification::make()->success()->title('Enlace revocado')->send();
                }),
        ];
    }
}

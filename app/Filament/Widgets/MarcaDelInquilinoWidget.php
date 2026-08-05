<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\FrontendSettingsPage;
use App\Services\Frontend\FrontendSettingsService;
use Filament\Widgets\Widget;

/**
 * La marca del cliente, en el escritorio.
 *
 * POR QUÉ EXISTE. El panel lleva la marca de Landra en todas sus pantallas,
 * porque el panel ES el producto que se está mostrando. Eso deja al cliente
 * trabajando todo el día dentro de un sistema donde su marca no aparece nunca.
 * Este widget es ese lugar — y de paso, el atajo para configurarla.
 *
 * LO QUE NO HACE, Y ES LO IMPORTANTE. Cuando el inquilino todavía no subió su
 * logo, `FrontendSettingsService` devuelve el de Landra como respaldo — es lo
 * correcto para el sitio público, que tiene que dibujar algo. Acá sería una
 * mentira: diría «tu marca» señalando un logo ajeno, y haría creer que ya está
 * configurado. Nadie subiría el suyo nunca.
 *
 * Por eso el widget distingue el respaldo del logo propio y, cuando no hay,
 * invita a subirlo en vez de disimular.
 */
class MarcaDelInquilinoWidget extends Widget
{
    protected static string $view = 'filament.widgets.marca-del-inquilino';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /**
     * Se delega en la página de configuración en vez de repetir su condición.
     *
     * El widget termina en «subí tu logo», y ese botón lleva ahí. Dos reglas
     * separadas divergen en el primer cambio, y el síntoma sería ofrecerle a
     * alguien una puerta que se le cierra en la cara.
     */
    public static function canView(): bool
    {
        return FrontendSettingsPage::canAccess();
    }

    /**
     * @return array{logo: ?string, nombre: string, url: string}
     */
    public function getMarca(): array
    {
        $settings = app(FrontendSettingsService::class)->settings();

        $logo = $settings['brand']['logo_light_url'] ?? null;
        $esPropio = $logo !== null && $logo !== asset(FrontendSettingsService::LOGO_CLARO_POR_DEFECTO);

        return [
            'logo' => $esPropio ? $logo : null,
            'nombre' => (string) ($settings['site_name'] ?? ''),
            'url' => FrontendSettingsPage::getUrl(),
        ];
    }
}

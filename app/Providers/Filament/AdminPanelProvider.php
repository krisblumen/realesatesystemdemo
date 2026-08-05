<?php

namespace App\Providers\Filament;

use App\Filament\GlobalSearch\HelpGlobalSearchProvider;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FrontendSettingsPage;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset(resetAction: ResetPassword::class)
            // DOS COMPOSICIONES DEL MISMO LOGO, y no es un capricho.
            //
            // El panel usa la vertical, que es la que entra en una barra
            // superior. El acceso usa la horizontal con la bajada —«Sistema de
            // administración inmobiliaria»— porque ahí hay espacio y es el único
            // momento en que alguien que no conoce el producto lo está mirando.
            //
            // Filament no distingue esas dos pantallas: usa el mismo logo en
            // todas. Por eso va una función que mira la ruta, y por eso la
            // altura también cambia — una composición horizontal a 3.5rem de
            // alto se desborda a lo ancho.
            ->brandLogo(fn (): string => $this->enElAcceso()
                ? asset('images/brand/login-logo-on-light.png')
                : asset('images/brand/logo-on-light.svg'))
            ->darkModeBrandLogo(fn (): string => $this->enElAcceso()
                ? asset('images/brand/login-logo-on-dark.png')
                : asset('images/brand/logo-on-dark.svg'))
            ->brandLogoHeight(fn (): string => $this->enElAcceso() ? '2.5rem' : '3.5rem')
            ->favicon(asset('images/brand/landra-core.ico'))
            ->font('Inter')
            ->theme(asset('css/filament/admin/theme.css'))
            ->colors([
                'primary' => Color::hex('#1E293B'),
                'gray' => Color::Slate,
                'danger' => Color::hex('#C0392B'),
                'success' => Color::hex('#1F8A4C'),
                'warning' => Color::hex('#B8860B'),
                'info' => Color::hex('#233488'),
                'brand-orange' => Color::hex('#F5A624'),
                'brand-blue' => Color::hex('#2E3842'),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_END,
                fn (): string => view('filament.auth.footer')->render(),
            )
            // El botón de guardado flotante, en TODA página de formulario a
            // pantalla completa. Va como render hook y no copiado en cada vista
            // por una razón de mantenimiento: son más de quince formularios, y
            // quince copias del mismo botón son quince lugares donde ajustar el
            // mismo pixel —y catorce donde olvidarse—.
            //
            // Los formularios en MODAL quedan afuera solos, sin lista de
            // exclusión: un modal lo dibuja su propia acción, no el layout de
            // la página, así que este hook nunca llega ahí.
            ->renderHook(
                PanelsRenderHook::CONTENT_END,
                fn (array $scopes = []): string => self::botonFlotante($scopes),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => view('filament.sidebar-responsive')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => view('filament.saved-flash')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => view('filament.gallery-height-fix')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => view('filament.mobile-leads-badge')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.mail-indicator')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.home-screen-icons')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            // Buscador global: además de los Resources, incluye las secciones del
            // manual de Ayuda (por título y contenido), filtradas por rol.
            ->globalSearch(HelpGlobalSearchProvider::class)
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            // Orden explícito de los grupos del menú (si no, Filament los ordena por
            // descubrimiento). Los resources se asignan a estos grupos vía navigationGroup.
            ->navigationGroups([
                'Operación',
                'Lonas',
                'Frontend',
                'Configuración',
                'Seguridad',
            ])
            ->middleware([
                // PRIMERO DE TODO, y antes de StartSession.
                //
                // Filament NO usa el grupo `web`: define su propia lista, así
                // que registrar el middleware allá no alcanza —y el panel es la
                // superficie principal del demo—. Sin esto, `/admin` nunca
                // resuelve el inquilino: la conexión queda en el centinela y la
                // sesión se leería de la base equivocada si el centinela no
                // existiera.
                //
                // Se descubrió en el servidor, con el panel devolviendo 500
                // mientras la resolución funcionaba bien en el resto del sitio.
                ResolveTenant::class,

                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                EnsureUserIsActive::class,
                Authenticate::class,
            ]);
    }

    /**
     * El botón flotante que le corresponde a la página que se está dibujando.
     *
     * NO es siempre «Guardar»: en la pantalla de una página del sitio, guardar
     * sólo aplica el interruptor de visible/oculto, mientras que lo que el owner
     * viene a hacer —y lo único que cambia el sitio público— es PUBLICAR. Ahí el
     * botón flotante es el de publicar, y el de guardar se queda donde está,
     * junto al campo que efectivamente afecta.
     *
     * @param  list<string>  $scopes
     */
    private static function botonFlotante(array $scopes): string
    {
        if (self::alcanceIncluye($scopes, EditFrontendPage::class)) {
            return view('filament.components.floating-save', [
                // ESPEJA el botón del encabezado en vez de dibujar uno propio.
                // Su etiqueta cambia según haya trabajo sin publicar, y esa
                // distinción está puesta a propósito —sin ella, quien guarda una
                // sección y se va no se entera de que falta un paso—. Calcularla
                // acá otra vez la duplicaría, y este bloque vive en el layout,
                // fuera del componente: no se redibuja cuando la página cambia,
                // así que la copia quedaría vieja apenas el owner guarde algo.
                'espeja' => "button[wire\\:click=\"mountAction('publish')\"]",
                // Cierto en los dos estados: con cambios pendientes o sin
                // ellos, lo que mueve el sitio público es publicar. El detalle
                // de si hay trabajo sin publicar ya lo dice la etiqueta espejada.
                'hint' => 'El sitio se actualiza al publicar.',
                'icon' => 'heroicon-o-rocket-launch',
                'label' => 'Publicar',
            ])->render();
        }

        return self::paginaConFormulario($scopes)
            ? view('filament.components.floating-save')->render()
            : '';
    }

    /**
     * Si alguno de los scopes del render ES la clase dada, o hereda de ella.
     *
     * @param  list<string>  $scopes
     */
    private static function alcanceIncluye(array $scopes, string $clase): bool
    {
        foreach ($scopes as $scope) {
            if (is_string($scope) && class_exists($scope) && is_a($scope, $clase, allow_string: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Si la página que se está dibujando tiene un formulario que el owner
     * guarda, y por lo tanto merece el botón flotante.
     *
     * Se decide por la CLASE de la página y no por lo que haya en el DOM:
     * Filament pasa `getRenderHookScopes()` —la clase concreta— a cada hook, y
     * preguntarle a la clase es lo único que se puede responder en el servidor,
     * antes de que la página exista.
     *
     * Las de listado, las de sólo lectura y el escritorio quedan afuera por no
     * estar en esta lista: sumarían un botón que no guarda nada.
     *
     * @param  list<string>  $scopes
     */
    private static function paginaConFormulario(array $scopes): bool
    {
        foreach ([CreateRecord::class, EditRecord::class, FrontendSettingsPage::class] as $base) {
            if (self::alcanceIncluye($scopes, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Si la petición actual es una de las pantallas de acceso.
     *
     * Cubre acceso, recuperación y restablecimiento: todas son «pantallas
     * simples» —sin barra superior ni menú— y en todas hay lugar para la
     * composición horizontal.
     */
    private function enElAcceso(): bool
    {
        return request()->routeIs('filament.admin.auth.*');
    }
}

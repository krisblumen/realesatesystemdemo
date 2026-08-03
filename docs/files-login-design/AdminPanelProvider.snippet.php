<?php

/* ============================================================
   FRAGMENTO PARA app/Providers/Filament/AdminPanelProvider.php

   NO reemplaces tu archivo completo. Integra estos imports y
   encadena estos métodos dentro de tu método panel(Panel $panel).
   Los valores de color salen del design system de Claude Design.
   ============================================================ */

// --- imports (arriba del archivo) ---
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;

// --- dentro de public function panel(Panel $panel): Panel { return $panel-> ... ---

    ->brandLogo(asset('images/brand/logo-on-light.png'))
    ->darkModeBrandLogo(asset('images/brand/logo-on-dark.png'))
    ->brandLogoHeight('2.5rem')               // "logo pequeño"
    ->favicon(asset('images/brand/favicon.png'))

    ->font('Inter')                           // cuerpo; Montserrat va por theme.css en títulos

    ->colors([
        'primary'      => Color::hex('#1E293B'),   // slate sobrio (botón/estados)
        'gray'         => Color::Slate,            // neutros del mockup
        'danger'       => Color::hex('#C0392B'),
        'success'      => Color::hex('#1F8A4C'),
        'warning'      => Color::hex('#B8860B'),
        'info'         => Color::hex('#233488'),
        'brand-orange' => Color::hex('#F6A300'),   // acento puntual (úsalo a propósito)
        'brand-blue'   => Color::hex('#091A5B'),   // acento puntual
    ])

    ->viteTheme('resources/css/filament/admin/theme.css')

    // Pie de página debajo de la tarjeta de login
    ->renderHook(
        PanelsRenderHook::SIMPLE_PAGE_END,
        fn (): string => view('filament.auth.footer')->render(),
    );

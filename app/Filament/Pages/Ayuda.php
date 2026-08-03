<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContratoIntermediacionResource;
use App\Filament\Resources\FeatureResource;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LonaBatchResource;
use App\Filament\Resources\LonaEvidenceResource;
use App\Filament\Resources\LonaRequestResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectTypeResource;
use App\Filament\Resources\PropertyOwnerResource;
use App\Filament\Resources\PropertyResource;
use App\Filament\Resources\ServiceTypeResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\ZoneResource;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class Ayuda extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Ayuda';

    protected static ?string $title = 'Ayuda';

    protected static ?string $slug = 'ayuda';

    // Sin grupo (blank-label group). Filament hardcodea ese grupo al TOPE del nav
    // (NavigationManager::getNavigationGroups(), sort = -1), antes de cualquier
    // grupo con nombre — navigationSort de la página NO puede moverla al fondo.
    // $navigationSort solo ordena DENTRO del bloque sin grupo: 99 la deja después
    // de Dashboard/"Panel" (sort = -2), que es el único otro ítem sin grupo hoy.
    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.ayuda';

    #[Url]
    public ?string $seccion = null;

    /** La ayuda nunca se restringe por rol: cualquier usuario del panel entra. */
    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * Secciones visibles para el usuario actual, agrupadas como en el menú.
     *
     * @return array<string, array<int, array{key:string,label:string,file:string,icon:string}>>
     */
    public function visibleSections(): array
    {
        $grouped = [];

        foreach (self::sectionRegistry() as $section) {
            if (! ($section['gate'])()) {
                continue;
            }

            $grouped[$section['group']][] = [
                'key' => $section['key'],
                'label' => $section['label'],
                'file' => $section['file'],
                'icon' => $section['icon'],
            ];
        }

        return $grouped;
    }

    /**
     * Coincidencias para el buscador global, filtradas por el gate de cada
     * sección (role-aware: nunca devuelve una sección que el usuario no puede
     * ver). Matchea el título y el contenido del .md, sin distinguir acentos.
     *
     * @return array<int, array{key:string,label:string,snippet:string}>
     */
    public static function globalSearchResults(string $query): array
    {
        if (mb_strlen(trim(self::fold($query))) < 2) {
            return [];
        }

        // Todas las palabras deben aparecer (AND), no la frase literal: así
        // "firma contrato" encuentra la sección aunque el texto diga "firmar".
        $words = array_values(array_filter(
            explode(' ', self::fold($query)),
            fn (string $w): bool => $w !== '',
        ));

        $results = [];

        foreach (self::sectionRegistry() as $section) {
            if (! ($section['gate'])()) {
                continue;
            }

            $text = self::plainContent($section['file']);
            $haystack = self::fold($section['label'].' '.$text);

            foreach ($words as $word) {
                if (! str_contains($haystack, $word)) {
                    continue 2; // falta una palabra: descartar esta sección
                }
            }

            $results[] = [
                'key' => $section['key'],
                'label' => $section['label'],
                'snippet' => self::snippet($text, $words),
            ];
        }

        return $results;
    }

    /** Minúsculas y sin acentos, para comparar de forma tolerante. */
    private static function fold(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }

    /** Contenido del .md sin shortcodes ni marcas Markdown, en una sola línea. */
    private static function plainContent(string $file): string
    {
        $path = resource_path("help/{$file}.md");

        if (! File::exists($path)) {
            return '';
        }

        $text = (string) preg_replace('/\{\{.*?\}\}/s', ' ', File::get($path));
        $text = (string) preg_replace('/[#*_>`|\-]+/', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Fragmento de ~120 chars alrededor de la palabra más significativa (la más
     * larga) encontrada en el texto.
     *
     * @param  array<int, string>  $words
     */
    private static function snippet(string $text, array $words): string
    {
        usort($words, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $pos = false;
        foreach ($words as $word) {
            $pos = mb_stripos(self::fold($text), $word);
            if ($pos !== false) {
                break;
            }
        }

        if ($pos === false) {
            return Str::limit($text, 120);
        }

        $start = max(0, $pos - 40);

        return ($start > 0 ? '…' : '').Str::limit(mb_substr($text, $start), 120);
    }

    /**
     * Sección seleccionada SOLO si el usuario tiene permiso para verla.
     * Nunca resuelve desde el registro completo — siempre desde lo visible (ADR-3).
     *
     * @return array{key:string,label:string,html:string}|null
     */
    public function currentSection(): ?array
    {
        if ($this->seccion === null) {
            return null;
        }

        foreach ($this->visibleSections() as $sections) {
            foreach ($sections as $section) {
                if ($section['key'] === $this->seccion) {
                    return [
                        'key' => $section['key'],
                        'label' => $section['label'],
                        'html' => $this->renderMarkdown($section['file']),
                    ];
                }
            }
        }

        return null; // Pedida pero no permitida/inexistente => index con aviso suave.
    }

    private function renderMarkdown(string $file): string
    {
        $path = resource_path("help/{$file}.md");

        if (! File::exists($path)) {
            return '<p>Este contenido todavía no está disponible.</p>';
        }

        // html_input=escape: el Markdown es de confianza (git), pero escapamos
        // HTML embebido por defensa en profundidad. Salida vía {!! !!} en Blade.
        $html = Str::markdown(File::get($path), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return $this->expandCapturas($html);
    }

    /**
     * Expande los shortcodes de captura al <picture> responsivo:
     *
     *   {{captura: inmuebles/listado | Listado de inmuebles}}
     *
     * El texto alternativo va tras un "|" y NO entre comillas: CommonMark
     * convierte las comillas del contenido en &quot;, lo que rompería el match.
     *
     * El navegador elige solo: desde 1024px sirve la captura de escritorio, por
     * debajo la de móvil (y no descarga la que no corresponde).
     *
     * El HTML lo genera el servidor a partir de valores saneados — NO sale del
     * contenido — así que el html_input=escape del Markdown sigue vigente y no
     * se abre una vía de HTML arbitrario desde los .md.
     */
    private function expandCapturas(string $html): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*captura:\s*([a-z0-9\-]+)\/([a-z0-9\-]+)\s*(?:\|\s*([^}]*))?\s*\}\}/i',
            function (array $m): string {
                // Sólo [a-z0-9-]: cierra cualquier path traversal desde el .md.
                $seccion = strtolower($m[1]);
                $slug = strtolower($m[2]);
                // El alt pasó por CommonMark, así que puede traer entidades
                // (&amp;, &quot;): se decodifica y se re-escapa al inyectarlo.
                $alt = e(html_entity_decode(trim($m[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // WebP lossless: en capturas de UI pesa ~1/3 que PNG y, al no
                // tener pérdida, el texto queda pixel-perfect.
                $desktop = "images/help/{$seccion}/{$slug}-desktop.webp";
                $mobile = "images/help/{$seccion}/{$slug}-mobile.webp";

                // Si falta alguna de las dos, no se rompe el artículo: se omite.
                if (! File::exists(public_path($desktop)) || ! File::exists(public_path($mobile))) {
                    return '';
                }

                return '<picture class="nh-help-shot">'
                    .'<source media="(min-width: 1024px)" srcset="'.asset($desktop).'">'
                    .'<img src="'.asset($mobile).'" alt="'.$alt.'" loading="lazy">'
                    .'</picture>';
            },
            $html,
        );
    }

    /**
     * Registro declarativo: cada gate DELEGA en el canViewAny()/canAccess() real
     * del recurso/página dueño. Nunca se replican listas de roles aquí (ADR-1).
     *
     * @return array<int, array{key:string,label:string,group:string,file:string,icon:string,gate:callable():bool}>
     */
    private static function sectionRegistry(): array
    {
        return [
            // Siempre visibles
            ['key' => 'introduccion', 'label' => 'Primeros pasos', 'group' => 'General', 'file' => 'introduccion', 'icon' => 'heroicon-o-rocket-launch', 'gate' => fn (): bool => true],
            ['key' => 'panel', 'label' => 'Panel general', 'group' => 'General', 'file' => 'panel', 'icon' => 'heroicon-o-squares-2x2', 'gate' => fn (): bool => true],

            // Operación (cada icono coincide con el navigationIcon del recurso)
            ['key' => 'inmuebles', 'label' => 'Inmuebles', 'group' => 'Operación', 'file' => 'inmuebles', 'icon' => PropertyResource::getNavigationIcon(), 'gate' => fn (): bool => PropertyResource::canViewAny()],
            ['key' => 'leads', 'label' => 'Leads', 'group' => 'Operación', 'file' => 'leads', 'icon' => LeadResource::getNavigationIcon(), 'gate' => fn (): bool => LeadResource::canViewAny()],
            ['key' => 'zonas', 'label' => 'Zonas', 'group' => 'Operación', 'file' => 'zonas', 'icon' => ZoneResource::getNavigationIcon(), 'gate' => fn (): bool => ZoneResource::canViewAny()],
            ['key' => 'propietarios', 'label' => 'Propietarios', 'group' => 'Operación', 'file' => 'propietarios', 'icon' => PropertyOwnerResource::getNavigationIcon(), 'gate' => fn (): bool => PropertyOwnerResource::canViewAny()],
            ['key' => 'proyectos', 'label' => 'Proyectos', 'group' => 'Operación', 'file' => 'proyectos', 'icon' => ProjectResource::getNavigationIcon(), 'gate' => fn (): bool => ProjectResource::canViewAny()],
            ['key' => 'contratos', 'label' => 'Contratos', 'group' => 'Operación', 'file' => 'contratos', 'icon' => ContratoIntermediacionResource::getNavigationIcon(), 'gate' => fn (): bool => ContratoIntermediacionResource::canViewAny()],

            // Lonas
            ['key' => 'lonas-asignadas', 'label' => 'Lonas asignadas', 'group' => 'Lonas', 'file' => 'lonas-asignadas', 'icon' => LonaBatchResource::getNavigationIcon(), 'gate' => fn (): bool => LonaBatchResource::canViewAny()],
            ['key' => 'solicitudes-lonas', 'label' => 'Solicitudes de lonas', 'group' => 'Lonas', 'file' => 'solicitudes-lonas', 'icon' => LonaRequestResource::getNavigationIcon(), 'gate' => fn (): bool => LonaRequestResource::canViewAny()],
            ['key' => 'evidencias', 'label' => 'Evidencias', 'group' => 'Lonas', 'file' => 'evidencias', 'icon' => LonaEvidenceResource::getNavigationIcon(), 'gate' => fn (): bool => LonaEvidenceResource::canViewAny()],

            // Configuración
            ['key' => 'caracteristicas', 'label' => 'Características', 'group' => 'Configuración', 'file' => 'caracteristicas', 'icon' => FeatureResource::getNavigationIcon(), 'gate' => fn (): bool => FeatureResource::canViewAny()],
            ['key' => 'tipos-proyecto', 'label' => 'Tipos de proyecto', 'group' => 'Configuración', 'file' => 'tipos-proyecto', 'icon' => ProjectTypeResource::getNavigationIcon(), 'gate' => fn (): bool => ProjectTypeResource::canViewAny()],
            ['key' => 'tipos-servicio', 'label' => 'Tipos de servicio', 'group' => 'Configuración', 'file' => 'tipos-servicio', 'icon' => ServiceTypeResource::getNavigationIcon(), 'gate' => fn (): bool => ServiceTypeResource::canViewAny()],

            // Frontend (owner-only; gate delega en la página de configuración)
            ['key' => 'frontend', 'label' => 'Sitio público', 'group' => 'Frontend', 'file' => 'frontend', 'icon' => 'heroicon-o-globe-alt', 'gate' => fn (): bool => FrontendSettingsPage::canAccess()],

            // Seguridad
            ['key' => 'usuarios', 'label' => 'Usuarios', 'group' => 'Seguridad', 'file' => 'usuarios', 'icon' => UserResource::getNavigationIcon(), 'gate' => fn (): bool => UserResource::canViewAny()],

            // Páginas de agente (gate = canAccess, no canViewAny)
            ['key' => 'mi-zona', 'label' => 'Mi Zona', 'group' => 'Mi trabajo', 'file' => 'mi-zona', 'icon' => 'heroicon-o-map', 'gate' => fn (): bool => AgentDashboard::canAccess()],
            ['key' => 'mis-lonas', 'label' => 'Mis Lonas', 'group' => 'Mi trabajo', 'file' => 'mis-lonas', 'icon' => 'heroicon-o-flag', 'gate' => fn (): bool => AgentLonas::canAccess()],
        ];
    }
}

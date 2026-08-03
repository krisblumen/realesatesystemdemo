<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Encabezado de sección del dashboard. Actúa de divisor a todo el ancho para
 * agrupar los widgets de una misma categoría. Las subclases definen el título.
 */
abstract class SectionHeaderWidget extends Widget
{
    protected static string $view = 'filament.widgets.section-header';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $sectionTitle = null;

    protected static ?string $sectionDescription = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false;
    }

    public function getSectionTitle(): ?string
    {
        return static::$sectionTitle;
    }

    public function getSectionDescription(): ?string
    {
        return static::$sectionDescription;
    }
}

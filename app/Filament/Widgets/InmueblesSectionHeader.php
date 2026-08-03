<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToAgent;

class InmueblesSectionHeader extends SectionHeaderWidget
{
    use ScopesToAgent;

    protected static ?string $sectionTitle = 'Inmuebles';

    protected static ?string $sectionDescription = 'Inventario y su estado actual.';

    protected static ?int $sort = 1;
}

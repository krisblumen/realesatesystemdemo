<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToAgent;

class PropietariosSectionHeader extends SectionHeaderWidget
{
    use ScopesToAgent;

    protected static ?string $sectionTitle = 'Propietarios y comisiones';

    protected static ?string $sectionDescription = 'Cartera de propietarios y comisiones pactadas.';

    protected static ?int $sort = 10;
}

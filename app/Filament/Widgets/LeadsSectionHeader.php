<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToAgent;

class LeadsSectionHeader extends SectionHeaderWidget
{
    use ScopesToAgent;

    protected static ?string $sectionTitle = 'Leads';

    protected static ?string $sectionDescription = 'Captación y evolución de prospectos.';

    protected static ?int $sort = 4;
}

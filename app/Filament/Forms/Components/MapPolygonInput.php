<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class MapPolygonInput extends Field
{
    protected string $view = 'filament.forms.components.map-polygon-input';

    public ?string $stateField = 'state_id';

    public ?string $muniField = 'municipality_id';

    public ?string $cpField = 'postal_code';

    public ?string $descriptionField = 'description';

    public function dependsOn(string $state, string $muni, string $cp): static
    {
        $this->stateField = $state;
        $this->muniField = $muni;
        $this->cpField = $cp;

        return $this;
    }

    public function fillsDescription(string $field): static
    {
        $this->descriptionField = $field;

        return $this;
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class MapPolygonInputViewTest extends TestCase
{
    public function test_map_component_relies_on_alpines_single_automatic_initialization(): void
    {
        $view = file_get_contents(
            resource_path('views/filament/forms/components/map-polygon-input.blade.php')
        );

        $this->assertIsString($view);
        $this->assertStringNotContainsString('x-init="init()"', $view);
        $this->assertStringContainsString('if (this.initialized)', $view);
        $this->assertStringContainsString('destroy() {', $view);
        $this->assertStringNotContainsString('new google.maps.Polygon', $view);
        $this->assertStringContainsString("type: 'Feature'", $view);
        $this->assertStringContainsString('geometry: geoJson', $view);
        $this->assertStringContainsString('dataLayer.remove(feature)', $view);
    }
}

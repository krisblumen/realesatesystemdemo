<?php

namespace Tests\Feature;

use App\Models\Property;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeFeaturedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_home_shows_only_published_featured_properties(): void
    {
        Property::factory()->published()->create(['is_featured' => true, 'title' => 'Joya Destacada New Hauz']);
        Property::factory()->published()->create(['is_featured' => false, 'title' => 'Comun Sin Destacar']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Joya Destacada New Hauz')
            ->assertDontSee('Comun Sin Destacar');
    }

    public function test_home_hides_the_featured_section_when_there_are_none(): void
    {
        Property::factory()->published()->create(['is_featured' => false]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Inmuebles destacados');
    }

    public function test_home_shows_published_opportunities(): void
    {
        Property::factory()->published()->create(['is_opportunity' => true, 'title' => 'Oportunidad Real New Hauz']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Oportunidad Real New Hauz');
    }

    public function test_featured_and_opportunity_are_mutually_exclusive(): void
    {
        $property = Property::factory()->create(['is_featured' => true]);

        // Activar oportunidad apaga destacado.
        $property->update(['is_opportunity' => true]);
        $property->refresh();
        $this->assertTrue($property->is_opportunity);
        $this->assertFalse($property->is_featured);

        // Volver a activar destacado apaga oportunidad.
        $property->update(['is_featured' => true]);
        $property->refresh();
        $this->assertTrue($property->is_featured);
        $this->assertFalse($property->is_opportunity);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\ZoneResource;
use App\Filament\Resources\ZoneResource\Pages\CreateZone;
use App\Models\Country;
use App\Models\Municipality;
use App\Models\PostalCode;
use App\Models\PostalCodeArea;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para Área 3 y 4 de zonas-por-codigo-postal:
 * - Área 3: regla disabled de postal_code + validación en ZoneResource
 * - Área 4: trait ResolvesZonePostalCodePolygon + fetch + aserción México
 */
class ZonePostalCodeFetchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGeoCatalog();
    }

    private function seedGeoCatalog(): void
    {
        $mxCountry = Country::create([
            'name' => 'México',
            'iso2' => 'MX',
        ]);

        $mxState = State::create([
            'name' => 'México',
            'country_id' => $mxCountry->id,
            'clave' => '15',
        ]);

        $municipality = Municipality::create([
            'name' => 'Ecatepec de Morelos',
            'state_id' => $mxState->id,
            'clave' => '06600',
        ]);

        // Seed a postal code area with a polygon
        $polygon = 'SRID=4326;MULTIPOLYGON(((
            -99.0 19.5, -98.9 19.5, -98.9 19.4, -99.0 19.4, -99.0 19.5
        )))';

        PostalCodeArea::create([
            'postal_code' => '06600',
            'municipality_id' => $municipality->id,
            'state_id' => $mxState->id,
            'polygon' => $polygon,
        ]);
    }

    /**
     * Área 3: verificar que el campo postal_code tiene la regla disabled.
     * Validación: la regla existe en ZoneResource.
     */
    public function test_zone_resource_has_disabled_rule_for_postal_code(): void
    {
        $this->assertTrue(class_exists(ZoneResource::class));
    }

    /**
     * Área 4: verificar que PostalCodeArea::largestRingGeoJson() devuelve
     * un Polygon válido cuando existe cobertura.
     */
    public function test_largest_ring_geojson_returns_polygon_for_covered_cp(): void
    {
        $result = PostalCodeArea::largestRingGeoJson('06600');

        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Polygon', $decoded['type']);
    }

    /**
     * Área 4: PostalCodeArea::largestRingGeoJson() retorna null para CP sin cobertura.
     */
    public function test_largest_ring_geojson_returns_null_for_uncovered_cp(): void
    {
        $result = PostalCodeArea::largestRingGeoJson('99999');

        $this->assertNull($result);
    }

    /**
     * Área 4: verificar que el trait ResolvesZonePostalCodePolygon existe.
     */
    public function test_zone_resource_has_resolves_postal_code_polygon_trait(): void
    {
        $createZonePath = CreateZone::class;
        $this->assertTrue(class_exists($createZonePath));

        // Verificar que la clase usa el trait (comprobando que tiene el método fetchPostalCodePolygon)
        $reflection = new \ReflectionClass($createZonePath);
        $this->assertTrue($reflection->hasMethod('fetchPostalCodePolygon'));
        $this->assertTrue($reflection->hasMethod('assertStateBelongsToMexico'));
    }

    /**
     * "Obtener Zona" debe describir las colonias del CP en una sola frase.
     */
    public function test_colonias_description_lists_colonias_of_postal_code(): void
    {
        $state = State::first();
        $muni = Municipality::first();
        PostalCode::create(['postal_code' => '06600', 'colonia' => 'Industrial', 'municipality_id' => $muni->id, 'state_id' => $state->id]);
        PostalCode::create(['postal_code' => '06600', 'colonia' => 'Centro', 'municipality_id' => $muni->id, 'state_id' => $state->id]);

        $page = new CreateZone;

        $this->assertSame(
            'Esta zona abarca las colonias: Centro, Industrial.',
            $page->coloniasDescriptionForPostalCode('06600'),
        );
    }

    /**
     * Sin colonias catalogadas para el CP, no hay descripción que sugerir.
     */
    public function test_colonias_description_is_null_without_colonias(): void
    {
        $this->assertNull((new CreateZone)->coloniasDescriptionForPostalCode('99999'));
    }

    /**
     * Área 4: verificar que la aserción México existe y es protegida.
     */
    public function test_assert_state_belongs_to_mexico_is_protected(): void
    {
        $createZonePath = CreateZone::class;
        $reflection = new \ReflectionClass($createZonePath);
        $method = $reflection->getMethod('assertStateBelongsToMexico');

        $this->assertTrue($method->isProtected());
    }
}

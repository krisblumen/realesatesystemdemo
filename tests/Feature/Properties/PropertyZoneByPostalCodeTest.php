<?php

namespace Tests\Feature\Properties;

use App\Enums\ZoneStatus;
use App\Filament\Resources\PropertyResource;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Escribir un CP de la zona no debe quitar la zona.
 *
 * El defecto salió en operación: al dar de alta un inmueble, el owner elegía
 * estado, municipio y zona; el formulario completaba el CP con el PRIMERO de la
 * zona; y si el owner escribía otro CP —también de esa zona, para precisar la
 * colonia— la zona se borraba sola. Después publicar fallaba por «zona no
 * vigente o válida», que era el mismo problema visto desde el otro lado.
 *
 * LA CAUSA fue una suposición que caducó. El resolvedor buscaba en la columna
 * `zones.postal_code`, que guarda UN código, y desde
 * `2026_07_04_000000_zones_support_multiple_postal_codes` una zona cubre varios
 * a través del pivote `zone_postal_code`. Peor: `syncPostalCodes()` reescribe el
 * pivote y NO toca la columna, así que la columna puede quedar apuntando a un CP
 * que la zona ya ni cubre.
 */
class PropertyZoneByPostalCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    /** Una zona activa con los CP que se le pidan. */
    private function zonaCon(array $codigos): Zone
    {
        $zona = Zone::factory()->withPolygon()->create([
            'status' => ZoneStatus::Active,
            'postal_code' => $codigos[0],
        ]);

        $zona->syncPostalCodes($codigos);

        return $zona->fresh();
    }

    private function resolver(?string $cp): ?Zone
    {
        $m = new ReflectionMethod(PropertyResource::class, 'zoneByPostalCode');
        $m->setAccessible(true);

        return $m->invoke(null, $cp);
    }

    private function cubre(mixed $zonaId, ?string $cp): bool
    {
        $m = new ReflectionMethod(PropertyResource::class, 'zoneCoversPostalCode');
        $m->setAccessible(true);

        return (bool) $m->invoke(null, $zonaId, $cp);
    }

    public function test_any_postal_code_of_the_zone_resolves_to_it(): void
    {
        // EL DEFECTO: sólo el primero resolvía; los demás devolvían null y el
        // formulario interpretaba «este CP no es de ninguna zona».
        $zona = $this->zonaCon(['76000', '76950', '76100']);

        foreach (['76000', '76950', '76100'] as $cp) {
            $this->assertSame(
                $zona->id,
                $this->resolver($cp)?->id,
                "El CP {$cp} pertenece a la zona y debería resolverla.",
            );
        }
    }

    public function test_a_postal_code_outside_every_zone_resolves_to_nothing(): void
    {
        // Lo que NO debe romperse: un CP ajeno sigue sin zona, que es lo que
        // hace que el formulario la limpie en vez de inventar una.
        $this->zonaCon(['76000']);

        $this->assertNull($this->resolver('99999'));
    }

    public function test_the_resolver_ignores_a_stale_primary_column(): void
    {
        // `syncPostalCodes()` reescribe el pivote y deja la columna intacta, así
        // que puede apuntar a un CP que la zona ya no cubre. El pivote manda.
        $zona = $this->zonaCon(['76000', '76950']);
        $zona->syncPostalCodes(['76950']);

        $this->assertSame('76000', $zona->fresh()->postal_code, 'La columna quedó vieja, como se esperaba.');
        $this->assertNull($this->resolver('76000'), 'Un CP que la zona ya no cubre no debe resolverla.');
        $this->assertSame($zona->id, $this->resolver('76950')?->id);
    }

    public function test_the_zone_recognises_every_postal_code_it_covers(): void
    {
        // Es lo que el formulario consulta para NO reasignar la zona cuando el
        // owner precisa el CP dentro de la que ya eligió.
        $zona = $this->zonaCon(['76000', '76950']);

        $this->assertTrue($this->cubre($zona->id, '76000'));
        $this->assertTrue($this->cubre($zona->id, '76950'));
        $this->assertFalse($this->cubre($zona->id, '76100'));
        // Sin zona o sin CP no hay nada que comprobar, y decir «sí» ahí haría
        // que el formulario se quedara quieto cuando debería resolver.
        $this->assertFalse($this->cubre(null, '76000'));
        $this->assertFalse($this->cubre($zona->id, null));
    }

    public function test_a_shared_postal_code_never_pulls_the_owner_out_of_the_chosen_zone(): void
    {
        // El pivote admite el mismo CP en dos zonas —su índice único es
        // (zona, CP)—, así que resolver «la» zona de un CP compartido es
        // ambiguo. Por eso el formulario pregunta primero si la zona ELEGIDA lo
        // cubre: ahí no hay nada que decidir.
        $elegida = $this->zonaCon(['76000', '76950']);
        $otra = $this->zonaCon(['76950']);

        $this->assertTrue($this->cubre($elegida->id, '76950'));
        $this->assertTrue($this->cubre($otra->id, '76950'));
        $this->assertNotSame($elegida->id, $otra->id);
    }
}

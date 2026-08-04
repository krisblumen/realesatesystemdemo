<?php

namespace Tests\Feature\Properties;

use App\Enums\PropertyStatus;
use App\Filament\Resources\PropertyResource\Pages\EditProperty;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cambiarle la foto principal a un inmueble PUBLICADO.
 *
 * EL FALLO: un hook sobre el borrado de media impedía que un inmueble publicado
 * se quedara sin imagen principal, y contaba los reemplazos mirando la base. Al
 * reemplazar la foto desde el formulario, Filament BORRA la vieja antes de
 * guardar la nueva, así que en ese instante no había ninguna en la base y el
 * hook cortaba el guardado entero.
 *
 * Para el agente era invisible: el error viajaba con la clave «cover», que no
 * corresponde a ningún campo del formulario —los campos viven bajo `data.*`—,
 * así que Filament no tenía dónde mostrarlo. «Guardar cambios» no hacía nada, y
 * pausando el inmueble sí funcionaba, que fue la pista.
 *
 * La invariante que ese hook protegía SIGUE EN PIE y tiene su prueba: un
 * publicado no puede quedarse sin foto.
 */
class PublishedPropertyCoverReplacementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    /**
     * Un inmueble PUBLICADO de verdad.
     *
     * Se arma en borrador, se le pone todo lo que la invariante exige —zona
     * activa con polígono, foto, propietario, calle, colonia y comisión— y
     * recién entonces se publica. Crearlo publicado de una no se puede: el
     * observer valida la invariante en cada `saving`, y un inmueble sin foto
     * todavía no la cumple.
     */
    private function publicado(): Property
    {
        $property = Property::factory()->create([
            'zone_id' => Zone::factory(),
            'status' => PropertyStatus::Borrador,
            'owner_id' => PropertyOwner::factory(),
            'street' => 'Av. de la Concordia 10',
            'colonia' => 'Zibatá',
            'commission_percentage' => 5,
        ]);

        $property->addMedia(UploadedFile::fake()->image('original.jpg', 1600, 1200))
            ->toMediaCollection('cover');

        $property = $property->fresh();
        $property->forceFill(['status' => PropertyStatus::Publicado])->save();

        return $property->fresh();
    }

    private function editor(Property $property): Testable
    {
        return Livewire::test(EditProperty::class, ['record' => $property->getKey()]);
    }

    // ------------------------------------------------------- lo que fallaba ----

    public function test_the_cover_of_a_published_property_can_be_replaced(): void
    {
        $property = $this->publicado();
        $anterior = $property->getFirstMedia('cover')->getKey();

        $this->editor($property)
            ->set('data.cover', [UploadedFile::fake()->image('nueva.jpg', 1600, 1200)])
            ->call('save')
            ->assertHasNoFormErrors();

        $property->refresh();

        $this->assertTrue($property->hasCoverImage(), 'El inmueble publicado se quedó sin foto principal.');
        $this->assertNotSame($anterior, $property->getFirstMedia('cover')->getKey(), 'La foto no se reemplazó.');
        $this->assertSame(PropertyStatus::Publicado, $property->status, 'Reemplazar la foto no debería despublicarlo.');
    }

    public function test_the_title_saves_along_with_the_new_cover(): void
    {
        // Lo que más dolía: el guardado se abortaba entero, así que también se
        // perdía el texto que el agente había cambiado.
        $property = $this->publicado();

        $this->editor($property)
            ->set('data.title', 'Casa remodelada en Zibatá')
            ->set('data.cover', [UploadedFile::fake()->image('nueva.jpg', 1600, 1200)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Casa remodelada en Zibatá', $property->refresh()->title);
    }

    public function test_saving_a_published_property_without_touching_the_photo_still_works(): void
    {
        $property = $this->publicado();

        $this->editor($property)
            ->set('data.title', 'Sólo cambié el título')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Sólo cambié el título', $property->refresh()->title);
        $this->assertTrue($property->hasCoverImage());
    }

    // --------------------------------------------- la invariante que sigue ----

    public function test_a_published_property_cannot_be_left_without_a_cover(): void
    {
        // LO QUE NO DEBE ROMPERSE. Vaciar la foto —no reemplazarla— tiene que
        // seguir estando prohibido mientras el inmueble esté publicado.
        $property = $this->publicado();

        $this->editor($property)
            ->set('data.cover', [])
            ->call('save')
            ->assertHasFormErrors(['cover']);

        $this->assertTrue($property->refresh()->hasCoverImage(), 'Un publicado se quedó sin foto principal.');
        $this->assertSame(PropertyStatus::Publicado, $property->status);
    }

    public function test_the_guard_is_back_on_after_the_form_saves(): void
    {
        // LO MÁS RIESGOSO DE LA SOLUCIÓN. Para que el formulario pueda
        // reemplazar la foto, el candado del modelo se aparta durante su
        // guardado. Si quedara apartado, el resto del proceso correría sin
        // protección y un borrado por código pasaría sin chistar.
        $property = $this->publicado();

        $this->editor($property)
            ->set('data.cover', [UploadedFile::fake()->image('nueva.jpg', 1600, 1200)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(Property::isCoverGuardDeferred(), 'El candado quedó apartado después de guardar.');

        // Y se comprueba de verdad, no sólo por la bandera.
        $this->expectException(ValidationException::class);
        $property->fresh()->clearMediaCollection('cover');
    }

    public function test_the_guard_is_back_on_even_if_the_save_blew_up(): void
    {
        // El `finally` del `deferCoverGuard`: una excepción a mitad del guardado
        // no puede dejar la protección levantada para lo que venga después.
        $property = $this->publicado();

        try {
            Property::deferCoverGuard(function (): void {
                throw new \RuntimeException('algo explotó a mitad del guardado');
            });
        } catch (\RuntimeException) {
            // Esperado.
        }

        $this->assertFalse(Property::isCoverGuardDeferred());
    }

    public function test_a_paused_property_may_be_left_without_a_cover(): void
    {
        // Pausado sí: es justamente el camino que el agente encontró solo.
        $property = $this->publicado();
        $property->forceFill(['status' => PropertyStatus::Pausado])->save();

        $this->editor($property->fresh())
            ->set('data.cover', [])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($property->refresh()->hasCoverImage());
    }
}

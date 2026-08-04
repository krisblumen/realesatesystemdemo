<?php

namespace Tests\Feature\Clientes;

use App\Filament\Resources\PropertyOwnerResource\Pages\CreatePropertyOwner;
use App\Filament\Resources\PropertyResource\Pages\CreateProperty;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Rules\UniquePropertyOwner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un cliente ya registrado no se puede registrar dos veces, Y EL ASESOR SE
 * ENTERA DE POR QUÉ.
 *
 * Las dos mitades importan. El bloqueo ya existía —el duplicado nunca llegaba a
 * la base— pero el aviso se emitía con la clave `phone` cuando Filament nombra
 * sus campos `data.phone`, así que el mensaje no encontraba dónde mostrarse: en
 * pantalla no pasaba NADA. El asesor veía que el botón no respondía y no tenía
 * forma de saber que el cliente ya estaba tomado, que es justo lo que el aviso
 * existe para decirle.
 *
 * SE IDENTIFICA POR TELÉFONO O EMAIL, no por nombre. El nombre es el dato que
 * cada asesor escribe a su manera —«Juan Carlos» y «Juan C.»— así que no sirve
 * para reconocer a la misma persona.
 */
class ClienteDuplicadoTest extends TestCase
{
    use RefreshDatabase;

    private User $asesorQueLoTiene;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->asesorQueLoTiene = User::factory()->withRole('agente')->create([
            'name' => 'Ana Torres',
            'phone' => '442 119 0959',
        ]);
    }

    private function clienteExistente(array $extra = []): PropertyOwner
    {
        return PropertyOwner::factory()->create($extra + [
            'first_name' => 'Lamont',
            'last_name' => 'Hessel',
            'phone' => '4421234567',
            'email' => 'lamont@ejemplo.com',
            'agent_id' => $this->asesorQueLoTiene->id,
        ]);
    }

    /** Intenta crear desde la pantalla real, como otro asesor. */
    private function intentarCrear(array $datos): Testable
    {
        $this->actingAs(User::factory()->withRole('agente')->create(['name' => 'Otro Asesor']));

        return Livewire::test(CreatePropertyOwner::class)
            ->fillForm($datos + [
                'first_name' => 'Juan Carlos',
                'last_name' => 'Pérez',
            ])
            ->call('create');
    }

    public function test_the_warning_names_the_advisor_and_their_phone(): void
    {
        // EL DEFECTO: esto se bloqueaba en silencio. El mensaje existía pero
        // colgaba de una clave que el formulario no muestra.
        $this->clienteExistente();

        $this->intentarCrear(['phone' => '4421234567', 'email' => 'nuevo@ejemplo.com'])
            ->assertHasFormErrors(['phone']);

        $this->assertSame(1, PropertyOwner::count(), 'El duplicado no debe crearse.');
    }

    public function test_a_modal_opens_carrying_the_warning(): void
    {
        // El error bajo el campo se lee sólo si uno lo busca. El modal se dibuja
        // con el mismo componente que las confirmaciones del panel, así que el
        // agente ya lo reconoce, y no se puede pasar por alto.
        $this->clienteExistente();

        $componente = $this->intentarCrear(['phone' => '4421234567', 'email' => 'nuevo@ejemplo.com']);

        $componente->assertSet('mountedActions.0', 'clienteDuplicado');
        $this->assertStringContainsString('Ana Torres', $componente->get('avisoClienteDuplicado'));
        $this->assertStringContainsString('442 119 0959', $componente->get('avisoClienteDuplicado'));
    }

    public function test_the_same_email_is_enough_to_be_the_same_client(): void
    {
        // Alcanza con UNO de los dos datos: exigir que coincidan ambos dejaría
        // pasar al mismo cliente cargado con un solo dato de contacto.
        $this->clienteExistente();

        $this->intentarCrear(['phone' => '4429998877', 'email' => 'lamont@ejemplo.com'])
            ->assertHasFormErrors(['email']);

        $this->assertSame(1, PropertyOwner::count());
    }

    public function test_a_different_spelling_of_the_name_no_longer_slips_through(): void
    {
        // Antes se exigía nombre + apellido + teléfono, los tres juntos, y por
        // eso el mismo cliente escrito distinto entraba dos veces.
        $this->clienteExistente();

        $this->intentarCrear(['phone' => '442 123 4567', 'email' => 'otro@ejemplo.com'])
            ->assertHasFormErrors(['phone']);

        $this->assertSame(1, PropertyOwner::count(), 'El teléfono se compara por sus dígitos.');
    }

    public function test_a_genuinely_new_client_is_created(): void
    {
        // Lo que NO debe romperse: la red no puede ser tan amplia que impida
        // registrar clientes nuevos.
        $this->clienteExistente();

        $this->intentarCrear(['phone' => '4429998877', 'email' => 'nuevo@ejemplo.com'])
            ->assertHasNoFormErrors();

        $this->assertSame(2, PropertyOwner::count());
    }

    public function test_both_contact_fields_are_required(): void
    {
        // Son los que identifican al cliente: sin uno de ellos, el mismo cliente
        // vuelve a entrar por el hueco que quedó.
        $this->actingAs(User::factory()->withRole('agente')->create());

        Livewire::test(CreatePropertyOwner::class)
            ->fillForm(['first_name' => 'Juan', 'last_name' => 'Pérez', 'phone' => '', 'email' => ''])
            ->call('create')
            ->assertHasFormErrors(['phone' => 'required', 'email' => 'required']);
    }

    public function test_the_message_carries_the_advisor_and_the_phone(): void
    {
        // El texto es el punto de toda la función: sin el nombre y el teléfono,
        // el asesor sabe que no puede registrarlo pero no a quién dirigirse.
        $duplicado = $this->clienteExistente();

        $mensaje = (new UniquePropertyOwner)->mensajeDe($duplicado);

        $this->assertStringContainsString('Ana Torres', $mensaje);
        $this->assertStringContainsString('442 119 0959', $mensaje);
    }

    public function test_without_a_phone_on_file_the_advisor_is_still_named(): void
    {
        // El teléfono es opcional en el perfil. Decir «teléfono: —» sería ruido;
        // el nombre solo ya sirve para saber a quién buscar.
        $this->asesorQueLoTiene->forceFill(['phone' => null])->saveQuietly();
        $duplicado = $this->clienteExistente();

        $mensaje = (new UniquePropertyOwner)->mensajeDe($duplicado->fresh());

        $this->assertStringContainsString('Ana Torres', $mensaje);
        $this->assertStringContainsString('directorio', $mensaje);
    }

    /*
    |---------------------------------------------------------------------------
    | El alta rápida desde el formulario de inmueble
    |---------------------------------------------------------------------------
    |
    | El «+» junto al propietario abre su propio modal, y ahí el aviso volvía a
    | perderse: el mensaje se emitía con la clave suelta `phone`, pero dentro de
    | un modal anidado los campos se llaman
    | `mountedFormComponentActionsData.0.phone`. Mismo síntoma que en la pantalla
    | de propietarios —el duplicado no se creaba y en pantalla no pasaba nada—
    | por la misma causa, en la otra puerta.
    |
    */

    /** Intenta el alta rápida desde el formulario de inmueble. */
    private function intentarAltaRapida(array $datos): Testable
    {
        $this->actingAs(User::factory()->withRole('agente')->create(['name' => 'Otro Asesor']));

        return Livewire::test(CreateProperty::class)
            ->mountFormComponentAction('owner_id', 'createOption')
            ->setFormComponentActionData($datos + [
                'first_name' => 'Juan Carlos',
                'last_name' => 'Pérez',
            ])
            ->callMountedFormComponentAction();
    }

    public function test_the_quick_create_modal_says_why_it_refuses(): void
    {
        // EL DEFECTO: el alta se frenaba en silencio. Acá el aviso va flotante
        // —y no otro modal— porque esto ya corre dentro del modal del «+»:
        // encimar uno sobre otro obliga a cerrar dos cosas para volver.
        $this->clienteExistente();

        $this->intentarAltaRapida(['phone' => '4421234567', 'email' => 'nuevo@ejemplo.com'])
            ->assertNotified('Ese cliente ya está registrado')
            ->assertHasErrors('mountedFormComponentActionsData.0.phone');

        $this->assertSame(1, PropertyOwner::count(), 'El duplicado no debe crearse.');
    }

    public function test_the_quick_create_also_recognises_the_client_by_email(): void
    {
        // Las dos puertas comprueban LOS DOS datos. Antes ésta miraba sólo el
        // teléfono, así que el mismo cliente con otro número entraba por acá.
        $this->clienteExistente();

        $this->intentarAltaRapida(['phone' => '4429998877', 'email' => 'lamont@ejemplo.com'])
            ->assertHasErrors('mountedFormComponentActionsData.0.email');

        $this->assertSame(1, PropertyOwner::count());
    }

    public function test_the_quick_create_demands_both_contact_fields(): void
    {
        // El email era opcional acá y obligatorio en la otra pantalla. Ese hueco
        // alcanzaba para colar sin email al cliente que la comprobación necesita
        // reconocer por email.
        $this->actingAs(User::factory()->withRole('agente')->create());

        Livewire::test(CreateProperty::class)
            ->mountFormComponentAction('owner_id', 'createOption')
            ->setFormComponentActionData(['first_name' => 'Juan', 'last_name' => 'Pérez', 'phone' => '', 'email' => ''])
            ->callMountedFormComponentAction()
            ->assertHasFormComponentActionErrors(['phone' => 'required', 'email' => 'required']);
    }

    public function test_the_quick_create_still_registers_a_new_client(): void
    {
        // Lo que NO debe romperse: el atajo tiene que seguir siendo un atajo.
        $this->clienteExistente();

        $this->intentarAltaRapida(['phone' => '4429998877', 'email' => 'nuevo@ejemplo.com'])
            ->assertHasNoFormComponentActionErrors();

        $this->assertSame(2, PropertyOwner::count());
    }
}

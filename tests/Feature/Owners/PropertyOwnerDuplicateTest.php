<?php

namespace Tests\Feature\Owners;

use App\Models\PropertyOwner;
use App\Models\User;
use App\Rules\UniquePropertyOwner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PropertyOwnerDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * El criterio de duplicado ya NO usa el nombre.
     *
     * Antes exigía nombre + apellido + teléfono, los tres juntos, y por eso el
     * mismo cliente escrito distinto —«Juan Carlos» y «Juan C.»— entraba dos
     * veces. Ahora identifica por teléfono O email, que son los datos que cada
     * asesor no reescribe a su manera.
     */
    public function test_find_duplicate_matches_a_phone_however_it_was_typed(): void
    {
        $existing = PropertyOwner::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'phone' => '442 123 4567',
            'email' => 'juan@ejemplo.com',
        ]);

        // Se compara por dígitos: los espacios no hacen a dos personas.
        $this->assertSame($existing->id, PropertyOwner::findDuplicate('4421234567')?->id);

        // Y el nombre ya no interviene: alcanza con el teléfono.
        $this->assertSame($existing->id, PropertyOwner::findDuplicate('442 123 4567')?->id);

        $this->assertNull(PropertyOwner::findDuplicate('9999999999'));
    }

    public function test_find_duplicate_also_recognises_the_client_by_email(): void
    {
        $existing = PropertyOwner::factory()->create([
            'phone' => '4421234567',
            'email' => 'Lamont@Ejemplo.com',
        ]);

        // Alcanza con UNO de los dos datos, y el email no distingue mayúsculas.
        $this->assertSame($existing->id, PropertyOwner::findDuplicate('5559998877', 'lamont@ejemplo.com')?->id);
    }

    /**
     * El aviso NOMBRA al asesor que tiene al cliente. A propósito.
     *
     * Hubo una versión que devolvía un mensaje neutro —«contacta a un
     * administrador»— para no exponer al otro agente. Se revirtió por pedido
     * del owner: el objetivo del aviso es que los dos asesores se pongan de
     * acuerdo, y mandarlos a un tercero para averiguar un nombre agrega un paso
     * sin proteger nada que el equipo no comparta.
     */
    public function test_the_warning_names_the_advisor_who_has_the_client(): void
    {
        $agentA = User::factory()->withRole('agente')->create(['phone' => '442 119 0959']);
        $agentB = User::factory()->withRole('agente')->create();
        PropertyOwner::factory()->create([
            'first_name' => 'Ana', 'last_name' => 'Lopez', 'phone' => '5551112222', 'agent_id' => $agentA->id,
        ]);

        $this->actingAs($agentB);

        $validator = Validator::make(
            ['phone' => '5551112222'],
            ['phone' => [new UniquePropertyOwner]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString($agentA->name, $validator->errors()->first('phone'));
        $this->assertStringContainsString('442 119 0959', $validator->errors()->first('phone'));
    }

    public function test_unique_rule_passes_when_no_duplicate(): void
    {
        $this->actingAs(User::factory()->withRole('agente')->create());

        $validator = Validator::make(
            ['first_name' => 'Nuevo', 'last_name' => 'Cliente', 'phone' => '5550000000'],
            ['phone' => [new UniquePropertyOwner]],
        );

        $this->assertFalse($validator->fails());
    }
}

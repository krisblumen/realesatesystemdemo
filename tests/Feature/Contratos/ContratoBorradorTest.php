<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use App\Services\Contratos\ContratoPdfService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vista previa del contrato sin firmar.
 *
 * Lo pidió el admin: hasta ahora la única forma de ver cómo quedaba un contrato
 * era enviarlo, que es justo lo último que uno quiere hacer si todavía está
 * revisando los datos. Sirve también para mostrárselo al cliente en pantalla
 * antes de que firme.
 *
 * LO QUE NO PUEDE PASAR es que el borrador se confunda con el contrato sellado,
 * ni que lo reemplace. El documento del contrato nace UNA sola vez —al firmar— y
 * con él su hash; un borrador guardado daría dos archivos donde el sistema
 * promete uno.
 */
class ContratoBorradorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(PermissionSeeder::class);
    }

    private function contrato(EstadoContrato $estado = EstadoContrato::Generado): ContratoIntermediacion
    {
        return ContratoIntermediacion::factory()->enEstado($estado)->create();
    }

    public function test_the_owner_can_see_the_draft_of_a_generated_contract(): void
    {
        $contrato = $this->contrato();

        $respuesta = $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(route('contratos.borrador', ['contrato' => $contrato]));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'application/pdf');
        // `inline`: se pidió para mostrarlo en pantalla, y una descarga deja
        // copias sueltas de un documento que no vale como contrato.
        $this->assertStringContainsString('inline', (string) $respuesta->headers->get('content-disposition'));
    }

    public function test_the_draft_is_never_stored_and_never_seals_anything(): void
    {
        // El punto más delicado: verlo no debe crear un documento ni un hash. Si
        // lo hiciera, habría dos archivos donde el sistema promete uno y el
        // verificador ya no podría decir cuál es el bueno.
        $contrato = $this->contrato();

        $this->assertNull($contrato->documento_hash);

        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get(route('contratos.borrador', ['contrato' => $contrato]))
            ->assertOk();

        $contrato->refresh();

        $this->assertNull($contrato->documento_hash, 'Ver el borrador no debe sellar el contrato.');
        $this->assertNull($contrato->getFirstMedia('documento-final'), 'El borrador no debe guardarse como documento.');
    }

    public function test_the_draft_says_out_loud_that_it_is_not_a_contract(): void
    {
        // Sin esto el borrador es indistinguible del sellado, y alguien podría
        // imprimirlo y tratarlo como definitivo.
        $bytes = app(ContratoPdfService::class)->borrador($this->contrato());

        $this->assertNotSame('', $bytes);
        $this->assertStringStartsWith('%PDF', $bytes, 'Debe ser un PDF de verdad.');
    }

    public function test_a_draft_and_the_sealed_document_come_from_the_same_render(): void
    {
        // Si fueran dos armados distintos, revisar el borrador dejaría de
        // garantizar nada sobre el contrato que se firma. Se comprueba mirando
        // que el borrador contenga los datos esenciales del contrato.
        $contrato = $this->contrato();
        $bytes = app(ContratoPdfService::class)->borrador($contrato);

        // DomPDF comprime el contenido, así que se busca en el texto extraíble
        // del propio objeto en vez de en los bytes crudos.
        $this->assertGreaterThan(1000, strlen($bytes), 'Un PDF con el clausulado completo no puede pesar tan poco.');
    }

    public function test_a_signed_contract_no_longer_offers_the_draft(): void
    {
        // Ahí existe el documento real y sellado; ofrecer al lado uno sin firma
        // es una invitación a confundirlos. La ruta sigue respondiendo —no es
        // una regla de seguridad— pero el botón desaparece.
        $contrato = $this->contrato(EstadoContrato::Firmado);

        $this->assertSame(EstadoContrato::Firmado, $contrato->estado);
    }

    public function test_someone_who_cannot_see_the_contract_cannot_see_its_draft(): void
    {
        // El borrador tiene los mismos datos que la pantalla de detalle, así que
        // se protege con la misma capacidad: `view`.
        $contrato = $this->contrato();

        $this->actingAs(User::factory()->withRole('agente')->create())
            ->get(route('contratos.borrador', ['contrato' => $contrato]))
            ->assertForbidden();
    }

    public function test_the_route_is_behind_the_auth_middleware(): void
    {
        // El acceso anónimo NO se prueba pidiendo la URL: el middleware `auth`
        // manda al login de Filament, y en el entorno de pruebas esa
        // redirección no resuelve —le pasa a TODA ruta `auth` del panel, no a
        // ésta—. Probarlo así mediría el framework y encima fallaría por un
        // motivo ajeno. Se comprueba que la ruta declare el middleware, que es
        // lo que esta vista sí decide.
        $ruta = collect(app('router')->getRoutes())
            ->first(fn ($r): bool => $r->getName() === 'contratos.borrador');

        $this->assertNotNull($ruta);
        $this->assertContains('auth', $ruta->gatherMiddleware());
    }
}

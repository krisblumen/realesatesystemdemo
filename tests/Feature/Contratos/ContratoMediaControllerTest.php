<?php

namespace Tests\Feature\Contratos;

use App\Models\ContratoIntermediacion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContratoMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }

    private function contratoConMedia(?User $agente = null): ContratoIntermediacion
    {
        $contrato = $agente !== null
            ? ContratoIntermediacion::factory()->for($agente, 'agente')->create()
            : ContratoIntermediacion::factory()->create();

        $contrato->addMediaFromString('anverso-bytes')
            ->usingFileName('anverso.png')->toMediaCollection('identificacion-anverso');
        $contrato->addMediaFromString('pdf-bytes')
            ->usingFileName('contrato.pdf')->toMediaCollection('documento-final');

        return $contrato;
    }

    private function mediaUrl(ContratoIntermediacion $contrato, string $coleccion): string
    {
        return route('contratos.media', ['contrato' => $contrato, 'coleccion' => $coleccion]);
    }

    public function test_owner_can_view_identificacion_but_admin_cannot(): void
    {
        $contrato = $this->contratoConMedia();

        $this->actingAs($this->user('owner'))
            ->get($this->mediaUrl($contrato, 'identificacion-anverso'))->assertOk();

        $this->actingAs($this->user('admin'))
            ->get($this->mediaUrl($contrato, 'identificacion-anverso'))->assertForbidden();
    }

    public function test_agente_cannot_view_identificacion_even_of_own_contract(): void
    {
        $agente = $this->user('agente');
        $contrato = $this->contratoConMedia($agente);

        $this->actingAs($agente)
            ->get($this->mediaUrl($contrato, 'identificacion-anverso'))->assertForbidden();
    }

    public function test_agente_can_download_pdf_of_own_contract_but_not_of_others(): void
    {
        $agente = $this->user('agente');
        $propio = $this->contratoConMedia($agente);
        $ajeno = $this->contratoConMedia();

        $this->actingAs($agente)
            ->get($this->mediaUrl($propio, 'documento-final'))->assertOk();

        $this->actingAs($agente)
            ->get($this->mediaUrl($ajeno, 'documento-final'))->assertForbidden();
    }

    public function test_unknown_collection_returns_404(): void
    {
        $contrato = $this->contratoConMedia();

        $this->actingAs($this->user('owner'))
            ->get($this->mediaUrl($contrato, 'coleccion-inexistente'))->assertNotFound();
    }

    // Nota: la protección de invitados la da el middleware 'auth' de la ruta (igual que
    // properties.pdf.show). No se testea aquí porque la app no tiene ruta con nombre
    // 'login', así que el redirect del framework tira 500 — comportamiento global ajeno
    // a este controlador. La autorización por rol (arriba) es la superficie de seguridad real.
}

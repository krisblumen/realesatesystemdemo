<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Tenancy\InquilinoActual;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Un contrato firmado en el demo dice que es una demostración.
 *
 * EL RIESGO CONCRETO. El demo firma contratos DE VERDAD: genera un PDF con
 * folio, sello digital, hash SHA-256 y una página pública de verificación. Es
 * una de las funciones que el demo existe para lucir, y funciona igual que en
 * producción.
 *
 * Los datos fiscales son de relleno a propósito —ver `_cuerpo-contrato`— y eso
 * delata el documento a quien lo lea con atención. A quien no, no.
 *
 * SE ACTIVA CUANDO HAY INQUILINO y no con una bandera. Mismo criterio que el
 * cierre del entorno: una bandera es algo que alguien puede olvidarse de
 * encender, y el síntoma de olvidarla es un contrato de demo que parece real.
 */
class ContratoDeDemostracionTest extends TestCase
{
    private function render(array $datos = []): string
    {
        return view('contratos._aviso-demo', $datos)->render();
    }

    private function conInquilino(): void
    {
        app(InquilinoActual::class)->fijar(new Tenant(['slug' => 'aaaabbbbcccc']));
    }

    public function test_inside_a_tenant_the_document_says_it_is_a_demonstration(): void
    {
        $this->conInquilino();

        $html = $this->render();

        $this->assertStringContainsString('DEMOSTRACIÓN', $html);
        $this->assertStringContainsString('SIN VALIDEZ LEGAL', $html);
    }

    public function test_without_a_tenant_there_is_no_mark(): void
    {
        // La plataforma corriendo para un cliente propio emite contratos reales.
        // Marcarlos como demostración sería peor que no marcar los del demo — y
        // nadie tiene que acordarse de apagar nada.
        $this->assertSame('', trim($this->render()));
    }

    public function test_the_watermark_is_opt_in_because_only_the_pdf_has_room(): void
    {
        // En el PDF la marca de agua se ve de lejos y sobrevive a una fotocopia.
        // En una página web sería ruido encima del contenido.
        $this->conInquilino();

        $this->assertStringNotContainsString('marca-demo', $this->render());
        $this->assertStringContainsString('marca-demo', $this->render(['comoMarcaDeAgua' => true]));
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function superficiesQueLoMuestran(): array
    {
        return [
            'el PDF' => ['resources/views/pdf/contrato-intermediacion.blade.php'],
            'la página de firma' => ['resources/views/public/contratos/show.blade.php'],
            'la de verificación' => ['resources/views/public/contratos/verificar.blade.php'],
        ];
    }

    #[DataProvider('superficiesQueLoMuestran')]
    public function test_every_surface_that_shows_a_contract_includes_it(string $vista): void
    {
        // Las tres cuentan. El PDF es el documento; la página de firma es donde
        // alguien acepta; y la de verificación es la que suena a autoridad — si
        // el documento dijera «demostración» y esa página no, la que manda es
        // la que parece oficial.
        $this->assertStringContainsString(
            'contratos._aviso-demo',
            (string) file_get_contents(base_path($vista)),
            "«{$vista}» tiene que mostrar el aviso: es una superficie donde alguien lee el contrato.",
        );
    }
}

<?php

namespace Tests\Feature\Properties;

use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\PropertyResource;
use App\Filament\Resources\PropertyResource\Pages\EditProperty;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las fotos de inmuebles y proyectos aceptan lo que pesa una foto de verdad.
 *
 * EL FALLO QUE ESTO EVITA no era un aviso. El límite estaba en 5 MB y una foto
 * de teléfono actual lo pasa sin esfuerzo; cuando eso ocurría, FilePond marcaba
 * el archivo como inválido y el NAVEGADOR bloqueaba el envío del formulario
 * entero por validación nativa: no se disparaba `submit`, no salía ningún pedido
 * al servidor, y el globo de error queda anclado a un input que no se ve. Para
 * el agente, «Guardar cambios» simplemente dejaba de responder — y se perdían
 * también los campos de texto que hubiera cambiado.
 *
 * El límite tiene DOS bordes y por eso se prueban los dos: tiene que aceptar lo
 * que pesa una foto de teléfono, y quedarse por debajo de `upload_max_filesize`
 * de PHP — por encima de eso el archivo ni siquiera llega al servidor, así que
 * el fallo se mudaría de lugar en vez de desaparecer.
 *
 * Y EL RECORTE QUEDA LIBRE. Una versión anterior subía el límite a 12 MB
 * reduciendo la foto en el navegador; se revirtió porque Filament deriva la
 * proporción del recorte de las medidas de ese redimensionado, y declarar
 * 1920×1920 dejaba el editor cuadrado: no había forma de recortar en otra forma.
 */
class PropertyPhotoUploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que pesa hoy una foto de teléfono sin recortar, en KB. */
    private const FOTO_DE_TELEFONO_KB = 8192;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    /**
     * Los campos de foto tal como los ve el agente.
     *
     * Se leen de la PÁGINA montada y no armando un Form a mano: la pantalla es
     * la que decide qué campos existen, y un formulario construido aparte podría
     * pasar mientras la real está rota.
     *
     * @return array<string, BaseFileUpload>
     */
    private function camposDeFoto(string $pageClass, int $recordId): array
    {
        $componentes = Livewire::test($pageClass, ['record' => $recordId])
            ->instance()->getForm('form')->getComponents();

        $encontrados = [];

        $walk = function (array $components) use (&$walk, &$encontrados): void {
            foreach ($components as $child) {
                if ($child instanceof BaseFileUpload) {
                    $encontrados[$child->getName()] = $child;
                }
                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $walk($componentes);

        return $encontrados;
    }

    public static function recursosConFotos(): array
    {
        return [
            'inmuebles' => [EditProperty::class, 'inmueble'],
            'proyectos' => [EditProject::class, 'proyecto'],
        ];
    }

    /**
     * Un registro de cada tipo, que es lo que la pantalla de edición necesita.
     *
     * El proyecto se crea a mano porque no tiene fábrica; con dos campos
     * alcanza, y lo que se mira es la forma del formulario, no sus datos.
     */
    private function registro(string $tipo): int
    {
        if ($tipo === 'inmueble') {
            return Property::factory()->create()->getKey();
        }

        return Project::query()->create([
            'title' => 'Proyecto de prueba',
            'slug' => 'proyecto-de-prueba',
        ])->getKey();
    }

    #[DataProvider('recursosConFotos')]
    public function test_a_real_phone_photo_fits(string $pageClass, string $tipo): void
    {
        foreach ($this->camposDeFoto($pageClass, $this->registro($tipo)) as $nombre => $campo) {
            $this->assertGreaterThanOrEqual(
                self::FOTO_DE_TELEFONO_KB,
                $campo->getMaxSize(),
                "«{$nombre}» rechaza una foto de teléfono, y al rechazarla deja el formulario sin poder guardarse.",
            );
        }
    }

    #[DataProvider('recursosConFotos')]
    public function test_the_limit_stays_under_what_php_actually_accepts(string $pageClass, string $tipo): void
    {
        // El techo real es `upload_max_filesize`. Un límite por encima de él
        // acepta en el formulario archivos que después mueren en el servidor:
        // el fallo se muda, no desaparece.
        $php = self::kilobytes((string) ini_get('upload_max_filesize'));

        foreach ($this->camposDeFoto($pageClass, $this->registro($tipo)) as $nombre => $campo) {
            $this->assertLessThanOrEqual(
                $php,
                $campo->getMaxSize(),
                "«{$nombre}» acepta más de lo que PHP deja subir ({$php} KB).",
            );
        }
    }

    #[DataProvider('recursosConFotos')]
    public function test_the_crop_is_free_by_default(string $pageClass, string $tipo): void
    {
        // LO QUE SE ROMPIÓ Y NO PUEDE VOLVER A ROMPERSE. Filament DERIVA la
        // proporción del recorte de las medidas del redimensionado: declarar
        // 1920×1920 dejaba el editor cuadrado y no había forma de recortar en
        // otra forma. El recorte queda libre sólo si el viewport no está fijado
        // —de ahí salía el `aspectRatio` del editor—, y eso pide que no haya
        // medidas de redimensionado.
        foreach ($this->camposDeFoto($pageClass, $this->registro($tipo)) as $nombre => $campo) {
            $this->assertNull(
                $campo->getImageEditorViewportWidth(),
                "«{$nombre}» fija la proporción del recorte y no deja recortar libremente.",
            );
            $this->assertNull($campo->getImageEditorViewportHeight(), "«{$nombre}» fija el alto del recorte.");
        }
    }

    #[DataProvider('recursosConFotos')]
    public function test_the_editor_still_offers_fixed_ratios(string $pageClass, string $tipo): void
    {
        // Libre por defecto, pero con presets a mano: quien quiere una portada
        // 16:9 no tiene que lograrla a pulso.
        foreach ($this->camposDeFoto($pageClass, $this->registro($tipo)) as $nombre => $campo) {
            $ratios = $campo->getImageEditorAspectRatiosForJs();

            $this->assertContains('NaN', $ratios, "«{$nombre}» no ofrece recorte libre.");
            $this->assertGreaterThan(2, count($ratios), "«{$nombre}» no ofrece proporciones fijas.");
        }
    }

    /** «10M», «8192K» y demás, en KB. */
    private static function kilobytes(string $ini): int
    {
        $numero = (int) $ini;

        return match (strtoupper(substr(trim($ini), -1))) {
            'G' => $numero * 1024 * 1024,
            'M' => $numero * 1024,
            'K' => $numero,
            default => (int) ($numero / 1024),
        };
    }

    public function test_both_resources_share_one_limit(): void
    {
        // Eran dos números iguales escritos por separado; el día que uno suba,
        // el otro tiene que subir con él.
        foreach ($this->camposDeFoto(EditProject::class, $this->registro('proyecto')) as $campo) {
            $this->assertSame(PropertyResource::MAX_FOTO_KB, $campo->getMaxSize());
        }
    }
}

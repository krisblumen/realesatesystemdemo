<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\Pages\ListFrontendPages;
use App\Models\FrontendPage;
use App\Models\User;
use App\Support\Frontend\PublicRoutes;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Al editar una página del sitio, el título dice CUÁL.
 *
 * Las cinco páginas usan la misma pantalla y el encabezado salía del nombre del
 * modelo, así que las cinco decían «Editar Página Del Sitio» y nada más. Las
 * secciones de abajo tampoco alcanzan para deducirlo: varias páginas comparten
 * los mismos tipos, y todas empiezan por la portada.
 *
 * El nombre sale del MISMO allowlist que nombra los enlaces del sitio público.
 * Dos listas paralelas terminarían llamando distinto a la misma página en el
 * panel y en el menú.
 */
class FrontendPageTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function page(string $key): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    public static function paginas(): array
    {
        return [
            'home' => ['home', 'Inicio'],
            'nosotros' => ['nosotros', 'Nosotros'],
            'servicios' => ['servicios', 'Servicios'],
            'inversionistas' => ['inversionistas', 'Inversionistas'],
            'contacto' => ['contacto', 'Contacto'],
        ];
    }

    #[DataProvider('paginas')]
    public function test_the_edit_screen_says_which_page_it_is(string $key, string $nombre): void
    {
        $titulo = Livewire::test(EditFrontendPage::class, ['record' => $this->page($key)->getKey()])
            ->instance()->getTitle();

        $this->assertStringContainsString($nombre, $titulo, "Editar «{$key}» no dice qué página es.");
        // Y no pierde la frase con la que el owner ya reconoce esta pantalla.
        $this->assertStringContainsString('Página Del Sitio', $titulo);
    }

    public function test_every_page_gets_its_own_title(): void
    {
        // Lo que fallaba: cinco pantallas con el mismo encabezado. Éste mira las
        // cinco de una, así que no lleva provider.
        $titulos = array_map(
            fn (array $p): string => Livewire::test(EditFrontendPage::class, ['record' => $this->page($p[0])->getKey()])
                ->instance()->getTitle(),
            array_values(self::paginas()),
        );

        $this->assertCount(5, array_unique($titulos), 'Dos páginas comparten el mismo título.');
    }

    #[DataProvider('paginas')]
    public function test_the_name_matches_the_public_menu(string $key, string $nombre): void
    {
        // Una lista aparte se despegaría: si el sitio llama «Inmobiliaria» a una
        // página, el panel no debería llamarla de otra forma.
        $this->assertSame(PublicRoutes::defaultLabel($key), $this->page($key)->label());
        $this->assertSame($nombre, $this->page($key)->label());
    }

    #[DataProvider('paginas')]
    public function test_breadcrumbs_and_search_use_the_same_name(string $key, string $nombre): void
    {
        $this->assertSame($nombre, FrontendPageResource::getRecordTitle($this->page($key)));
    }

    public function test_the_listing_shows_the_name_and_keeps_the_key_visible(): void
    {
        // El nombre para leer, la clave técnica debajo para quien la necesite.
        Livewire::test(ListFrontendPages::class)
            ->assertSee('Inicio')
            ->assertSee('Inversionistas')
            ->assertSee('home');
    }

    public function test_an_unknown_key_falls_back_to_the_key_itself(): void
    {
        // Feo pero identifica la página, que es justo lo que hace falta cuando
        // algo no calza. Devolver vacío dejaría el título peor que antes.
        $huerfana = new FrontendPage(['key' => 'pagina-que-no-existe']);

        $this->assertSame('pagina-que-no-existe', $huerfana->label());
    }
}

<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\BrandPalette;
use App\Services\Frontend\FrontendPagePublisher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La placa del ícono y su dibujo, elegibles por sección.
 *
 * El dibujo se guarda SÓLO si el owner lo eligió. Sin elección sigue a su placa,
 * y no por prolijidad: el color principal es tinta oscura, así que elegir una
 * placa oscura —una acción de un solo clic— dejaba el ícono invisible.
 */
class FrontendIconColorsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function sections(): array
    {
        return [
            'valores' => ['nosotros', 'values'],
            'qué hacemos' => ['home', 'capability_cards'],
        ];
    }

    private function render(string $page, string $type, array $colores): string
    {
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->withRole('owner')->create();
        $this->actingAs($owner);

        $section = FrontendPage::query()->where('key', $page)->firstOrFail()
            ->sections()->where('type', $type)->firstOrFail();

        $items = $type === 'values'
            ? [['title' => 'Confianza', 'description' => 'Cumplimos.', 'icon' => 'shield']]
            : [['title' => 'Arquitectura', 'description' => 'Diseñamos.', 'icon' => 'shield']];

        $section->forceFill(['payload' => $colores + ['title' => 'Encabezado', 'items' => $items]])->saveQuietly();

        $p = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($p, $p->draft_revision, $owner);

        return $this->get($page === 'home' ? '/' : "/{$page}")->assertOk()->getContent();
    }

    #[DataProvider('sections')]
    public function test_an_untouched_section_keeps_the_icon_it_always_had(string $page, string $type): void
    {
        $html = $this->render($page, $type, []);

        $this->assertStringContainsString('bg-navy-50', $html);
        $this->assertStringContainsString('text-brand-primary', $html);
    }

    #[DataProvider('sections')]
    public function test_the_owner_can_paint_the_plate_and_the_glyph(string $page, string $type): void
    {
        $html = $this->render($page, $type, ['icon_bg_color' => 'accent', 'icon_color' => 'neutral-5']);

        $this->assertMatchesRegularExpression('/bg-brand-accent[^"]*text-ink|text-ink[^"]*bg-brand-accent/', $html);
    }

    #[DataProvider('sections')]
    public function test_a_dark_plate_never_swallows_the_glyph(string $page, string $type): void
    {
        // El caso que rompía: elegir placa oscura y nada más.
        $html = $this->render($page, $type, ['icon_bg_color' => 'primary']);

        $this->assertStringContainsString('text-on-brand-primary', $html);
    }

    public function test_an_explicit_glyph_colour_still_wins(): void
    {
        // La deducción es un default, no una tutela.
        $section = FrontendSection::query()->where('type', 'values')->firstOrFail();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'title' => 'Nuestros valores',
            'icon_bg_color' => 'primary',
            'icon_color' => 'primary',
            'items' => [['title' => 'Confianza', 'description' => 'Cumplimos.']],
        ]);

        $this->assertSame('primary', $payload['icon_color']);
    }

    // ------------------------------------------- la galería, pintada en vivo --

    private function editor(string $page, string $type): string
    {
        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());

        $section = FrontendPage::query()->where('key', $page)->firstOrFail()
            ->sections()->where('type', $type)->firstOrFail();

        return Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey())->html();
    }

    #[DataProvider('sections')]
    public function test_the_gallery_binds_to_the_very_fields_that_paint_it(string $page, string $type): void
    {
        // Si la galería y el selector no apuntan a la MISMA propiedad de Livewire,
        // elegir un color no repinta nada y el error es invisible desde el
        // servidor: los dos lados renderizan bien por separado.
        $html = $this->editor($page, $type);

        // Comillas SIMPLES: en dobles, PHP interpola `$entangle`. El HTML puede
        // traer la comilla escapada (`&#039;`) por venir dentro de un atributo.
        $rutas = function (string $clave) use ($html): array {
            preg_match_all('/entangle\((?:&#039;|\')('.$clave.'[^&\']*)(?:&#039;|\')\)/', $html, $m);

            return $m[1];
        };

        $placa = [1 => $rutas('[^&\']*icon_bg_color')];
        $glifo = [1 => $rutas('[^&\']*icon_color')];

        $this->assertNotEmpty($placa[1], 'La galería no se engancha al color de la placa.');
        $this->assertGreaterThanOrEqual(2, count($placa[1]), 'Sólo hay un consumidor: falta el selector o falta la galería.');
        $this->assertCount(1, array_unique($placa[1]), 'La galería mira una propiedad y el selector escribe en otra.');
        $this->assertCount(1, array_unique($glifo[1]));
    }

    public function test_the_gallery_agrees_with_the_page_on_which_plates_are_dark(): void
    {
        // La galería resuelve el color del dibujo en el navegador para no pagar
        // una vuelta al servidor por cada ficha. Esa lista tiene que salir del
        // MISMO `needsDarkText()` que corre la vista, o la galería mostraría un
        // ícono que la página no publica.
        $html = $this->editor('nosotros', 'values');

        // `@js()` no emite el array pelado sino `JSON.parse('…')`, con las
        // comillas escapadas a `"` para poder viajar dentro del atributo.
        preg_match("/oscuras:\s*JSON\.parse\('(.*?)'\)/s", $html, $m);
        $this->assertNotEmpty($m, 'La galería no declara qué placas son oscuras.');

        $enLaGaleria = json_decode(str_replace('\u0022', '"', $m[1]), true);
        $this->assertIsArray($enLaGaleria, 'La lista de placas oscuras no es JSON válido: '.$m[1]);
        $paleta = app(BrandPalette::class);

        $esperadas = [];
        foreach (array_keys($paleta->swatches()) as $clave) {
            if (! $paleta->needsDarkText($clave)) {
                $esperadas[] = $clave;
            }
        }

        $this->assertSame($esperadas, $enLaGaleria);
    }

    public function test_not_choosing_the_glyph_is_not_saved(): void
    {
        $section = FrontendSection::query()->where('type', 'values')->firstOrFail();

        $payload = app(SectionPayloadCompiler::class)->compile($section, [
            'title' => 'Nuestros valores',
            'items' => [['title' => 'Confianza', 'description' => 'Cumplimos.']],
        ]);

        $this->assertSame('navy', $payload['icon_bg_color']);
        $this->assertArrayNotHasKey('icon_color', $payload);
    }
}

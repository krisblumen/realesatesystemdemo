<?php

namespace Tests\Feature\Frontend;

use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendSection;
use App\Models\FrontendSetting;
use App\Services\Frontend\Media\MediaPromotionState;
use App\Services\Frontend\Media\PromotableMediaOwner;
use App\Services\Frontend\Media\PromotableMediaOwners;
use App\Services\Frontend\PublishedMediaReference;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Épica 12.3, Lote A — la abstracción de promoción.
 *
 * El pipeline pasa a servir a más de un dueño. Lo que estos tests protegen no es
 * una funcionalidad nueva: es que la variación viva en la estrategia y NO se
 * cuele de vuelta al job como una rama por tipo, y que un dueño no declarado no
 * promueva nada.
 */
class FrontendMediaStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
    }

    // ------------------------------------------------ registry fail-closed --

    public function test_the_registry_resolves_the_page_section_strategy(): void
    {
        $section = FrontendSection::query()->firstOrFail();
        $media = $section->addMedia(UploadedFile::fake()->image('s.png'))->toMediaCollection('images');

        $this->assertInstanceOf(
            PublishedMediaReference::class,
            app(PromotableMediaOwners::class)->for($media),
        );
    }

    public function test_a_model_type_without_a_declared_strategy_resolves_to_nothing(): void
    {
        // La media de marca vive en la misma tabla y usa los mismos flags, y está
        // deliberadamente fuera de alcance. Sin fail-closed, un default la
        // promovería junto con todo lo demás.
        $brand = FrontendSetting::current()
            ->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo-light');

        $this->assertNull(app(PromotableMediaOwners::class)->for($brand));
    }

    public function test_a_wrong_collection_of_a_known_model_resolves_to_nothing(): void
    {
        // No alcanza con acertar el model_type: una colección que la estrategia
        // no declara tampoco se promueve.
        $section = FrontendSection::query()->firstOrFail();
        $otra = $section->addMedia(UploadedFile::fake()->image('x.png'))->toMediaCollection('otra-coleccion');

        $this->assertNull(app(PromotableMediaOwners::class)->for($otra));
    }

    public function test_the_job_leaves_an_undeclared_owner_untouched(): void
    {
        $brand = FrontendSetting::current()
            ->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo-light');

        $brand->setCustomProperty(MediaPromotionState::PENDING, true)->save();
        $disco = $brand->disk;

        app()->call([new PromoteFrontendMedia((string) $brand->uuid), 'handle']);

        // Ni promueve, ni limpia el flag, ni cambia el disco: no la toca.
        $brand->refresh();
        $this->assertTrue($brand->getCustomProperty(MediaPromotionState::PENDING));
        $this->assertNull($brand->getCustomProperty(MediaPromotionState::PROMOTED));
        $this->assertSame($disco, $brand->disk);
    }

    public function test_the_job_does_nothing_for_an_unknown_uuid(): void
    {
        app()->call([new PromoteFrontendMedia('11111111-2222-4333-8444-555555555555'), 'handle']);

        $this->expectNotToPerformAssertions();
    }

    // -------------------------------------------------- guard estructural --

    public function test_the_job_has_no_branch_by_owner_type(): void
    {
        // El punto del lote: la variación vive en la estrategia. Una rama por
        // tipo dentro del job reintroduce los dos pipelines que se querían
        // evitar — y lo haría de a poco, un `if` por vez.
        // Se mira el CÓDIGO, no los comentarios: el docblock del job explica
        // justamente por qué no hay ramas por tipo, y nombrar los modelos ahí es
        // documentación, no acoplamiento.
        $fuente = $this->codeWithoutComments(app_path('Jobs/PromoteFrontendMedia.php'));

        foreach (['FrontendSection', 'FrontendService', 'FrontendPage', 'FrontendSetting'] as $modelo) {
            $this->assertStringNotContainsString(
                $modelo,
                $fuente,
                "El job nombra «{$modelo}»: la variación por dueño volvió al mecanismo.",
            );
        }

        $this->assertStringNotContainsString('instanceof', $fuente);
    }

    public function test_the_state_machine_lives_in_exactly_one_place(): void
    {
        // `PublishedMediaReference` conserva sus métodos de estado como
        // delegación (§3.1b). Si alguno vuelve a tener lógica propia, hay dos
        // máquinas de estados y una de las dos va a divergir.
        $fuente = $this->codeWithoutComments(app_path('Services/Frontend/PublishedMediaReference.php'));

        foreach (['setCustomProperty', 'forgetCustomProperty', 'hasCustomProperty'] as $primitiva) {
            $this->assertStringNotContainsString(
                $primitiva,
                $fuente,
                "«{$primitiva}» volvió a PublishedMediaReference: la máquina de estados se duplicó.",
            );
        }
    }

    /** El fuente sin comentarios ni docblocks: sólo lo que se ejecuta. */
    private function codeWithoutComments(string $path): string
    {
        $codigo = '';

        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $codigo .= is_array($token) ? $token[1] : $token;
        }

        return $codigo;
    }

    // ------------------------------------------------- contrato adoptado --

    public function test_the_page_strategy_implements_the_contract(): void
    {
        $strategy = app(PublishedMediaReference::class);

        $this->assertInstanceOf(PromotableMediaOwner::class, $strategy);
        $this->assertSame((new FrontendSection)->getMorphClass(), $strategy->modelType());
        $this->assertSame('images', $strategy->collection());
    }

    public function test_the_model_type_comes_from_the_morph_map_not_a_hardcoded_class(): void
    {
        // Si el proyecto define un morph map, un FQCN literal dejaría de matchear
        // silenciosamente y nada se promovería nunca.
        $this->assertSame(
            (new FrontendSection)->getMorphClass(),
            app(PublishedMediaReference::class)->modelType(),
        );
    }

    public function test_the_public_constants_still_answer_for_the_previous_callers(): void
    {
        // Los llamadores de 12.1 usan PublishedMediaReference::PENDING. Las
        // constantes se conservan apuntando a la máquina común: mismo valor, un
        // solo origen.
        $this->assertSame(MediaPromotionState::PENDING, PublishedMediaReference::PENDING);
        $this->assertSame(MediaPromotionState::PROMOTED, PublishedMediaReference::PROMOTED);
        $this->assertSame(MediaPromotionState::AUTHORIZING_REVISION, PublishedMediaReference::AUTHORIZING_REVISION);
    }
}

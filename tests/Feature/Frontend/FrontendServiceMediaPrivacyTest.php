<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendService;
use App\Models\User;
use App\Services\Frontend\Media\ServiceMediaReference;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\UsesRealPostgresConnections;
use Tests\TestCase;

/**
 * Épica 12.3, Lote B — T3-2, T3-6, T3-7: privacidad del borrador y unicidad.
 *
 * Dos garantías distintas que se prueban juntas porque protegen lo mismo: que
 * una imagen de servicio pertenezca a UN dueño y sólo él pueda verla antes de
 * que se publique.
 */
class FrontendServiceMediaPrivacyTest extends TestCase
{
    use RefreshDatabase;
    use UsesRealPostgresConnections;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
    }

    private function service(): FrontendService
    {
        return FrontendService::query()->firstOrFail();
    }

    private function attach(FrontendService $service, string $name = 'foto.png'): Media
    {
        return $service->addMedia(UploadedFile::fake()->image($name, 1200, 800))
            ->toMediaCollection(ServiceMediaReference::COLLECTION);
    }

    private function url(FrontendService $service, string $uuid): string
    {
        return route('frontend.services.media', ['service' => $service->getKey(), 'uuid' => $uuid]);
    }

    // -------------------------------------------------------------- T3-2 ----

    public function test_the_owner_can_preview_the_draft_image_inline(): void
    {
        $service = $this->service();
        $media = $this->attach($service);

        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get($this->url($service, (string) $media->uuid))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_the_five_failure_cases_answer_the_same_404(): void
    {
        $service = $this->service();
        $media = $this->attach($service);
        $owner = User::factory()->withRole('owner')->create();

        // 1. Anónimo.
        $this->get($this->url($service, (string) $media->uuid))->assertNotFound();

        // 2. Autenticado sin permiso.
        $this->actingAs(User::factory()->withRole('agente')->create())
            ->get($this->url($service, (string) $media->uuid))->assertNotFound();

        // 3. Servicio inexistente.
        $this->actingAs($owner)
            ->get(route('frontend.services.media', ['service' => 999999, 'uuid' => (string) $media->uuid]))
            ->assertNotFound();

        // 4. Uuid mal formado — 404, nunca SQLSTATE 22P02.
        $this->actingAs($owner)->get($this->url($service, 'no-soy-un-uuid'))->assertNotFound();

        // 5. Uuid de OTRO servicio.
        $otro = FrontendService::query()->where('id', '!=', $service->getKey())->firstOrFail();
        $ajena = $this->attach($otro, 'ajena.png');
        $this->actingAs($owner)->get($this->url($service, (string) $ajena->uuid))->assertNotFound();
    }

    public function test_a_soft_deleted_service_can_still_be_previewed(): void
    {
        // Su media sigue siendo suya: el panel debe poder mostrarla aunque el
        // contenido ya no esté vigente en el sitio.
        $service = $this->service();
        $media = $this->attach($service);
        $service->delete();

        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get($this->url($service, (string) $media->uuid))
            ->assertOk();
    }

    public function test_the_draft_image_has_no_public_url(): void
    {
        $media = $this->attach($this->service());

        $this->assertSame('frontend-private', $media->disk);
        $this->assertFalse(Storage::disk('public')->exists($media->getPathRelativeToRoot()));
    }

    // -------------------------------------------------------------- T3-6 ----

    public function test_two_live_services_cannot_share_an_image_by_direct_sql(): void
    {
        // Por SQL directo, salteando el servicio de dominio: es el camino que la
        // validación de la aplicación NO puede cubrir, y para el que existe el
        // índice. La v1 del diseño afirmaba que esto era imposible sin él.
        $primero = $this->service();
        $segundo = FrontendService::query()->where('id', '!=', $primero->getKey())->firstOrFail();
        $media = $this->attach($primero);

        DB::table('frontend_services')->where('id', $primero->getKey())
            ->update(['image_media_id' => (string) $media->uuid]);

        $this->expectException(QueryException::class);

        DB::table('frontend_services')->where('id', $segundo->getKey())
            ->update(['image_media_id' => (string) $media->uuid]);
    }

    public function test_a_soft_deleted_service_does_not_block_the_uuid(): void
    {
        // El índice es PARCIAL sobre filas vivas: dar de baja un servicio libera
        // la exclusividad, que es lo que permite reasignar sin bloquearse.
        $primero = $this->service();
        $segundo = FrontendService::query()->where('id', '!=', $primero->getKey())->firstOrFail();
        $media = $this->attach($primero);

        DB::table('frontend_services')->where('id', $primero->getKey())
            ->update(['image_media_id' => (string) $media->uuid]);
        $primero->delete();

        DB::table('frontend_services')->where('id', $segundo->getKey())
            ->update(['image_media_id' => (string) $media->uuid]);

        $this->assertSame((string) $media->uuid, $segundo->fresh()->image_media_id);
    }

    public function test_several_services_can_have_no_image_at_once(): void
    {
        // Un `unique()` de Blueprint sería TOTAL y chocaría con varios nulos.
        $this->assertGreaterThan(1, FrontendService::query()->whereNull('image_media_id')->count());
    }

    // -------------------------------------------------------------- T3-7 ----

    public function test_two_real_connections_racing_for_the_same_uuid_leave_one_winner(): void
    {
        // Los servicios los crea una MIGRACIÓN, así que están committeados y una
        // conexión externa los ve. La media, en cambio, hay que insertarla por la
        // misma conexión real: lo que escribe `RefreshDatabase` vive dentro de
        // una transacción sin commitear y sería invisible desde afuera.
        $primero = $this->service();
        $segundo = FrontendService::query()->where('id', '!=', $primero->getKey())->firstOrFail();
        $uuid = (string) Str::uuid();

        $a = $this->realConnection('pgsql_service_media_a');
        $b = $this->realConnection('pgsql_service_media_b');
        $exitos = 0;

        try {
            $a->table('media')->insert([
                'model_type' => (new FrontendService)->getMorphClass(),
                'model_id' => $primero->getKey(),
                'uuid' => $uuid,
                'collection_name' => ServiceMediaReference::COLLECTION,
                'name' => 'carrera',
                'file_name' => 'carrera.png',
                'mime_type' => 'image/png',
                'disk' => 'frontend-private',
                'conversions_disk' => 'frontend-private',
                'size' => 1024,
                'manipulations' => '[]',
                'custom_properties' => '[]',
                'generated_conversions' => '[]',
                'responsive_images' => '[]',
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ([[$a, $primero], [$b, $segundo]] as [$conexion, $servicio]) {
                try {
                    $conexion->table('frontend_services')
                        ->where('id', $servicio->getKey())
                        ->update(['image_media_id' => $uuid]);
                    $exitos++;
                } catch (\Throwable) {
                    // El índice rechazó al segundo: es el resultado esperado.
                }
            }

            $this->assertSame(1, $exitos, 'Dos conexiones lograron apuntar al mismo uuid.');
            $this->assertSame(1, (int) $a->table('frontend_services')
                ->where('image_media_id', $uuid)->count());
        } finally {
            // Autocommit: lo que escribieron sobrevive al test y hay que
            // deshacerlo a mano, o contamina las corridas siguientes.
            $this->releaseRealConnections([
                "UPDATE frontend_services SET image_media_id = NULL WHERE image_media_id = '{$uuid}'",
                "DELETE FROM media WHERE uuid = '{$uuid}'",
            ]);
        }
    }
}

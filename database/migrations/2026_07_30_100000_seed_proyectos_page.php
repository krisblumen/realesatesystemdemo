<?php

use App\Actions\Frontend\SeedFrontendPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `proyectos` pasa a ser la sexta página canónica del CMS (extensión de
 * RFC-075, cambio cms-pagina-proyectos — Work Unit 1: Fundación): registra la
 * página + sus tres secciones (`hero`, `projects_list`, `final_cta`) y
 * siembra sus payloads con el contenido que `site/proyectos.blade.php` YA
 * muestra. El cutover recién llega en el Work Unit 3; esta migración sola no
 * puede cambiar nada de lo que hoy se ve (§16.7).
 *
 * **Por qué `SeedFrontendPages::run()` de nuevo**, si ya corre en
 * `2026_07_24_100200_seed_frontend_canonical_pages.php`: esa migración ya
 * corrió en producción con el registro de CINCO páginas — `firstOrCreate` no
 * vuelve atrás a crear la sexta sola porque el config cambió después. Es
 * idempotente por diseño (D1 del design) precisamente para que esta migración
 * pueda invocarla de nuevo sin duplicar nada de lo que ya existe.
 *
 * **Por qué `projects_list` y `final_cta` SÍ llevan payload sembrado.** El
 * snapshot publicado carga TODA sección canónica, payload nulo incluido; un
 * `final_cta` en null dibujaría una tarjeta navy vacía en la primera
 * publicación en vez del cierre actual (`cta.blade.php` hace default a
 * `primary` sin título ni cuerpo). Precedente textual:
 * `2026_07_27_110000_replace_home_service_list_with_capability_cards.php`.
 * `projects_list` va SIN `background_color` para heredar el gradiente literal
 * de la variante `catalog` (design D7); `final_cta` sí lo lleva —`neutral-4`,
 * el vecino de paleta del gris oscuro que ya usa hoy.
 *
 * **Por qué el hero SÍ lleva payload pero SIN la clave `slides`.** Sin
 * sembrarlo, el editor abriría en blanco sobre una página con texto — el
 * mismo defecto que `2026_07_28_100100_seed_hero_drafts_from_fallback.php`
 * cerró para las otras cinco. Esa migración ya corrió y no alcanza a una
 * página que todavía no existía, así que el sembrado del hero corre acá.
 * A diferencia de aquella, `slides` se OMITE en vez de sembrarse vacía
 * (design D3): un payload publicado con `slides: []` le dice al render «el
 * owner publicó sin imagen» y apaga el fondo (§16.1.1); ausente, en cambio,
 * sigue leyéndose como «no inicializado» y `presentHero()` sigue aplicando el
 * fondo del fallback — el mismo mecanismo que ya usa `fallbackHeroPayload()`
 * para `slides`. Publicar el hero tal cual queda sembrado no debe apagar el
 * carrusel de fondo. Por la misma razón (ausencia = «no inicializado») la
 * clave `logo` tampoco se siembra: el owner no subió su logo propio todavía,
 * y `logo_enabled` sigue gobernando el logo de marca hasta que lo haga.
 *
 * **Trampa encontrada al escribir esta migración** (emparentada con el
 * hallazgo de las «tres trampas» de este mismo cambio, pero no una de ellas):
 * `2026_07_28_100100_...` lee `hero_fallback` de forma DINÁMICA — recorre
 * TODAS sus claves, no una lista fija de las cinco páginas para las que se
 * escribió. En una instalación incremental real, esa migración ya corrió
 * antes de que `proyectos` existiera en el config y nunca la toca. Pero en un
 * `migrate` desde cero (CI, `composer setup`, un dev nuevo) TODAS las
 * migraciones corren juntas contra el config YA actualizado: esa migración
 * antigua llega primero en el batch, encuentra `hero_fallback.proyectos` y
 * siembra el mismo contenido — con `slides: []`, matando el fondo §16.7 antes
 * de que ésta tenga oportunidad de sembrar sin esa clave. `seedHeroDraft()`
 * se defiende de las dos historias posibles: si el payload sigue en null,
 * siembra completo; si no, asume que sólo pudo haberlo escrito esa otra
 * migración —la página no existía hasta este mismo `up()`— y corrige
 * quitando únicamente `slides`, sin pisar el resto.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SeedFrontendPages::class)->run();

        $pageId = DB::table('frontend_pages')->where('key', 'proyectos')->value('id');

        if ($pageId === null) {
            return;
        }

        $this->seedHeroDraft($pageId);

        DB::table('frontend_sections')
            ->where('frontend_page_id', $pageId)
            ->where('section_key', 'projects_list')
            ->whereNull('payload')
            ->update([
                'payload' => json_encode([
                    'eyebrow' => 'Despacho de arquitectura · New Hauz',
                    'title' => 'A-74 lleva cada proyecto del concepto arquitectónico a la obra terminada.',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        DB::table('frontend_sections')
            ->where('frontend_page_id', $pageId)
            ->where('section_key', 'final_cta')
            ->whereNull('payload')
            ->update([
                'payload' => json_encode([
                    'title' => '¿Tienes un terreno o un proyecto en mente?',
                    'body' => 'Conversemos cómo convertirlo en realidad, del diseño a la entrega.',
                    'primary_cta' => ['label' => 'Agenda una cita', 'type' => 'route', 'target' => 'contacto'],
                    'background_color' => 'neutral-4',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    private function seedHeroDraft(int $pageId): void
    {
        $fallback = (array) config('frontend-sections.hero_fallback.proyectos');

        // Mismo allowlist que `2026_07_28_100100_seed_hero_drafts_from_fallback.php`.
        // `logo` y `slides` quedan afuera A PROPÓSITO: ninguno de los dos está en
        // esta lista, así que ninguno llega al payload sembrado (ver docblock).
        $seed = collect($fallback)
            ->only(['eyebrow', 'title', 'subtitle', 'logo_enabled', 'logo_size', 'text_align', 'primary_cta', 'secondary_cta'])
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();

        if (($seed['title'] ?? '') === '') {
            return;
        }

        // Lo que el schema exige sí o sí, con el mismo valor que usa el render.
        $seed['text_align'] ??= 'left';
        $seed['logo_enabled'] ??= false;
        $seed['logo_size'] ??= 'md';

        $current = DB::table('frontend_sections')
            ->where('frontend_page_id', $pageId)
            ->where('section_key', 'hero')
            ->value('payload');

        // NULL → instalación incremental real: ésta es la primera escritura del
        // borrador. Un valor no nulo sólo puede venir de
        // `2026_07_28_100100_seed_hero_drafts_from_fallback.php`, que lee
        // `hero_fallback` de forma DINÁMICA — en un `migrate` desde cero (CI,
        // instalación nueva) corre ANTES que ésta y ya alcanza a `proyectos`
        // ahora que tiene entrada en el config, sembrando el mismo contenido
        // pero CON `slides: []`. La página no existía hasta este mismo `up()`,
        // así que nada más pudo haberlo tocado entre medio: se corrige quitando
        // sólo `slides`, sin pisar el resto de lo que esa migración escribió.
        $payload = $current === null ? $seed : (array) json_decode((string) $current, true);
        unset($payload['slides']);

        DB::table('frontend_sections')
            ->where('frontend_page_id', $pageId)
            ->where('section_key', 'hero')
            ->update([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Sólo borra `proyectos` — las otras cinco páginas no las creó ni las
        // toca esta migración (§16.7). El rollback en caliente sin deploy sigue
        // siendo deshabilitar la página desde el panel (design, Migration/Rollout).
        $pageId = DB::table('frontend_pages')->where('key', 'proyectos')->value('id');

        if ($pageId === null) {
            return;
        }

        DB::table('frontend_sections')->where('frontend_page_id', $pageId)->delete();
        DB::table('frontend_pages')->where('id', $pageId)->delete();
    }
};

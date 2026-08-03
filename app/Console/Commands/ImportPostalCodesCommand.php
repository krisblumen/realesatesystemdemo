<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use App\Models\PostalCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportPostalCodesCommand extends Command
{
    /**
     * Source: open-mexico/mexico-geojson (GitHub, MIT), 32 GeoJSON files (one per state).
     * Confirmed by inspecting db_estados/Pais-Estado-Municipio/01-Ags.geojson: the postal
     * code feature property is `d_codigo`, delivered as a JSON number (not zero-padded).
     */
    private const CP_PROPERTY = 'd_codigo';

    private const BATCH_SIZE = 100;

    protected $signature = 'geo:import-postal-codes {--state= : Clave del estado para importar solo ese archivo} {--path= : Directorio con los GeoJSON por estado}';

    protected $description = 'Importa el catalogo de poligonos por codigo postal desde GeoJSON por estado.';

    public function handle(): int
    {
        $files = $this->resolveFiles();

        $totalUpserted = 0;
        $totalLinked = 0;

        foreach ($files as $file) {
            $fc = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

            if (($fc['type'] ?? null) !== 'FeatureCollection') {
                throw new RuntimeException("Archivo {$file} no es un FeatureCollection.");
            }

            // Group features by normalized CP: a postal code can appear in several
            // features (disjoint islands); these are accumulated into one MultiPolygon per CP.
            $geomByCp = [];

            foreach ($fc['features'] as $feature) {
                $cp = $this->normalizeCp($feature['properties'][self::CP_PROPERTY] ?? null);

                if ($cp === null) {
                    continue;
                }

                $geomByCp[$cp][] = $feature['geometry'];
            }

            foreach (array_chunk(array_keys($geomByCp), self::BATCH_SIZE) as $cpBatch) {
                foreach ($cpBatch as $cp) {
                    $upserted = $this->upsertPostalCodeArea($cp, $geomByCp[$cp]);

                    if ($upserted === null) {
                        $this->warn("CP {$cp}: geometria vacia, se omite.");

                        continue;
                    }

                    $totalUpserted++;

                    if ($upserted['linked']) {
                        $totalLinked++;
                    }
                }
            }
        }

        $this->info("postal_code_areas upserted={$totalUpserted}, linked_to_municipality={$totalLinked}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $geometries
     * @return array{linked: bool}|null
     */
    private function upsertPostalCodeArea(string $cp, array $geometries): ?array
    {
        $municipalityId = $this->resolveMunicipalityId($cp);
        $stateId = $this->resolveStateIdFromMunicipality($municipalityId);

        // Build a single SRID=4326 MultiPolygon from N geometries (Polygon or MultiPolygon).
        // Each input geometry is flattened to its individual Polygon components via
        // ST_Dump first: ST_Collect over a mix of Polygon/MultiPolygon rows otherwise
        // produces a GeometryCollection (not a flat MultiPolygon) when any input row is
        // already a MultiPolygon by itself.
        $multiEwkt = DB::selectOne(
            <<<'SQL'
            SELECT ST_AsEWKT(
                ST_Multi(
                    ST_SetSRID(
                        ST_Collect(parts.geom),
                        4326
                    )
                )
            ) AS ewkt
            FROM (
                SELECT (ST_Dump(ST_GeomFromGeoJSON(value::text))).geom AS geom
                FROM json_array_elements(?::json) AS value
            ) parts
            SQL,
            [json_encode(array_values($geometries), JSON_THROW_ON_ERROR)],
        )?->ewkt;

        if ($multiEwkt === null) {
            return null;
        }

        // `polygon` is NOT NULL, so it must be present on the initial INSERT, not added via
        // a follow-up UPDATE. Eloquent's upsert() value-list does not support raw SQL
        // expressions, so the whole upsert is done via a parameterized DB::statement.
        DB::statement(
            <<<'SQL'
            INSERT INTO postal_code_areas (postal_code, municipality_id, state_id, polygon, created_at, updated_at)
            VALUES (?, ?, ?, ST_GeomFromEWKT(?), NOW(), NOW())
            ON CONFLICT (postal_code) DO UPDATE SET
                municipality_id = EXCLUDED.municipality_id,
                state_id = EXCLUDED.state_id,
                polygon = EXCLUDED.polygon,
                updated_at = EXCLUDED.updated_at
            SQL,
            [$cp, $municipalityId, $stateId, $multiEwkt],
        );

        return ['linked' => $municipalityId !== null];
    }

    private function resolveMunicipalityId(string $cp): ?int
    {
        // Prefer the administrative postal_codes catalog (more reliable, sourced from
        // cp_queretaro.xlsx); fall back to the legacy best-effort `clave` match when there is
        // no postal_codes row for this CP. If neither matches, the FK stays null.
        $viaPostalCode = PostalCode::query()->where('postal_code', $cp)->value('municipality_id');

        if ($viaPostalCode !== null) {
            return $viaPostalCode;
        }

        return Municipality::query()->where('clave', $cp)->value('id');
    }

    private function resolveStateIdFromMunicipality(?int $municipalityId): ?int
    {
        if ($municipalityId === null) {
            return null;
        }

        return Municipality::query()->whereKey($municipalityId)->value('state_id');
    }

    /**
     * Casts to string, zero-pads to 5 digits, validates `^\d{5}$`. Returns null otherwise.
     *
     * The dataset delivers `d_codigo` as a JSON number, so postal codes starting with
     * zero (e.g. CDMX/Edomex '0XXXX') arrive without the leading zero and MUST be padded.
     */
    private function normalizeCp(mixed $rawCp): ?string
    {
        if ($rawCp === null) {
            return null;
        }

        $cp = str_pad((string) $rawCp, 5, '0', STR_PAD_LEFT);

        return preg_match('/^\d{5}$/', $cp) === 1 ? $cp : null;
    }

    /**
     * @return list<string>
     */
    private function resolveFiles(): array
    {
        $path = $this->resolvePath();
        $state = $this->option('state');

        $allFiles = array_values(array_filter(
            glob($path.DIRECTORY_SEPARATOR.'*.geojson') ?: [],
            fn (string $file): bool => is_file($file),
        ));

        if (empty($allFiles)) {
            throw new RuntimeException("No se encontraron archivos .geojson en: {$path}");
        }

        sort($allFiles);

        if ($state === null) {
            return $allFiles;
        }

        $matching = array_values(array_filter(
            $allFiles,
            fn (string $file): bool => str_contains(basename($file), (string) $state),
        ));

        if (empty($matching)) {
            throw new RuntimeException("No se encontro un archivo .geojson para --state={$state} en: {$path}");
        }

        return $matching;
    }

    private function resolvePath(): string
    {
        $path = $this->option('path');

        if ($path !== null && is_dir((string) $path)) {
            return (string) $path;
        }

        $default = base_path('db_estados/Pais-Estado-Municipio');

        if (is_dir($default)) {
            return $default;
        }

        throw new RuntimeException('No existe la ruta de GeoJSON: '.($path !== null ? (string) $path : $default));
    }
}

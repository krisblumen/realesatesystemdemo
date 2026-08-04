<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\PostalCode;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
 * Source: db_estados/cp_queretaro.xlsx, sheet "Querétaro", headers
 * `postal_code, colonia, state_id, imunicipality_id` in row 1 (note the literal column name
 * typo `imunicipality_id`), 3,272 data rows, all state_id='22'. Requires MunicipalitySeeder to
 * have run first (resolves municipality_id/state_id via municipalities.inegi_code scoped to
 * the state whose inegi_code = '22').
 */
class PostalCodeSeeder extends Seeder
{
    public function run(): void
    {
        $municipalitiesByInegi = Municipality::query()
            ->whereHas('state', fn ($query) => $query->where('inegi_code', '22'))
            ->get(['id', 'inegi_code', 'state_id'])
            ->keyBy('inegi_code');

        foreach ($this->readRows() as [$postalCode, $colonia, , $muniInegi]) {
            if (! preg_match('/^\d{1,5}$/', (string) $postalCode)) {
                continue;
            }

            $postalCode = str_pad((string) $postalCode, 5, '0', STR_PAD_LEFT);
            $muniInegi = str_pad((string) $muniInegi, 3, '0', STR_PAD_LEFT);
            $municipality = $municipalitiesByInegi->get($muniInegi);

            PostalCode::query()->updateOrCreate(
                ['postal_code' => $postalCode, 'colonia' => trim((string) $colonia)],
                [
                    'municipality_id' => $municipality?->id,
                    'state_id' => $municipality?->state_id,
                ],
            );
        }
    }

    /**
     * @return list<array{0: mixed, 1: mixed, 2: mixed, 3: mixed}>
     */
    private function readRows(): array
    {
        $spreadsheet = IOFactory::load(base_path('db_estados/cp_queretaro.xlsx'));
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        array_shift($rows); // header row: ['postal_code', 'colonia', 'state_id', 'imunicipality_id']

        return $rows;
    }
}

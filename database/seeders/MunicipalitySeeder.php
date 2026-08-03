<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\State;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
 * Source: db_estados/municipalities.xlsx, sheet "Hoja1", headers
 * `state_id, municipality_id, MUNICIPIO` in row 1, 2,478 well-formed data rows. Requires
 * StateSeeder to have run first (resolves state_id via states.inegi_code).
 */
class MunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        $stateIdsByInegiCode = State::query()->pluck('id', 'inegi_code');

        foreach ($this->readRows() as [$stateInegi, $muniInegi, $name]) {
            if (! preg_match('/^\d{1,2}$/', (string) $stateInegi)) {
                continue;
            }

            $stateInegi = str_pad((string) $stateInegi, 2, '0', STR_PAD_LEFT);
            $stateId = $stateIdsByInegiCode->get($stateInegi);

            if ($stateId === null) {
                continue; // state not seeded; should not happen if StateSeeder ran first.
            }

            $muniInegi = str_pad((string) $muniInegi, 3, '0', STR_PAD_LEFT);
            $normalizedName = mb_convert_case(mb_strtolower((string) $name), MB_CASE_TITLE, 'UTF-8');

            // Match key is (state_id, name), NOT inegi_code: `municipalities` already enforces
            // unique(state_id, name). See StateSeeder for the same reasoning.
            Municipality::query()->updateOrCreate(
                ['state_id' => $stateId, 'name' => $normalizedName],
                ['inegi_code' => $muniInegi],
            );
        }
    }

    /**
     * @return list<array{0: mixed, 1: mixed, 2: mixed}>
     */
    private function readRows(): array
    {
        $spreadsheet = IOFactory::load(base_path('db_estados/municipalities.xlsx'));
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        array_shift($rows); // header row: ['state_id', 'municipality_id', 'MUNICIPIO']

        return $rows;
    }
}

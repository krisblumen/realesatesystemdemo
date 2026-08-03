<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
 * Source: db_estados/states.xlsx, sheet "catálogo entidades", headers `state_id, state` in row 1,
 * 32 data rows (rows 2-33). `state_id` values are zero-padded INEGI strings '01'..'32'. Row 35
 * has a stray footnote row with only 1 populated cell — skipped via the state_id regex guard.
 */
class StateSeeder extends Seeder
{
    public function run(): void
    {
        $mexico = Country::query()->where('iso2', 'MX')->firstOrFail();

        foreach ($this->readRows() as [$inegiCode, $name]) {
            if (! preg_match('/^\d{1,2}$/', (string) $inegiCode)) {
                continue;
            }

            $inegiCode = str_pad((string) $inegiCode, 2, '0', STR_PAD_LEFT);

            // Match key is (country_id, name), NOT inegi_code: `states` already enforces
            // unique(country_id, name). Matching by inegi_code would INSERT a duplicate row
            // for any pre-existing name-only state row (e.g. from the retired geo:import path),
            // violating that constraint instead of backfilling inegi_code in place.
            State::query()->updateOrCreate(
                ['country_id' => $mexico->id, 'name' => trim((string) $name)],
                ['inegi_code' => $inegiCode],
            );
        }
    }

    /**
     * @return list<array{0: mixed, 1: mixed}>
     */
    private function readRows(): array
    {
        $spreadsheet = IOFactory::load(base_path('db_estados/states.xlsx'));
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        array_shift($rows); // header row: ['state_id', 'state']

        return $rows;
    }
}

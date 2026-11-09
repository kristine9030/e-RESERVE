<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the registrar's masterlist (BSU-ARASOF, 1st semester A.Y. 2026-2027).
 *
 * 9,373 students from CABEIHM, CAS, CCJE, CHS, CICS and CTE, extracted from the
 * scanned PDFs; the per-college totals match the registrar's SUMMARY sheet.
 * The Laboratory School and Professional Subjects lists are deliberately excluded.
 */
class EnrolledStudentSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('data/enrolled_students.sql');

        if (!is_file($file)) {
            $this->command?->warn('database/data/enrolled_students.sql not found — masterlist NOT loaded.');
            return;
        }

        DB::table('enrolled_students')->truncate();

        // One INSERT (500 rows) at a time — the whole file would exceed MySQL's max_allowed_packet.
        foreach (preg_split('/;\s*\n/', (string) file_get_contents($file)) ?: [] as $statement) {
            $statement = trim(preg_replace('/^--.*$/m', '', $statement));
            if ($statement !== '' && stripos($statement, 'INSERT') === 0) {
                DB::unprepared($statement);
            }
        }

        $this->command?->info('Masterlist loaded: ' . number_format(DB::table('enrolled_students')->count()) . ' students.');
    }
}

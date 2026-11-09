<?php

namespace Database\Seeders;

use App\Models\SscMember;
use Illuminate\Database\Seeder;

/** Loads the SSC roster. Safe to run again: it never touches emails or accounts already set. */
class SscRosterSeeder extends Seeder
{
    private const EXECUTIVES = [
        'President', 'Vice President for Internal Affairs', 'Vice President for External Affairs',
        'Secretary General', 'Deputy Secretary General', 'Finance Officer', 'Deputy Finance Officer',
        'Auditor', 'Public Relations Officer', 'Business Manager',
    ];

    public function run(): void
    {
        foreach ((array) require database_path('data/ssc_roster.php') as $i => $line) {
            [$position, $fullName] = array_map('trim', explode('|', $line, 2));

            $group = match (true) {
                $position === 'Adviser'                    => 'adviser',
                $position === 'Consultant'                 => 'consultant',
                in_array($position, self::EXECUTIVES, true) => 'executive',
                str_ends_with($position, 'Governor')       => 'governor',
                default                                    => 'committee',
            };

            SscMember::updateOrCreate(
                ['position' => $position, 'full_name' => $fullName],
                [
                    'org_group'    => $group,
                    'committee'    => $group === 'committee' ? explode(' ', $position)[0] : null,
                    'display_name' => SscMember::displayName($fullName, $group),
                    'sort'         => $i + 1,
                ]
            );
        }
    }
}

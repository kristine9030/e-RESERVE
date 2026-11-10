<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * How the event's tickets are split between the three groups, and who may change the split.
 *
 * Each group has its own allotment (default 2,500 students / 200 faculty & staff / 300 sponsors).
 * A seat a group does not use stays HELD for that group. It is never handed to another group
 * on its own: SSC moves seats from one group to another in Site Settings.
 */
class TicketQuota
{
    public const GROUPS = [
        'student'  => 'Students',
        'employee' => 'Faculty, instructors & staff',
        'sponsor'  => 'Sponsors',
    ];

    /** Which group a users row belongs to (u = the users alias). */
    public const GROUP_SQL = "CASE WHEN u.member_type = 'sponsor' THEN 'sponsor' WHEN u.member_type IS NULL OR u.member_type = 'student' THEN 'student' ELSE 'employee' END";

    private static function key(string $group): string
    {
        return 'quota_' . $group;
    }

    public static function limit(string $group): int
    {
        return max(0, (int) Setting::read(self::key($group), (string) config('ereserve.quotas.' . $group, 0)));
    }

    /** @return array<string,int> */
    public static function limits(): array
    {
        $out = [];
        foreach (array_keys(self::GROUPS) as $g) {
            $out[$g] = self::limit($g);
        }

        return $out;
    }

    /** Seats held by tickets that are not void, per group. @return array<string,int> */
    public static function taken(): array
    {
        $out = array_fill_keys(array_keys(self::GROUPS), 0);
        foreach (DB::table('tickets as t')->join('users as u', 'u.id', '=', 't.user_id')->where('t.status', '<>', 'void')
            ->selectRaw(self::GROUP_SQL . ' as g, COUNT(*) as c')->groupBy('g')->get() as $r) {
            $out[$r->g] = (int) $r->c;
        }

        return $out;
    }

    public static function takenFor(string $group): int
    {
        return self::taken()[$group] ?? 0;
    }

    /** The venue's normal seat count (config). */
    public static function baseCapacity(): int
    {
        return (int) config('ereserve.event.capacity');
    }

    /** Seats actually on offer: the venue count, or more if SSC deliberately went past it. */
    public static function capacity(): int
    {
        return max(self::baseCapacity(), (int) Setting::read('capacity_override', '0'));
    }

    /** @return ?string an error message, or null when the split is allowed */
    public static function validate(array $limits): ?string
    {
        $taken = self::taken();
        foreach (self::GROUPS as $g => $label) {
            if ($limits[$g] < 0) {
                return 'Seats cannot be negative.';
            }
            if ($limits[$g] < ($taken[$g] ?? 0)) {
                return $label . ' already hold ' . ($taken[$g] ?? 0) . ' tickets, so their allotment cannot go below that.';
            }
        }
        return null;
    }

    public static function save(array $limits): void
    {
        foreach (self::GROUPS as $g => $_) {
            Setting::store(self::key($g), (string) (int) $limits[$g]);
        }
        // past the venue count only by SSC's confirmed choice; back within it, the extra allowance is dropped
        $total = array_sum($limits);
        Setting::store('capacity_override', (string) ($total > self::baseCapacity() ? $total : 0));
    }
}

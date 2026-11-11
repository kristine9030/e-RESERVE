<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * The soft-launch gate. The landing page is public from day one, but student
 * sign-in and account creation stay shut until the SSC opens them — while the
 * SSC keeps testing everything through their own door at /ssc.
 *
 * Modes, kept in the student_access setting:
 *   auto   — follow the schedule: open once the opening moment passes (default)
 *   open   — open right now, whatever the schedule says
 *   closed — shut until the SSC changes this, whatever the schedule says
 */
class StudentAccess
{
    public static function mode(): string
    {
        $mode = Setting::read('student_access');

        return in_array($mode, ['auto', 'open', 'closed'], true) ? $mode : 'auto';
    }

    /** True once students may sign in and create accounts. */
    public static function open(): bool
    {
        return match (self::mode()) {
            'open'   => true,
            'closed' => false,
            default  => ! self::opensAt()->isFuture(),   // inclusive: the doors open at the scheduled second itself
        };
    }

    public static function opensAt(): Carbon
    {
        $stored = Setting::read('student_access_opens_at');
        $value  = $stored !== '' ? $stored : (string) config('ereserve.event.registration_opens_at');

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return Carbon::parse(config('ereserve.event.registration_opens_at'));   // a bad stored date never locks people out
        }
    }

    /** The opening moment as text, e.g. "Nov 15, 2026 · 8:00 AM" — for buttons and notices. */
    public static function opensAtText(string $format = 'M j, Y · g:i A'): string
    {
        return self::opensAt()->format($format);
    }
}

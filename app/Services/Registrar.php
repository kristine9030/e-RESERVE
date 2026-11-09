<?php

namespace App\Services;

use App\Models\EnrolledStudent;

/**
 * Colleges, programmes and the masterlist check.
 *
 * An SR-code may only open an account when it is on the registrar's list under
 * the college the student chose.
 */
class Registrar
{
    /** @return array<string,string> code => full name */
    public function colleges(): array
    {
        return config('ereserve.colleges');
    }

    /** @return array<string,list<string>> */
    public function programs(): array
    {
        return config('ereserve.programs');
    }

    public function programInCollege(string $program, string $college): bool
    {
        return in_array($program, $this->programs()[$college] ?? [], true);
    }

    /** SR-code shape: 22-10234, or the older N14-02607. */
    public function validCode(string $code): bool
    {
        return (bool) preg_match('/^N?\d{2}-\d{5}$/', $code);
    }

    /** @return string empty when fine, otherwise the message to show */
    public function enrollmentProblem(string $srCode, string $college): string
    {
        $row = EnrolledStudent::find($srCode);

        if ($row === null) {
            return 'That SR-code is not on the ARASOF-Nasugbu masterlist for A.Y. 2026-2027. Check for typos, or contact the SSC.';
        }
        if ($row->college !== $college) {
            return 'That SR-code is enrolled under a different college. Please choose your correct college.';
        }

        return '';
    }
}

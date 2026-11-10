<?php

namespace App\Support;

/**
 * Which documents a person uploads: students three, faculty/staff their employee ID,
 * sponsors their proof of sponsorship. One place decides, so every screen agrees.
 */
final class DocumentSets
{
    /** 'student' | 'sponsor' | 'employee' for a users.member_type value. */
    public static function setName(?string $memberType): string
    {
        $memberType = $memberType ?: 'student';

        return match (true) {
            $memberType === 'student' => 'student',
            $memberType === 'sponsor' => 'sponsor',
            default                   => 'employee',
        };
    }

    /** @return list<string> document keys, in upload order */
    public static function keysFor(?string $memberType): array
    {
        return array_values((array) config('ereserve.document_sets.' . self::setName($memberType), []));
    }

    /** @return array<string,array> key => config meta (label, short, icon, hint, rules) */
    public static function metaFor(?string $memberType): array
    {
        $all = (array) config('ereserve.documents');

        return array_intersect_key($all, array_flip(self::keysFor($memberType)));
    }

    public static function countFor(?string $memberType): int
    {
        return count(self::keysFor($memberType));
    }

    /** SQL fragment: how many documents the user in this row must upload (for COUNT comparisons). */
    public static function sqlRequired(string $memberTypeColumn = 'u.member_type'): string
    {
        $student = self::countFor('student');
        $sponsor = self::countFor('sponsor');
        $other   = self::countFor('faculty');

        return "(CASE WHEN COALESCE($memberTypeColumn, 'student') = 'student' THEN $student WHEN $memberTypeColumn = 'sponsor' THEN $sponsor ELSE $other END)";
    }
}

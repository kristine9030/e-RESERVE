<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SscMember extends Model
{
    protected $guarded = ['id'];

    /** Group labels in the order the SSC Team page shows them. */
    public const GROUPS = [
        'adviser'   => 'Adviser',
        'consultant' => 'Consultant',
        'executive' => 'Executive Officers',
        'governor'  => 'College Governors',
        'committee' => 'Committees',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Villacrusis, JC Zylene Castillo" -> "JC Zylene Castillo Villacrusis". */
    public static function displayName(string $fullName, string $group): string
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));

        // The adviser and consultant are written "Given Surname" (with titles): keep as is.
        if (in_array($group, ['adviser', 'consultant'], true) || !str_contains($fullName, ',')) {
            return $fullName;
        }

        $parts   = array_values(array_filter(array_map('trim', explode(',', $fullName)), 'strlen'));
        $surname = array_shift($parts);

        return trim(implode(' ', $parts) . ' ' . $surname);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequest extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    public const FIELDS = ['student_no' => 'SR-code', 'email' => 'GSuite email'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        if ($this->field === 'student_no' && $this->relationLoaded('user') && $this->user?->isEmployee()) {
            return 'Employee ID';
        }

        return self::FIELDS[$this->field] ?? $this->field;
    }
}

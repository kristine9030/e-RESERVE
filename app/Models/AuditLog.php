<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only trail of reviews, ticket changes and gate scans. */
class AuditLog extends Model
{
    protected $table = 'audit_log';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }
}

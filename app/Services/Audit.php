<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Append-only trail of anything that touched a ticket or a document. */
class Audit
{
    public static function log(string $action, string $entityType, ?string $entityId = null, array $context = []): void
    {
        $user = Auth::user();

        try {
            AuditLog::create([
                'actor_id'    => $user?->id,
                'actor_name'  => $user?->name ?? 'system',
                'actor_role'  => $user?->role ?? 'system',
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'context'     => $context ?: null,
                'ip_address'  => substr((string) request()->ip(), 0, 45),
                'created_at'  => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[e-RESERVE] audit failed: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Support;

/** Turns an audit_log row into a sentence a person can read. */
final class ActivityText
{
    private const DOC = ['waiver' => 'waiver', 'cor' => 'COR', 'id' => 'school ID', 'employee_id' => 'employee ID', 'sponsorship' => 'proof of sponsorship'];

    public static function describe(string $action, ?string $entity, ?string $entityId, ?array $c): string
    {
        $c ??= [];
        return match (true) {
            $action === 'login'                 => 'Logged in.',
            $action === 'login.google'          => 'Logged in with Google.',
            $action === 'login.failed'          => 'Failed sign-in for ' . ($c['email'] ?? 'an unknown email') . '.',
            $action === 'logout'                => 'Logged out.',
            $action === 'user.register'         => 'Created an account' . (isset($c['type']) ? ' (' . $c['type'] . ')' : '') . '.',
            $action === 'user.register.google'  => 'Created an account with Google.',
            $action === 'email.verified'        => 'Confirmed their email.',
            $action === 'email.verification_sent' => 'A confirmation email was sent.',
            $action === 'password.change'       => 'Changed their password.',
            $action === 'password.reset'        => 'Reset their password by email.',
            $action === 'profile.update'        => 'Updated their profile' . (!empty($c['changed']) ? ' (' . implode(', ', (array) $c['changed']) . ')' : '') . '.',
            $action === 'document.upload'       => 'Uploaded ' . ($c['file'] ?? 'a document') . '.',
            $action === 'document.view'         => 'Opened a ' . (self::DOC[$c['type'] ?? ''] ?? 'document') . '.',
            $action === 'document.approve'      => 'Approved document #' . $entityId . '.',
            $action === 'document.reject'       => 'Returned document #' . $entityId . (!empty($c['note']) ? ': ' . $c['note'] : '') . '.',
            $action === 'ticket.issue'          => 'Ticket ' . $entityId . ' was issued.',
            $action === 'ticket.restore'        => 'Ticket ' . $entityId . ' was restored.',
            $action === 'ticket.void'           => 'Ticket ' . $entityId . ' was voided' . (!empty($c['reason']) ? ' (' . $c['reason'] . ')' : '') . '.',
            $action === 'gate.checkin'          => 'Scanned ticket ' . $entityId . ' at the gate' . (!empty($c['gate']) ? ' (' . $c['gate'] . ')' : '') . '.',
            $action === 'records.export'        => 'Exported ' . ($c['group'] ?? 'records') . ' as ' . $entityId . '.',
            $action === 'user.disable'          => 'Turned off the account of ' . ($c['student'] ?? 'a user') . '.',
            $action === 'user.enable'           => 'Turned on the account of ' . ($c['student'] ?? 'a user') . '.',
            str_starts_with($action, 'staff.')  => 'Staff account ' . substr($action, 6) . ' for ' . ($c['name'] ?? 'a member') . '.',
            $action === 'sponsor.add'           => 'Added sponsor ' . ($c['name'] ?? '') . '.',
            $action === 'sponsor.remove'        => 'Removed sponsor ' . ($c['name'] ?? '') . '.',
            $action === 'sponsor.toggle'        => 'Sponsor visibility changed.',
            $action === 'sponsor.inquiry'       => 'Sponsorship enquiry from ' . $entityId . '.',
            str_starts_with($action, 'sponsor.inquiry.') => 'Marked an enquiry ' . substr($action, 16) . '.',
            str_starts_with($action, 'setting.') => 'Turned ' . (($c['on'] ?? false) ? 'on' : 'off') . ' “' . substr($action, 8) . '” on the landing page.',
            $action === 'quota.set'             => 'Set the tickets per group: ' . self::pairs($c) . '.',
            $action === 'quota.transfer'        => 'Moved ' . ($c['count'] ?? '?') . ' seats from ' . ($c['from'] ?? '?') . ' to ' . ($c['to'] ?? '?') . '.',
            $action === 'quota.exceed'          => 'Confirmed offering ' . ($c['total'] ?? '?') . ' tickets, above the venue count.',
            $action === 'quota.exceed.refused'  => 'Tried to go above the venue count but did not confirm.',
            str_starts_with($action, 'superadmin.') => self::superadmin($action, $entityId, $c),
            default                             => self::fallback($action, $entity, $entityId, $c),
        };
    }

    private static function superadmin(string $action, ?string $id, array $c): string
    {
        return match ($action) {
            'superadmin.user.create'  => 'Created ' . ($c['role'] ?? 'an') . ' account for ' . ($c['email'] ?? '#' . $id) . '.',
            'superadmin.user.reset'   => 'Reset the password of ' . ($c['email'] ?? '#' . $id) . '.',
            'superadmin.user.disable' => 'Deactivated ' . ($c['email'] ?? '#' . $id) . '.',
            'superadmin.user.enable'  => 'Reactivated ' . ($c['email'] ?? '#' . $id) . '.',
            'superadmin.export'       => 'Exported the activity log (' . ($c['rows'] ?? '?') . ' rows).',
            default                   => self::fallback($action, null, $id, $c),
        };
    }

    private static function pairs(array $c): string
    {
        return implode(', ', array_map(fn ($k, $v) => $k . ' ' . $v, array_keys($c), $c));
    }

    private static function fallback(string $action, ?string $entity, ?string $id, array $c): string
    {
        $text = ucfirst(str_replace(['.', '_'], ' ', $action));
        if ($id !== null && $id !== '') {
            $text .= ' · ' . ($entity ? $entity . ' ' : '') . $id;
        }

        return $text . '.';
    }
}

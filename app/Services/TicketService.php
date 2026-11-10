<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use App\Support\DocumentSets;
use Illuminate\Support\Facades\DB;

/**
 * Documents → progress → ticket.
 *
 *   account → 3 PDFs uploaded → ticket number issued → QR scanned at the gate
 *
 * A document is `submitted` on upload, then `approved` or `rejected` by SSC
 * staff. A ticket is issued only when ALL THREE are approved, and only while
 * seats remain (config ereserve.event.capacity). Rejecting any one voids the
 * ticket and frees its seat; the next fully-approved student takes it.
 */
class TicketService
{
    /** Per-request cache; every write calls forget(). */
    private array $cache = [];

    public function forget(): void
    {
        $this->cache = [];
    }

    /** Run a query and return plain arrays. */
    public static function rows(string $sql, array $bindings = []): array
    {
        return array_map(static fn($r) => (array) $r, DB::select($sql, $bindings));
    }

    public static function one(string $sql, array $bindings = []): ?array
    {
        return self::rows($sql, $bindings)[0] ?? null;
    }

    /* ----------------------------------------------------------------- docs */

    /** @return array<string,array> keyed by document type */
    public function documents(int $userId): array
    {
        if (isset($this->cache['docs'][$userId])) {
            return $this->cache['docs'][$userId];
        }

        $out = [];
        foreach (DB::table('documents')->where('user_id', $userId)->get() as $row) {
            $out[$row->doc_type] = [
                'id'          => (int) $row->id,
                'type'        => $row->doc_type,
                'name'        => $row->original_name,
                'size'        => (int) $row->size_bytes,
                'status'      => $row->status,
                'note'        => $row->review_note,
                'uploaded_at' => $row->uploaded_at,
                'reviewed_at' => $row->reviewed_at,
            ];
        }

        return $this->cache['docs'][$userId] = $out;
    }

    /** Faculty, instructor, staff or sponsor (anyone who is not a student). */
    public function isEmployee(int $userId): bool
    {
        return $this->cache['emp'][$userId] ??= DB::table('users')->where('id', $userId)->where('role', 'student')->where('member_type', '<>', 'student')->exists();
    }

    /** @return array{total:int,uploaded:int,approved:int,rejected:int,pending:int,missing:array,complete:bool,all_approved:bool,blocked:bool} */
    public function progress(int $userId): array
    {
        $types    = DocumentSets::keysFor(DB::table('users')->where('id', $userId)->value('member_type'));
        $docs     = $this->documents($userId);
        $docs     = array_intersect_key($docs, array_flip($types));
        $missing  = array_values(array_diff($types, array_keys($docs)));
        $statuses = array_column($docs, 'status');
        $rejected = count(array_keys($statuses, 'rejected', true));

        return [
            'total'    => count($types),
            'uploaded' => count($docs),
            'approved' => count(array_keys($statuses, 'approved', true)),
            'rejected' => $rejected,
            'pending'  => count(array_keys($statuses, 'submitted', true)),
            'missing'  => $missing,
            'complete' => $missing === [] && $rejected === 0,          // everything uploaded, nothing returned
            'all_approved' => $missing === [] && count(array_keys($statuses, 'approved', true)) === count($types),
            'blocked'  => $rejected > 0,   // a returned file stops the ticket until replaced
        ];
    }

    /* --------------------------------------------------------------- ticket */

    public function ticketFor(int $userId): ?array
    {
        if (array_key_exists($userId, $this->cache['ticket'] ?? [])) {
            return $this->cache['ticket'][$userId];
        }

        $row = self::one(
            'SELECT t.*, u.name, u.email, u.student_no, u.member_type, u.program, u.year_level, c.scanned_at, c.gate
             FROM tickets t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN checkins c ON c.ticket_id = t.id
             WHERE t.user_id = ? LIMIT 1',
            [$userId]
        );

        return $this->cache['ticket'][$userId] = $row === null ? null : $this->shape($row);
    }

    public function shape(array $row): array
    {
        $ev = config('ereserve.event');

        return [
            'id'          => (int) $row['id'],
            'ticket_no'   => $row['ticket_no'],
            'name'        => $row['name'] ?? '',
            'student_no'  => $row['student_no'] ?? '',
            'member_type' => $row['member_type'] ?? 'student',
            'program'     => $row['program'] ?? '',
            'year'        => $row['year_level'] ?? '',
            'email'       => $row['email'] ?? '',
            'event'       => $ev['name'],
            'venue'       => $ev['venue'],
            'date_text'   => $ev['date_text'],
            'time_text'   => $ev['time_text'],
            'status'      => $row['status'],
            'verify_hash' => $row['verify_hash'],
            'issued_at'   => $row['issued_at'],
            'checked_in'  => !empty($row['scanned_at']),
            'checked_at'  => $row['scanned_at'] ?? null,
            'gate'        => $row['gate'] ?? null,
        ];
    }

    public function slotsRemaining(): int
    {
        $taken = (int) DB::table('tickets')->where('status', '<>', 'void')->count();

        return max(0, TicketQuota::capacity() - $taken);
    }

    /** HMAC baked into the QR payload, keyed by the app key. */
    public function verifyHash(string $ticketNo, int $userId): string
    {
        return hash_hmac('sha256', $ticketNo . '|' . $userId, (string) config('app.key'));
    }

    /** What the gate scanner reads off a ticket: number plus a short signature. */
    public function qrPayload(array $ticket): string
    {
        $no  = (string) ($ticket['ticket_no'] ?? '');
        $sig = substr((string) ($ticket['verify_hash'] ?? ''), 0, 12);

        return $sig === '' ? $no : $no . '|' . $sig;
    }

    /** Resolve a scanned payload to a ticket, rejecting a wrong signature. */
    public function fromPayload(string $payload): ?array
    {
        $parts    = explode('|', trim($payload), 2);
        $ticketNo = $parts[0];

        $row = self::one(
            'SELECT t.*, u.name, u.email, u.student_no, u.member_type, u.program, u.year_level, c.scanned_at, c.gate
             FROM tickets t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN checkins c ON c.ticket_id = t.id
             WHERE t.ticket_no = ? LIMIT 1',
            [$ticketNo]
        );

        if ($row === null) {
            return null;
        }

        $ticket = $this->shape($row);

        if (count($parts) === 2 && !hash_equals(substr($ticket['verify_hash'], 0, 12), $parts[1])) {
            return null;
        }

        return $ticket;
    }

    /** Next number in a named sequence, safe under concurrent requests. */
    public function nextSequence(string $key): int
    {
        return DB::transaction(function () use ($key) {
            $row = DB::table('sequences')->where('seq_key', $key)->lockForUpdate()->first();

            if ($row === null) {
                DB::table('sequences')->insert(['seq_key' => $key, 'current_value' => 1]);
                return 1;
            }

            $next = (int) $row->current_value + 1;
            DB::table('sequences')->where('seq_key', $key)->update(['current_value' => $next]);

            return $next;
        });
    }

    /** Seats currently held by tickets that are not void. */
    private function seatsTaken(): int
    {
        return (int) DB::table('tickets')->where('status', '<>', 'void')->count();
    }

    /**
     * Bring a student's ticket in line with their documents.
     *
     *   all three APPROVED, seat free → issue a ticket, or re-validate a void one
     *   all three approved, no seat   → `full` (they keep their place in the queue)
     *   anything not approved         → void a valid ticket (a used one stays used)
     *
     * Seat counting happens while holding a lock on the `ticket` sequence row,
     * so concurrent approvals can never push the total past the capacity.
     *
     * @return array{state:string, ticket_no:?string}  none|issued|restored|voided|full|unchanged
     */
    public function sync(int $userId): array
    {
        $this->forget();
        $progress = $this->progress($userId);
        $existing = DB::table('tickets')->where('user_id', $userId)->first();
        $u        = DB::table('users')->where('id', $userId)->first(['status', 'email_verified_at']);
        $active   = ($u->status ?? '') === 'active';
        $eligible = $progress['all_approved'] && $active;

        if (!$eligible) {
            if ($existing && $existing->status === 'valid') {
                $voided = DB::table('tickets')->where('id', $existing->id)->where('status', 'valid')
                    ->update(['status' => 'void', 'voided_at' => now()]);

                if ($voided) {
                    Audit::log('ticket.void', 'ticket', $existing->ticket_no, [
                        'reason' => $active ? 'document not approved' : 'account disabled',
                    ]);
                    $this->forget();
                    $this->promoteWaiting();   // the freed seat goes to the next approved student

                    return ['state' => 'voided', 'ticket_no' => $existing->ticket_no];
                }
            }

            return ['state' => $progress['missing'] === [] && !$progress['blocked'] ? 'none' : 'unchanged',
                    'ticket_no' => $existing->ticket_no ?? null];
        }

        $capacity = TicketQuota::capacity();

        // Make sure the counter row exists, outside any transaction: doing this inside
        // it deadlocks against the FOR UPDATE below when many requests arrive together.
        DB::table('sequences')->insertOrIgnore(['seq_key' => 'ticket', 'current_value' => 0]);

        try {
            $group = DocumentSets::setName(DB::table('users')->where('id', $userId)->value('member_type'));

            $outcome = DB::transaction(function () use ($userId, $capacity, $group) {
                // One lock serialises every issue/restore, so the count below is exact.
                $seq = DB::table('sequences')->where('seq_key', 'ticket')->lockForUpdate()->first();

                $row = DB::table('tickets')->where('user_id', $userId)->first();

                if ($row && $row->status !== 'void') {
                    return ['unchanged', $row->ticket_no];
                }
                // full overall, or this group has used up the seats SSC allotted to it (unused seats of other groups stay held)
                if ($this->seatsTaken() >= $capacity || TicketQuota::takenFor($group) >= TicketQuota::limit($group)) {
                    return ['full', $row->ticket_no ?? null];
                }
                if ($row) {
                    DB::table('tickets')->where('id', $row->id)->update(['status' => 'valid', 'voided_at' => null]);
                    return ['restored', $row->ticket_no];
                }

                $next = (int) $seq->current_value + 1;
                DB::table('sequences')->where('seq_key', 'ticket')->update(['current_value' => $next]);

                $no = config('ereserve.ticket.prefix') . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
                DB::table('tickets')->insert([
                    'ticket_no'   => $no,
                    'user_id'     => $userId,
                    'verify_hash' => $this->verifyHash($no, $userId),
                    'status'      => 'valid',
                    'issued_at'   => now(),
                ]);

                return ['issued', $no];
            }, 5);   // retry a few times if MySQL picks this transaction as a deadlock victim
        } catch (UniqueConstraintViolationException) {
            // Belt and braces: the unique index on user_id still lets exactly one through.
            $this->forget();
            return ['state' => 'unchanged', 'ticket_no' => DB::table('tickets')->where('user_id', $userId)->value('ticket_no')];
        }

        [$state, $ticketNo] = $outcome;
        $this->forget();

        if ($state === 'issued') {
            Audit::log('ticket.issue', 'ticket', $ticketNo, ['user_id' => $userId]);
            Notifier::ticketReady($userId, (string) $ticketNo);
        } elseif ($state === 'restored') {
            Audit::log('ticket.restore', 'ticket', $ticketNo);
            Notifier::ticketReady($userId, (string) $ticketNo, true);
        }

        return ['state' => $state, 'ticket_no' => $ticketNo];
    }

    /**
     * Hand freed seats to students whose three documents are approved but who
     * have no ticket yet, in the order they finished (earliest approval first).
     *
     * @return int tickets issued
     */
    public function promoteWaiting(): int
    {
        $need   = DocumentSets::sqlRequired('u.member_type');
        $issued = 0;

        // each group is filled only up to its own allotment
        foreach (array_keys(TicketQuota::GROUPS) as $group) {
            $free = min($this->slotsRemaining(), TicketQuota::limit($group) - TicketQuota::takenFor($group));
            if ($free <= 0) {
                continue;
            }

            $ids = DB::table('documents as d')
                ->join('users as u', 'u.id', '=', 'd.user_id')
                ->leftJoin('tickets as t', 't.user_id', '=', 'd.user_id')
                ->where('u.role', 'student')->where('u.status', 'active')->whereNull('t.id')
                ->whereRaw(TicketQuota::GROUP_SQL . ' = ?', [$group])
                ->groupBy('d.user_id', 'u.member_type')
                ->havingRaw("COUNT(*) = $need AND SUM(d.status = 'approved') = $need")
                ->orderByRaw('MAX(d.reviewed_at) ASC')->orderBy('d.user_id')
                ->limit($free)->pluck('d.user_id');

            foreach ($ids as $id) {
                if ($this->sync((int) $id)['state'] === 'issued') {
                    $issued++;
                }
            }
        }

        return $issued;
    }
}

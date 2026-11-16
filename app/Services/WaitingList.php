<?php

namespace App\Services;

use App\Models\User;
use App\Support\DocumentSets;
use Illuminate\Support\Facades\DB;

/**
 * Seat counting, and closing uploads when a group is full.
 *
 * A seat in a group is spoken for as soon as a person uploads their FIRST file (even before the SSC approves
 * them), so they can always finish what they started. Once a group's allotment is all spoken for, uploads close for everyone who does not
 * already hold a seat: there is no waiting list, so nobody is left hoping. (A seat freed by a disabled or
 * deleted account simply reopens uploads.)
 *
 * The waiting-list pieces below (waitingFor, release) remain only for people who were waitlisted before
 * uploads were closed; nobody new is added to it.
 */
class WaitingList
{
    public function __construct(private TicketService $tickets)
    {
    }

    /** The ticket group ('student' | 'employee' | 'sponsor') a users row belongs to. */
    public static function groupOf(?string $memberType): string
    {
        return DocumentSets::setName($memberType);
    }

    /** Has this person uploaded every file their group needs? */
    public function hasUploadedAll(int $userId, ?string $memberType): bool
    {
        $have = DB::table('documents')->where('user_id', $userId)->pluck('doc_type')->all();

        return array_diff(DocumentSets::keysFor($memberType), $have) === [];
    }

    /** People in a group who have started uploading (at least one file) and are NOT on the waiting list: their seat is spoken for. */
    public static function reservedFor(string $group): int
    {
        return (int) DB::table('users as u')->where('u.role', 'student')->where('u.status', 'active')   // a disabled account gives its seat back
            ->where(fn ($w) => $w->whereNotNull('u.upload_completed_at')->orWhereExists(fn ($d) => $d->from('documents as d')->whereColumn('d.user_id', 'u.id')->select(DB::raw(1))))
            ->whereNull('u.waitlisted_at')
            ->whereRaw(TicketQuota::GROUP_SQL . ' = ?', [$group])->count();
    }

    /** People on the waiting list in a group. */
    public static function waitingFor(string $group): int
    {
        return (int) DB::table('users as u')->where('u.role', 'student')->where('u.status', 'active')->whereNotNull('u.waitlisted_at')
            ->whereRaw(TicketQuota::GROUP_SQL . ' = ?', [$group])->count();
    }

    /** Seats the SSC could release right now: the allotment minus what is spoken for or already ticketed. */
    public static function openSeats(string $group): int
    {
        return max(0, TicketQuota::limit($group) - max(self::reservedFor($group), TicketQuota::takenFor($group)));
    }

    /** Is this group's allotment fully spoken for? */
    public static function isFull(string $group): bool
    {
        return self::openSeats($group) <= 0;
    }

    /**
     * Are uploads closed for this person? Yes once their group's seats (or the venue) are all spoken for and
     * they do not already hold one. Nobody is put on a waiting list any more: they simply cannot upload.
     * Someone who already started uploading, has a ticket, or was waitlisted earlier can still upload and replace files.
     * Call it while holding the `sequences.ticket` lock when the answer must be exact.
     */
    public static function uploadsClosedFor(int $userId, ?string $memberType): bool
    {
        $row = DB::table('users')->where('id', $userId)->first(['upload_completed_at', 'waitlisted_at']);
        if (($row->upload_completed_at ?? null) !== null || ($row->waitlisted_at ?? null) !== null
            || DB::table('documents')->where('user_id', $userId)->exists()   // started already: their seat is held, let them finish
            || DB::table('tickets')->where('user_id', $userId)->exists()) {
            return false;
        }

        $group = self::groupOf($memberType);

        return max(self::reservedFor($group), TicketQuota::takenFor($group)) >= TicketQuota::limit($group)
            || self::totalReserved() >= TicketQuota::capacity();
    }

    private static function totalReserved(): int
    {
        return array_sum(array_map([self::class, 'reservedFor'], array_keys(TicketQuota::GROUPS)));
    }

    /**
     * The SSC lets some waiting people in, longest-waiting first, up to the seats that are open. Anyone already
     * approved gets their ticket now; the others are told and go through the normal review.
     *
     * @return array{released:int,ticketed:int}
     */
    public function release(string $group, int $count, ?User $by = null): array
    {
        $ids = [];
        DB::table('sequences')->insertOrIgnore(['seq_key' => 'ticket', 'current_value' => 0]);

        DB::transaction(function () use ($group, $count, &$ids) {
            DB::table('sequences')->where('seq_key', 'ticket')->lockForUpdate()->first();

            $n = min($count, self::openSeats($group));
            if ($n < 1) {
                return;
            }
            $ids = DB::table('users as u')->where('u.role', 'student')->where('u.status', 'active')->whereNotNull('u.waitlisted_at')
                ->whereRaw(TicketQuota::GROUP_SQL . ' = ?', [$group])->orderBy('u.waitlisted_at')->orderBy('u.id')
                ->limit($n)->pluck('u.id')->all();
            DB::table('users')->whereIn('id', $ids)->update(['waitlisted_at' => null]);
        }, 5);

        $ticketed = 0;
        foreach ($ids as $id) {
            $state = $this->tickets->sync((int) $id)['state'];   // an approved person gets their ticket (and its email) right away
            if (in_array($state, ['issued', 'restored'], true)) {
                $ticketed++;
            } else {
                Notifier::ticketsReleased((int) $id);
            }
        }

        if ($ids) {
            Audit::log('waiting.release', 'setting', $group, ['count' => count($ids), 'ticketed' => $ticketed, 'by' => $by?->name]);
        }

        return ['released' => count($ids), 'ticketed' => $ticketed];
    }
}

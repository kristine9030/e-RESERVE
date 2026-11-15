<?php

namespace App\Services;

use App\Models\User;
use App\Support\DocumentSets;
use Illuminate\Support\Facades\DB;

/**
 * "The tickets are full" without false hope.
 *
 * A seat in a group is spoken for as soon as a person has uploaded EVERY file they need (even before the
 * SSC approves them). When a group's allotment is already spoken for, the next person to finish uploading
 * is told the tickets are full, is promised nothing, and waits for the SSC's announcement. They are put
 * on the waiting list and are never given a ticket automatically: not when a seat frees up, not when
 * the allotment is raised. Only the SSC releases seats, in Site Settings.
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

    /** People in a group who finished uploading and are NOT on the waiting list (their seat is spoken for). */
    public static function reservedFor(string $group): int
    {
        return (int) DB::table('users as u')->where('u.role', 'student')->where('u.status', 'active')   // a disabled account gives its seat back
            ->whereNotNull('u.upload_completed_at')->whereNull('u.waitlisted_at')
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
     * Called when someone saves a file. The first time they have uploaded everything, either their seat is
     * spoken for or, if the group is already full, they go on the waiting list and are told so.
     *
     * @return bool true when they were put on the waiting list
     */
    public function afterUpload(User $user): bool
    {
        $row = DB::table('users')->where('id', $user->id)->first(['upload_completed_at', 'waitlisted_at', 'member_type']);
        if (!$row || $row->upload_completed_at !== null || $row->waitlisted_at !== null || !$this->hasUploadedAll((int) $user->id, $row->member_type)) {
            return false;
        }

        $group = self::groupOf($row->member_type);
        DB::table('sequences')->insertOrIgnore(['seq_key' => 'ticket', 'current_value' => 0]);

        $waiting = DB::transaction(function () use ($user, $group) {
            // the same lock tickets use: two people finishing at once cannot both take the last seat
            DB::table('sequences')->where('seq_key', 'ticket')->lockForUpdate()->first();

            $now = DB::table('users')->where('id', $user->id)->first(['upload_completed_at', 'waitlisted_at']);
            if ($now->upload_completed_at !== null || $now->waitlisted_at !== null) {
                return false;
            }

            $full = self::reservedFor($group) >= TicketQuota::limit($group) || self::totalReserved() >= TicketQuota::capacity();
            DB::table('users')->where('id', $user->id)->update(['upload_completed_at' => now(), 'waitlisted_at' => $full ? now() : null]);

            return $full;
        }, 5);

        if ($waiting) {
            Audit::log('waiting.add', 'user', (string) $user->id, ['group' => $group, 'student' => $user->name]);
            Notifier::ticketsFull((int) $user->id);
        }

        return $waiting;
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

<?php

namespace App\Services;

use App\Mail\StudentNotice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Permanently removes a participant's account (a student, faculty, staff or sponsor): the account, its
 * files, its ticket and its notices. Disabling stays the gentle option; this one cannot be undone.
 *
 * It refuses SSC accounts, a person who already went through the gate (their check-in is a record we
 * keep), and the person doing the deleting. The person can be told by email first, in the SSC's own words.
 */
class AccountDeleter
{
    public function __construct(private TicketService $tickets, private TicketImage $images)
    {
    }

    /** Why this account may not be deleted, or null when it may. */
    public function problem(User $user, ?User $by = null): ?string
    {
        if ($by && $user->id === $by->id) {
            return 'You cannot delete your own account.';
        }
        if ($user->role !== 'student' || $user->ssc_access) {
            return 'SSC and staff accounts are not deleted here.';
        }
        if (DB::table('tickets as t')->join('checkins as c', 'c.ticket_id', '=', 't.id')->where('t.user_id', $user->id)->exists()) {
            return 'This person was already admitted at the gate. Their check-in is kept on record, so disable the account instead.';
        }

        return null;
    }

    /**
     * @return array{ok:bool,message:string,emailed:bool}
     */
    public function delete(User $user, ?string $note, bool $notify, ?User $by = null): array
    {
        if ($problem = $this->problem($user, $by)) {
            return ['ok' => false, 'message' => $problem, 'emailed' => false];
        }

        // Everything we still need once the rows are gone.
        $name     = (string) $user->name;
        $email    = (string) $user->email;
        $type     = (string) ($user->member_type ?: 'student');
        $bounced  = $user->email_bounced_at !== null;   // that address cannot be reached, do not try
        $ticket   = $this->tickets->ticketFor((int) $user->id);
        $files    = DB::table('documents')->where('user_id', $user->id)->pluck('stored_name')->all();

        // The account's rows (documents, ticket, check-in, notices, change requests) go with it: those
        // tables are tied to the user and removed by the database.
        DB::transaction(function () use ($user, $email) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $user->delete();
        });

        // Then the files on disk: the uploads and the saved ticket picture.
        foreach ($files as $stored) {
            if ($this->isStoredName((string) $stored)) {
                Storage::disk('local')->delete('documents/' . $stored);
            }
        }
        if ($ticket) {
            $this->images->forget($ticket);
        }

        $this->tickets->forget();
        if ($ticket) {
            $this->tickets->promoteWaiting();   // the freed seat goes to the next approved person
        }

        $emailed = false;
        if ($notify && !$bounced && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($email, $name)->queue(StudentNotice::accountDeleted($name, trim((string) $note)));
                $emailed = true;
            } catch (Throwable $e) {
                report($e);   // the account is already gone; a mail problem must not undo or hide that
            }
        }

        Audit::log('user.delete', 'user', (string) $user->id, [
            'name' => $name, 'email' => $email, 'type' => $type, 'files' => count($files), 'had_ticket' => (bool) $ticket, 'emailed' => $emailed,
        ]);

        return ['ok' => true, 'emailed' => $emailed, 'message' => $name . '’s account was deleted'
            . ($emailed ? ' and they were told by email.' : ($notify ? '. They could not be emailed.' : '.'))];
    }

    private function isStoredName(string $name): bool
    {
        return (bool) preg_match('/^\d{8}-[0-9a-f]{32}\.(pdf|jpg|png|webp)$/', $name);
    }
}

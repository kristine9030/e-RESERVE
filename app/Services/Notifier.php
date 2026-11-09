<?php

namespace App\Services;

use App\Mail\StudentNotice;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells a student what happened: a notice under the bell in the app, and (for the
 * moments that matter) an email. Every call is fire-and-forget: a failure is
 * logged and never allowed to undo or block a review, an upload or a ticket.
 */
class Notifier
{
    /** All three PDFs are in; now they wait for SSC. */
    public static function submitted(int $userId): void
    {
        self::notice($userId, 'submitted', 'Documents submitted',
            'All three are in. SSC will review them. You will be notified here and by email.', route('tracking'));

        // One email per hour at most, however many times a file is swapped.
        if (Cache::add('mail:submitted:' . $userId, 1, 3600)) {
            self::mail($userId, fn (User $u) => StudentNotice::submitted($u->name));
        }
    }

    /** One document approved (in-app only: the email comes when the ticket is ready). */
    public static function documentApproved(int $userId, string $docType): void
    {
        $label = (string) config("ereserve.documents.$docType.label", 'document');

        self::notice($userId, 'approved', ucfirst($label) . ' approved', 'SSC approved your ' . $label . '.', route('tracking'));
    }

    public static function ticketReady(int $userId, string $ticketNo, bool $restored = false): void
    {
        self::notice($userId, 'ticket', $restored ? 'Your ticket is valid again' : 'Your ticket is ready!',
            'Ticket ' . $ticketNo . '. Open it to see your QR code.', route('ticket'));
        // Fresh read: the ticket was committed a moment ago and an earlier lookup may have cached "none".
        tickets()->forget();
        $ticket = tickets()->ticketFor($userId);

        self::mail($userId, fn (User $u) => StudentNotice::ticketReady($u->name, $ticketNo, $restored, $ticket));
    }

    public static function documentReturned(int $userId, string $docType, string $note): void
    {
        $label = (string) config("ereserve.documents.$docType.label", 'document');

        self::notice($userId, 'returned', ucfirst($label) . ' returned', $note, route('documents.index'));
        self::mail($userId, fn (User $u) => StudentNotice::documentReturned($u->name, $label, $note));
    }

    public static function waitlisted(int $userId): void
    {
        self::notice($userId, 'waitlist', 'Approved, but no seat yet',
            'All documents approved. You are in line for the next released ticket.', route('tracking'));
        self::mail($userId, fn (User $u) => StudentNotice::waitlisted($u->name));
    }

    public static function changeSubmitted(int $userId, string $label): void
    {
        self::notice($userId, 'submitted', 'Change request sent', 'SSC will review your ' . $label . ' change. You will be told here when it is decided.', route('profile'));
    }

    public static function changeDecided(int $userId, string $label, bool $approved, string $note, bool $needsVerify = false): void
    {
        $body = $approved
            ? 'SSC approved your ' . $label . ' change.' . ($needsVerify ? ' Confirm your new email using the link we just sent it.' : '')
            : 'SSC could not approve your ' . $label . ' change' . ($note !== '' ? ': ' . $note : '.');

        self::notice($userId, $approved ? 'approved' : 'returned', $label . ' change ' . ($approved ? 'approved' : 'returned'), $body, route('profile'));
    }

    private static function notice(int $userId, string $type, string $title, ?string $body, ?string $url): void
    {
        try {
            Notice::create([
                'user_id' => $userId,
                'type'    => $type,
                'title'   => mb_substr($title, 0, 120),
                'body'    => $body === null ? null : mb_substr($body, 0, 400),
                'url'     => $url,
            ]);
        } catch (Throwable $e) {
            Log::warning('[e-RESERVE] in-app notice failed: ' . $e->getMessage());
        }
    }

    private static function mail(int $userId, \Closure $make): void
    {
        try {
            $user = User::find($userId);

            if ($user && $user->email && $user->role === 'student') {
                Mail::to($user->email, $user->name)->queue($make($user));
            }
        } catch (Throwable $e) {
            Log::warning('[e-RESERVE] notification failed: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SR-code and GSuite email are the student's identity, so they are never edited
 * directly. The student files a request with a reason; SSC approves or returns it.
 * Everything is checked again when SSC decides, because the world may have changed.
 */
class ChangeRequestService
{
    public function __construct(private AccountService $accounts, private Registrar $registrar, private TicketService $tickets)
    {
    }

    /** @return string empty when the value is acceptable, otherwise the message to show */
    private function problem(User $user, string $field, string $value): string
    {
        if ($field === 'student_no') {
            if ($user->isEmployee()) {
                if (!$this->accounts->employeeIdValid($value)) {
                    return 'Enter your employee ID (letters, numbers, dash or slash, 3 to 20 characters).';
                }
            } elseif (!$this->registrar->validCode($value)) {
                return 'The SR-code must look like 22-10234.';
            }
            if (strcasecmp($value, (string) $user->student_no) === 0) {
                return 'That is already your SR-code.';
            }
            if (User::where('student_no', $value)->where('id', '<>', $user->id)->exists()) {
                return 'That is already registered to another account.';
            }

            return $user->isEmployee() ? '' : $this->registrar->enrollmentProblem($value, (string) $user->college_code);
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'That does not look like a valid email address.';
        }
        if (!$this->accounts->domainAllowed($value)) {
            return $this->accounts->domainRejection($value);
        }
        if (strcasecmp($value, (string) $user->email) === 0) {
            return 'That is already your email.';
        }
        if (User::where('email', $value)->where('id', '<>', $user->id)->exists()) {
            return 'An account already exists for that email.';
        }

        return '';
    }

    /** @return array{ok:bool,message:string} */
    public function submit(User $user, string $field, string $value, string $reason): array
    {
        $fail = static fn (string $m) => ['ok' => false, 'message' => $m];

        if (!isset(ChangeRequest::FIELDS[$field]) || $user->role !== 'student') {
            return $fail('That detail cannot be changed here.');
        }
        $value  = $field === 'email' ? strtolower(trim($value)) : strtoupper(trim($value));
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');

        if (mb_strlen($reason) < 10) {
            return $fail('Tell SSC why it has to change (at least a few words).');
        }
        if (($this->tickets->ticketFor((int) $user->id)['status'] ?? '') === 'used') {
            return $fail('Your ticket was already used at the gate, so this can no longer change.');
        }
        if (ChangeRequest::where('user_id', $user->id)->where('field', $field)->where('status', 'pending')->exists()) {
            return $fail('You already have a pending request for your ' . ChangeRequest::FIELDS[$field] . '. Wait for SSC to decide.');
        }
        if ($p = $this->problem($user, $field, $value)) {
            return $fail($p);
        }

        $req = ChangeRequest::create([
            'user_id' => $user->id, 'field' => $field, 'old_value' => $user->{$field}, 'new_value' => $value,
            'reason' => mb_substr($reason, 0, 300),
        ]);
        Audit::log('change.request', 'user', (string) $user->id, ['field' => $field, 'to' => $value]);
        Notifier::changeSubmitted((int) $user->id, $req->label());

        return ['ok' => true, 'message' => 'Request sent. SSC will review it and you will be notified here.'];
    }

    /** @return array{ok:bool,message:string} */
    public function decide(User $admin, int $id, bool $approve, string $note = ''): array
    {
        if (!$admin->isAdmin()) {
            return ['ok' => false, 'message' => 'Only SSC staff can decide requests.'];
        }
        $note = trim($note);
        if (!$approve && mb_strlen($note) < 5) {
            return ['ok' => false, 'message' => 'Tell the student why it was returned (at least a few words).'];
        }

        $verify = null;
        $req    = null;
        $err    = DB::transaction(function () use ($id, $approve, $note, $admin, &$verify, &$req) {
            $req = ChangeRequest::with('user')->lockForUpdate()->find($id);
            if (!$req || $req->status !== 'pending') {
                return 'That request was already decided. Reload the page.';
            }
            $user = $req->user;

            if ($approve) {
                if ($p = $this->problem($user, $req->field, $req->new_value)) {
                    return 'Cannot approve: ' . $p;
                }
                if ($req->field === 'student_no') {
                    $user->forceFill(['student_no' => $req->new_value])->save();
                } else {
                    // A new address has to be proven again, so the account becomes unverified until they confirm it.
                    $user->forceFill([
                        'email' => $req->new_value, 'email_verified_at' => null,
                        'verify_token_hash' => null, 'verify_expires_at' => null, 'verify_sent_at' => null,
                    ])->save();
                    $verify = $user;
                }
            }

            $req->update([
                'status' => $approve ? 'approved' : 'rejected', 'decided_by' => $admin->id,
                'decided_at' => now(), 'decision_note' => $note === '' ? null : mb_substr($note, 0, 250),
            ]);

            return null;
        });

        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }

        $this->tickets->forget();
        Audit::log('change.' . ($approve ? 'approve' : 'reject'), 'user', (string) $req->user_id, ['field' => $req->field, 'to' => $req->new_value]);
        Notifier::changeDecided((int) $req->user_id, $req->label(), $approve, $note, $req->field === 'email');

        if ($verify) {
            try {
                $this->accounts->sendVerification($verify->fresh());
            } catch (\Throwable $e) {
                report($e);   // they can use "resend" on the verify page
            }
        }

        return ['ok' => true, 'message' => $req->label() . ' change for ' . $req->user->name . ($approve ? ' approved.' : ' returned.')];
    }
}

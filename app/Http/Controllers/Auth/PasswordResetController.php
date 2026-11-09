<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** "Forgot password": emailed one-time link → choose a new password. */
class PasswordResetController extends Controller
{
    public function __construct(private AccountService $accounts)
    {
    }

    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withInput()->with('reset_error', 'That does not look like a valid email address.');
        }
        if (!$this->accounts->staffEmailAllowed($email)) {
            return back()->withInput()->with('reset_error', $this->accounts->domainRejection($email));
        }

        // Same answer whether or not the account exists, so this form cannot be
        // used to discover who is registered. The broker also throttles per account.
        // A mail hiccup must never turn into an error page (or reveal that the account exists).
        try {
            Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('reset_sent', 'If that address has an e-RESERVE account, a reset link is on its way. '
            . 'It is valid for 60 minutes — check Spam too.');
    }

    public function form(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request)
    {
        $email    = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password', '');
        $back     = fn(string $m) => back()->withInput($request->only('email'))->with('reset_error', $m);

        if ($problem = $this->accounts->passwordProblem($password)) {
            return $back($problem);
        }
        if (!hash_equals($password, (string) $request->input('password_confirm', ''))) {
            return $back('The two passwords do not match.');
        }

        $staff  = false;
        $status = Password::reset(
            ['email' => $email, 'password' => $password, 'token' => (string) $request->input('token')],
            function (User $user, string $new) use (&$staff) {
                $staff = $user->isStaff();
                // Opening the link proves the mailbox, so the address counts as confirmed too.
                $user->forceFill([
                    'password'          => $new,
                    'must_change_password' => false,       // they just chose their own
                    'remember_token'    => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
                Audit::log('password.reset', 'user', (string) $user->id);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $back('That reset link is invalid or has expired. Request a new one.');
        }

        // Staff go back to their own door, students to the student sign-in.
        return redirect()->route($staff ? 'ssc.login' : 'login')
            ->with('flash', flash('success', 'Password updated', 'Sign in with your new password.'));
    }
}

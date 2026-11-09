<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** Changing your own contact number and password (students and staff). */
class AccountController extends Controller
{
    public function __construct(private AccountService $accounts)
    {
    }

    /**
     * A student edits their own details: name, college, programme, year level, contact.
     * SR-code and GSuite email stay fixed (they are the sign-in and the ticket identity).
     * The name is locked once SSC has approved a document, so the files and the ticket keep matching.
     */
    public function updateProfile(Request $request, \App\Services\Registrar $registrar, \App\Services\TicketService $tickets)
    {
        $user = $request->user();
        $err  = fn (string $m) => back()->withInput()->with('flash', flash('error', 'Check your details', e($m)))->with('open_edit', true);

        if ($user->isEmployee()) {
            return $this->updateEmployeeProfile($request, $err, $tickets);
        }

        $name    = trim(preg_replace('/\s+/', ' ', (string) $request->input('name', $user->name)) ?? '');
        $college = trim((string) $request->input('college', $user->college_code));
        $program = trim((string) $request->input('program', $user->program));
        $year    = trim((string) $request->input('year_level', $user->year_level));
        $mobile  = trim((string) $request->input('mobile', ''));
        $digits  = preg_replace('/\D/', '', $mobile) ?? '';

        $approved = \App\Models\Document::where('user_id', $user->id)->where('status', 'approved')->exists();
        if ($name !== $user->name) {
            if ($approved) {
                return $err('Your name is locked because SSC already approved one of your documents. Ask SSC if it has to change.');
            }
            if (mb_strlen($name) < 3 || mb_strlen($name) > 160) {
                return $err('Enter your full name as it appears on your student record.');
            }
        }
        if (!isset($registrar->colleges()[$college])) {
            return $err('Choose your college from the list.');
        }
        if (!$registrar->programInCollege($program, $college)) {
            return $err('Choose a programme that belongs to your college.');
        }
        if (!in_array($year, config('ereserve.year_levels'), true)) {
            return $err('Choose your year level.');
        }
        if ($mobile !== '' && !preg_match('/^09\d{9}$/', $digits)) {
            return $err('Enter your mobile number as 09XXXXXXXXX.');
        }
        // The masterlist still decides which college an SR-code belongs to.
        if ($college !== $user->college_code && ($problem = $registrar->enrollmentProblem((string) $user->student_no, $college))) {
            return $err($problem);
        }

        $before = $user->only(['name', 'college_code', 'program', 'year_level', 'mobile']);
        $parts  = preg_split('/\s+/', $name) ?: [$name];
        $user->forceFill([
            'name' => $name, 'college_code' => $college, 'program' => $program, 'year_level' => $year,
            'mobile' => $mobile === '' ? null : $digits,
            'initials' => strtoupper(mb_substr($parts[0], 0, 1) . mb_substr((string) end($parts), 0, 1)),
        ])->save();
        $tickets->forget();   // the ticket shows name, programme and year

        $after = $user->only(['name', 'college_code', 'program', 'year_level', 'mobile']);
        Audit::log('profile.update', 'user', (string) $user->id, ['changed' => array_keys(array_diff_assoc($after, $before))]);

        return redirect()->route('profile')->with('flash', flash('success', 'Profile updated', 'Your details were saved.'));
    }

    /** Faculty / staff edit their name, category and contact number. */
    private function updateEmployeeProfile(Request $request, \Closure $err, \App\Services\TicketService $tickets)
    {
        $user   = $request->user();
        $name   = trim(preg_replace('/\s+/', ' ', (string) $request->input('name', $user->name)) ?? '');
        $type   = trim((string) $request->input('member_type', $user->member_type));
        $mobile = trim((string) $request->input('mobile', ''));
        $digits = preg_replace('/\D/', '', $mobile) ?? '';

        if (mb_strlen($name) < 3 || mb_strlen($name) > 160) {
            return $err('Enter your full name.');
        }
        if (!isset(config('ereserve.employee_types')[$type])) {
            return $err('Choose your category.');
        }
        if ($mobile !== '' && !preg_match('/^09\d{9}$/', $digits)) {
            return $err('Enter your mobile number as 09XXXXXXXXX.');
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $user->forceFill([
            'name' => $name, 'member_type' => $type, 'program' => config('ereserve.employee_types')[$type],
            'mobile' => $mobile === '' ? null : $digits,
            'initials' => strtoupper(mb_substr($parts[0], 0, 1) . mb_substr((string) end($parts), 0, 1)),
        ])->save();
        $tickets->forget();
        Audit::log('profile.update', 'user', (string) $user->id);

        return redirect()->route('profile')->with('flash', flash('success', 'Profile updated', 'Your details were saved.'));
    }

    /** Ask SSC to change the SR-code or GSuite email. Nothing changes until they approve. */
    public function requestChange(Request $request, \App\Services\ChangeRequestService $requests)
    {
        $key = 'change-request:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 6)) {
            return back()->with('flash', flash('error', 'Slow down', 'Too many requests. Try again in ' . RateLimiter::availableIn($key) . ' seconds.'));
        }
        RateLimiter::hit($key, 600);

        $r = $requests->submit($request->user(), (string) $request->input('field'), (string) $request->input('new_value'), (string) $request->input('reason'));

        return back()->withInput()->with('flash', $r['ok']
            ? flash('success', 'Request sent', e($r['message']))
            : flash('error', 'Could not send the request', e($r['message'])))->with($r['ok'] ? [] : ['open_request' => (string) $request->input('field')]);
    }

    public function passwordForm()
    {
        return view('account.password', [
            'hasPassword' => filled(auth()->user()->password),
            'forced'      => (bool) auth()->user()->must_change_password,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $back = fn(string $m) => back()->with('password_error', $m);

        $key = 'password:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $back('Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.');
        }

        // A Google-only account has no password yet, so there is nothing to confirm.
        if (filled($user->password) && !Hash::check((string) $request->input('current_password'), $user->password)) {
            RateLimiter::hit($key, 300);
            return $back('Your current password is not correct.');
        }

        $new = (string) $request->input('password', '');
        if ($problem = $this->accounts->passwordProblem($new)) {
            return $back($problem);
        }
        if (!hash_equals($new, (string) $request->input('password_confirm', ''))) {
            return $back('The two passwords do not match.');
        }

        $forced = (bool) $user->must_change_password;
        if ($forced && filled($user->password) && Hash::check($new, $user->password)) {
            return $back('Choose a different password from the temporary one you were given.');
        }
        $user->forceFill(['password' => $new, 'must_change_password' => false])->save();
        RateLimiter::clear($key);
        Audit::log('password.change', 'user', (string) $user->id);

        $home = $user->isStaff() ? $user->homeRoute() : route('profile');

        return redirect($home)->with('flash', flash('success', 'Password changed',
            $forced ? 'Your own password is set. You can use e-RESERVE now.' : 'Use your new password the next time you sign in.'));
    }
}

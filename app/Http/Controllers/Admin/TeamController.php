<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SscMember;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The SSC roster and its logins. Every SSC account is a full-access "admin"
 * (the adviser, consultant, executives, governors and committee members alike).
 * Gate-only scanner accounts are separate and made with artisan.
 */
class TeamController extends Controller
{
    public function __construct(private AccountService $accounts)
    {
    }

    public function index()
    {
        $members = SscMember::with('user:id,role,status,ssc_access,last_login_at')->orderBy('sort')->get();

        return view('admin.team', [
            'groups'  => $members->groupBy('org_group'),
            'total'   => $members->count(),
            'active'  => $members->filter(fn ($m) => $m->hasAccess())->count(),
            'domains' => allowed_domains_text(),
        ]);
    }

    /** Create the login for a member (or reset it, handing over a fresh password once). */
    public function account(Request $request, SscMember $member)
    {
        $email = strtolower(trim((string) $request->input('email', $member->email ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$this->accounts->staffEmailAllowed($email)) {
            return back()->with('flash', flash('error', 'Check the email', e($email === '' ? 'Type their GSuite email first.' : $this->accounts->domainRejection($email))));
        }

        // The address must not already belong to someone else.
        $taken = SscMember::where('email', $email)->where('id', '!=', $member->id)->exists();
        $user  = User::where('email', $email)->first();

        if ($taken || ($user && $member->user_id && $user->id !== $member->user_id)) {
            return back()->with('flash', flash('error', 'Email already in use',
                e($email) . ' already belongs to another account. Use that person’s own GSuite address.'));
        }

        // They already registered as a student / faculty / sponsor: no new login, just SSC access
        // on that same account (same email, same password they already use).
        if ($user && $user->role === 'student') {
            if ($user->email_verified_at === null) {
                return back()->with('flash', flash('error', 'Email not confirmed yet',
                    e($user->name) . ' has not confirmed their email yet. Ask them to confirm it first, then try again.'));
            }
            if ($user->status !== 'active') {
                return back()->with('flash', flash('error', 'Account is turned off', 'That account is disabled, so SSC access cannot be given.'));
            }
            $user->forceFill(['ssc_access' => true])->save();
            $member->forceFill(['email' => $email, 'user_id' => $user->id])->save();
            Audit::log('staff.grant', 'user', (string) $user->id, ['position' => $member->position, 'name' => $member->display_name]);

            return back()->with('flash', flash('success', 'SSC access given',
                '<strong>' . e($member->display_name) . '</strong> · ' . e($member->position) . '<br>'
                . 'They sign in with the <strong>same email and password</strong> they use as a student. '
                . 'At <code>/ssc</code> they open the SSC panel; from the student side they can switch with “SSC panel”.'));
        }

        if ($user && $user->role !== 'admin') {
            return back()->with('flash', flash('error', 'Email already in use', e($email) . ' already belongs to another account.'));
        }

        $password = Str::password(14, symbols: false);
        $created  = $user === null;
        $name     = $member->display_name;

        $user ??= new User();
        $user->forceFill([
            'email' => $email, 'password' => $password, 'must_change_password' => true,
            'role' => 'admin', 'name' => $name, 'status' => 'active',
            'initials' => strtoupper(mb_substr($name, 0, 1) . mb_substr((string) strrchr(' ' . $name, ' '), 1, 1)),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $member->forceFill(['email' => $email, 'user_id' => $user->id])->save();

        Audit::log($created ? 'staff.create' : 'staff.reset', 'user', (string) $user->id, ['position' => $member->position, 'name' => $name]);

        return back()->with('flash', flash('success', $created ? 'Account created' : 'Password reset',
            '<div style="text-align:left;font-size:.92rem">'
            . '<div><strong>' . e($name) . '</strong> · ' . e($member->position) . '</div>'
            . '<div class="mt-2">Email: <code>' . e($email) . '</code></div>'
            . '<div>Password: <code style="font-size:1rem">' . e($password) . '</code> '
            . '<button type="button" class="btn btn-sm btn-ghost ms-1" data-copy="' . e($password) . '"><i class="fa-regular fa-copy"></i> Copy</button></div>'
            . '<div class="mt-3" style="color:#B87A18"><i class="fa-solid fa-triangle-exclamation"></i> '
            . 'Shown <strong>once</strong>. Give it to them privately and they will be asked to choose their own password when they first sign in at <code>/ssc</code>.</div></div>'));
    }

    /** Turn a member's access off or back on (a disabled account cannot sign in). */
    public function access(Request $request, SscMember $member)
    {
        $user = $member->user;
        abort_if($user === null, 404);
        $member->setRelation('user', $user);

        if ($user->id === $request->user()->id) {
            return back()->with('flash', flash('error', 'Not your own account', 'You cannot turn off your own access.'));
        }

        $disable = $request->input('action') === 'disable';
        // A student account keeps working as a student; only its SSC access is switched.
        $user->forceFill($member->isStudentLink() ? ['ssc_access' => !$disable] : ['status' => $disable ? 'disabled' : 'active'])->save();
        Audit::log($disable ? 'staff.disable' : 'staff.enable', 'user', (string) $user->id, ['position' => $member->position]);

        return back()->with('flash', flash('success', $disable ? 'Access turned off' : 'Access restored',
            e($member->display_name) . ($disable ? ' can no longer sign in.' : ' can sign in again.')));
    }
}

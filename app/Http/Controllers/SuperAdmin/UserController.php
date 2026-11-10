<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Audit;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Every account in the system, in one place, and the only screen that can create staff and other super admins. */
class UserController extends Controller
{
    /** Filter key => [label, badge colour]. */
    public const ROLES = [
        'superadmin' => ['Super Admin', 'danger'],
        'admin'      => ['SSC Admin', 'warning'],
        'scanner'    => ['Gate Scanner', 'info'],
        'employee'   => ['Faculty / Staff', 'primary'],
        'sponsor'    => ['Sponsor', 'purple'],
        'student'    => ['Student', 'success'],
    ];

    /** What can be created: key => [label, group]. */
    public const KINDS = [
        'admin' => ['SSC Admin (full staff access)', 'admin'], 'scanner' => ['Gate Scanner (check-in only)', 'scanner'],
        'superadmin' => ['Super Admin', 'superadmin'],
        'faculty' => ['Faculty', 'employee'], 'instructor' => ['Instructor', 'employee'], 'staff' => ['Staff', 'employee'],
        'sponsor' => ['Sponsor', 'sponsor'], 'student' => ['Student', 'student'],
    ];

    public function __construct(private AccountService $accounts)
    {
    }

    /** The filter group a users row belongs to. */
    public static function groupOf(object $u): string
    {
        return match (true) {
            in_array($u->role, ['superadmin', 'admin', 'scanner'], true) => $u->role,
            ($u->member_type ?? 'student') === 'sponsor'                 => 'sponsor',
            ($u->member_type ?? 'student') === 'student'                 => 'student',
            default                                                      => 'employee',
        };
    }

    /** @return array<string,array{label:string,tone:string,count:int}> */
    public static function roleCounts(): array
    {
        $out = [];
        foreach (self::ROLES as $k => [$label, $tone]) {
            $out[$k] = ['label' => $label, 'tone' => $tone, 'count' => 0];
        }
        foreach (DB::table('users')->selectRaw('role, member_type, COUNT(*) as c')->groupBy('role', 'member_type')->get() as $r) {
            $out[self::groupOf($r)]['count'] += (int) $r->c;
        }

        return $out;
    }

    private function scope(Request $request)
    {
        $q = User::query();
        $r = (string) $request->query('role');
        match ($r) {
            'superadmin', 'admin', 'scanner' => $q->where('role', $r),
            'employee' => $q->where('role', 'student')->whereNotIn('member_type', ['student', 'sponsor']),
            'sponsor'  => $q->where('role', 'student')->where('member_type', 'sponsor'),
            'student'  => $q->where('role', 'student')->where(fn ($w) => $w->whereNull('member_type')->orWhere('member_type', 'student')),
            default    => null,
        };
        if (in_array($request->query('status'), ['active', 'disabled'], true)) {
            $q->where('status', $request->query('status'));
        }
        if (($s = trim((string) $request->query('q'))) !== '') {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('student_no', 'like', $like));
        }

        return $q;
    }

    public function index(Request $request)
    {
        $users = $this->scope($request)->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('superadmin.users', [
            'users'  => $users,
            'roles'  => self::roleCounts(),
            'total'  => User::count(),
            'kinds'  => self::KINDS,
            'types'  => config('ereserve.employee_types'),
        ]);
    }

    public function store(Request $request)
    {
        $kind  = (string) $request->input('kind');
        $name  = trim(preg_replace('/\s+/', ' ', (string) $request->input('name')) ?? '');
        $email = strtolower(trim((string) $request->input('email')));
        $no    = strtoupper(trim((string) $request->input('id_no')));
        $bad   = fn (string $m) => back()->withInput()->with('open_add', true)->with('flash', flash('error', 'Account not created', e($m)));

        if (!isset(self::KINDS[$kind])) {
            return $bad('Choose what kind of account this is.');
        }
        $group = self::KINDS[$kind][1];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            return $bad('Enter the person’s full name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            return $bad('That does not look like a valid email address.');
        }
        if ($group === 'admin' || $group === 'scanner') {
            if (!$this->accounts->staffEmailAllowed($email)) {
                return $bad($this->accounts->domainRejection($email));
            }
        } elseif (in_array($group, ['employee', 'student'], true) && !$this->accounts->domainAllowed($email)) {
            return $bad($this->accounts->domainRejection($email));
        }
        if (User::where('email', $email)->exists()) {
            return $bad('An account with that email already exists.');
        }
        if ($group === 'student') {
            if (!preg_match('/^\d{2}-\d{5}$/', $no)) {
                return $bad('A student needs an SR-code like 24-12345.');
            }
        } elseif ($group === 'employee' && $no !== '' && !$this->accounts->employeeIdValid($no)) {
            return $bad('That employee ID is not in a valid format.');
        }
        if ($no !== '' && User::where('student_no', $no)->exists()) {
            return $bad('That ID number is already registered to someone else.');
        }

        $password = trim((string) $request->input('password'));
        $generated = $password === '';
        if ($generated) {
            $password = Str::password(14, symbols: false);
        } elseif ($problem = $this->accounts->passwordProblem($password)) {
            return $bad($problem);
        }

        $user = new User();
        $user->forceFill([
            'email' => $email, 'password' => $password, 'name' => $name,
            'initials' => strtoupper(mb_substr($name, 0, 1) . mb_substr((string) strrchr(' ' . $name, ' '), 1, 1)),
            'role' => in_array($group, ['superadmin', 'admin', 'scanner'], true) ? $group : 'student',
            'member_type' => match ($group) { 'student' => 'student', 'sponsor' => 'sponsor', 'employee' => $kind, default => 'student' },
            'student_no' => $no === '' ? null : $no,
            'program' => $group === 'employee' ? config('ereserve.employee_types')[$kind] : ($group === 'admin' || $group === 'scanner' ? 'Supreme Student Council' : null),
            'status' => 'active', 'must_change_password' => true, 'email_verified_at' => now(),
        ])->save();

        Audit::log('superadmin.user.create', 'user', (string) $user->id, ['email' => $email, 'role' => self::KINDS[$kind][0]]);

        return back()->with('flash', flash('success', 'Account created',
            '<strong>' . e($name) . '</strong> · ' . e(self::KINDS[$kind][0]) . '<br>'
            . 'Temporary password' . ($generated ? ' (shown once)' : '') . ': <code>' . e($password) . '</code><br>'
            . 'They must choose their own password the first time they sign in.'));
    }

    public function reset(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('flash', flash('error', 'Not for yourself', 'Change your own password from your account menu.'));
        }

        $password = Str::password(14, symbols: false);
        $user->forceFill(['password' => $password, 'must_change_password' => true])->save();
        Audit::log('superadmin.user.reset', 'user', (string) $user->id, ['email' => $user->email]);

        return back()->with('flash', flash('success', 'Password reset',
            e($user->name) . ' ·  new temporary password (shown once): <code>' . e($password) . '</code>'));
    }

    public function toggle(User $user, TicketService $tickets)
    {
        if ($user->id === auth()->id()) {
            return back()->with('flash', flash('error', 'Not allowed', 'You cannot deactivate your own account.'));
        }

        $disable = $user->isActive();
        if ($disable && $user->isSuperAdmin() && User::where('role', 'superadmin')->where('status', 'active')->count() <= 1) {
            return back()->with('flash', flash('error', 'Not allowed', 'That is the last active super admin.'));
        }

        $user->forceFill(['status' => $disable ? 'disabled' : 'active'])->save();

        if ($user->role === 'student') {   // a held ticket follows the account, as on the SSC side
            if ($disable) {
                DB::table('tickets')->where('user_id', $user->id)->where('status', 'valid')->update(['status' => 'void', 'voided_at' => now()]);
            } else {
                $tickets->sync($user->id);
            }
            $tickets->forget();
        }

        Audit::log($disable ? 'superadmin.user.disable' : 'superadmin.user.enable', 'user', (string) $user->id, ['email' => $user->email]);

        return back()->with('flash', flash('success', $disable ? 'Account deactivated' : 'Account reactivated',
            e($user->name) . ($disable ? ' can no longer sign in.' : ' can sign in again.')));
    }
}

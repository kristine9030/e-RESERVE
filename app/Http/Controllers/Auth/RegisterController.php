<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Services\Registrar;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function __construct(private AccountService $accounts, private Registrar $registrar)
    {
    }

    /**
     * Step 1 is "who are you": a student, or faculty / instructor / staff / administration.
     * Each goes to its own form. A Google sign-up in progress is always a student.
     */
    public function show(Request $request)
    {
        $pending = session('google_pending');
        $as      = $pending ? 'student' : (string) $request->query('as', '');
        $next    = (string) $request->query('next', '');

        if ($as === 'employee') {
            return view('auth.register-employee', ['next' => $next, 'types' => config('ereserve.employee_types')]);
        }
        if ($as !== 'student') {
            return view('auth.register-choose', ['next' => $next]);
        }

        return view('auth.register', [
            'next'     => $next,
            'pending'  => $pending,
            'colleges' => $this->registrar->colleges(),
            'programs' => $this->registrar->programs(),
            'years'    => config('ereserve.year_levels'),
        ]);
    }

    public function store(Request $request)
    {
        $pending  = session('google_pending');
        $employee = !$pending && $request->input('as') === 'employee';

        $result = $pending
            ? $this->accounts->registerGoogle($request->all(), $pending)
            : ($employee ? $this->accounts->registerEmployee($request->all()) : $this->accounts->registerStudent($request->all()));

        if (!$result['ok']) {
            return back()
                ->withInput($request->except('password', 'password_confirm'))
                ->with('register_error', ['message' => $result['message'], 'field' => (string) ($result['field'] ?? '')]);
        }

        $first = e(explode(' ', $result['user']->name)[0]);

        if ($pending) {
            return redirect()->route('documents.index')->with('flash', flash(
                'success', 'Welcome, ' . $first . '!', 'Your account is ready. Upload your three documents. SSC reviews them and your ticket is issued once all three are approved.'
            ));
        }

        // Nothing can happen until the address is proved, so go straight to the confirm screen.
        // If the email could not be sent, say so instead of claiming it was.
        if (!($result['mail_ok'] ?? true)) {
            return redirect()->route('verify')->with('flash', flash(
                'warning',
                'Account created, ' . $first . '!',
                'We could not send the confirmation email to <strong>' . e($result['user']->email) . '</strong> just now. Press <strong>Resend the link</strong> on the next screen.'
            ));
        }

        return redirect()->route('verify')->with('flash', flash(
            'success',
            'Welcome to e-RESERVE, ' . $first . '!',
            ($employee ? 'One last step, then your ticket is ready: ' : 'One last step: ')
            . 'open the confirmation link we sent to <strong>' . e($result['user']->email) . '</strong>. Check Spam if it is not in your inbox.'
        ));
    }
}

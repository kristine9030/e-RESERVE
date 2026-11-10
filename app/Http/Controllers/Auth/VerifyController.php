<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use Illuminate\Http\Request;

/** Confirm an email address from the emailed link, and resend it. */
class VerifyController extends Controller
{
    public function __construct(private AccountService $accounts)
    {
    }

    public function show(Request $request)
    {
        $state   = 'prompt';
        $message = 'Open the link in the email we sent you.';

        if ($request->filled('token')) {
            $result  = $this->accounts->verifyToken((string) $request->query('token'));
            $state   = $result['ok'] ? 'confirmed' : 'failed';
            $message = $result['message'];

            if ($result['ok']) {
                session()->flash('flash', flash(
                    'success', 'Email confirmed!',
                    'Your account is fully verified. Next, upload your '
                        . implode(', ', array_map(static fn ($m) => strtolower($m['label']), \App\Support\DocumentSets::metaFor($result['user']?->member_type)))
                        . ' to get your ticket for ' . ev('name') . '.'
                ));
            }
        } elseif (session()->has('verify_result')) {
            [$state, $message] = [session('verify_result.state'), session('verify_result.message')];
        }

        return view('auth.verify', [
            'state'   => $state,
            'message' => $message,
            'devLink' => session('verify_result.link'),
            'user'    => current_user(),
            'verified' => $request->user()?->hasVerifiedEmail() ?? false,
        ]);
    }

    public function resend(Request $request)
    {
        $result = $this->accounts->sendVerification($request->user());

        $state = match ($result['code']) {
            'sent'      => 'sent',
            'throttled' => 'waiting',
            'already'   => 'prompt',
            default     => 'failed',
        };

        $message = $result['message'];

        // Only a local, log-only mailer may show the link on screen. On any real
        // transport (smtp, ses, mailgun, resend...) the link must reach the inbox,
        // otherwise anyone could "confirm" an address they do not own.
        $showLink = in_array(config('mail.default'), ['log', 'array'], true) && !app()->isProduction();
        if ($state === 'sent' && $showLink && $result['link']) {
            $message = 'A confirmation link was generated.';
        }

        return redirect()->route('verify')->with('verify_result', [
            'state' => $state, 'message' => $message, 'link' => $showLink ? $result['link'] : null,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SponsorInquiry;
use App\Services\Audit;
use Illuminate\Http\Request;

/** "Want to sponsor?" form on the landing page: saved for SSC, no account needed. */
class SponsorInquiryController extends Controller
{
    public function store(Request $request)
    {
        // A hidden field real people never fill in: bots do. Pretend it worked.
        if (filled($request->input('website_url'))) {
            return back()->with('flash', flash('success', 'Thank you!', 'The SSC received your message.'));
        }

        $bad = fn (string $m) => back()->withInput()->with('flash', flash('error', 'Check the form', e($m)))->with('open_sponsor', true);

        $company = trim(preg_replace('/\s+/', ' ', (string) $request->input('company')) ?? '');
        $contact = trim(preg_replace('/\s+/', ' ', (string) $request->input('contact_name')) ?? '');
        $email   = strtolower(trim((string) $request->input('email')));
        $phone   = trim((string) $request->input('phone'));
        $message = trim((string) $request->input('message'));

        if (mb_strlen($company) < 2 || mb_strlen($company) > 120) {
            return $bad('Enter the name of your company or organization.');
        }
        if (mb_strlen($contact) < 2 || mb_strlen($contact) > 120) {
            return $bad('Enter the name of the person we should contact.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            return $bad('Enter an email address we can reply to.');
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,32}$/', $phone)) {
            return $bad('Enter a phone number with digits only (you can leave it out).');
        }
        if (mb_strlen($message) > 1000) {
            return $bad('Keep the message under 1,000 characters.');
        }

        // The same address cannot flood the inbox.
        if (SponsorInquiry::where('email', $email)->where('created_at', '>', now()->subDay())->count() >= 2) {
            return $bad('We already have your message. The SSC will reply to ' . $email . ' soon.');
        }

        SponsorInquiry::create([
            'company' => $company, 'contact_name' => $contact, 'email' => $email,
            'phone' => $phone === '' ? null : $phone, 'message' => $message === '' ? null : $message,
            'ip' => substr((string) $request->ip(), 0, 45),
        ]);
        Audit::log('sponsor.inquiry', 'sponsor', $company);

        return redirect(route('home') . '#become-a-sponsor')->with('flash', flash(
            'success', 'Thank you, ' . e(explode(' ', $contact)[0]) . '!', 'The SSC received your message and will reply to <strong>' . e($email) . '</strong>.'
        ));
    }
}

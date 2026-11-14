<?php

namespace App\Http\Controllers;

use App\Models\SponsorInquiry;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/** "Want to sponsor?" form on the landing page: saved for SSC, no account needed. */
class SponsorInquiryController extends Controller
{
    public function store(Request $request)
    {
        // A hidden field real people never fill in: bots do. Pretend it worked.
        if (filled($request->input('website_url'))) {
            return back()->with('flash', flash('success', 'Thank you!', 'The SSC received your message.'));
        }

        // Real people take a few seconds to fill the form. Scripts post at once, or without the stamp the page gave them.
        $stamp = null;
        try {
            $stamp = (int) Crypt::decryptString((string) $request->input('form_token'));
        } catch (\Throwable) {
        }
        if (!$stamp || time() - $stamp < 4) {
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
        // Spam almost always carries a web address; a real sponsor does not need to paste one.
        $link = '~(https?:|www\.|[a-z0-9-]+\.(com|net|org|pro|io|xyz|info|biz|online|site|top|shop|ru|cn)\b)~i';
        if (preg_match($link, $company . ' ' . $contact . ' ' . $message)) {
            return $bad('Please leave web links out of your message. The SSC will reply to your email.');
        }
        if (mb_strlen($message) > 1000) {
            return $bad('Keep the message under 1,000 characters.');
        }

        // One connection cannot flood the inbox either (a few real sponsors may share a campus IP).
        if (SponsorInquiry::where('ip', substr((string) $request->ip(), 0, 45))->where('created_at', '>', now()->subHour())->count() >= 5) {
            return $bad('Too many messages were sent from this connection. Please try again in an hour or message the SSC on Facebook.');
        }

        // The same address cannot flood the inbox.
        if (SponsorInquiry::where('email', $email)->where('created_at', '>', now()->subDay())->count() >= 2) {
            return $bad('We already have your message. The SSC will reply to ' . $email . ' soon.');
        }

        SponsorInquiry::create([
            'company' => $company, 'contact_name' => $contact, 'email' => $email,
            'phone' => $phone === '' ? null : $phone, 'message' => $message === '' ? null : $message,
            'ip' => substr((string) $request->ip(), 0, 45), 'created_at' => now(),
        ]);
        Audit::log('sponsor.inquiry', 'sponsor', $company);

        return redirect(route('home') . '#become-a-sponsor')->with('flash', flash(
            'success', 'Thank you, ' . e(explode(' ', $contact)[0]) . '!', 'The SSC received your message and will reply to <strong>' . e($email) . '</strong>.'
        ));
    }
}

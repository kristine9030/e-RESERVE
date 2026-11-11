<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\StudentAccess;

/** Static copy for the landing page and the dashboards. */
class SiteContent
{
    public static function pipeline(): array
    {
        return [
            ['num' => '01', 'icon' => 'fa-user-plus', 'title' => 'Create your account',
             'text' => 'Sign up with your BatStateU GSuite address, then open the confirmation link we email you.', 'tag' => 'Takes 2 minutes'],
            ['num' => '02', 'icon' => 'fa-file-arrow-up', 'title' => 'Submit 3 required documents',
             'text' => 'Your signed waiver, your Certificate of Registration (COR) and your school ID — one PDF each.', 'tag' => 'Max 5 MB each'],
            ['num' => '03', 'icon' => 'fa-ticket', 'title' => 'Get your ticket number',
             'text' => 'Once SSC approves all three files, your personal ticket number and QR code are issued.', 'tag' => 'After approval'],
            ['num' => '04', 'icon' => 'fa-qrcode', 'title' => 'Scan at the gate',
             'text' => 'Show the QR on your phone or on paper. The gate scans it and you walk straight in.', 'tag' => 'Event night'],
        ];
    }

    public static function guidelines(): array
    {
        $domain = config('ereserve.allowed_email_domains')[0];

        return [
            'eligibility' => [
                'label' => 'Who can join', 'icon' => 'fa-user-graduate',
                'items' => [
                    ['fa-graduation-cap', 'Currently enrolled students', 'Enrolled at BatStateU The NEU ARASOF-Nasugbu this semester. Sign up with your @' . $domain . ' account; your SR-code is checked against the registrar’s masterlist.'],
                    ['fa-briefcase', 'Faculty, instructors and staff', 'BatStateU The NEU ARASOF-Nasugbu faculty, instructors and staff. Sign up with your @' . $domain . ' email. No confirmation email is needed.'],
                    ['fa-handshake', 'Sponsors', 'Partners and companies supporting SIGLA 2026. Any email works, but you confirm it with the link we send.'],
                    ['fa-envelope', 'Use the right email', 'Students and employees must use their university email. Personal emails are only for sponsors.'],
                    ['fa-user-lock', 'One person, one ticket', 'Each person gets exactly one ticket. A second account or a shared ticket will be voided.'],
                    ['fa-ban', 'Non-transferable', 'Your ticket is tied to your name and your ID (SR-code, employee ID or sponsor ID). It cannot be sold, lent or gifted.'],
                ],
            ],
            'documents' => [
                'label' => 'What to upload', 'icon' => 'fa-file-pdf',
                'items' => [
                    ['fa-file-signature', 'Students: signed waiver', 'Download the waiver from your dashboard, print it, sign it in ink, then scan it. Every page must be included.'],
                    ['fa-file-lines', 'Students: COR', 'Your Certificate of Registration for the current semester. Your name and SR-code must be readable.'],
                    ['fa-id-card', 'Students: school ID', 'The front of your valid BatStateU ID, face visible, no glare, no filters.'],
                    ['fa-id-badge', 'Faculty, instructors and staff: employee ID', 'One PDF with the front of your BatStateU employee ID. Your name must match your account.'],
                    ['fa-handshake', 'Sponsors: proof of sponsorship', 'One PDF showing your sponsorship, such as the SSC letter, the agreement or a receipt.'],
                    ['fa-circle-check', 'Clear and complete', 'Blurry, cropped, edited or expired documents are returned and your ticket is paused until you replace them.'],
                ],
            ],
            'files' => [
                'label' => 'File rules', 'icon' => 'fa-file-circle-check',
                'items' => [
                    ['fa-file-pdf', 'PDF only', 'Only .pdf files are accepted. Phone photos and Word files must be saved or exported as PDF first.'],
                    ['fa-weight-hanging', 'Up to 5 MB each', 'Scan at 150–200 dpi or use your phone’s “scan to PDF”. Larger files are rejected before they upload.'],
                    ['fa-rotate', 'You can replace a file', 'Uploaded the wrong one? Upload again any time — until staff approve it or your ticket has been used.'],
                    ['fa-shield-halved', 'Private by design', 'Your documents are stored securely and opened only by you and authorised SSC staff, for verification only.'],
                ],
            ],
            'ticket' => [
                'label' => 'Your ticket', 'icon' => 'fa-ticket',
                'items' => [
                    ['fa-bolt', 'Issued after approval', 'No payment needed: your ticket number appears as soon as SSC approves your documents (all three for students, one for faculty, staff and sponsors), while tickets last.'],
                    ['fa-qrcode', 'The QR is your pass', 'Your QR code carries a secret signature, so a copied or made-up ticket number fails at the gate.'],
                    ['fa-triangle-exclamation', 'If a file is returned', 'Staff may return a document with a note. Your ticket is paused, then restored — same number — once you replace it.'],
                    ['fa-hourglass-half', 'Tickets are limited', (Setting::enabled('show_slots') ? 'Only ' . number_format(\App\Services\TicketQuota::capacity()) . ' tickets are available' : 'Tickets are limited') . ', given to the first people whose documents are approved. Upload early and make sure each file is clear.'],
                ],
            ],
            'gate' => [
                'label' => 'Event night', 'icon' => 'fa-door-open',
                'items' => [
                    ['fa-clock', 'Arrive early', 'The event runs 5:00 PM – 10:00 PM. Be at the gate before 5:00 PM so you are scanned in before the program starts.'],
                    ['fa-mobile-screen', 'Bring your QR', 'Show it on your phone with the brightness turned up, or bring a printed copy as backup.'],
                    ['fa-id-badge', 'Bring your ID', 'Bring your school ID, employee ID or a valid ID. Gate staff may match your QR against it.'],
                    ['fa-user-check', 'One scan per ticket', 'A ticket can be scanned once. After that it shows as already used — screenshots will not get a second person in.'],
                ],
            ],
        ];
    }

    public static function faqs(): array
    {
        $limited = Setting::enabled('show_slots') ? 'limited to ' . number_format(\App\Services\TicketQuota::capacity()) : 'limited';
        $domain  = '@' . config('ereserve.allowed_email_domains')[0];

        $faqs = [
            ['Who can get a ticket?', 'Currently enrolled students of BatStateU The NEU ARASOF-Nasugbu, its faculty, instructors and staff, and sponsors of SIGLA 2026. Students and employees sign up with their ' . $domain . ' GSuite account; sponsors can use any email they check.'],
        ];

        // The soft launch: while the doors are shut, say so.
        if (! StudentAccess::open()) {
            $faqs[] = ['When can I create an account?', 'This page is already live, but sign-in and account creation open on ' . StudentAccess::opensAtText() . '. You can already read the guidelines, the lineup and this FAQ while you wait.'];
        }

        return array_merge($faqs, [
            ['Do I have to pay?', 'No. Your ticket is free. It is issued after SSC approves your documents. There is nothing to send through GCash.'],
            ['What do I need to upload?', 'Students upload three PDFs: the signed waiver, the Certificate of Registration (COR) for this semester, and the front of the school ID. Faculty, instructors and staff upload one PDF of their employee ID. Sponsors upload one PDF as proof of sponsorship. Each file can be up to 5 MB.'],
            ['Can I sign in with Google?', 'Yes. On the sign-in page, press “Continue with Google” and use your ' . $domain . ' account — no password to remember. You can also sign in with your email and password instead.'],
            ['I am faculty or staff. Do I need to confirm my email?', 'No. Sign up with your ' . $domain . ' GSuite address, pick whether you are faculty, an instructor or staff, and enter your employee ID — then go straight to uploading it. Sponsors do confirm their email by a link, since they may use any address.'],
            ['How do sponsors join?', 'Two ways. Not ready yet? Press “Become a sponsor” on this page and send your details — the SSC will reply to the email you give. Ready to register? Choose “I’m a sponsor” when you create your account, confirm your email, then upload one PDF as proof of sponsorship.'],
            ['Where do I get the waiver? (students)', 'Sign in, open My Documents and press “Download waiver form”. Print it, have your parent or guardian and you sign it in ink, scan it and upload the PDF.'],
            ['How do I make a PDF from my phone?', 'Use any “scan to PDF” feature: Google Drive, Adobe Scan, Microsoft Lens, or the Notes app on iPhone. Photos can also be exported as PDF from your gallery’s print option.'],
            ['What if I forget my password?', 'Press “Forgot password?” on the sign-in page and enter your account email. We email you a link to pick a new password.'],
            ['When do I get my ticket number?', 'Once SSC has approved your required documents (all three for students, one for faculty, staff and sponsors), your ticket number and QR code appear on your dashboard and on My Ticket. Tickets are ' . $limited . ', so upload early. If every ticket for your group is already claimed, you are placed on the waiting list and get one automatically the moment a seat is released.'],
            ['Why was my document returned?', 'SSC staff check every file. If something is unreadable, cropped, expired or unsigned, the file is returned with a note. Your ticket is paused until you upload a corrected copy, then it is restored with the same number.'],
            ['How do I know where my registration stands?', 'Open the Track page: it lists every step — account created, email confirmed, documents uploaded, SSC approval, ticket issued and scanned at the gate — with the current one highlighted. The bell at the top of the app also pings you whenever a document is approved, returned, or your ticket is ready.'],
            ['How do I get in on event night?', 'Show the QR code on your ticket at the gate. It is scanned once and you are admitted. Bring your ID in case staff want to match it.'],
            ['What if I lose my ticket?', 'Nothing is lost. Sign in to e-RESERVE any time and open My Ticket — show the QR on your phone, download it as a JPG, or print a copy.'],
            ['Can I give my ticket to a friend?', 'No. Tickets are tied to your name and ID, and a ticket can only be scanned once.'],
        ]);
    }

    public static function aboutHighlights(): array
    {
        return [
            ['fa-people-group', 'Made for every student, faculty and staff', 'A night for the whole campus, part of the 70th Founding Anniversary celebration.'],
            ['fa-file-pdf', 'Paperless Sign-up', 'Upload your required documents from your phone. No queues at the SSC office, no paper forms to hand in.'],
            ['fa-ticket', 'Free Ticket', 'Your ticket number is issued as soon as your documents are approved. No payment needed.'],
            ['fa-qrcode', 'Contactless Entry', 'Scan-and-go gates powered by signed QR codes — no printed manifest, no long lines.'],
        ];
    }
}

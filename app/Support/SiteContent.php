<?php

namespace App\Support;

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
                    ['fa-graduation-cap', 'Currently enrolled students only', 'You must be enrolled at BatStateU TNEU – ARASOF Nasugbu this semester. Your SR-code is checked against the registrar’s masterlist.'],
                    ['fa-envelope', 'A GSuite account is required', 'Register with your @' . $domain . ' address. Personal email addresses are not accepted.'],
                    ['fa-user-lock', 'One student, one ticket', 'Each student gets exactly one ticket. A second account or a shared ticket will be voided.'],
                    ['fa-ban', 'Non-transferable', 'Your ticket is tied to your name and SR-code. It cannot be sold, lent or gifted.'],
                ],
            ],
            'documents' => [
                'label' => 'The 3 documents', 'icon' => 'fa-file-pdf',
                'items' => [
                    ['fa-file-signature', 'Signed waiver', 'Download the waiver from your dashboard, print it, sign it in ink, then scan it. Every page must be included.'],
                    ['fa-file-lines', 'COR', 'Your Certificate of Registration for the current semester. Your name and SR-code must be readable.'],
                    ['fa-id-card', 'School ID', 'The front of your valid BatStateU ID, face visible, no glare, no filters.'],
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
                    ['fa-bolt', 'Issued after approval', 'No payment needed: your ticket number appears as soon as SSC approves all three documents, while tickets last.'],
                    ['fa-qrcode', 'The QR is your pass', 'Your QR code carries a secret signature, so a copied or made-up ticket number fails at the gate.'],
                    ['fa-triangle-exclamation', 'If a file is returned', 'Staff may return a document with a note. Your ticket is paused, then restored — same number — once you replace it.'],
                    ['fa-hourglass-half', 'Tickets are limited', 'Only ' . number_format((int) config('ereserve.event.capacity')) . ' tickets are available, given to the first students whose three documents are approved. Upload early and make sure each file is clear.'],
                ],
            ],
            'gate' => [
                'label' => 'Event night', 'icon' => 'fa-door-open',
                'items' => [
                    ['fa-clock', 'Arrive early', 'The event runs 5:00 PM – 10:00 PM. Be at the gate before 5:00 PM so you are scanned in before the program starts.'],
                    ['fa-mobile-screen', 'Bring your QR', 'Show it on your phone with the brightness turned up, or bring a printed copy as backup.'],
                    ['fa-id-badge', 'Bring your school ID', 'Gate staff may match your QR against your physical ID.'],
                    ['fa-user-check', 'One scan per ticket', 'A ticket can be scanned once. After that it shows as already used — screenshots will not get a second person in.'],
                ],
            ],
        ];
    }

    public static function faqs(): array
    {
        return [
            ['Who can get a ticket?', 'All currently enrolled students of Batangas State University TNEU – ARASOF Nasugbu with an active university GSuite account.'],
            ['Do I have to pay?', 'No. Your ticket is issued after SSC approves your signed waiver, COR and school ID. There is nothing to send through GCash.'],
            ['What do I need to upload?', 'Three required documents, each saved as a PDF: your signed waiver, your Certificate of Registration (COR) for this semester, and the front of your school ID. Each can be up to 5 MB.'],
            ['Where do I get the waiver?', 'Sign in, open Documents and choose “Download waiver”. Print it, sign it in ink, scan it and upload the PDF.'],
            ['How do I make a PDF from my phone?', 'Use any “scan to PDF” feature — Google Drive, Adobe Scan, Microsoft Lens, or the Notes app on iPhone. Photos can also be exported as PDF from your gallery’s print option.'],
            ['When do I get my ticket number?', 'Once SSC has approved all three of your documents, your ticket number and QR code appear on your dashboard. Tickets are limited to ' . number_format((int) config('ereserve.event.capacity')) . ', so upload early.'],
            ['Why was my document returned?', 'SSC staff check every file. If something is unreadable, cropped, expired or unsigned, the file is returned with a note. Your ticket is paused until you upload a corrected copy — then it is restored with the same number.'],
            ['How do I get in on event night?', 'Show the QR code on your ticket at the gate. It is scanned once and you are admitted. Bring your school ID in case staff want to match it.'],
            ['What if I lose my ticket?', 'Nothing is lost. Sign in to e-RESERVE any time and open My Ticket to see or print it again.'],
            ['Can I give my ticket to a friend?', 'No. Tickets are tied to your name and SR-code, and a ticket can only be scanned once.'],
        ];
    }

    public static function aboutHighlights(): array
    {
        return [
            ['fa-people-group', 'Made for Every Student', 'A night for the whole campus, part of the 70th Founding Anniversary celebration.'],
            ['fa-file-pdf', 'Paperless Sign-up', 'Submit your 3 required documents from your phone. No queues at the SSC office, no paper forms to hand in.'],
            ['fa-ticket', 'Free Ticket', 'Your ticket number is issued as soon as your documents are approved. No payment needed.'],
            ['fa-qrcode', 'Contactless Entry', 'Scan-and-go gates powered by signed QR codes — no printed manifest, no long lines.'],
        ];
    }
}

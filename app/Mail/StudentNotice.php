<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One layout, three notices a student can receive after SSC reviews them:
 * ticket ready, a document returned, or approved-but-no-seat-left.
 * Queued, so an SMTP hiccup never slows down or breaks an approval click.
 */
class StudentNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $subjectLine,
        public string $studentName,
        public string $heading,
        public array $paragraphs,
        public ?string $buttonLabel = null,
        public ?string $buttonUrl = null,
        public ?string $highlight = null,
        public ?array $ticket = null,        // set only for the "ticket ready" mail: draws the ticket pass (no QR in email)
        public string $kind = 'info',        // received | ticket | returned | waitlist: picks the icon and label
    ) {
    }

    public static function submitted(string $name): self
    {
        return new self(
            'We received your documents · ' . config('ereserve.event.name'),
            $name,
            'Documents received. Now we wait for SSC.',
            [
                'Your waiver, COR and school ID are in. SSC staff will review each one.',
                'You do not need to do anything else. We will email you as soon as there is a decision, '
                    . 'and you can also check the bell icon or the Track page in e-RESERVE any time.',
                'Your ticket is issued only after all three documents are approved.',
            ],
            'Track my status',
            route('tracking'),
            kind: 'received',
        );
    }

    /** @param array|null $ticket TicketService::ticketFor() shape; null falls back to a number-only mail */
    public static function ticketReady(string $name, string $ticketNo, bool $restored = false, ?array $ticket = null): self
    {
        return new self(
            'Your ticket is ready · ' . config('ereserve.event.name'),
            $name,
            $restored ? 'Your ticket is valid again' : 'Your ticket is ready!',
            [
                (($ticket['member_type'] ?? 'student') === 'student' ? 'SSC approved your waiver, COR and school ID. ' : 'Your email is confirmed. ')
                    . 'Your pass is below. Open it in e-RESERVE on the night and show the QR code at the gate; '
                    . 'it admits one person and is scanned once.',
            ],
            'Open my ticket',
            route('ticket'),
            $ticketNo,
            $ticket,
            'ticket',
        );
    }

    public static function documentReturned(string $name, string $docLabel, string $note): self
    {
        return new self(
            'Action needed: your ' . $docLabel . ' was returned',
            $name,
            'Please replace your ' . $docLabel,
            [
                'SSC staff could not approve your ' . $docLabel . '. Their note:',
                '“' . $note . '”',
                'Upload a corrected PDF and it will be reviewed again. Any ticket you held is on hold until then.',
            ],
            'Fix my documents',
            route('documents.index'),
            kind: 'returned',
        );
    }

    public static function waitlisted(string $name): self
    {
        return new self(
            'Documents approved · no seat available yet',
            $name,
            'Approved, but the tickets for your group are all taken',
            [
                'All three of your documents are approved. Unfortunately all ' . $cap . ' tickets have already been issued.',
                'You are in line: if a ticket is released, yours is issued automatically and we will email you.',
            ],
            'Check my status',
            route('tracking'),
            kind: 'waitlist',
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notice', text: 'emails.notice-text');
    }

    /** No attachments: the QR code is never sent by email, only shown inside e-RESERVE. */
    public function attachments(): array
    {
        return [];
    }
}

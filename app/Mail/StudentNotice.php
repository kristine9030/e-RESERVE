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
        public ?array $ticket = null,        // set only for the "ticket ready" mail: draws the ticket card + QR
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
                    . 'Your ticket is below. Show the QR code at the gate; '
                    . 'it admits one person and is scanned once.',
                'Save the attached picture of your ticket to your phone, so you can still get in if your signal is weak on the night.',
            ],
            'Open my ticket',
            route('ticket'),
            $ticketNo,
            $ticket,
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
        );
    }

    public static function waitlisted(string $name): self
    {
        $cap = number_format((int) config('ereserve.event.capacity'));

        return new self(
            'Documents approved · no seat available yet',
            $name,
            'Approved, but all ' . $cap . ' tickets are taken',
            [
                'All three of your documents are approved. Unfortunately all ' . $cap . ' tickets have already been issued.',
                'You are in line: if a ticket is released, yours is issued automatically and we will email you.',
            ],
            'Check my status',
            route('tracking'),
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

    /** The whole ticket as a picture, only on the ticket mail: save it straight to the phone. */
    public function attachments(): array
    {
        if (!$this->ticket) {
            return [];
        }

        $jpg  = app(\App\Services\TicketImage::class)->jpg($this->ticket);
        $name = 'ticket-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $this->ticket['ticket_no']) . '.jpg';

        return [Attachment::fromData(fn () => $jpg, $name)->withMime('image/jpeg')];
    }
}

<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The confirmation link a new student receives. */
class ConfirmEmail extends Mailable
{
    public function __construct(public string $studentName, public string $link, public int $hours)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your e-RESERVE account · ' . config('ereserve.event.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirm', text: 'emails.confirm-text');
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "Forgot password" link, in the same look as every other e-RESERVE email. */
class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $studentName, public string $link, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your e-RESERVE password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reset', text: 'emails.reset-text');
    }
}

<?php

namespace App\Mail;

use App\Models\SponsorInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Goes to the SSC inbox when someone sends the "Want to sponsor?" form. Reply-To is the
 * sponsor's own address, so pressing Reply continues the conversation by email.
 * Queued, so a mail hiccup never breaks the form for the visitor.
 */
class SponsorInquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public SponsorInquiry $inquiry)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New sponsor inquiry · ' . $this->inquiry->company,
            replyTo: [new Address($this->inquiry->email, $this->inquiry->contact_name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.inquiry', text: 'emails.inquiry-text');
    }

    public function attachments(): array
    {
        return [];
    }
}

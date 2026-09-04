<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $alertTitle,
        public readonly string $alertMessage,
        public readonly ?string $ticketRef = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->alertTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Hello '.e($this->recipientName).',</p>'
                .'<p><strong>'.e($this->alertTitle).'</strong></p>'
                .'<p>'.e($this->alertMessage).'</p>'
                .($this->ticketRef ? '<p>Ticket: <code>'.e($this->ticketRef).'</code></p>' : '')
                .'<p>Sign in to the Risk Management System to take action.</p>',
        );
    }
}

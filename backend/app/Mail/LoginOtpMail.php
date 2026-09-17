<?php

namespace App\Mail;

use App\Services\LoginOtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $otp,
        public readonly int $minutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.otp_from.address', LoginOtpService::FROM_ADDRESS);
        $fromName = (string) config('mail.otp_from.name', 'ACCC OTP Service');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Your sign-in code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.login-otp',
            text: 'mail.login-otp-text',
        );
    }
}

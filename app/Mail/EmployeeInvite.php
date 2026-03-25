<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Employee $employee,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Þú hefur verið boðið á Timr',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.employee-invite',
        );
    }
}

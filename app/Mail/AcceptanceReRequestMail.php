<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Asks a holder to accept the terms again for items they already hold.
 *
 * Deliberately not the unaccepted-items reminder: a holder who accepted a year ago
 * and is being asked again reads "Reminder: You have Unaccepted Items" as a bug, so
 * this is framed as a fresh request rather than a nudge about something they ignored.
 */
class AcceptanceReRequestMail extends BaseMailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $holder,
        public readonly int $itemCount,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: trans('mail.acceptance_re_request'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'notifications.markdown.acceptance-re-request',
            with: [
                'assigned_to' => $this->holder->present()->fullName,
                'count' => $this->itemCount,
                'accept_url' => route('account.accept'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

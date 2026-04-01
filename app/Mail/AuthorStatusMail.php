<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuthorStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $status;
    public $messageText;
    public $portalUrl;
    public $actionText;

    public function __construct($user, $status, $messageText, ?string $portalUrl = null, ?string $actionText = null)
    {
        $this->user = $user;
        $this->status = $status;
        $this->messageText = $messageText;
        $this->portalUrl = $portalUrl;
        $this->actionText = $actionText;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Author Account {$this->status}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.author-status'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

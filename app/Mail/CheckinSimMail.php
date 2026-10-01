<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\Sim;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CheckinSimMail extends BaseMailable
{
    use Queueable, SerializesModels;

    public function __construct(Sim $sim, $checkedOutTo, User $checkedInBy, $note)
    {
        $this->item = $sim;
        $this->target = $checkedOutTo;
        $this->admin = $checkedInBy;
        $this->note = $note;
        $this->settings = Setting::getSettings();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: trans('admin/sims/mail.checkin_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.markdown.checkin-sim',
            with: [
                'item' => $this->item,
                'admin' => $this->admin,
                'note' => $this->note,
                'target' => $this->target,
                'recipientName' => CheckoutSimMail::recipientName($this->target),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

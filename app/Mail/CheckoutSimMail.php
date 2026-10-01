<?php

namespace App\Mail;

use App\Models\Asset;
use App\Models\Setting;
use App\Models\Sim;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CheckoutSimMail extends BaseMailable
{
    use Queueable, SerializesModels;

    public function __construct(Sim $sim, $checkedOutTo, User $checkedOutBy, $acceptance, $note)
    {
        $this->item = $sim;
        $this->admin = $checkedOutBy;
        $this->note = $note;
        $this->target = $checkedOutTo;
        $this->acceptance = $acceptance;
        $this->settings = Setting::getSettings();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: trans('admin/sims/mail.checkout_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.markdown.checkout-sim',
            with: [
                'item' => $this->item,
                'admin' => $this->admin,
                'note' => $this->note,
                'target' => $this->target,
                'recipientName' => self::recipientName($this->target),
            ]
        );
    }

    /**
     * The person the mail is addressed to: the user, or the user an asset
     * is checked out to when the SIM went into an asset.
     */
    public static function recipientName($target): string
    {
        if ($target instanceof User) {
            return (string) $target->display_name;
        }

        if (($target instanceof Asset) && ($target->assignedTo instanceof User)) {
            return (string) $target->assignedTo->display_name;
        }

        return (string) ($target->display_name ?? '');
    }

    public function attachments(): array
    {
        return [];
    }
}

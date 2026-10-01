<?php

namespace App\Notifications;

use App\Models\Sim;
use App\Models\User;

class CheckoutSimNotification extends SimWebhookNotification
{
    /**
     * Same signature the CheckoutableListener uses for every checkout notification.
     */
    public function __construct(Sim $item, $target, User $admin, public $acceptance = null, $note = null)
    {
        parent::__construct($item, $target, $admin, $note);
    }

    protected function title(): string
    {
        return trans('admin/sims/mail.checkout_notification');
    }

    protected function slackIcon(): string
    {
        return ':arrow_up:';
    }
}

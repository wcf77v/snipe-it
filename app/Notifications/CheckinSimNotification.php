<?php

namespace App\Notifications;

class CheckinSimNotification extends SimWebhookNotification
{
    protected function title(): string
    {
        return trans('admin/sims/mail.checkin_notification');
    }

    protected function slackIcon(): string
    {
        return ':arrow_down:';
    }
}

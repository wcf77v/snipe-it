<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\Sim;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Channels\SlackWebhookChannel;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\GoogleChat\Card;
use NotificationChannels\GoogleChat\GoogleChatChannel;
use NotificationChannels\GoogleChat\GoogleChatMessage;
use NotificationChannels\GoogleChat\Section;
use NotificationChannels\GoogleChat\Widgets\KeyValue;
use NotificationChannels\MicrosoftTeams\MicrosoftTeamsChannel;
use NotificationChannels\MicrosoftTeams\MicrosoftTeamsMessage;

/**
 * Slack / Microsoft Teams / Google Chat webhook message for a SIM card
 * checkout or checkin. Mirrors CheckoutComponentNotification; subclasses
 * only supply the title and the Slack emoji.
 */
abstract class SimWebhookNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Sim $item,
        public $target,
        public User $admin,
        public $note = null,
    ) {}

    abstract protected function title(): string;

    abstract protected function slackIcon(): string;

    public function via()
    {
        $settings = Setting::getSettings();
        $notifyBy = [];

        if ($settings->webhook_selected == 'google' && $settings->webhook_endpoint) {
            $notifyBy[] = GoogleChatChannel::class;
        }

        if ($settings->webhook_selected == 'microsoft' && $settings->webhook_endpoint) {
            $notifyBy[] = MicrosoftTeamsChannel::class;
        }

        if ($settings->webhook_selected == 'slack' || $settings->webhook_selected == 'general') {
            $notifyBy[] = SlackWebhookChannel::class;
        }

        return $notifyBy;
    }

    /**
     * Key facts shown in every channel. Secrets are never included.
     */
    protected function facts(): array
    {
        $facts = [
            trans('mail.assigned_to') => (string) ($this->target?->display_name ?? ''),
            trans('general.by') => (string) ($this->admin?->display_name ?? ''),
        ];

        if ($this->item->msisdn) {
            $facts[trans('admin/sims/table.msisdn')] = (string) $this->item->formatted_msisdn;
        }

        if ($this->item->supplier) {
            $facts[trans('admin/sims/table.operator')] = (string) $this->item->supplier->name;
        }

        if ($this->item->company) {
            $facts[trans('general.company')] = (string) $this->item->company->name;
        }

        if ($this->note) {
            $facts[trans('mail.notes')] = (string) $this->note;
        }

        return $facts;
    }

    public function toSlack()
    {
        $settings = Setting::getSettings();
        $item = $this->item;
        $note = $this->note;
        $fields = $this->facts();
        unset($fields[trans('mail.notes')]);

        if ($this->target && method_exists($this->target, 'present')) {
            $fields[trans('mail.assigned_to')] = '<'.$this->target->present()->viewUrl().'|'.$this->target->display_name.'>';
        }

        return (new SlackMessage)
            ->content($this->slackIcon().' :iphone: '.$this->title())
            ->from($settings->webhook_botname ?: 'Snipe-Bot')
            ->to($settings->webhook_channel ?: '')
            ->attachment(function ($attachment) use ($item, $note, $fields) {
                $attachment->title(htmlspecialchars_decode($item->display_name), $item->present()->viewUrl())
                    ->fields($fields)
                    ->content($note);
            });
    }

    public function toMicrosoftTeams()
    {
        $settings = Setting::getSettings();

        if (! Str::contains($settings->webhook_endpoint, 'workflows')) {
            $message = MicrosoftTeamsMessage::create()
                ->to($settings->webhook_endpoint)
                ->type('success')
                ->addStartGroupToSection('activityTitle')
                ->title($this->title())
                ->addStartGroupToSection('activityText')
                ->fact(htmlspecialchars_decode($this->item->display_name), '', 'activityTitle');

            foreach ($this->facts() as $label => $value) {
                $message->fact($label, $value);
            }

            return $message;
        }

        return [$this->title(), [trans('mail.item') => htmlspecialchars_decode($this->item->display_name)] + $this->facts()];
    }

    public function toGoogleChat()
    {
        $settings = Setting::getSettings();

        return GoogleChatMessage::create()
            ->to($settings->webhook_endpoint)
            ->card(
                Card::create()
                    ->header('<strong>'.$this->title().'</strong>', htmlspecialchars_decode($this->item->display_name))
                    ->section(
                        Section::create(
                            KeyValue::create(
                                trans('mail.assigned_to'),
                                (string) ($this->target?->display_name ?? ''),
                                (string) ($this->item->formatted_msisdn ?? ''),
                            )->onClick(route('sims.show', $this->item->id))
                        )
                    )
            );
    }
}

<?php

namespace App\Notifications;

use App\Models\EventReminderSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderSubscriptionConfirmation extends Notification
{
    public function __construct(public readonly EventReminderSubscription $subscription, public readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Reminder subscription confirmed: ' . $this->subscription->event->title)
            ->line('Your event reminder subscription is active.')
            ->action('Unsubscribe', url("/l/{$this->subscription->lodge->slug}/reminders/unsubscribe/{$this->token}"));
    }
}

<?php

namespace App\Notifications;

use App\Models\EventReminderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification
{
    use Queueable;

    public function __construct(public readonly EventReminderDelivery $delivery)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $occurrence = $this->delivery->occurrence;
        $event = $this->delivery->event;

        $message = (new MailMessage)
            ->subject('Reminder: ' . $event->title)
            ->greeting('Hello' . ($this->delivery->subscription->name ? ' ' . $this->delivery->subscription->name : '') . ',')
            ->line('This is a reminder for ' . $event->title . '.')
            ->line('When: ' . $occurrence->starts_at->setTimezone($event->time_zone)->format('l, F j, Y g:i A T'));

        $location = $occurrence->location_name_override ?: $event->location_name;
        if ($location) {
            $message->line('Where: ' . $location);
        }

        return $message;
    }
}

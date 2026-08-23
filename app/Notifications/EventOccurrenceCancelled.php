<?php

namespace App\Notifications;

use App\Models\EventOccurrence;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventOccurrenceCancelled extends Notification
{
    public function __construct(public readonly EventOccurrence $occurrence)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Event cancelled: ' . ($this->occurrence->title_override ?: $this->occurrence->event->title))
            ->line('This event occurrence has been cancelled.')
            ->line($this->occurrence->starts_at->copy()->setTimezone($this->occurrence->event->time_zone)->toDayDateTimeString());
    }
}

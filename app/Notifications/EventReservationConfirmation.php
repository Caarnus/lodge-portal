<?php

namespace App\Notifications;

use App\Models\EventReservation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReservationConfirmation extends Notification
{
    public function __construct(public readonly EventReservation $reservation, public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Reservation confirmed: '.$this->reservation->event->title)
            ->line('Your reservation is confirmed.')
            ->action('Manage reservation', url("/l/{$this->reservation->lodge->slug}/reservations/cancel/{$this->token}"));
    }
}

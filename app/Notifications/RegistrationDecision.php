<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationDecision extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $decision,
        public readonly ?string $lodgeName,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->decision === 'approved';
        $message = (new MailMessage)
            ->subject('Registration '.($approved ? 'approved' : 'rejected'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your registration'.($this->lodgeName ? ' for '.$this->lodgeName : '').' has been '.$this->decision.'.');

        if (! $approved && $this->reason) {
            $message->line('Reason: '.$this->reason);
        }

        return $approved
            ? $message->action('Open the lodge portal', route('dashboard'))
            : $message;
    }
}

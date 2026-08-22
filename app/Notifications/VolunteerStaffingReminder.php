<?php

namespace App\Notifications;

use App\Models\EventVolunteerReminderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VolunteerStaffingReminder extends Notification
{
    use Queueable;
    public function __construct(private readonly EventVolunteerReminderDelivery $delivery) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $occurrence = $this->delivery->occurrence; $event = $this->delivery->event; $position = $this->delivery->position;
        return (new MailMessage)->subject("Volunteer staffing reminder: {$position->name}")->greeting("Volunteer staffing reminder for {$event->title}")->line("Position: {$position->name}")->line('This is a staffing commitment reminder, not an attendance reservation or ordinary event reminder.')->line($occurrence->starts_at->setTimezone($event->time_zone)->format('l, F j, Y g:i A T'));
    }
}

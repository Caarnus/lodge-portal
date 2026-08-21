<?php

namespace App\Enums;

enum ReminderSubscriptionStatus: string
{
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';
}

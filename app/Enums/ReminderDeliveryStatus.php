<?php

namespace App\Enums;

enum ReminderDeliveryStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';
}

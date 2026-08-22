<?php

namespace App\Enums;

enum VolunteerReminderDeliveryStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';
}

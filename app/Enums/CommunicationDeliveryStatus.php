<?php

namespace App\Enums;

enum CommunicationDeliveryStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Prepared = 'prepared';
    case Mailed = 'mailed';
}

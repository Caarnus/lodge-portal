<?php

namespace App\Enums;

enum LodgeCommunicationStatus: string
{
    case Draft = 'draft';
    case Sending = 'sending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
}

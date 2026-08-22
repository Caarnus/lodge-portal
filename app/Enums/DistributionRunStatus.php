<?php

namespace App\Enums;

enum DistributionRunStatus: string
{
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Sending = 'sending';
    case Completed = 'completed';
    case CompletedWithFailures = 'completed_with_failures';
    case Cancelled = 'cancelled';
}

<?php

namespace App\Enums;

enum DeliveryChannel: string
{
    case Email = 'email';
    case Postal = 'postal';
}

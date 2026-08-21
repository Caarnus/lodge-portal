<?php

namespace App\Enums;

enum ReservationFieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Select = 'select';
    case Checkbox = 'checkbox';
}

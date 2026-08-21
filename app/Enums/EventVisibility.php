<?php

namespace App\Enums;

enum EventVisibility: string
{
    case Public = 'public';
    case Masons = 'masons';
    case Lodge = 'lodge';
}

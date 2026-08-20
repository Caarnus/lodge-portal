<?php

namespace App\Enums;

enum LodgeStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case DisabledLocked = 'disabled_locked';
}

<?php

namespace App\Enums;

enum EventQualification: string
{
    case EnteredApprentice = 'ea';
    case FellowCraft = 'fc';
    case MasterMason = 'mm';
    case PastMaster = 'pm';
}

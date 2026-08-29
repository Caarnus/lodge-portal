<?php

namespace App\Enums;

enum RitualProficiencyStatus: string
{
    case NotKnown = 'not_known';
    case Learning = 'learning';
    case Proficient = 'proficient';
}

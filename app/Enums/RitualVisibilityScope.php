<?php

namespace App\Enums;

enum RitualVisibilityScope: string
{
    case Hidden = 'hidden';
    case OwnLodge = 'own_lodge';
    case ParticipatingLodges = 'participating_lodges';
}

<?php

namespace App\Enums;

enum DirectoryVisibilityScope: string
{
    case Hidden = 'hidden';
    case OwnLodge = 'own_lodge';
    case ParticipatingLodges = 'participating_lodges';
}

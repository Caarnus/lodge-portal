<?php

namespace App\Enums;

enum DirectoryAudience: string
{
    case OwnLodge = 'own_lodge';
    case ParticipatingLodges = 'participating_lodges';
}

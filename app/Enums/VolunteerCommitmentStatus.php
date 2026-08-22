<?php

namespace App\Enums;

enum VolunteerCommitmentStatus: string
{
    case Committed = 'committed';
    case Withdrawn = 'withdrawn';
    case AdministrativelyRemoved = 'administratively_removed';
}

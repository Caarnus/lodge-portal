<?php

namespace App\Enums;

enum WebsitePageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

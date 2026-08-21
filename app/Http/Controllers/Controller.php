<?php

namespace App\Http\Controllers;

use App\Models\Lodge;

abstract class Controller
{
    protected function allowLodge(Lodge $lodge, string $permission): void
    {
        abort_unless(request()->user()?->hasLodgePermission($lodge, $permission), 403);
    }
}

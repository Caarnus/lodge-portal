<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApproved
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->approval_status !== 'approved') {
            return redirect()->route('pending');
        }

        return $next($request);
    }
}

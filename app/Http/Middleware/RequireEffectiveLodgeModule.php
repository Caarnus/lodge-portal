<?php

namespace App\Http\Middleware;

use App\Models\Lodge;
use App\Services\LodgeModuleState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEffectiveLodgeModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $lodge = $request->route('lodge');
        if (! $lodge instanceof Lodge) {
            $lodge = Lodge::find($lodge);
        }
        abort_unless($lodge instanceof Lodge && app(LodgeModuleState::class)->isEffective($lodge, $module), 404);

        return $next($request);
    }
}

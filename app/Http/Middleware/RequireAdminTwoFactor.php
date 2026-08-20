<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAdminTwoFactor
{
    public function handle(Request $r, Closure $next)
    {
        $u = $r->user();
        $admin = $u?->is_platform_admin || ($u?->lodges()->exists());
        if (config('security.admin_2fa_required') && $admin && ! $u?->two_factor_confirmed_at) {
            return redirect()->route('two-factor.settings')->with('error', 'Two-factor authentication is required for administrators.');
        }

        return $next($r);
    }
}

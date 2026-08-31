<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Lodge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        $returnTo = $request->query('return_to');
        if (is_string($returnTo) && str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//')) {
            $request->session()->put('url.intended', $returnTo);
            $request->session()->forget('auth_lodge_id');
            $path = parse_url($returnTo, PHP_URL_PATH);
            if (is_string($path) && preg_match('#^/l/([^/]+)#', $path, $matches)) {
                $lodgeId = Lodge::where('slug', $matches[1])->where('status', 'active')->value('id');
                if ($lodgeId) {
                    $request->session()->put('auth_lodge_id', $lodgeId);
                }
            }
        } else {
            $request->session()->forget('auth_lodge_id');
        }

        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

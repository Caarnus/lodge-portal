<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Lodge;
use App\Models\Person;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register', ['lodges' => Lodge::where('status', 'active')->orderBy('name')->get(['id', 'name', 'number'])]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'home_lodge_id' => [
                'required',
                Rule::exists('lodges', 'id')->where('status', 'active'),
            ],
        ]);

        $people = Person::whereRaw('lower(email) = ?', [strtolower($request->email)])
            ->limit(2)
            ->pluck('id');
        $person = $people->count() === 1 ? $people->first() : null;
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'home_lodge_id' => $request->integer('home_lodge_id'),
            'person_id' => $person,
            'approval_status' => 'pending',
        ]);

        event(new Registered($user));

        Auth::login($user);

        return to_route('pending');
    }
}

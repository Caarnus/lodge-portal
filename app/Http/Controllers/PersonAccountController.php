<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit;
use App\Services\PersonAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PersonAccountController extends Controller
{
    public function store(Request $request, Lodge $lodge, Person $person, PersonAccess $access)
    {
        abort_unless($access->canManagePerson($request->user(), $lodge, $person), 403);
        if (! $person->email) {
            throw ValidationException::withMessages(['email' => 'Add an email address before inviting this person.']);
        }

        $user = DB::transaction(function () use ($request, $person, $lodge) {
            $user = User::query()->where('email', $person->email)->first();
            if ($person->user || ($user?->person_id && $user->person_id !== $person->id)) {
                throw ValidationException::withMessages(['email' => 'This person or email is already linked to another account.']);
            }
            if ($user && strtolower($user->email) !== strtolower($person->email)) {
                throw ValidationException::withMessages(['email' => 'The account email does not match this person.']);
            }
            $user ??= User::create([
                'name' => $person->display_name,
                'email' => $person->email,
                'password' => Str::password(32),
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ]);
            $user->update(['person_id' => $person->id]);
            Audit::record('person.account_linked', $person, $lodge, null, ['user_id' => $user->id]);

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('notice', 'The account invitation has been sent.');
    }

    public function revoke(Request $request, Lodge $lodge, Person $person, PersonAccess $access)
    {
        abort_unless($access->canView($request->user(), $lodge, $person), 404);
        $this->allowLodge($lodge, 'roles.manage');
        $user = $person->user;
        abort_unless($user, 404);
        $deleted = DB::table('lodge_user_roles')->where('lodge_id', $lodge->id)->where('user_id', $user->id)->delete();
        Audit::record('person.lodge_access_revoked', $person, $lodge, ['role_assignments' => $deleted], ['role_assignments' => 0]);

        return back();
    }
}

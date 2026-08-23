<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SelfProfileService
{
    public function personFor(User $user): Person
    {
        $person = filled($user->person_id)
            ? Person::withTrashed()->find($user->person_id)
            : null;

        if (!$person || $person->trashed() || $person->merged_at || $person->is_deceased) {
            throw new AuthorizationException('Your account is not linked to an active person profile.');
        }

        return $person;
    }

    public function update(User $actor, array $data): User
    {
        $expectedPersonId = $actor->person_id;
        if (!$expectedPersonId) {
            throw new AuthorizationException('Your account is not linked to an active person profile.');
        }

        try {
            $result = DB::transaction(function () use ($actor, $expectedPersonId, $data) {
                $user = User::query()->lockForUpdate()->findOrFail($actor->id);
                if ($user->person_id !== $expectedPersonId) {
                    throw new AuthorizationException('Your account link changed before this profile update could be saved.');
                }

                $person = Person::withTrashed()->lockForUpdate()->find($expectedPersonId);
                if (!$person || $person->trashed() || $person->merged_at || $person->is_deceased) {
                    throw new AuthorizationException('Your account is not linked to an active person profile.');
                }

                $email = $data['email'];
                $this->ensureEmailIsAvailable($email, $user, $person);
                $personData = Arr::only($data, [
                    'preferred_name',
                    'email',
                    'phone',
                    'mailing_address_line_1',
                    'mailing_address_line_2',
                    'mailing_city',
                    'mailing_state',
                    'mailing_postal_code',
                ]);
                $changedFields = collect($personData)->filter(fn($value, string $field) => $person->getAttribute($field) !== $value)
                    ->keys()->all();
                $emailChanged = $user->email !== $email;

                $person->fill($personData);
                $person->save();
                $user->forceFill([
                    'name' => $person->fresh()->display_name,
                    'email' => $email,
                ]);
                if ($emailChanged) {
                    $user->email_verified_at = null;
                }
                $user->save();

                if ($changedFields !== [] || $user->wasChanged(['name', 'email', 'email_verified_at'])) {
                    Audit::record('profile.updated', $person, null, [
                        'fields' => $changedFields,
                    ], [
                        'fields' => $changedFields,
                        'email_changed' => $emailChanged,
                    ]);
                }

                return ['user' => $user->fresh(), 'email_changed' => $emailChanged];
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'That email address is already in use.']);
        }

        if ($result['email_changed']) {
            $result['user']->sendEmailVerificationNotification();
        }

        return $result['user'];
    }

    private function ensureEmailIsAvailable(string $email, User $user, Person $person): void
    {
        $userConflict = User::query()->whereKeyNot($user->id)->whereRaw('LOWER(email) = ?', [$email])->exists();
        $personConflict = Person::withTrashed()->whereKeyNot($person->id)->whereRaw('LOWER(email) = ?', [$email])->exists();

        if ($userConflict || $personConflict) {
            throw ValidationException::withMessages(['email' => 'That email address is already in use.']);
        }
    }
}

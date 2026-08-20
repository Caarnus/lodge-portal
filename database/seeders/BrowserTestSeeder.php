<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('E2E_USER_PASSWORD');
        if (! app()->environment('local') || ! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('Browser test users require a local environment and E2E_USER_PASSWORD of at least 12 characters.');
        }

        foreach (['lodge-a-admin', 'lodge-b-admin', 'multi-lodge-admin'] as $name) {
            $user = User::firstOrNew(['email' => $name.'@example.test']);
            $user->forceFill([
                'name' => str($name)->replace('-', ' ')->title(),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'approval_status' => 'approved',
                'approved_at' => now(),
            ])->save();
        }
    }
}

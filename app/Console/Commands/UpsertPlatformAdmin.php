<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UpsertPlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {--name=} {--password=}';

    protected $description = 'Create or update the initial platform administrator';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password');
        if (! $password || strlen($password) < 12) {
            $this->error('A password of at least 12 characters is required.');

            return self::FAILURE;
        }

        $u = User::firstOrNew(['email' => strtolower($this->argument('email'))]);
        $u->forceFill([
            'name' => $this->option('name') ?: 'Platform Administrator',
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
        ])->save();
        $this->info("Platform administrator ready: {$u->email}");

        return self::SUCCESS;
    }
}

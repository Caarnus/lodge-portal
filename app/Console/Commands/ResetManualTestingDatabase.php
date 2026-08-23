<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetManualTestingDatabase extends Command
{
    protected $signature = 'manual:reset {--force : Reset without confirmation}';

    protected $description = 'DESTROYS all database data, reruns migrations, and loads deterministic manual-test data.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Destroy all database data and rebuild manual test data?')) {
            return self::FAILURE;
        }

return $this->call('migrate:fresh', ['--seed' => true, '--seeder' => 'ManualTestingSeeder', '--force' => true]);
    }
}

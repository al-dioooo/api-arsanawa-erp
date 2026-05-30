<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantDeveloperRole extends Command
{
    protected $signature = 'developer:grant {email : Existing user email address}';

    protected $description = 'Grant the global Developer role to an existing user.';

    public function handle(): int
    {
        $user = User::query()
            ->where('email', $this->argument('email'))
            ->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $user->forceFill(['is_developer' => true])->save();

        $this->info("Developer role granted to {$user->email}.");

        return self::SUCCESS;
    }
}

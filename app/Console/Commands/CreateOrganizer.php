<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateOrganizer extends Command
{
    protected $signature = 'rsvp:create-organizer {--allow-short-password : Allow a password of at least 8 characters for a temporary account}';

    protected $description = 'Create an organizer account for the RSVP dashboard';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Organizer name'));
        $username = strtolower(trim((string) $this->ask('Organizer username')));
        $email = strtolower(trim((string) $this->ask('Organizer email', $username.'@localhost.test')));
        $minimumPasswordLength = $this->option('allow-short-password') ? 8 : 12;
        $password = (string) $this->secret("Organizer password (at least {$minimumPasswordLength} characters)");

        $validator = validator(
            ['name' => $name, 'username' => $username, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:users,username'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', "min:{$minimumPasswordLength}"],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info('Organizer account created.');

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $username = config('admin.username');
        $password = config('admin.password');

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Set ADMIN_EMAIL to a valid email address before running the admin seeder.');
        }

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        if (! is_string($username) || $username === '') {
            throw new RuntimeException('Set ADMIN_USERNAME before running the admin seeder.');
        }

        if (! is_string($password) || mb_strlen($password) < 12) {
            throw new RuntimeException('Set ADMIN_PASSWORD to a password of at least 12 characters before running the admin seeder.');
        }

        User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.name', 'Admin'),
                'username' => $username,
                'password' => Hash::make($password),
            ],
        );
    }
}

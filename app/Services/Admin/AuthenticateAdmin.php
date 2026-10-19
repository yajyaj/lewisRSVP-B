<?php

namespace App\Services\Admin;

use App\Models\AdminAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateAdmin
{
    public function authenticate(string $identifier, string $password, string $field = 'identifier'): User
    {
        $identifier = strtolower(trim($identifier));
        $user = User::query()->where('email', $identifier)->orWhere('username', $identifier)->first();

        if (! $user || ! Hash::check($password, $user->password) || ! $user->canAccessAdmin()) {
            throw ValidationException::withMessages([
                $field => ['The provided credentials are incorrect.'],
            ]);
        }

        AdminAuditEvent::create(['actor_id' => $user->id, 'action' => 'auth.login']);

        return $user;
    }
}

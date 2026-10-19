<?php

namespace App\Services\Admin;

use App\Models\AdminAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function save(User $actor, array $data, bool $password = false): User
    {
        return DB::transaction(function () use ($actor, $data, $password): User {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($user)->authorize('updateProfile', $user);
            if (! hash_equals($user->editVersion(), $data['version'])) {
                throw ValidationException::withMessages(['version' => ['Your account changed. Reload before saving.']]);
            }
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['The password is incorrect.']]);
            }
            if ($password) {
                $user->password = $data['password'];
            } else {
                if (User::query()->whereKeyNot($user->id)->where(fn ($query) => $query->where('email', $data['email'])->orWhere('username', $data['email']))->exists()) {
                    throw ValidationException::withMessages(['email' => ['This login identifier is already in use.']]);
                }
                $user->fill(['name' => $data['name'], 'email' => $data['email']]);
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
            }
            $user->save();
            if ($password) {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            AdminAuditEvent::create(['actor_id' => $user->id, 'subject_id' => $user->id, 'action' => $password ? 'user.password_changed' : 'profile.updated', 'reason' => 'Changed by the account owner.']);

            return $user;
        }, 3);
    }
}

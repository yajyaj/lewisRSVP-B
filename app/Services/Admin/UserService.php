<?php

namespace App\Services\Admin;

use App\Models\AdminAuditEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function save(?User $subject, array $data, User $actor): User
    {
        return DB::transaction(function () use ($subject, $data, $actor): User {
            $accounts = User::query()->orderBy('id')->lockForUpdate()->get();
            $actor = $this->actor($accounts, $actor, $data['current_password']);
            if ($subject) {
                $subject = $accounts->firstWhere('id', $subject->id);
                abort_unless($subject, 404);
                Gate::forUser($actor)->authorize('update', $subject);
                $this->version($subject, $data['version']);
                $this->safeguards($subject, $data['role'], (bool) $data['is_active'], $actor, $accounts);
            } else {
                Gate::forUser($actor)->authorize('create', User::class);
                $subject = new User;
                $subject->password = $data['password'];
            }
            $others = $accounts->reject(fn (User $user): bool => $user->id === $subject->id);
            foreach (['email', 'username'] as $field) {
                $value = $data[$field] ?? null;
                if ($value && $others->contains(fn (User $user): bool => strtolower($user->email) === strtolower($value) || strtolower($user->username ?? '') === strtolower($value))) {
                    throw ValidationException::withMessages([$field => ['This login identifier is already in use.']]);
                }
            }
            $new = ! $subject->exists;
            $wasActive = $subject->is_active;
            $subject->fill(['name' => $data['name'], 'email' => $data['email'], 'username' => $data['username'] ?? null]);
            if ($subject->isDirty('email')) {
                $subject->email_verified_at = null;
            }
            $subject->role = $data['role'];
            $subject->is_active = (bool) $data['is_active'];
            $subject->save();
            if ($wasActive && ! $subject->is_active) {
                $this->invalidateSessions($subject);
            }
            AdminAuditEvent::create(['actor_id' => $actor->id, 'subject_id' => $subject->id, 'action' => $new ? 'user.created' : (($wasActive && ! $subject->is_active) ? 'user.deactivated' : 'user.updated'), 'reason' => $data['change_reason']]);

            return $subject;
        }, 3);
    }

    public function deactivate(User $subject, array $data, User $actor): User
    {
        return DB::transaction(function () use ($subject, $data, $actor): User {
            $accounts = User::query()->orderBy('id')->lockForUpdate()->get();
            $actor = $this->actor($accounts, $actor, $data['current_password']);
            $subject = $accounts->firstWhere('id', $subject->id);
            abort_unless($subject, 404);
            Gate::forUser($actor)->authorize('delete', $subject);
            $this->version($subject, $data['version']);
            $this->safeguards($subject, $subject->role, false, $actor, $accounts);
            if (! $subject->is_active) {
                throw ValidationException::withMessages(['user' => ['This account is already inactive.']]);
            }
            $subject->is_active = false;
            $subject->save();
            $this->invalidateSessions($subject);
            AdminAuditEvent::create(['actor_id' => $actor->id, 'subject_id' => $subject->id, 'action' => 'user.deactivated', 'reason' => $data['change_reason']]);

            return $subject;
        }, 3);
    }

    public function password(User $subject, array $data, User $actor): User
    {
        return DB::transaction(function () use ($subject, $data, $actor): User {
            $accounts = User::query()->orderBy('id')->lockForUpdate()->get();
            $actor = $this->actor($accounts, $actor, $data['current_password']);
            $subject = $accounts->firstWhere('id', $subject->id);
            abort_unless($subject, 404);
            Gate::forUser($actor)->authorize('resetPassword', $subject);
            $this->version($subject, $data['version']);
            $subject->password = $data['password'];
            $subject->save();
            $this->invalidateSessions($subject);
            AdminAuditEvent::create(['actor_id' => $actor->id, 'subject_id' => $subject->id, 'action' => 'user.password_changed', 'reason' => $data['change_reason']]);

            return $subject;
        }, 3);
    }

    private function actor(Collection $accounts, User $actor, string $password): User
    {
        $fresh = $accounts->firstWhere('id', $actor->id);
        abort_unless($fresh?->isSuperAdmin(), 403);
        if (! Hash::check($password, $fresh->password)) {
            throw ValidationException::withMessages(['current_password' => ['The password is incorrect.']]);
        }

        return $fresh;
    }

    private function safeguards(User $subject, string $role, bool $active, User $actor, Collection $accounts): void
    {
        if ($subject->id === $actor->id && ! $active) {
            throw ValidationException::withMessages(['is_active' => ['You cannot deactivate your own account.']]);
        }
        if ($subject->isSuperAdmin() && ($role !== 'super_admin' || ! $active) && $accounts->filter(fn (User $user): bool => $user->isSuperAdmin())->count() <= 1) {
            throw ValidationException::withMessages(['role' => ['Keep at least one active Super Admin.']]);
        }
    }

    private function version(User $subject, string $version): void
    {
        if (! hash_equals($subject->editVersion(), $version)) {
            throw ValidationException::withMessages(['version' => ['This account changed. Reload before saving.']]);
        }
    }

    private function invalidateSessions(User $subject): void
    {
        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $subject->id)->delete();
    }
}

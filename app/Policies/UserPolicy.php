<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function updateProfile(User $user, User $subject): bool
    {
        return $user->canAccessAdmin() && $user->id === $subject->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $subject): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $subject): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->isSuperAdmin();
    }

    public function resetPassword(User $user, User $subject): bool
    {
        return $user->isSuperAdmin();
    }
}

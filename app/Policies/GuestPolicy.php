<?php

namespace App\Policies;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GuestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, Guest $guest): Response
    {
        return (new EventProjectPolicy)->view($user, $guest->eventProject);
    }

    public function update(User $user, Guest $guest): Response
    {
        return $this->view($user, $guest);
    }
}

<?php

namespace App\Policies;

use App\Models\RsvpResponse;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RsvpResponsePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, RsvpResponse $reservation): Response
    {
        return (new EventProjectPolicy)->view($user, $reservation->eventProject);
    }

    public function update(User $user, RsvpResponse $reservation): Response
    {
        return $this->view($user, $reservation);
    }

    public function delete(User $user, RsvpResponse $reservation): Response
    {
        return $this->view($user, $reservation);
    }

    public function restore(User $user, RsvpResponse $reservation): Response
    {
        return $this->view($user, $reservation);
    }
}

<?php

namespace App\Policies;

use App\Models\EventProject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, EventProject $event): Response
    {
        return $user->canAccessAdmin() && ($user->isSuperAdmin() || $user->id === $event->owner_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, EventProject $event): Response
    {
        return $this->view($user, $event);
    }

    public function delete(User $user, EventProject $event): Response
    {
        return $this->view($user, $event);
    }
}

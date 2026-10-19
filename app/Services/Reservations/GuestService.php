<?php

namespace App\Services\Reservations;

use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GuestService
{
    public function update(Guest $guest, array $data, User $actor): Guest
    {
        return DB::transaction(function () use ($guest, $data, $actor): Guest {
            EventProject::query()->lockForUpdate()->findOrFail($guest->event_project_id);
            $responses = $guest->rsvpResponses()->orderBy('id')->lockForUpdate()->get();
            $guest = Guest::query()->lockForUpdate()->findOrFail($guest->id);
            Gate::forUser($actor)->authorize('update', $guest);
            if (! hash_equals($guest->editVersion(), $data['version'])) {
                throw ValidationException::withMessages(['version' => ['This contact changed. Reload before saving.']]);
            }
            $guest->fill(['full_name' => trim($data['full_name']), 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null])->save();
            foreach ($responses as $response) {
                $response->revision++;
                $response->save();
            }
            AdminAuditEvent::create(['actor_id' => $actor->id, 'subject_id' => $guest->id, 'action' => 'guest.updated', 'reason' => $data['change_reason']]);

            return $guest;
        }, 3);
    }
}

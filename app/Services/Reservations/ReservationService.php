<?php

namespace App\Services\Reservations;

use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Models\Invitation;
use App\Models\RsvpResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private ReservationRules $rules) {}

    public function respond(EventProject $event, ?Invitation $invitation, array $input): RsvpResponse
    {
        return DB::transaction(function () use ($event, $invitation, $input): RsvpResponse {
            $event = EventProject::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($event->status === 'published' && $event->access_mode === ($invitation ? 'private' : 'public'), 404);
            if ($event->rsvp_deadline?->isPast()) {
                throw ValidationException::withMessages(['rsvp' => ['The RSVP deadline for this event has passed.']]);
            }
            if ($invitation) {
                $invitation = Invitation::query()->where('event_project_id', $event->id)->where('status', 'active')->findOrFail($invitation->id);
            }
            $rules = $this->rules->rules($event, true);
            if (! $invitation) {
                $rules['response_key'] = ['required', 'uuid'];
            }
            $data = $this->rules->normalize(Validator::make($input, $rules)->validate());
            $hash = $invitation ? null : hash('sha256', $data['response_key']);
            $response = $event->rsvpResponses()->when($invitation, fn ($query) => $query->where('invitation_id', $invitation->id), fn ($query) => $query->where('public_response_key_hash', $hash))->lockForUpdate()->first();
            if ($response?->cancelled_at) {
                throw ValidationException::withMessages(['rsvp' => ['This reservation was cancelled by the organizer. Please contact the host.']]);
            }
            if (! $response) {
                $guest = $invitation?->guest ?? $event->guests()->create($this->contact($data));
                $response = new RsvpResponse;
                $response->eventProject()->associate($event);
                $response->guest()->associate($guest);
                $response->invitation()->associate($invitation);
                $response->public_response_key_hash = $hash;
            } elseif (! $invitation) {
                $response->guest->update($this->contact($data));
            }
            $this->persist($response, $data, true);
            $this->audit($response, null, 'reservation.responded');

            return $response;
        }, 3);
    }

    public function create(EventProject $event, array $input, User $actor): RsvpResponse
    {
        return DB::transaction(function () use ($event, $input, $actor): RsvpResponse {
            $event = EventProject::query()->lockForUpdate()->findOrFail($event->id);
            Gate::forUser($actor)->authorize('update', $event);
            $hash = hash('sha256', $input['submission_key']);
            $existing = $event->rsvpResponses()->where('public_response_key_hash', $hash)->first();
            if ($existing) {
                return $existing;
            }
            $data = $this->validateAdmin($event, $input);
            $guest = $event->guests()->create($this->contact($data));
            $response = new RsvpResponse;
            $response->eventProject()->associate($event);
            $response->guest()->associate($guest);
            $response->public_response_key_hash = $hash;
            $this->persist($response, $data);
            $this->audit($response, $actor, 'reservation.created', $input['change_reason']);

            return $response;
        }, 3);
    }

    public function update(RsvpResponse $reservation, array $input, User $actor, bool $legacy = false): RsvpResponse
    {
        return DB::transaction(function () use ($reservation, $input, $actor, $legacy): RsvpResponse {
            EventProject::query()->lockForUpdate()->findOrFail($reservation->event_project_id);
            $reservation = RsvpResponse::query()->lockForUpdate()->findOrFail($reservation->id);
            Gate::forUser($actor)->authorize('update', $reservation);
            if ($reservation->cancelled_at) {
                throw ValidationException::withMessages(['reservation' => ['Restore this reservation before editing it.']]);
            }
            $data = $this->validateAdmin($reservation->eventProject, $input, $reservation);
            $this->checkRevision($reservation, $data['revision'] ?? null);
            $reservation->guest->update($this->contact($data));
            $this->persist($reservation, $data);
            $this->audit($reservation, $actor, 'reservation.updated', $data['change_reason'] ?? ($legacy ? 'Updated through the existing organizer API.' : null));

            return $reservation->load('guest');
        }, 3);
    }

    public function setCancelled(RsvpResponse $reservation, bool $cancelled, array $input, User $actor): RsvpResponse
    {
        return DB::transaction(function () use ($reservation, $cancelled, $input, $actor): RsvpResponse {
            EventProject::query()->lockForUpdate()->findOrFail($reservation->event_project_id);
            $reservation = RsvpResponse::query()->lockForUpdate()->findOrFail($reservation->id);
            Gate::forUser($actor)->authorize($cancelled ? 'delete' : 'restore', $reservation);
            $this->checkRevision($reservation, $input['revision']);
            if ((bool) $reservation->cancelled_at === $cancelled) {
                throw ValidationException::withMessages(['reservation' => ['The reservation is already in that state. Reload the page.']]);
            }
            $reservation->cancelled_at = $cancelled ? now() : null;
            $reservation->revision++;
            $reservation->save();
            $this->audit($reservation, $actor, $cancelled ? 'reservation.cancelled' : 'reservation.restored', $input['change_reason']);

            return $reservation;
        }, 3);
    }

    private function validateAdmin(EventProject $event, array $input, ?RsvpResponse $existing = null): array
    {
        $rules = $this->rules->rules($event);
        $rules['revision'] = ['nullable', 'integer', 'min:1'];
        $rules['change_reason'] = ['nullable', 'string', 'max:500'];
        if ($existing && (int) ($input['party_size'] ?? 0) === $existing->party_size) {
            $rules['party_size'] = ['required', 'integer', 'min:0', 'max:'.max($existing->party_size, $event->max_party_size)];
            $rules['additional_guest_names'] = ['nullable', 'array'];
        }
        if ($existing && ($input['attendance_status'] ?? '') === 'declined') {
            $rules['additional_guest_names'] = ['nullable', 'array', 'max:19'];
        }
        if ($existing && ! array_key_exists('additional_guest_names', $input)) {
            $input['additional_guest_names'] = $existing->additional_guest_names;
        }

        return $this->rules->normalize(Validator::make($input, $rules)->validate());
    }

    private function persist(RsvpResponse $response, array $data, bool $public = false): void
    {
        $new = ! $response->exists;
        $response->fill([
            'attendance_status' => $data['attendance_status'], 'party_size' => $data['party_size'],
            'additional_guest_names' => $data['additional_guest_names'] ?? [],
            'dietary_or_accessibility_notes' => $data['dietary_or_accessibility_notes'] ?? null,
            'message' => $data['message'] ?? null, 'submitted_at' => ($new || $public) ? now() : $response->submitted_at,
        ]);
        $response->revision = $new ? 1 : $response->revision + 1;
        $response->save();
    }

    private function contact(array $data): array
    {
        return ['full_name' => $data['full_name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null];
    }

    private function checkRevision(RsvpResponse $response, mixed $revision): void
    {
        if ($revision !== null && (int) $revision !== $response->revision) {
            throw ValidationException::withMessages(['revision' => ['This reservation changed since you opened it. Reload before saving.']]);
        }
    }

    private function audit(RsvpResponse $response, ?User $actor, string $action, ?string $reason = null): void
    {
        AdminAuditEvent::create(['actor_id' => $actor?->id, 'subject_id' => $response->id, 'action' => $action, 'reason' => $reason]);
    }
}

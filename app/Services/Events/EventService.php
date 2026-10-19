<?php

namespace App\Services\Events;

use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EventService
{
    public function __construct(private EventRules $rules) {}

    public function archive(EventProject $event, User $actor, array $data): void
    {
        DB::transaction(function () use ($event, $actor, $data): void {
            $event = EventProject::query()->lockForUpdate()->findOrFail($event->id);
            $actor = $actor->fresh();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('delete', $event);
            if (! hash_equals($event->editVersion(), $data['version'])) {
                throw ValidationException::withMessages(['version' => ['This event changed. Reload before deleting.']]);
            }
            if ($event->status === 'archived') {
                throw ValidationException::withMessages(['event' => ['This event is already archived.']]);
            }
            $event->update(['status' => 'archived']);
            AdminAuditEvent::create(['actor_id' => $actor->id, 'subject_id' => $event->id, 'action' => 'event.archived', 'reason' => $data['deletion_reason']]);
        }, 3);
    }

    public function save(?EventProject $event, array $input, User $actor, ?string $version = null, ?string $reason = null): EventProject
    {
        return DB::transaction(function () use ($event, $input, $actor, $version, $reason): EventProject {
            if ($event) {
                $event = EventProject::query()->lockForUpdate()->findOrFail($event->id);
            }
            $actor = $actor->fresh();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize($event ? 'update' : 'create', $event ?? EventProject::class);
            if ($event && $version !== null && ! hash_equals($event->editVersion(), $version)) {
                throw ValidationException::withMessages(['version' => ['This event changed. Reload before saving.']]);
            }
            $data = Validator::make($input, $this->rules->rules($event))->validate();
            if (! empty($data['start_time']) && ! empty($data['end_time']) && $data['end_time'] <= $data['start_time']) {
                throw ValidationException::withMessages(['end_time' => ['End time must be later than start time on the event date.']]);
            }
            if (array_key_exists('event_stops', $data) && ! empty($data['event_stops'])) {
                foreach ($data['event_stops'] as $index => $stop) {
                    if (! empty($stop['start_time']) && ! empty($stop['end_time']) && $stop['end_time'] <= $stop['start_time']) {
                        throw ValidationException::withMessages(['event_stops.'.$index.'.end_time' => ['Occasion end time must be later than its start time.']]);
                    }
                }
                $first = $data['event_stops'][0];
                $data['occasion_labels'] = array_column($data['event_stops'], 'label');
                foreach (['venue_name', 'venue_address', 'map_url', 'start_time', 'end_time'] as $field) {
                    $data[$field] = $first[$field] ?? null;
                }
            }
            $labels = $data['occasion_labels'] ?? null;
            unset($data['occasion_labels']);
            $revoke = $event?->access_mode === 'private' && $data['access_mode'] === 'public';
            $new = $event === null;
            if ($new) {
                $event = $actor->eventProjects()->create($data);
            } else {
                $event->fill($data)->save();
            }
            if ($labels !== null && $event->occasionLabels()->pluck('label')->all() !== array_values($labels)) {
                $event->occasionLabels()->delete();
                foreach ($labels as $position => $label) {
                    $event->occasionLabels()->create(['label' => $label, 'display_order' => $position]);
                }
            }
            if ($revoke) {
                $event->invitations()->where('status', 'active')->update(['status' => 'revoked', 'revoked_at' => now()]);
            }
            AdminAuditEvent::create(['actor_id' => $actor->id, 'subject_id' => $event->id, 'action' => $new ? 'event.created' : 'event.updated', 'reason' => $reason]);

            return $event->fresh()->load('occasionLabels');
        }, 3);
    }
}

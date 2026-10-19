<?php

namespace App\Services\Reservations;

use App\Models\EventProject;
use Illuminate\Validation\ValidationException;

class ReservationRules
{
    public function rules(EventProject $event, bool $public = false): array
    {
        $maximum = $event->allow_plus_ones ? $event->max_party_size : 1;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'attendance_status' => ['required', 'in:attending,declined'],
            'party_size' => [$public ? 'nullable' : 'required', 'integer', 'min:0', 'max:'.$maximum],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'additional_guest_names' => ['nullable', 'array', 'max:'.max(0, $maximum - 1)],
            'additional_guest_names.*' => ['required', 'string', 'max:100'],
            'dietary_or_accessibility_notes' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function normalize(array $data): array
    {
        $attending = $data['attendance_status'] === 'attending';
        $partySize = $attending ? (int) ($data['party_size'] ?? 1) : 0;
        if ($attending && $partySize < 1) {
            throw ValidationException::withMessages(['party_size' => ['Attending responses must include at least one attendee.']]);
        }
        if ($attending && count($data['additional_guest_names'] ?? []) > $partySize - 1) {
            throw ValidationException::withMessages(['additional_guest_names' => ['Enter no more names than the number of additional attendees.']]);
        }

        return array_merge($data, ['party_size' => $partySize, 'full_name' => trim($data['full_name'])]);
    }
}

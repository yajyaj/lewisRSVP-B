<?php

namespace App\Services\Events;

use App\Models\EventProject;
use Illuminate\Validation\Rule;

class EventRules
{
    public function rules(?EventProject $event = null): array
    {
        $slug = Rule::unique('event_projects', 'public_slug');
        if ($event) {
            $slug->ignore($event->id);
        }

        return [
            'event_stops' => ['sometimes', 'nullable', 'array', 'max:2'],
            'event_stops.*' => ['required', 'array:label,venue_name,venue_address,map_url,start_time,end_time'],
            'event_stops.*.label' => ['required', 'string', 'max:80'],
            'event_stops.*.venue_name' => ['nullable', 'string', 'max:255'],
            'event_stops.*.venue_address' => ['nullable', 'string', 'max:255'],
            'event_stops.*.map_url' => ['nullable', 'url:http,https', 'max:2048'],
            'event_stops.*.start_time' => ['nullable', 'date_format:H:i'],
            'event_stops.*.end_time' => ['nullable', 'date_format:H:i'],
            'internal_name' => ['required', 'string', 'max:120'],
            'public_slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug],
            'landing_subtitle' => ['sometimes', 'nullable', 'string', 'max:300'],
            'landing_title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'combined_title' => ['required', 'string', 'max:160'], 'welcome_message' => ['nullable', 'string', 'max:3000'],
            'event_date' => ['required', 'date'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'], 'venue_name' => ['nullable', 'string', 'max:255'], 'venue_address' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'url:http,https', 'max:2048'], 'rsvp_deadline' => ['nullable', 'date'],
            'cover_image_url' => ['nullable', 'url:http,https', 'max:2048'], 'gallery_urls' => ['nullable', 'array', 'max:12'], 'gallery_urls.*' => ['required', 'url:http,https', 'max:2048'],
            'theme' => ['required', Rule::in(['sage', 'rose', 'blue', 'cream'])], 'access_mode' => ['required', Rule::in(['public', 'private'])],
            'allow_plus_ones' => ['required', 'boolean'], 'max_party_size' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
            'occasion_labels' => ['sometimes', 'array', 'max:8'], 'occasion_labels.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
        ];
    }
}

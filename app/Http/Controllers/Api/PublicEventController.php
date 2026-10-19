<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventProject;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;

class PublicEventController extends Controller
{
    public function website(): JsonResponse
    {
        $slug = config('app.public_event_slug');
        abort_unless(is_string($slug) && $slug !== '', 404);

        return $this->show($slug);
    }

    public function show(string $slug): JsonResponse
    {
        $event = EventProject::query()
            ->where('public_slug', $slug)
            ->where('status', 'published')
            ->where('access_mode', 'public')
            ->with('occasionLabels')
            ->firstOrFail();

        return response()->json(['event' => $this->publicDetails($event)]);
    }

    public function invitation(string $token): JsonResponse
    {
        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->with(['eventProject.occasionLabels', 'guest'])
            ->firstOrFail();

        abort_unless(
            $invitation->eventProject->status === 'published'
                && $invitation->eventProject->access_mode === 'private',
            404,
        );

        return response()->json([
            'event' => $this->publicDetails($invitation->eventProject),
            'guest_name' => $invitation->guest->full_name,
        ]);
    }

    private function publicDetails(EventProject $event): array
    {
        return [
            'id' => $event->id,
            'public_slug' => $event->public_slug,
            'combined_title' => $event->combined_title,
            'landing_title' => $event->landing_title,
            'landing_subtitle' => $event->landing_subtitle,
            'welcome_message' => $event->welcome_message,
            'event_date' => $event->event_date->toDateString(),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'timezone' => $event->timezone,
            'event_stops' => $event->event_stops ?? [],
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'map_url' => $event->map_url,
            'rsvp_deadline' => $event->rsvp_deadline?->toIso8601String(),
            'cover_image_url' => $event->cover_image_url,
            'gallery_urls' => $event->gallery_urls ?? [],
            'theme' => $event->theme,
            'allow_plus_ones' => $event->allow_plus_ones,
            'max_party_size' => $event->max_party_size,
            'occasion_labels' => $event->occasionLabels->pluck('label')->values(),
        ];
    }
}

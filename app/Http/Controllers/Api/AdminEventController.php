<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventProject;
use App\Models\RsvpResponse;
use App\Services\Events\EventService;
use App\Services\Reservations\ReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = EventProject::query()->accessibleTo($request->user())
            ->with('occasionLabels')
            ->withCount(['rsvpResponses', 'invitations'])
            ->latest()
            ->get();

        return response()->json(['events' => $events]);
    }

    public function store(Request $request, EventService $service): JsonResponse
    {
        $event = $service->save(null, $request->all(), $request->user(), reason: 'Created through the existing organizer API.');

        return response()->json(['event' => $event], 201);
    }

    public function show(Request $request, int $event): JsonResponse
    {
        return response()->json([
            'event' => $this->ownedEvent($request, $event)->load('occasionLabels'),
        ]);
    }

    public function update(Request $request, int $event, EventService $service): JsonResponse
    {
        $project = $this->ownedEvent($request, $event);
        $project = $service->save($project, $request->all(), $request->user(), reason: 'Updated through the existing organizer API.');

        return response()->json(['event' => $project]);
    }

    public function destroy(Request $request, int $event): Response
    {
        $this->ownedEvent($request, $event)->delete();

        return response()->noContent();
    }

    public function duplicate(Request $request, int $event): JsonResponse
    {
        $source = $this->ownedEvent($request, $event)->load('occasionLabels');
        $copy = DB::transaction(function () use ($source, $request): EventProject {
            $attributes = $source->only([
                'internal_name',
                'public_slug',
                'combined_title',
                'landing_title',
                'landing_subtitle',
                'welcome_message',
                'event_date',
                'start_time',
                'end_time',
                'timezone',
                'event_stops',
                'venue_name',
                'venue_address',
                'map_url',
                'cover_image_url',
                'gallery_urls',
                'theme',
                'access_mode',
                'allow_plus_ones',
                'max_party_size',
            ]);
            $attributes['internal_name'] .= ' (copy)';
            $attributes['combined_title'] .= ' (copy)';
            $attributes['public_slug'] = $this->uniqueSlug(
                Str::slug($attributes['public_slug'].'-copy'),
            );
            $attributes['status'] = 'draft';
            $attributes['rsvp_deadline'] = null;

            $copy = $request->user()->eventProjects()->create($attributes);
            $this->syncLabels($copy, $source->occasionLabels->pluck('label')->all());

            return $copy;
        });

        return response()->json(['event' => $copy->load('occasionLabels')], 201);
    }

    public function metrics(Request $request, int $event): JsonResponse
    {
        $project = $this->ownedEvent($request, $event);
        $responses = $project->rsvpResponses()->active();

        return response()->json([
            'metrics' => [
                'submissions' => (clone $responses)->count(),
                'attending_parties' => (clone $responses)->where('attendance_status', 'attending')->count(),
                'confirmed_attendees' => (clone $responses)->where('attendance_status', 'attending')->sum('party_size'),
                'declined' => (clone $responses)->where('attendance_status', 'declined')->count(),
                'pending' => $project->invitations()
                    ->where('status', 'active')
                    ->whereDoesntHave('rsvpResponse')
                    ->count(),
            ],
        ]);
    }

    public function responses(Request $request, int $event): JsonResponse
    {
        $project = $this->ownedEvent($request, $event);
        $query = $project->rsvpResponses()->active()->with(['guest', 'invitation']);

        if ($request->filled('status') && in_array($request->string('status')->toString(), ['attending', 'declined'], true)) {
            $query->where('attendance_status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->whereHas('guest', function (Builder $guestQuery) use ($search): void {
                $guestQuery->where('full_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        return response()->json($query->latest('submitted_at')->paginate(50));
    }

    public function updateResponse(Request $request, int $response, ReservationService $service): JsonResponse
    {
        $rsvp = RsvpResponse::query()->with('guest')->findOrFail($response);
        abort_unless(
            EventProject::query()->accessibleTo($request->user())->whereKey($rsvp->event_project_id)->exists(),
            404,
        );

        $updated = $service->update($rsvp, $request->all(), $request->user(), true);

        return response()->json(['response' => $updated]);
    }

    public function export(Request $request, int $event): Response
    {
        $project = $this->ownedEvent($request, $event);
        $filename = Str::slug($project->internal_name).'-rsvps.csv';
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Guest name',
            'Email',
            'Phone',
            'Attendance',
            'Party size',
            'Additional guests',
            'Dietary/accessibility notes',
            'Message',
            'Submitted at',
        ]);

        $project->rsvpResponses()->active()->with('guest')->orderBy('submitted_at')->chunk(200, function ($rows) use ($stream): void {
            foreach ($rows as $row) {
                $values = [
                    $row->guest->full_name,
                    $row->guest->email ?? '',
                    $row->guest->phone ?? '',
                    $row->attendance_status,
                    $row->party_size,
                    implode(', ', $row->additional_guest_names ?? []),
                    $row->dietary_or_accessibility_notes ?? '',
                    $row->message ?? '',
                    $row->submitted_at?->toIso8601String() ?? '',
                ];

                fputcsv($stream, array_map($this->spreadsheetSafe(...), $values));
            }
        });

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ownedEvent(Request $request, int $id): EventProject
    {
        return EventProject::query()->accessibleTo($request->user())->findOrFail($id);
    }

    private function syncLabels(EventProject $event, array $labels): void
    {
        $event->occasionLabels()->delete();
        foreach (array_values($labels) as $index => $label) {
            $event->occasionLabels()->create([
                'label' => trim($label),
                'display_order' => $index,
            ]);
        }
    }

    private function uniqueSlug(string $slug): string
    {
        $candidate = $slug;
        $index = 2;
        while (EventProject::query()->where('public_slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$index++;
        }

        return $candidate;
    }

    private function spreadsheetSafe(mixed $value): string|int
    {
        if (! is_string($value) || $value === '') {
            return is_int($value) ? $value : '';
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }
}

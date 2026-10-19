<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventActionRequest;
use App\Http\Requests\Admin\EventSettingsRequest;
use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Services\Events\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventSettingsController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EventProject::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:draft,published,archived'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = EventProject::query()->accessibleTo($request->user());
        if ($request->filled('search')) {
            $query->where(fn ($query) => $query->where('internal_name', 'like', '%'.$filters['search'].'%')->orWhere('combined_title', 'like', '%'.$filters['search'].'%'));
        }
        if ($request->filled('status')) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', '!=', 'archived');
        }
        $events = $query->orderByDesc('event_date')->orderByDesc('id')->paginate(12)->withQueryString();

        return view('admin.event-settings.index', compact('events', 'filters') + ['section' => 'Event Settings']);
    }

    public function create(): View
    {
        Gate::authorize('create', EventProject::class);

        return view('admin.event-settings.form', ['section' => 'Event Settings', 'event' => null, 'history' => null]);
    }

    public function edit(EventProject $event): View
    {
        Gate::authorize('update', $event);
        $event->load('occasionLabels');
        $history = AdminAuditEvent::query()->where('subject_id', $event->id)->where('action', 'like', 'event.%')->with('actor')->orderByDesc('id')->paginate(10);

        return view('admin.event-settings.form', compact('event', 'history') + ['section' => 'Event Settings']);
    }

    public function store(EventSettingsRequest $request, EventService $service): RedirectResponse
    {
        $event = $service->save(null, $request->eventData(), $request->user(), reason: $request->validated('change_reason'));

        return redirect()->route('admin.event-settings.edit', $event)->with('status', 'Event created. Publication status: '.$event->status.'.');
    }

    public function update(EventSettingsRequest $request, EventProject $event, EventService $service): RedirectResponse
    {
        $event = $service->save($event, $request->eventData(), $request->user(), $request->validated('version'), $request->validated('change_reason'));

        return redirect()->route('admin.event-settings.edit', $event)->with('status', 'Event settings saved. Published invitations use these details.');
    }

    public function destroy(EventActionRequest $request, EventProject $event, EventService $service): RedirectResponse
    {
        $service->archive($event, $request->user(), $request->validated());

        return redirect()->route('admin.event-settings')->with('status', 'Event deleted from active events. Guest, invitation and RSVP records were retained. Find it with the Archived / deleted filter.');
    }
}

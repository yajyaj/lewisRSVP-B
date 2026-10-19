<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuestRequest;
use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Models\Guest;
use App\Services\Reservations\GuestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GuestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Guest::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'event' => ['nullable', 'integer', 'min:1'], 'response' => ['nullable', 'in:responded,pending'], 'page' => ['nullable', 'integer', 'min:1']]);
        $events = EventProject::query()->accessibleTo($request->user())->orderBy('internal_name')->get();
        if ($request->filled('event')) {
            abort_unless($events->contains('id', $request->integer('event')), 404);
        }
        $query = Guest::query()->whereIn('event_project_id', $events->modelKeys())->with('eventProject')->withCount('rsvpResponses');
        if ($request->filled('event')) {
            $query->where('event_project_id', $request->integer('event'));
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('full_name', 'like', '%'.$filters['search'].'%')->orWhere('email', 'like', '%'.$filters['search'].'%')->orWhere('phone', 'like', '%'.$filters['search'].'%'));
        }
        if (($filters['response'] ?? '') === 'responded') {
            $query->whereHas('rsvpResponses');
        }
        if (($filters['response'] ?? '') === 'pending') {
            $query->whereDoesntHave('rsvpResponses')->whereHas('invitations', fn ($q) => $q->where('status', 'active'));
        }
        $guests = $query->orderBy('full_name')->orderBy('id')->paginate(20)->withQueryString();

        return view('admin.guests.index', compact('guests', 'events', 'filters') + ['section' => 'Guests']);
    }

    public function show(Guest $guest): View
    {
        Gate::authorize('view', $guest);
        $guest->load('eventProject');
        $responses = $guest->rsvpResponses()->with(['guest', 'eventProject'])->orderByDesc('id')->paginate(10, ['*'], 'responses_page');
        $history = AdminAuditEvent::query()->where('subject_id', $guest->id)->where('action', 'guest.updated')->with('actor')->orderByDesc('id')->paginate(10, ['*'], 'history_page');

        return view('admin.guests.show', compact('guest', 'responses', 'history') + ['section' => 'Guests']);
    }

    public function edit(Guest $guest): View
    {
        Gate::authorize('update', $guest);

        return view('admin.guests.form', ['section' => 'Guests', 'guest' => $guest->load('eventProject')]);
    }

    public function update(GuestRequest $request, Guest $guest, GuestService $service): RedirectResponse
    {
        $guest = $service->update($guest, $request->validated(), $request->user());

        return redirect()->route('admin.guests.show', $guest)->with('status', 'Guest contact updated. Linked reservation counts were preserved.');
    }
}

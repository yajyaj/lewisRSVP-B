<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReservationActionRequest;
use App\Http\Requests\Admin\ReservationFilterRequest;
use App\Http\Requests\Admin\ReservationRequest;
use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Models\Guest;
use App\Models\RsvpResponse;
use App\Services\Reservations\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(ReservationFilterRequest $request): View
    {
        $filters = $request->validated();
        $events = EventProject::query()->accessibleTo($request->user())->orderBy('internal_name')->get();
        if ($request->filled('event')) {
            abort_unless($events->contains('id', $request->integer('event')), 404);
        }
        $query = RsvpResponse::query()->whereIn('event_project_id', $events->modelKeys())->with(['guest', 'eventProject']);
        if ($request->filled('event')) {
            $query->where('event_project_id', $request->integer('event'));
        }
        if (($filters['status'] ?? '') === 'cancelled') {
            $query->whereNotNull('cancelled_at');
        } else {
            $query->active();
            if ($request->filled('status')) {
                $query->where('attendance_status', $filters['status']);
            }
        }
        if ($request->filled('search')) {
            $term = trim($filters['search']);
            $query->where(function ($query) use ($term): void {
                $query->whereHas('guest', fn ($guest) => $guest->where('full_name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
                if (preg_match('/^(?:RSVP-)?0*([1-9][0-9]*)$/i', $term, $matches)) {
                    $query->orWhere('id', $matches[1]);
                }
            });
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $field => $operator) {
            if ($request->filled($field)) {
                $query->whereDate('submitted_at', $operator, $filters[$field]);
            }
        }
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('submitted_at'),
            'party' => $query->orderByDesc('party_size'),
            'name' => $query->orderBy(Guest::select('full_name')->whereColumn('guests.id', 'rsvp_responses.guest_id')),
            default => $query->orderByDesc('submitted_at'),
        };
        $reservations = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.reservations.index', compact('reservations', 'events', 'filters') + ['section' => 'Reservations']);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', RsvpResponse::class);
        $events = EventProject::query()->accessibleTo($request->user())->orderBy('internal_name')->get();

        return view('admin.reservations.form', ['section' => 'Reservations', 'reservation' => null, 'events' => $events, 'submissionKey' => Str::uuid()->toString()]);
    }

    public function store(ReservationRequest $request, ReservationService $service): RedirectResponse
    {
        $event = EventProject::query()->accessibleTo($request->user())->findOrFail($request->integer('event_project_id'));
        $reservation = $service->create($event, $request->validated(), $request->user());

        return redirect()->route('admin.reservations.show', $reservation)->with('status', 'Reservation saved.');
    }

    public function show(RsvpResponse $reservation): View
    {
        Gate::authorize('view', $reservation);
        $reservation->load(['guest', 'eventProject']);
        $history = AdminAuditEvent::query()->where('subject_id', $reservation->id)->where('action', 'like', 'reservation.%')->with('actor')->orderByDesc('id')->paginate(10);

        return view('admin.reservations.show', compact('reservation', 'history') + ['section' => 'Reservations']);
    }

    public function edit(RsvpResponse $reservation): View
    {
        Gate::authorize('update', $reservation);
        $reservation->load(['guest', 'eventProject']);

        return view('admin.reservations.form', ['section' => 'Reservations', 'reservation' => $reservation, 'events' => collect([$reservation->eventProject]), 'submissionKey' => null]);
    }

    public function update(ReservationRequest $request, RsvpResponse $reservation, ReservationService $service): RedirectResponse
    {
        $service->update($reservation, $request->validated(), $request->user());

        return redirect()->route('admin.reservations.show', $reservation)->with('status', 'Reservation updated.');
    }

    public function destroy(ReservationActionRequest $request, RsvpResponse $reservation, ReservationService $service): RedirectResponse
    {
        $service->setCancelled($reservation, true, $request->validated(), $request->user());

        return redirect()->route('admin.reservations.show', $reservation)->with('status', 'Reservation deleted from active reservations. Guest details and history were retained; restore it using the Cancelled filter.');
    }

    public function restore(ReservationActionRequest $request, RsvpResponse $reservation, ReservationService $service): RedirectResponse
    {
        $service->setCancelled($reservation, false, $request->validated(), $request->user());

        return redirect()->route('admin.reservations.show', $reservation)->with('status', 'Reservation restored.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReservationFilterRequest;
use App\Models\EventProject;
use App\Models\Invitation;
use App\Models\RsvpResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReservationFilterRequest $request): View
    {
        $events = EventProject::query()->accessibleTo($request->user())->orderBy('event_date')->orderBy('id')->get();
        $selected = $request->filled('event') ? $events->firstWhere('id', $request->integer('event')) : null;
        abort_if($request->filled('event') && ! $selected, 404);
        $ids = $selected ? [$selected->id] : $events->modelKeys();
        $query = RsvpResponse::query()->whereIn('event_project_id', $ids);
        $stats = (clone $query)->selectRaw("COUNT(*) AS total, COALESCE(SUM(CASE WHEN cancelled_at IS NULL AND attendance_status = 'attending' THEN 1 ELSE 0 END), 0) AS attending, COALESCE(SUM(CASE WHEN cancelled_at IS NULL AND attendance_status = 'declined' THEN 1 ELSE 0 END), 0) AS declined, COALESCE(SUM(CASE WHEN cancelled_at IS NULL AND attendance_status = 'attending' THEN party_size ELSE 0 END), 0) AS attendees, COALESCE(SUM(CASE WHEN cancelled_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS cancelled")->first();
        $pending = Invitation::query()->whereIn('event_project_id', $ids)->where('status', 'active')->whereDoesntHave('rsvpResponse')->count();
        $recent = $query->with(['guest', 'eventProject'])->orderByDesc('updated_at')->orderByDesc('id')->limit(8)->get();

        return view('admin.dashboard.index', ['section' => 'Dashboard', 'events' => $events, 'selected' => $selected, 'stats' => $stats, 'pending' => $pending, 'recent' => $recent, 'snapshot' => $selected ?? $events->first()]);
    }
}

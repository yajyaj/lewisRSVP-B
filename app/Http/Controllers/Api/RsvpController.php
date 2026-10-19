<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventProject;
use App\Models\Invitation;
use App\Services\Reservations\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RsvpController extends Controller
{
    public function public(Request $request, string $slug, ReservationService $service): JsonResponse
    {
        $event = EventProject::query()
            ->where('public_slug', $slug)
            ->where('status', 'published')
            ->where('access_mode', 'public')
            ->firstOrFail();

        return $this->saveResponse($request, $event, null, $service);
    }

    public function private(Request $request, string $token, ReservationService $service): JsonResponse
    {
        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->with(['eventProject', 'guest'])
            ->firstOrFail();

        abort_unless(
            $invitation->eventProject->status === 'published'
                && $invitation->eventProject->access_mode === 'private',
            404,
        );

        return $this->saveResponse($request, $invitation->eventProject, $invitation, $service);
    }

    private function saveResponse(
        Request $request,
        EventProject $event,
        ?Invitation $invitation,
        ReservationService $service,
    ): JsonResponse {
        $response = $service->respond($event, $invitation, $request->all());

        return response()->json([
            'message' => 'Your RSVP has been saved.',
            'attendance_status' => $response->attendance_status,
        ], 201);
    }
}

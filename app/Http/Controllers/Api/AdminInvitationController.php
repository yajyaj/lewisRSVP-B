<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventProject;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminInvitationController extends Controller
{
    public function index(Request $request, int $event): JsonResponse
    {
        $project = $this->ownedEvent($request, $event);

        return response()->json([
            'invitations' => $project->invitations()
                ->with(['guest', 'rsvpResponse'])
                ->latest()
                ->get()
                ->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'status' => $invitation->status,
                    'created_at' => $invitation->created_at,
                    'guest' => $invitation->guest->only(['id', 'full_name', 'email', 'phone']),
                    'has_responded' => $invitation->rsvpResponse !== null,
                ]),
        ]);
    }

    public function store(Request $request, int $event): JsonResponse
    {
        $project = $this->ownedEvent($request, $event);
        if ($project->access_mode !== 'private') {
            throw ValidationException::withMessages([
                'access_mode' => ['Switch this event to private invitations before creating invitation links.'],
            ]);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $guest = $project->guests()->create([
            'full_name' => trim($data['full_name']),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);
        $token = Str::random(64);
        $invitation = new Invitation([
            'token_hash' => hash('sha256', $token),
        ]);
        $invitation->eventProject()->associate($project);
        $invitation->guest()->associate($guest);
        $invitation->save();

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return response()->json([
            'invitation' => [
                'id' => $invitation->id,
                'guest' => $guest->only(['id', 'full_name', 'email', 'phone']),
                'url' => $frontendUrl.'/?invite='.$token,
            ],
        ], 201);
    }

    public function revoke(Request $request, int $invitation): JsonResponse
    {
        $invite = Invitation::query()->with('eventProject')->findOrFail($invitation);
        abort_unless(
            EventProject::query()->accessibleTo($request->user())->whereKey($invite->event_project_id)->exists(),
            404,
        );

        $invite->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return response()->json(['message' => 'Invitation link revoked.']);
    }

    private function ownedEvent(Request $request, int $id): EventProject
    {
        return EventProject::query()->accessibleTo($request->user())->findOrFail($id);
    }
}

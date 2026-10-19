<?php

namespace App\Http\Middleware;

use App\Models\EventProject;
use App\Models\Invitation;
use App\Models\RsvpResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeLegacyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $method = $request->route()->getActionMethod();
        if ($request->route('event') !== null) {
            $event = EventProject::findOrFail($request->route('event'));
        } elseif ($request->route('response') !== null) {
            $event = RsvpResponse::findOrFail($request->route('response'))->eventProject;
        } elseif ($request->route('invitation') !== null) {
            $event = Invitation::findOrFail($request->route('invitation'))->eventProject;
        } else {
            Gate::authorize($method === 'store' ? 'create' : 'viewAny', EventProject::class);

            return $next($request);
        }

        $ability = match ($method) {
            'update', 'updateResponse', 'store', 'revoke' => 'update',
            'destroy' => 'delete',
            default => 'view',
        };
        Gate::authorize($ability, $event);

        return $next($request);
    }
}

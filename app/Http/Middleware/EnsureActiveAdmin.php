<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $accessRevision = $user?->accessRevision() ?? 0;
        $accessKey = 'admin_access_revision.'.($user?->id ?? 'guest');
        $accessChanged = $request->hasSession() && (int) $request->session()->get($accessKey, 0) !== $accessRevision;
        $signatureKey = 'admin_password_signature.'.($user?->id ?? 'guest');
        $signature = $user ? hash('sha256', $user->getAuthPassword()) : '';
        $passwordChanged = $request->hasSession() && $request->session()->has($signatureKey)
            && ! hash_equals($request->session()->get($signatureKey), $signature);
        if (! $user?->canAccessAdmin() || $passwordChanged || $accessChanged) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            abort(403, 'Your account does not have administration access.');
        }

        if ($request->hasSession()) {
            $request->session()->put($signatureKey, $signature);
            $request->session()->put($accessKey, $accessRevision);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}

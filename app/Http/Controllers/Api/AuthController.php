<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditEvent;
use App\Services\Admin\AuthenticateAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request, AuthenticateAdmin $authenticator): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required_without:email', 'string', 'max:255'],
            'email' => ['required_without:identifier', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $identifierField = array_key_exists('identifier', $validated) ? 'identifier' : 'email';
        $user = $authenticator->authenticate($validated[$identifierField], $validated['password'], $identifierField);

        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $request->session()->put('admin_access_revision.'.$user->id, $user->accessRevision());
        $request->session()->put('admin_password_signature.'.$user->id, hash('sha256', $user->getAuthPassword()));

        return response()->json(['user' => $request->user()]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        AdminAuditEvent::create(['actor_id' => $request->user()->id, 'action' => 'auth.logout']);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }
}

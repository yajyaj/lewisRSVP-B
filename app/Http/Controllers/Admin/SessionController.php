<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\AdminAuditEvent;
use App\Services\Admin\AuthenticateAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request, AuthenticateAdmin $authenticator): RedirectResponse
    {
        $user = $authenticator->authenticate($request->validated('identifier'), $request->validated('password'));
        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $request->session()->put('admin_access_revision.'.$user->id, $user->accessRevision());
        $request->session()->put('admin_password_signature.'.$user->id, hash('sha256', $user->getAuthPassword()));

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        AdminAuditEvent::create(['actor_id' => $request->user()->id, 'action' => 'auth.logout']);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

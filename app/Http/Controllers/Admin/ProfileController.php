<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProfilePasswordRequest;
use App\Http\Requests\Admin\ProfileRequest;
use App\Services\Admin\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('updateProfile', $request->user());

        return view('admin.profile.index', ['section' => 'My Profile', 'subject' => $request->user()]);
    }

    public function update(ProfileRequest $request, ProfileService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated());

        return redirect()->route('admin.profile')->with('status', 'Your profile was updated.');
    }

    public function password(ProfilePasswordRequest $request, ProfileService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated(), true);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'Password changed. Sign in again with your new password.');
    }
}

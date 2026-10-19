<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserActionRequest;
use App\Http\Requests\Admin\UserPasswordRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AdminAuditEvent;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'role' => ['nullable', 'in:staff,super_admin'], 'status' => ['nullable', 'in:active,inactive'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = User::query()->select(['id', 'name', 'email', 'username', 'role', 'is_active', 'created_at'])->addSelect(['last_login_at' => AdminAuditEvent::select('created_at')->whereColumn('actor_id', 'users.id')->where('action', 'auth.login')->orderByDesc('id')->limit(1)]);
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')->orWhere('email', 'like', '%'.$filters['search'].'%')->orWhere('username', 'like', '%'.$filters['search'].'%'));
        }
        if ($request->filled('role')) {
            $query->where('role', $filters['role']);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        $users = $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users', 'filters') + ['section' => 'Users']);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', ['section' => 'Users', 'subject' => null]);
    }

    public function store(UserRequest $request, UserService $service): RedirectResponse
    {
        $subject = $service->save(null, $request->validated(), $request->user());

        return redirect()->route('admin.users.show', $subject)->with('status', 'Organizer account created.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);
        $history = AdminAuditEvent::query()->where('subject_id', $user->id)->where('action', 'like', 'user.%')->with('actor')->orderByDesc('id')->paginate(10);

        return view('admin.users.show', ['section' => 'Users', 'subject' => $user, 'history' => $history]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', ['section' => 'Users', 'subject' => $user]);
    }

    public function update(UserRequest $request, User $user, UserService $service): RedirectResponse
    {
        $subject = $service->save($user, $request->validated(), $request->user());
        if ($subject->id === $request->user()->id && ! $subject->isSuperAdmin()) {
            return redirect()->route('admin.dashboard')->with('status', 'Your account was updated.');
        }

        return redirect()->route('admin.users.show', $subject)->with('status', 'Organizer account updated.');
    }

    public function destroy(UserActionRequest $request, User $user, UserService $service): RedirectResponse
    {
        $subject = $service->deactivate($user, $request->validated(), $request->user());

        return redirect()->route('admin.users.show', $subject)->with('status', 'Account deactivated. Events and guest records were retained.');
    }

    public function passwordForm(User $user): View
    {
        Gate::authorize('resetPassword', $user);

        return view('admin.users.password', ['section' => 'Users', 'subject' => $user]);
    }

    public function password(UserPasswordRequest $request, User $user, UserService $service): RedirectResponse
    {
        $subject = $service->password($user, $request->validated(), $request->user());
        if ($subject->id === $request->user()->id) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('status', 'Password changed. Sign in with your new password.');
        }

        return redirect()->route('admin.users.show', $subject)->with('status', 'Password changed. The target account must sign in again.');
    }
}

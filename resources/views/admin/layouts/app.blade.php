<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $section }} &middot; Gather administration</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="bg-mist text-deep antialiased">
<a href="#main-content" class="skip-link">Skip to content</a>
<div class="admin-frame">
<button class="admin-backdrop" data-close-sidebar aria-label="Close navigation" tabindex="-1" hidden>
</button>
<aside id="admin-sidebar" class="admin-sidebar" aria-label="Administration navigation">
<div class="flex min-h-24 items-center justify-between gap-3 border-b border-white/15 px-5">
<a href="{{ route('admin.dashboard') }}" class="flex min-h-11 items-center gap-3" aria-label="Gather administration home">
<span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/15 font-bold">g.</span>
<span class="sidebar-label text-xl font-semibold tracking-tight">gather<span class="text-teal-200">.</span>
</span>
</a>
<button class="mobile-close size-11 place-items-center rounded-lg hover:bg-white/10" data-close-sidebar aria-label="Close navigation">
<x-admin.icon name="close" />
</button>
</div>
<nav class="flex-1 space-y-6 px-3 py-7" aria-label="Main">
@foreach (['Overview' => ['dashboard' => 'Dashboard'], 'RSVP Management' => ['reservations' => 'Reservations', 'guests' => 'Guests'], 'Administration' => ['event-settings' => 'Event Settings', 'profile' => 'My Profile', 'users' => 'Users']] as $group => $links)
<div class="grid gap-1">
<p class="sidebar-label px-3 pb-2 text-xs font-semibold uppercase tracking-widest text-teal-100/80">{{ $group }}</p>
@foreach ($links as $name => $label)
@if ($name !== 'users' || auth()->user()->can('viewAny', App\Models\User::class))
<a href="{{ route(in_array($name, ['reservations', 'users', 'guests']) ? 'admin.'.$name.'.index' : 'admin.'.$name) }}" title="{{ $label }}" @class(['admin-nav-link', 'is-active' => (request()->routeIs('admin.'.$name) || request()->routeIs('admin.'.$name.'.*'))]) @if((request()->routeIs('admin.'.$name) || request()->routeIs('admin.'.$name.'.*'))) aria-current="page" @endif
>
<x-admin.icon :name="$name" />
<span class="sidebar-label">{{ $label }}</span>
<span class="sr-only collapsed-label">{{ $label }}</span>
</a>
@endif

@endforeach
</div>
@endforeach

</nav>
<div class="border-t border-white/15 p-3">
<button id="collapse-sidebar" class="admin-nav-link w-full" aria-expanded="true" aria-controls="admin-sidebar" title="Collapse sidebar">
<x-admin.icon name="arrow" />
<span class="sidebar-label">Collapse sidebar</span>
</button>
<form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="admin-nav-link w-full" title="Sign out">
<x-admin.icon name="logout" />
<span class="sidebar-label">Sign out</span>
<span class="sr-only collapsed-label">Sign out</span>
</button>
</form>
</div>
</aside>
<div class="admin-workspace min-w-0">
<header class="flex min-h-24 flex-wrap items-center justify-between gap-4 border-b border-line bg-white px-4 py-4 sm:px-8">
<div class="flex min-w-0 items-center gap-3">
<button id="open-sidebar" class="mobile-open size-11 shrink-0 place-items-center rounded-xl border border-line" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation">
<x-admin.icon name="menu" />
</button>
<div>
<p class="text-xs font-medium uppercase tracking-widest text-muted">Administration / {{ $section }}</p>
<p class="mt-1 font-semibold">Your celebration, thoughtfully managed.</p>
</div>
</div>
<details class="relative max-w-full">
<summary class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-line px-3 py-2">
<span class="grid size-8 shrink-0 place-items-center rounded-full bg-teal-50 font-semibold text-ocean">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
<span class="max-w-40 truncate text-sm font-semibold">{{ auth()->user()->name }}</span>
<span aria-hidden="true">&#8964;</span>
</summary>
<div class="absolute right-0 z-10 mt-2 w-56 rounded-xl border border-line bg-white p-2 shadow-lg">
<a class="flex min-h-11 items-center rounded-lg px-3 hover:bg-mist" href="{{ route('admin.profile') }}">My profile</a>
<form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="flex min-h-11 w-full items-center rounded-lg px-3 hover:bg-mist">Sign out</button>
</form>
</div>
</details>
</header>
<main id="main-content" tabindex="-1" class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-8 sm:py-10">@if(session('status'))<p class="rounded-xl border border-teal-200 bg-teal-50 p-4" role="status">{{ session('status') }}</p>@endif
 @yield('content')</main>
<footer class="px-4 pb-8 text-xs text-muted sm:px-8">Gather &middot; Celebration administration</footer>
</div>
</div>
</body>
</html>

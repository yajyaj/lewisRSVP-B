<div class="flex flex-wrap items-end justify-between gap-4">
<div>
<p class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-ocean">Gather workspace</p>
<h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $section }}</h1>
<p class="mt-3 max-w-2xl leading-7 text-muted">A calm, organized space for the people and details behind your celebration.</p>
</div>
<span class="rounded-full border border-teal-200 bg-teal-50 px-4 py-2 text-sm font-semibold text-ocean">{{ auth()->user()->isSuperAdmin() ? 'Super Admin' : 'Admin / Staff' }}</span>
</div>
<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
<x-admin.card>
<div class="mb-6 grid size-12 place-items-center rounded-2xl bg-teal-50 text-ocean">
<x-admin.icon :name="str_replace('admin.', '', request()->route()->getName())" class="size-6" />
</div>
<h2 class="text-xl font-semibold">{{ $section === 'Dashboard' ? 'Welcome to your admin workspace' : $section.' workspace' }}</h2>
<p class="mt-3 leading-7 text-muted">Your secure administration foundation is ready. This section&#8217;s working tools will be added in its approved implementation phase.</p>
<p class="mt-4 rounded-xl bg-mist p-4 text-sm leading-6 text-muted">Existing events and responses remain available in the current organizer interface while the Blade administration is built.</p>
<dl class="mt-6 grid gap-4 border-t border-line pt-6 sm:grid-cols-2">
<div>
<dt class="text-sm text-muted">Signed in as</dt>
<dd class="mt-1 break-words font-semibold">{{ auth()->user()->name }}</dd>
</div>
<div>
<dt class="text-sm text-muted">Access</dt>
<dd class="mt-1 font-semibold">{{ auth()->user()->isSuperAdmin() ? 'All event projects' : 'Your event projects' }}</dd>
</div>
</dl>
</x-admin.card>
<x-admin.card>
<p class="text-xs font-semibold uppercase tracking-widest text-ocean">Implementation roadmap</p>
<h2 class="mt-3 text-lg font-semibold">A careful rollout</h2>
<ol class="mt-6 grid gap-5 text-sm">@foreach (['Foundation' => 'Secure access and responsive navigation', 'Dashboard & reservations' => 'Response metrics and reservation tools', 'Users & guests' => 'People, permissions and contact details', 'Settings & profile' => 'Event details and account preferences'] as $step => $description)<li class="flex gap-3">
<span @class(['grid size-7 shrink-0 place-items-center rounded-full', 'bg-ocean text-white' => $loop->first, 'bg-mist' => !$loop->first])>{{ $loop->iteration }}</span>
<div>
<p class="font-semibold">{{ $step }}</p>
<p class="mt-1 text-muted">{{ $description }}</p>
</div>
</li>@endforeach
</ol>
</x-admin.card>
</div>

@extends('admin.layouts.app')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4"><div><p class="mb-2 text-xs font-semibold uppercase tracking-widest text-ocean">RSVP management</p><h1 class="text-3xl font-semibold tracking-tight">Reservations</h1><p class="mt-3 text-muted">Search, review and maintain your guest responses.</p></div><a class="admin-secondary" href="{{ route('admin.reservations.create') }}">Add reservation</a></div>
<x-admin.errors />
<x-admin.card>
<form method="GET" action="{{ route('admin.reservations.index') }}" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
<x-admin.input name="search" label="Name, email or reference" :value="$filters['search'] ?? ''" maxlength="255" />
<div class="grid gap-2"><label class="admin-label" for="event">Event</label><select class="admin-control" name="event" id="event"><option value="">All accessible events</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected(($filters['event'] ?? '') == $event->id)>{{ $event->internal_name }}</option>@endforeach</select></div>
<div class="grid gap-2"><label class="admin-label" for="status">Status</label><select class="admin-control" id="status" name="status"><option value="">All active reservations</option>@foreach(['attending' => 'Attending', 'declined' => 'Declined', 'cancelled' => 'Cancelled / deleted'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
<x-admin.input name="date_from" label="Submitted from (UTC)" type="date" :value="$filters['date_from'] ?? ''" /><x-admin.input name="date_to" label="Submitted through (UTC)" type="date" :value="$filters['date_to'] ?? ''" />
<div class="grid gap-2"><label class="admin-label" for="sort">Sort by</label><select class="admin-control" id="sort" name="sort">@foreach(['newest' => 'Newest submission', 'oldest' => 'Oldest submission', 'name' => 'Guest name', 'party' => 'Largest party'] as $value => $label)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="flex flex-wrap gap-3 sm:col-span-2 xl:col-span-3"><x-admin.button type="submit">Filter reservations</x-admin.button><a class="admin-secondary" href="{{ route('admin.reservations.index') }}">Clear filters</a></div>
</form>
<p class="mb-4 text-sm text-muted">{{ number_format($reservations->total()) }} {{ $reservations->total() === 1 ? 'reservation' : 'reservations' }} found</p>
<x-admin.reservations.table :reservations="$reservations->getCollection()" />
<div class="mt-5">{{ $reservations->links() }}</div>
</x-admin.card>
@endsection

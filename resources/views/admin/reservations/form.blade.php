@extends('admin.layouts.app')
@section('content')
<div><a class="inline-flex min-h-11 items-center text-sm font-semibold text-ocean underline underline-offset-4" href="{{ $reservation ? route('admin.reservations.show', $reservation) : route('admin.reservations.index') }}">&larr; Back to reservations</a><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $reservation ? 'Edit '.$reservation->reference : 'Add reservation' }}</h1><p class="mt-3 text-muted">{{ $reservation ? 'Keep the guest response accurate. A reason is required for changes.' : 'Record one guest response on behalf of an attendee.' }}</p></div>
<x-admin.errors />
@if($reservation?->cancelled_at)
<x-admin.card><h2 class="font-semibold">This reservation is cancelled</h2><p class="mt-2 text-muted">Restore it from the details page before editing.</p></x-admin.card>
@elseif($events->isEmpty())
<x-admin.card><h2 class="font-semibold">An event is needed first</h2><p class="mt-2 text-muted">Create an event in the existing organizer interface, then return here.</p></x-admin.card>
@else
<form method="POST" action="{{ $reservation ? route('admin.reservations.update', $reservation) : route('admin.reservations.store') }}" class="grid gap-6" data-reservation-form>
@csrf
@if($reservation) @method('PUT')<input type="hidden" name="revision" value="{{ $reservation->revision }}">@else<input type="hidden" name="submission_key" value="{{ old('submission_key', $submissionKey) }}">@endif
<x-admin.card><h2 class="mb-5 text-xl font-semibold">Guest & response</h2><div class="grid gap-5 sm:grid-cols-2">
@if($reservation)<div class="sm:col-span-2"><p class="admin-label">Event</p><p class="mt-2 break-words">{{ $reservation->eventProject->internal_name }}</p></div>@else<div class="grid gap-2 sm:col-span-2"><label class="admin-label" for="event_project_id">Event (required)</label><select class="admin-control" id="event_project_id" name="event_project_id" required>@foreach($events as $event)<option value="{{ $event->id }}" @selected(old('event_project_id') == $event->id)>{{ $event->internal_name }} &middot; {{ $event->event_date->format('M j, Y') }}</option>@endforeach</select></div>@endif
<x-admin.input name="full_name" label="Full name" :value="$reservation?->guest->full_name ?? ''" :required="true" maxlength="255" autocomplete="name" />
<div class="grid gap-2"><label class="admin-label" for="attendance_status">Attendance (required)</label><select class="admin-control" id="attendance_status" name="attendance_status" required><option value="attending" @selected(old('attendance_status', $reservation?->attendance_status) !== 'declined')>Attending</option><option value="declined" @selected(old('attendance_status', $reservation?->attendance_status) === 'declined')>Declined</option></select></div>
<x-admin.input name="email" label="Email (optional)" type="email" :value="$reservation?->guest->email ?? ''" maxlength="255" autocomplete="email" /><x-admin.input name="phone" label="Phone (optional)" type="tel" :value="$reservation?->guest->phone ?? ''" maxlength="40" autocomplete="tel" />
@if($reservation && $reservation->party_size > 1)
<x-admin.input name="party_size" label="Attendee count" type="number" :value="$reservation->party_size" :required="true" min="0" max="20" />
<p class="self-center text-sm leading-6 text-muted">This existing group response is retained. Event limits apply when changing its count.</p>
<div class="sm:col-span-2"><x-admin.textarea name="additional_guest_names_text" label="Additional guest names (one per line)" :value="implode(chr(10), $reservation->additional_guest_names ?? [])" maxlength="2000" /></div>
@else
<input type="hidden" name="party_size" value="1"><p class="text-sm leading-6 text-muted sm:col-span-2">One guest per reservation. Declined responses count as zero attendees.</p>
@endif
</div></x-admin.card>
<x-admin.card><h2 class="mb-5 text-xl font-semibold">Notes & change record</h2><div class="grid gap-5"><x-admin.textarea name="dietary_or_accessibility_notes" label="Dietary or accessibility notes (optional)" :value="$reservation?->dietary_or_accessibility_notes ?? ''" maxlength="2000" /><x-admin.textarea name="message" label="Guest message (optional)" :value="$reservation?->message ?? ''" maxlength="2000" /><x-admin.textarea name="change_reason" label="Reason for this entry or change" :required="true" minlength="3" maxlength="500" /><p class="text-xs leading-5 text-muted">Use a short operational reason. Do not include passwords, tokens or sensitive personal details.</p></div></x-admin.card>
<div class="flex flex-wrap gap-3"><x-admin.button type="submit">{{ $reservation ? 'Save changes' : 'Save reservation' }}</x-admin.button><a class="admin-secondary" href="{{ route('admin.reservations.index') }}">Cancel editing</a></div>
</form>
@endif
@endsection

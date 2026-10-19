@extends('admin.layouts.app')
@section('content')
<div class="mx-auto grid w-full max-w-4xl gap-6">
@php
    $deadline = $event?->rsvp_deadline ? $event->rsvp_deadline->copy()->timezone($event->timezone)->format('Y-m-d\TH:i') : '';
@endphp
<div><a class="inline-flex min-h-11 items-center text-sm font-semibold text-ocean underline underline-offset-4" href="{{ route('admin.event-settings') }}">&larr; All events</a><h1 class="mt-2 text-3xl font-semibold">{{ $event ? 'Edit event settings' : 'Create event' }}</h1><p class="mt-3 text-muted">{{ $event?->internal_name ?? 'Set up your celebration and invitation.' }}</p></div>
@if($event?->status === 'published' && $event->access_mode === 'public')<x-admin.card><p class="text-sm font-semibold">Published invitation</p><a class="mt-2 inline-flex min-h-11 max-w-full items-center break-all text-sm text-ocean underline underline-offset-4" href="{{ rtrim(config('app.frontend_url'), '/').'/'.$event->public_slug }}" target="_blank" rel="noopener noreferrer">{{ rtrim(config('app.frontend_url'), '/').'/'.$event->public_slug }}</a></x-admin.card>@endif
<x-admin.errors />
<form method="POST" action="{{ $event ? route('admin.event-settings.update', $event) : route('admin.event-settings.store') }}" class="grid gap-6" data-reservation-form>@csrf @if($event) @method('PUT')<input type="hidden" name="version" value="{{ $event->editVersion() }}">@endif
<x-admin.card><h2 class="mb-5 text-xl font-semibold">Invitation details</h2><div class="grid gap-5 sm:grid-cols-2"><x-admin.input name="internal_name" label="Internal event name" :value="$event?->internal_name ?? ''" :required="true" maxlength="120" /><div class="sm:col-span-2"><x-admin.input name="combined_title" label="Invitation title" :value="$event?->combined_title ?? ''" :required="true" maxlength="160" /></div><div class="sm:col-span-2"><x-admin.textarea name="welcome_message" label="Welcome message (optional)" :value="$event?->welcome_message ?? ''" maxlength="3000" /></div></div><p class="mt-4 text-sm text-muted">The internal name is for organizers. Guests see the invitation title and welcome message.</p></x-admin.card>
<x-admin.card><h2 class="mb-2 text-xl font-semibold">Landing page</h2><p class="mb-5 text-sm text-muted">Customize the opening screen guests see before entering the invitation.</p><div class="grid gap-5"><x-admin.input name="landing_title" label="Title (optional)" :value="$event?->landing_title ?? ''" maxlength="160" /><p class="text-xs text-muted">Leave the title or subtitle empty to use the default landing page text.</p><x-admin.textarea name="landing_subtitle" label="Subtitle (optional)" :value="$event?->landing_subtitle ?? ''" maxlength="300" /></div></x-admin.card>
<x-admin.card><h2 class="mb-5 text-xl font-semibold">Date & replies</h2><div class="grid gap-5 sm:grid-cols-2"><x-admin.input name="event_date" label="Event date" type="date" :value="$event?->event_date->format('Y-m-d') ?? ''" :required="true" /><x-admin.input name="rsvp_deadline_local" label="RSVP deadline (optional)" type="datetime-local" :value="$deadline" /></div><p class="mt-4 text-sm leading-6 text-muted">{{ ($event?->timezone ?? config('app.event_timezone')) === 'Asia/Manila' ? 'All times are local Philippine time.' : 'Times follow the saved local time for this event.' }} An empty deadline keeps replies open while the event is published.</p></x-admin.card>
@php
    $stops = old('event_stops', $event?->event_stops ?? [[
        'label' => $event?->occasionLabels->first()?->label ?? '',
        'venue_name' => $event?->venue_name ?? '', 'venue_address' => $event?->venue_address ?? '',
        'map_url' => $event?->map_url ?? '', 'start_time' => $event?->start_time ?? '', 'end_time' => $event?->end_time ?? '',
    ], ['label' => $event?->occasionLabels->skip(1)->first()?->label ?? '']]);
    $hasSecond = is_array($stops[1] ?? null) && count(array_filter($stops[1], fn ($value) => $value !== null && $value !== '')) > 0;
@endphp
@for($index = 0; $index < 2; $index++)
<div @if($index === 1) id="second-occasion" @if(!$hasSecond) hidden @endif @endif>
<fieldset @if($index === 1) data-second-occasion @disabled(!$hasSecond) @endif class="min-w-0">
<x-admin.card>
    <h2 class="mb-2 text-xl font-semibold">Occasion {{ $index + 1 }}{{ $index === 1 ? ' (optional)' : '' }}</h2>
    <p class="mb-5 text-sm text-muted">{{ $index === 0 ? 'For example, Christening.' : 'For example, 1st Birthday. Add the details for your second occasion.' }}</p>
    <div class="grid gap-5 sm:grid-cols-2">
        @foreach(['label' => 'Occasion name', 'venue_name' => 'Venue', 'venue_address' => 'Address', 'map_url' => 'Map URL', 'start_time' => 'Start time', 'end_time' => 'End time'] as $field => $label)
        <x-admin.input :name="'event_stops['.$index.']['.$field.']'" :id="'occasion-'.$index.'-'.$field" :label="$label" :value="$stops[$index][$field] ?? ''" :required="$field === 'label'" :type="in_array($field, ['start_time', 'end_time']) ? 'time' : ($field === 'map_url' ? 'url' : 'text')" :maxlength="$field === 'label' ? 80 : ($field === 'map_url' ? 2048 : 255)" />
        @endforeach
    </div>
    @if($index === 1)<button type="button" class="admin-secondary mt-5" data-remove-occasion>Remove occasion</button>@endif
</x-admin.card>
</fieldset>
</div>
@endfor
<button type="button" class="admin-secondary justify-self-start" data-add-occasion aria-controls="second-occasion" aria-expanded="{{ $hasSecond ? 'true' : 'false' }}" @if($hasSecond) hidden @endif>Add occasion</button>
<div class="flex flex-wrap gap-3"><x-admin.button type="submit">{{ $event ? 'Save event settings' : 'Create event' }}</x-admin.button><a class="admin-secondary" href="{{ route('admin.event-settings') }}">Cancel editing</a></div></form>
@if($event && $event->status !== 'archived')
@can('delete', $event)
<x-admin.card><h2 id="delete-event" tabindex="-1" class="text-xl font-semibold text-red-700">Delete event</h2><p class="mt-3 text-sm leading-6 text-muted">This archives the event and disables its public invitation, including the homepage if this is the selected event. Guests, invitations and RSVPs remain on record.</p><form method="POST" action="{{ route('admin.event-settings.destroy', $event) }}" class="mt-5 grid gap-4">@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $event->editVersion() }}"><x-admin.textarea name="deletion_reason" label="Reason for deletion" :required="true" minlength="3" maxlength="500" /><label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="size-5 shrink-0 accent-ocean"> I confirm I want to delete this event and disable its public invitation.</label><x-admin.button type="submit" class="justify-self-start">Delete event</x-admin.button></form></x-admin.card>
@endcan
@endif
@if($history)<x-admin.card><h2 class="text-xl font-semibold">Settings history</h2><ol class="mt-5 divide-y divide-line">@forelse($history as $entry)<li class="py-4"><p class="text-sm font-semibold">{{ $entry->action === 'event.created' ? 'Event created' : ($entry->action === 'event.archived' ? 'Event deleted (archived)' : 'Event settings updated') }}</p><p class="mt-1 text-xs text-muted">{{ $entry->actor?->name ?? 'Former organizer' }} &middot; {{ $entry->created_at->format('M j, Y H:i') }} UTC</p><p class="mt-2 break-words text-sm">{{ $entry->reason }}</p></li>@empty<li class="text-sm text-muted">No settings changes recorded yet.</li>@endforelse</ol><div class="mt-4">{{ $history->links() }}</div></x-admin.card>@endif
</div>
@endsection

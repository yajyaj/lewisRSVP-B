@props(['reservations'])
@if($reservations->isEmpty())
<div class="rounded-xl border border-dashed border-line p-8 text-center"><h2 class="font-semibold">No reservations found</h2><p class="mt-2 text-sm leading-6 text-muted">Responses will appear here. Try different filters or add a reservation.</p></div>
@else
<div class="overflow-x-auto rounded-xl border border-line" role="region" aria-label="Reservation results" tabindex="0">
<table class="w-full text-left text-sm">
    <caption class="sr-only">Reservations and guest responses</caption>
    <thead class="bg-mist text-xs uppercase tracking-wider text-muted"><tr><th scope="col" class="px-4 py-4">Guest / reference</th><th scope="col" class="px-4 py-4">Status</th><th scope="col" class="px-4 py-4">Guests</th><th scope="col" class="hidden px-4 py-4 lg:table-cell">Submitted</th><th scope="col" class="px-4 py-4">Actions</th></tr></thead>
    <tbody class="divide-y divide-line">
    @foreach($reservations as $reservation)
    <tr class="hover:bg-mist/70"><td class="max-w-64 px-4 py-4"><a class="inline-flex min-h-11 items-center break-words font-semibold text-ocean underline decoration-transparent underline-offset-4 hover:decoration-current" href="{{ route('admin.reservations.show', $reservation) }}">{{ $reservation->guest->full_name }}</a><p class="text-xs text-muted">{{ $reservation->reference }}</p><p class="mt-1 break-words text-xs text-muted">{{ $reservation->eventProject->internal_name }}</p></td><td class="px-4 py-4"><x-admin.reservations.status :reservation="$reservation" /></td><td class="px-4 py-4">{{ $reservation->cancelled_at ? 0 : $reservation->party_size }}</td><td class="hidden whitespace-nowrap px-4 py-4 text-muted lg:table-cell">{{ $reservation->submitted_at->format('M j, Y') }}<br><span class="text-xs">{{ $reservation->submitted_at->format('H:i') }} UTC</span></td><td class="px-4 py-4">@if(!$reservation->cancelled_at) @can('delete', $reservation)<a class="inline-flex min-h-11 items-center font-semibold text-red-700 underline underline-offset-4" href="{{ route('admin.reservations.show', $reservation) }}#delete-reservation">Delete<span class="sr-only"> {{ $reservation->reference }}</span></a>@endcan @else<a class="inline-flex min-h-11 items-center font-semibold text-ocean underline underline-offset-4" href="{{ route('admin.reservations.show', $reservation) }}#delete-reservation">Restore<span class="sr-only"> {{ $reservation->reference }}</span></a>@endif</td></tr>
    @endforeach
    </tbody>
</table>
</div>
@endif

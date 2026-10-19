@props(['name' => 'dashboard'])
@php
$paths = [
'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
'reservations' => 'M8 3v4 M16 3v4 M4 10h16 M5 5h14v15H4V5z M8 14h2 M14 14h2',
'guests' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 21v-2a4 4 0 0 0-3-4 M16 3a4 4 0 0 1 0 8',
'event-settings' => 'M4 6h16 M4 12h16 M4 18h16 M8 3v6 M16 9v6 M10 15v6',
'profile' => 'M20 21v-2a7 7 0 0 0-14 0v2 M13 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
'users' => 'M12 3 3 7v6c0 5 9 8 9 8s9-3 9-8V7z M8 12l3 3 5-6',
'menu' => 'M4 6h16 M4 12h16 M4 18h16', 'close' => 'm6 6 12 12 M6 18 18 6',
'arrow' => 'm14 7-5 5 5 5', 'logout' => 'M9 4H4v16h5 M14 8l4 4-4 4 M8 12h13'];
@endphp
<svg {{ $attributes->class(['size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
<path d="{{ $paths[$name] ?? $paths['dashboard'] }}" />
</svg>

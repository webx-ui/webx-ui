{{-- A group's pictogram (groups spec §2.5): a name of the set, or nothing — a group may go without one. --}}
@props(['name' => null])
@if (in_array($name, \WebxUi\Payments\MethodGroups::PICTOGRAMS, true))
<svg {{ $attributes->class(['webx-payments__pictogram']) }} viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@include('webx-payments::icons.'.$name)</svg>
@endif

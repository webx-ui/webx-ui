{{-- `<x-webx-blocks::region>` without module-blocks: an empty region, which prints its fallback. --}}
@props(['name', 'fallback' => null])

@if ($fallback)
    @include($fallback)
@endif

{{--
    What a row of the list says (groups spec §6.1, §3.4): the method's own picture or the group's
    pictogram, the title with the line under it, the marks on the right. The label around it is the
    list's: a group's head or a method.

    Override: <layer>/views/vendor/webx-payments/components/row.blade.php.
--}}
@props(['title', 'note' => null, 'icon' => null, 'pictogram' => null, 'marks' => []])
@if ($icon !== null && $icon !== '')
    <img class="webx-payments__icon" src="{{ $icon }}" alt="" width="32" height="32" loading="lazy">
@elseif ($pictogram !== null)
    <x-webx-payments::pictogram :name="$pictogram" />
@endif
<span class="webx-payments__row-text">
    <span class="webx-payments__row-title">{{ $title }}</span>
    @if ($note !== null && $note !== '')
        <span class="webx-payments__row-note">{{ $note }}</span>
    @endif
</span>
@if ($marks !== [])
    <ul class="webx-payments__marks">
        @foreach ($marks as $mark)
            <li class="webx-payments__marks-item" data-mark="{{ $mark }}"><x-webx-payments::mark :name="$mark" /></li>
        @endforeach
    </ul>
@endif

{{-- An inline <svg> in a comment is not a finding, nor one PHP builds for a data: URL. --}}
@php $picture = 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg"/>'); @endphp
<div class="card">
    <x-theme::icon name="phone" />
    <x-theme::icon class="card__icon" name="fax" />
    <x-theme::icon :name="$icon" />
    <svg viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>
</div>

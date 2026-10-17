{{-- theme_asset('img/commented.svg') in a comment is not a finding. --}}
@php $wave = theme_asset('img/gone.svg'); @endphp
<div class="banner" style="background-image: url({{ theme_asset('img/dot.svg') }})">
    <img src="{{ theme_asset('img/missing.png') }}" alt="" width="16" height="16">
    <img src="{{ theme_asset('../.env') }}" alt="" width="16" height="16">
    <img src="{{ theme_asset('img/'.$name) }}" alt="" width="16" height="16">
    <img src="{{ theme_asset("img/{$name}.png") }}" alt="" width="16" height="16">
</div>

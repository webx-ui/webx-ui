@foreach ($calls as $call)
    <a class="custom-cta" href="{{ $call->url }}">{{ $label }}</a>
@endforeach

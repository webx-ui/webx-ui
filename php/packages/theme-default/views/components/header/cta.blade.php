{{-- The entries of the `button` look in the header menu: calls to action, among the actions. --}}@foreach ($calls as $call)
    <a class="site-header__cta" @foreach ($call->attrs() as $name => $value) {{ $name }}="{{ $value }}" @endforeach>{{ $call->label }}</a>
@endforeach

@foreach ($items as $item)
    <a class="site-footer__link" @foreach ($item['attrs'] as $name => $value) {{ $name }}="{{ $value }}" @endforeach>{{ $item['label'] }}</a>
@endforeach

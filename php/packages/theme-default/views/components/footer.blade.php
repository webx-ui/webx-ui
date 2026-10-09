@php
    /**
     * The footer region's fallback. `menu('footer')` when the menus module is there and the
     * menu has entries; otherwise just the line every footer has. "Cookie settings" is there
     * either way: taking a consent back must be as easy as giving it (WIDGETS §9.1).
     *
     * Above them, the Contacts tab of the settings (WIDGETS §12): every number, the e-mails, the
     * main address and the networks — nothing when the tab is empty or the module is not there.
     */
    $contacts = function_exists('contacts') ? contacts() : null;
    $address = $contacts?->primaryAddress();
    $items = function_exists('menu')
        ? menu('footer')->map(fn ($item) => ['label' => $item->label, 'attrs' => $item->attrs()])
        : collect();
@endphp

<footer class="site-footer">
    <div class="site-container site-footer__inner">
        @if ($contacts !== null && ! $contacts->isEmpty())
            <div class="site-footer__contacts">
                <x-webx-phones layout="list" class="site-footer__phones" />
                @foreach ($contacts->emails() as $email)
                    <a class="site-footer__link" href="{{ $email->href() }}">{{ $email->address }}</a>
                @endforeach
                @if ($address !== null)
                    <address class="site-footer__address">
                        @if ($address->mapUrl() !== null)
                            <a class="site-footer__link" href="{{ $address->mapUrl() }}" target="_blank" rel="noopener">{{ $address->text }}</a>
                        @else
                            {{ $address->text }}
                        @endif
                    </address>
                @endif
                <x-webx-socials class="site-footer__socials" />
            </div>
        @endif

        <nav class="site-footer__nav" aria-label="{{ __('Footer') }}">
            @foreach ($items as $item)
                <a class="site-footer__link" @foreach ($item['attrs'] as $name => $value) {{ $name }}="{{ $value }}" @endforeach>{{ $item['label'] }}</a>
            @endforeach
            <x-webx-consent-link class="site-footer__link" />
        </nav>

        <p class="site-footer__copy">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </div>
</footer>

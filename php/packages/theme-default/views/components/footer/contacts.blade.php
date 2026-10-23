{{-- Every number, the e-mails, the main address and the networks of the Contacts tab. --}}@if ($contacts !== null && ! $contacts->isEmpty())
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

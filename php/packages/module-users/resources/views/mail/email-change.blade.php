{{-- The link that moves the sign-in to this address (spec §8.5). Override: views/vendor/webx-users/mail/email-change.blade.php. --}}
<x-webx-users::letter :title="__('webx-users::mail.email-change.subject', ['site' => $site])" :name="$name" :link="$link" :button="__('webx-users::mail.email-change.button')">
    <p>{{ __('webx-users::mail.email-change.text', ['site' => $site, 'email' => $email]) }}</p>
    <x-slot:note>{{ __('webx-users::mail.email-change.expires', ['minutes' => $minutes]) }} {{ __('webx-users::mail.ignore') }}</x-slot:note>
</x-webx-users::letter>

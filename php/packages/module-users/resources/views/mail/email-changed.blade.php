{{-- «Your sign-in address was changed», to the address it was changed from (spec §8.5). Override: views/vendor/webx-users/mail/email-changed.blade.php. --}}
<x-webx-users::letter :title="__('webx-users::mail.email-changed.subject', ['site' => $site])" :name="$name" :link="$link" :button="__('webx-users::mail.email-changed.button')">
    <p>{{ __('webx-users::mail.email-changed.text', ['site' => $site, 'email' => $email]) }}</p>
    <p>{{ __('webx-users::mail.email-changed.not-you') }}</p>
</x-webx-users::letter>

{{-- «Your password was changed» (spec §7 item 4). Override: views/vendor/webx-users/mail/password-changed.blade.php. --}}
<x-webx-users::letter :title="__('webx-users::mail.password-changed.subject', ['site' => $site])" :name="$name" :link="$link" :button="__('webx-users::mail.password-changed.button')">
    <p>{{ __('webx-users::mail.password-changed.text', ['site' => $site]) }}</p>
    <p>{{ __('webx-users::mail.password-changed.not-you') }}</p>
</x-webx-users::letter>

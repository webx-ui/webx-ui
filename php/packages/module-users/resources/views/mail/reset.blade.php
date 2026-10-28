{{-- The link of «Forgot your password?» (spec §7). Override: views/vendor/webx-users/mail/reset.blade.php. --}}
<x-webx-users::letter :title="__('webx-users::mail.reset.subject', ['site' => $site])" :name="$name" :link="$link" :button="__('webx-users::mail.reset.button')">
    <p>{{ __('webx-users::mail.reset.text', ['site' => $site]) }}</p>
    <x-slot:note>{{ __('webx-users::mail.reset.expires', ['minutes' => $minutes]) }} {{ __('webx-users::mail.ignore') }}</x-slot:note>
</x-webx-users::letter>

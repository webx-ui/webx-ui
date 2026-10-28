{{-- The link that confirms the address (spec §8.1). Override: views/vendor/webx-users/mail/verify.blade.php. --}}
<x-webx-users::letter :title="__('webx-users::mail.verify.subject', ['site' => $site])" :name="$name" :link="$link" :button="__('webx-users::mail.verify.button')">
    <p>{{ __('webx-users::mail.verify.text', ['site' => $site]) }}</p>
    <x-slot:note>{{ __('webx-users::mail.verify.expires', ['hours' => $hours]) }} {{ __('webx-users::mail.ignore') }}</x-slot:note>
</x-webx-users::letter>

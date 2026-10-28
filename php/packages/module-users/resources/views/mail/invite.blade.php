{{-- The invitation of a visitor made in the panel (spec §16.2). Override: views/vendor/webx-users/mail/invite.blade.php. --}}
<x-webx-users::letter :title="__('webx-users::mail.invite.subject', ['site' => $site])" :name="$name" :link="$link" :button="__('webx-users::mail.invite.button')">
    <p>{{ __('webx-users::mail.invite.text', ['site' => $site, 'email' => $email]) }}</p>
    <x-slot:note>{{ __('webx-users::mail.invite.expires', ['days' => $days]) }} {{ __('webx-users::mail.ignore') }}</x-slot:note>
</x-webx-users::letter>

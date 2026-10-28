{{-- «Sign-in with Google was linked» (spec §9.6, §9.7). Override: views/vendor/webx-users/mail/provider-linked.blade.php. --}}
<x-webx-users::letter :title="__('webx-users::mail.provider-linked.subject', ['site' => $site, 'provider' => $provider])" :name="$name" :link="$link" :button="__('webx-users::mail.provider-linked.button')">
    <p>{{ __('webx-users::mail.provider-linked.text', ['site' => $site, 'provider' => $provider]) }}</p>
    <p>{{ __('webx-users::mail.provider-linked.not-you') }}</p>
</x-webx-users::letter>

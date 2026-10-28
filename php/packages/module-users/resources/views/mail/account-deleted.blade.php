{{-- «Your account was deleted» (spec §14.1). Override: views/vendor/webx-users/mail/account-deleted.blade.php. --}}
<x-webx-users::letter :title="__('webx-users::mail.account-deleted.subject', ['site' => $site])" :name="$name">
    <p>{{ __('webx-users::mail.account-deleted.text', ['site' => $site]) }}</p>
    <p>{{ __('webx-users::mail.account-deleted.not-you') }}</p>
</x-webx-users::letter>

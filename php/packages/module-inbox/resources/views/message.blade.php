{{--
    What the form says back: the thank-you, and the one refusal that belongs to no field.

    Both are here before there is anything to put in them, with `hidden` on. Without
    JavaScript the page is printed again after the POST and this is filled in from the
    session; with it, the script fills the same two boxes in place. One shape either way, so a
    site that restyles this restyles both.

    The thank-you is printed raw because it is written in the panel's rich text editor — the
    same trust a page's content is printed with, and by the same people.
--}}
@php
    $heading = $message['heading'] ?? null;
    $text = $message['message'] ?? null;

    // A form whose thank-you nobody filled in still has to say something.
    if ($message !== null && ! $heading && ! $text) {
        $heading = __('webx-inbox::form.thank-you');
    }

    $refusals = $invalid['form'] ?? [];
@endphp

<div
    class="wx-form__message"
    data-webx-message
    data-webx-fallback="{{ __('webx-inbox::form.thank-you') }}"
    role="status"
    aria-live="polite"
    @if ($message === null) hidden @endif
>
    <p class="wx-form__message-heading" data-webx-message-heading>{{ $heading }}</p>
    <div class="wx-form__message-text" data-webx-message-text>{!! $text !!}</div>
</div>

<p
    class="wx-form__error wx-form__error--form"
    data-webx-form-error
    data-webx-failed="{{ __('webx-inbox::form.failed') }}"
    role="alert"
    @if ($refusals === []) hidden @endif
>{{ implode(' ', $refusals) }}</p>

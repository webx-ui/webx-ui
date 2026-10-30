{{--
    The step «Contacts» (spec §20.3): the buyer, and someone else to receive the order behind a
    checkbox. The fields are the widgets' (`<x-webx-field>`); what is asked is the tab «Checkout».
    A signed-in visitor's own contacts fill the fields until the buyer saves theirs.

    With the visitors' module (§25): «Sign in» for a guest opens the dialog of the sign-in, which
    `checkout.js` keeps from reloading the page — the step and the column are redrawn; «Sign out»
    for a visitor comes back here. Both stand outside the step's form: a form is never in a form.

    Classes: webx-orders-checkout__form, __visitor, __sign-in, __sign-out, __recipient.
--}}
@if ($visitor !== null)
    <div class="webx-orders-checkout__visitor">
        <p>{{ __('webx-orders::checkout.steps.signed-in', ['name' => $visitor->name()]) }}</p>
        @if ($signOut !== null)
            <form method="post" action="{{ $signOut['action'] }}" class="webx-orders-checkout__sign-out">
                @csrf
                <input type="hidden" name="back" value="{{ $signOut['back'] }}">
                <button type="submit">{{ __('webx-orders::checkout.steps.sign-out') }}</button>
            </form>
        @endif
    </div>
@elseif ($signIn)
    <p class="webx-orders-checkout__visitor">
        {{ __('webx-orders::checkout.steps.have-account') }}
        {{-- Dynamic: a tag of a module the site may not have fails the compiling of this view. --}}
        <x-dynamic-component component="webx-users::sign-in-button" class="webx-orders-checkout__sign-in" />
    </p>
@endif
<x-webx-form :action="$action" id="checkout-step-contacts-form" class="webx-orders-checkout__form">
    @include('webx-orders::checkout.parts.honeypot', ['id' => 'checkout-step-contacts-hp'])
    <x-webx-field name="customer.first_name" :label="__('webx-orders::checkout.fields.first-name')" :value="$customer['first_name'] ?? null" required autocomplete="given-name" />
    @if ($fields['last_name'] !== 'hidden')
        <x-webx-field name="customer.last_name" :label="__('webx-orders::checkout.fields.last-name')" :value="$customer['last_name'] ?? null" :required="$fields['last_name'] === 'required'" :optional="$fields['last_name'] === 'optional'" autocomplete="family-name" />
    @endif
    @if ($fields['phone'] !== 'hidden')
        <x-webx-field type="tel" name="customer.phone" :label="__('webx-orders::checkout.fields.phone')" :hint="__('webx-orders::checkout.fields.phone-hint')" :value="$customer['phone'] ?? null" :required="$fields['phone'] === 'required'" :optional="$fields['phone'] === 'optional'" />
    @endif
    @if ($fields['email'] !== 'hidden')
        <x-webx-field type="email" name="customer.email" :label="__('webx-orders::checkout.fields.email')" :value="$customer['email'] ?? null" :required="$fields['email'] === 'required'" :optional="$fields['email'] === 'optional'" />
    @endif
    @if ($withRecipient)
        <x-webx-field type="checkbox" name="recipient.other" :label="__('webx-orders::checkout.fields.recipient')" :checked="$recipient !== null" />
        {{-- Not `required` in the browser: the fields count only with the checkbox ticked, and the
             server says so under them. --}}
        <fieldset class="webx-orders-checkout__recipient">
            <legend>{{ __('webx-orders::checkout.fields.recipient-legend') }}</legend>
            <x-webx-field name="recipient.first_name" :label="__('webx-orders::checkout.fields.recipient-first-name')" :value="$recipient['first_name'] ?? null" />
            @if ($fields['last_name'] !== 'hidden')
                <x-webx-field name="recipient.last_name" :label="__('webx-orders::checkout.fields.recipient-last-name')" :value="$recipient['last_name'] ?? null" :optional="$fields['last_name'] === 'optional'" />
            @endif
            <x-webx-field type="tel" name="recipient.phone" :label="__('webx-orders::checkout.fields.recipient-phone')" :value="$recipient['phone'] ?? null" />
        </fieldset>
    @endif
    <x-webx-form.submit>{{ __('webx-orders::checkout.steps.save') }}</x-webx-form.submit>
</x-webx-form>

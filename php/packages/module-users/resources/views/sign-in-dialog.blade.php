{{--
    The dialog of the sign-in for a checkout (spec §12.1): three views in one <x-webx-dialog> —
    the sign-in, «Forgot your password?», the registration — that `sign-in.js` switches without
    leaving the page, the title of the dialog going with them. Printed once a page, before
    </body>, when something on it opens the dialog (SignInDialog).

    Without JavaScript the opener is a link to the page of the sign-in with the way back, a link to
    `#webx-sign-in` shows the dialog through `:target`, and the links between the views are links to
    the pages; a form sent from here is a plain POST and the page comes back with the dialog open on
    the view whose form it was. Nothing personal: the page may be cached for everybody.

    Override: <layer>/views/vendor/webx-users/sign-in-dialog.blade.php — keep the data-attributes
    and the id of the dialog. Classes: webx-users-sign-in, __view; the forms and the links are the
    pages' own (webx-account-auth__*).
--}}
@php
    $titles = [
        'sign-in' => __('webx-users::auth.sign_in.title'),
        'forgot' => __('webx-users::auth.forgot.title'),
        'register' => __('webx-users::auth.register.title'),
    ];
@endphp
<x-webx-dialog :id="$id" :title="$titles[$view]" sheet class="webx-users-sign-in" data-webx-sign-in-dialog>
    <section class="webx-users-sign-in__view" data-webx-sign-in-view="sign-in" data-webx-sign-in-title="{{ $titles['sign-in'] }}" @if ($view !== 'sign-in') hidden @endif>
        @include('webx-users::forms.sign-in', [...$signIn, 'id' => $forms['sign-in'], 'back' => $back, 'dialog' => true])
        <p class="webx-account-auth__forgot"><a href="{{ $forgot['page'] }}" data-webx-sign-in-show="forgot">{{ __('webx-users::auth.forgot.link') }}</a></p>
        <x-webx-users::social-buttons :back="$back" />
        @if ($register !== null)
            <div class="webx-account-auth__links">
                <p>{{ __('webx-users::auth.sign_in.no_account') }} <a href="{{ $register['page'] }}" data-webx-sign-in-show="register">{{ __('webx-users::auth.sign_in.register') }}</a></p>
            </div>
        @endif
    </section>
    <section class="webx-users-sign-in__view" data-webx-sign-in-view="forgot" data-webx-sign-in-title="{{ $titles['forgot'] }}" @if ($view !== 'forgot') hidden @endif>
        <p class="webx-account-auth__note">{{ __('webx-users::auth.forgot.intro') }}</p>
        @include('webx-users::forms.forgot', [...$forgot, 'id' => $forms['forgot'], 'back' => $back])
        <div class="webx-account-auth__links">
            <p><a href="{{ $signIn['page'] }}" data-webx-sign-in-show="sign-in">{{ __('webx-users::auth.forgot.back') }}</a></p>
        </div>
    </section>
    @if ($register !== null)
        <section class="webx-users-sign-in__view" data-webx-sign-in-view="register" data-webx-sign-in-title="{{ $titles['register'] }}" @if ($view !== 'register') hidden @endif>
            @include('webx-users::forms.register', [...$register, 'id' => $forms['register'], 'back' => $back, 'dialog' => true])
            <x-webx-users::social-buttons :back="$back" />
            <div class="webx-account-auth__links">
                <p>{{ __('webx-users::auth.register.have_account') }} <a href="{{ $signIn['page'] }}" data-webx-sign-in-show="sign-in">{{ __('webx-users::auth.register.sign_in') }}</a></p>
            </div>
        </section>
    @endif
</x-webx-dialog>
<script type="module" src="{{ $script }}"></script>

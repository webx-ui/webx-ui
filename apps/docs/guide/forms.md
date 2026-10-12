# Forms

A site has more than one form: a request through the Inbox, a sign-in and an account when the site
has users, a checkout when it has a shop, a reply to a vacancy, a subscription. Each is fields, how
they look, their errors, their checks and the sending. `webx-ui/widgets` holds what they share, so
that a module draws its form from the same parts and a theme paints one set of classes:

| Whose                          | What                                                                                |
| ------------------------------ | ----------------------------------------------------------------------------------- |
| **widgets** — fields           | the markup of a field, its look on the site's tokens, ARIA, `old()` and `$errors`   |
| **widgets** — the form         | CSRF, sending in place over a plain POST, errors by field, one answer for all forms |
| **widgets** — checks           | the server's rules in the browser, with the server's words; masks; file uploads     |
| **the owner of the form**      | where it goes, the rules on the server, what to do with the data                    |
| **Inbox**                      | the form builder in the panel, intake, spam protection, letters                     |
| **sign-in, account, checkout** | their own forms in Blade from the same fields                                       |

This page grows with the parts. Today: **the fields** and **the form** around them — sending in
place over a plain POST, the errors by field, one answer for every form, and a sign-in written out
in full. The checks in the browser follow.

The specification is §17 of
[`docs/architecture/WEBX_UI_WIDGETS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_WIDGETS.md).

## Fields

```blade
<x-webx-form :action="route('login')">
    <x-webx-field type="email" name="email" :label="__('auth.email')" required />
    <x-webx-field type="password" name="password" :label="__('auth.password')" required />
    <x-webx-field type="checkbox" name="remember" :label="__('auth.remember')" />
    <x-webx-form.submit>{{ __('auth.sign_in') }}</x-webx-form.submit>
</x-webx-form>
```

That is the whole of it. A field stands in any `<form>` — in `<x-webx-form>` (below) it is also
sent in place. A field claims its own files (`dist/field.js|css`, about 2 KB gzip), and
each one is a label, the control, a hint and the place of its error.

### Types

| `type`     | What the visitor gets                                                                                  |
| ---------- | ------------------------------------------------------------------------------------------------------ |
| `text`     | a line                                                                                                 |
| `email`    | the keyboard with @, `autocomplete="email"`, no capitals or corrections                                |
| `tel`      | the dial pad, `autocomplete="tel"`                                                                     |
| `url`      | the keyboard with / and .com                                                                           |
| `password` | never printed back; **Show** beside it (a toggle button, `aria-pressed`), only where the script runs   |
| `number`   | the digits pad; `step` with a fraction gives the decimal one                                           |
| `textarea` | a letter, `rows` 5 by default, resized only downwards                                                  |
| `select`   | an empty first option («Choose…» or `placeholder`) even when required; `multiple`                      |
| `radio`    | a `<fieldset>` with a `<legend>`, one of `:choices`                                                    |
| `checkbox` | with `:choices` — a group, the name gets `[]`; without — one tick with its words beside it, `:checked` |
| `consent`  | one tick whose words are the slot (a link allowed) or the label                                        |
| `date`     | the browser's picker, ISO in the value, `min` and `max`                                                |
| `file`     | a button and a line in the page's words, the name chosen or how many; `multiple`, `accept`             |
| `hidden`   | the input alone — what the page fills in                                                               |

`:choices` is `['value' => 'Label']`, or a list of `['value' => …, 'label' => …, 'disabled' => true]`.

```blade
<x-webx-field type="radio" name="delivery" :label="__('shop.delivery')" :choices="$methods" required inline />
<x-webx-field type="consent" name="terms" required>
    {!! __('shop.terms', ['url' => route('terms')]) !!}
</x-webx-field>
```

### Values and errors come from Laravel

The value is `old($name)` and the error `$errors->first($name)`: a form posted without JavaScript
comes back from `$request->validate()` with each answer where it was and each error under its own
field, and nothing to write for it.

- **A nested name** is written with dots — `fields.email`, `items.0.qty` — and the control is named
  with brackets (`fields[email]`, `items[0][qty]`), which PHP reads back into an array. The error
  is looked up by the dots, which is how the validator calls it. `fields[email]` works too.
- **A group's error** is its own (`extras`) or of any of its items (`extras.0`).
- **Once a form came back**, a group and a tick show only what was sent: a box the visitor
  unticked stays unticked, whatever the page's default.
- **A password and a file** never come back: the first is not printed into a page, the second a
  browser lets no page choose.
- **A named error bag** — `bag="login"`. An error of your own — `error="…"`.

### What a screen reader hears

The control is described by its hint and its error (`aria-describedby`), a wrong one says so
(`aria-invalid="true"`), a group is a `<fieldset>` whose `<legend>` is its label. The error is
there, empty and hidden, before there is anything to say — the form's script writes into it. The
field's root carries `data-webx-field="<dotted name>"`: what the errors of a 422 are matched by.
A required field has `required` and a `*` that a screen reader skips; `optional` prints the word
after the label instead, for a form where most fields are required.

### Hints for a keyboard and an autofill

`autocomplete`, `inputmode` and `enterkeyhint` are props with a default by type: `email` gets
`autocomplete="email"` and `inputmode="email"`, `tel` and `url` theirs, `password`
`current-password`, `number` `numeric` or `decimal`. A password manager and an address book work
only by them, so say what a field is when the type does not:

```blade
<x-webx-field name="first_name" :label="…" autocomplete="given-name" />
<x-webx-field type="password" name="password" :label="…" autocomplete="new-password" />
<x-webx-field type="number" name="degrees" :label="…" :inputmode="false" />  {{-- below zero: no pad has a minus --}}
<x-webx-field name="code" :label="…" enterkeyhint="send" />
```

`false` prints none. Other props: `placeholder`, `minlength`, `maxlength`, `pattern`, `min`, `max`,
`step`, `rows`, `multiple`, `inline` (choices in a row where the field is 30rem wide or more),
`disabled`, `readonly`, `id` (by default `field-<name>`, and `-2` for the second of a name on a
page). `class` goes on the field; any other attribute — `data-*`, a script's hook — on the control.

### Words

«Show», «Show password», «optional», «Choose…», «Choose a file», «No file chosen», «Files chosen:
:count» are `webx-widgets::widgets.field.*` in the ten languages of the panel, in the language of
the page. Each one is also a prop: `show-label`, `show-password-label`, `optional-label`,
`choose-label`, `file-label`, `no-file-label`, `files-label`.

### The look

A skeleton on the site's tokens: a control and a choice are at least 44px tall, the focus is a
2px outline in the accent, an error is words in `--site-color-danger` with a bar beside them —
never the colour alone.

| Classes                                                                                                                              | What                    |
| ------------------------------------------------------------------------------------------------------------------------------------ | ----------------------- |
| `webx-field`, `webx-field--<type>`, `is-invalid`, `is-required`, `is-disabled`                                                       | the field               |
| `__label` (`--legend` on a group), `__required`, `__optional`, `__hint`, `__error`                                                   | around the control      |
| `__control` (`--textarea`, `--select`, `--password`, `--file`), `__select`, `__password`, `__show`                                   | the control             |
| `__file`, `__file-button`, `__file-name`; `__choices`, `__choice` (`--inline`, `--single`, `--consent`), `__check`, `__choice-label` | files, groups and ticks |

Repaint it the way every widget is repainted — [Where the styles live](./styles):

- **tokens** — the field follows `color-border`, `color-danger`, `color-accent`, `radius-md`;
- **its own variables** on `:root` — `--webx-field-height`, `--webx-field-radius`,
  `--webx-field-border`, `--webx-field-bg`, `--webx-field-focus`, `--webx-field-gap`,
  `--webx-field-check`;
- **the same classes** in `theme/src/css` — they come later in the cascade;

<!-- v-pre: the mustaches below are Blade. -->

::: v-pre

- **another control of a type** — `theme/views/vendor/webx-widgets/components/field/<type>.blade.php`.
  Keep `{{ $control($attributes) }}` on the control: it carries `required`, the ARIA and the
  hints. The frame is `components/field.blade.php`.

:::

Without JavaScript the file input is the browser's own and there is no «Show»; everything else is
HTML and works as it is.

## The form

```blade
<x-webx-form :action="route('login')" class="sign-in">
    <x-webx-field type="email" name="email" :label="__('auth.email')" required />
    <x-webx-field type="password" name="password" :label="__('auth.password')" required />
    <x-webx-form.submit>{{ __('auth.sign_in') }}</x-webx-form.submit>
</x-webx-form>
```

`<x-webx-form>` is the `<form>` with what every form of the site needs, and `dist/form.js|css`
(about 3 KB gzip) only on the pages where one stands.

- **The token and the method** are printed by the form: `@csrf`, `@method` for `method="put"`,
  `patch` or `delete`. `:csrf="false"` is for a door that guards itself otherwise — Inbox's
  intake checks the Origin, a honeypot and a rate instead. `method="get"` — a search — is a plain
  form: nothing to send in place, no token.
- **Which form was sent.** A hidden `_webx_form` carries the form's `id` (`form-1`, `form-2`… in
  the order they stand, or `id="…"` — give one to a form of the layout, which stands on every
  page). It is how the page that comes back knows which of its forms to answer in.
- **Without JavaScript it is a plain POST.** The validator sends the page back with each error
  under its field; an error that belongs to no field of this form — a captcha, a rule about the
  whole form — is said in the form's alert, not nowhere. The answer of the controller comes back
  through the session to the form that was sent. A form with a file field is multipart by itself.
- **With JavaScript the same form is sent in place**, and comes to the same words in the same
  places:
  - `fetch` with `Accept: application/json` — or `XMLHttpRequest` when a file goes, for the
    upload's progress (`webx-form__progress`);
  - the token is `X-XSRF-TOKEN` from Laravel's cookie, not the one in the HTML: a page is cached
    with the token it was printed with. A 419 asks `GET /webx/widgets/csrf` for a fresh one and
    sends the form again — once; a second 419 says «The page has been open too long»;
  - while it goes the form has `is-sending` and `aria-busy`, its buttons `aria-disabled`: a second
    press, a second Enter send nothing. Held back rather than disabled — a disabled button would
    drop the focus of the one who pressed it;
  - a 422 in Laravel's format goes into the error places the fields already have: `is-invalid` on
    the field, `aria-invalid` on each control of it, the focus on the first field wrong in the
    order of the form. A key of a group's item (`extras.1`) is the group's. A corrected field
    loses its error as it is typed in;
  - the answer is said in a live region (`role="status"`, `aria-live="polite"`), the refusal in an
    alert (`role="alert"`) — both empty and without a box until there is something to say, so
    nothing on the page moves before.
- **Words** — «The form could not be sent…» and «The page has been open too long…»
  (`widgets.form.failed`, `widgets.form.expired`), «Close» of a dialog; props `failed-label`,
  `expired-label`, `close-label`.

### The answer

One convention for every form; a controller answers with one line and gets both ways:

```php
use WebxUi\Widgets\Forms\FormResponse;

return FormResponse::message(__('Thank you'), '<p>'.e(__('We will call you back.')).'</p>');
return FormResponse::redirect(route('account'));
return FormResponse::html(view('checkout.delivery', $data));
return FormResponse::ok();
```

| The answer                   | With JavaScript                                        | Without                                             |
| ---------------------------- | ------------------------------------------------------ | --------------------------------------------------- |
| `::message($heading, $html)` | under the fields with the heading; the form is emptied | back to the page, printed in the form that was sent |
| `::redirect($url)`           | goes there                                             | a redirect there                                    |
| `::html($view)`              | in place of the fields — the next step of a checkout   | back to the page, in place of that form's fields    |
| `::ok()`                     | nothing but `webx:form:success`                        | back to the page                                    |

To the script it is JSON — `{ "ok": true, "heading", "message", "redirect", "html" }`, the answer
Inbox gave first. The message is HTML and printed as it is: escape what a visitor wrote into it.
A controller that answers with a plain `redirect()` works too — the browser follows it and the
form goes where it ended.

### Events

On the `<form>`, bubbling — a module hangs its own on them and never writes a `fetch` of its own:

| Event               | When                         | `detail`                                         |
| ------------------- | ---------------------------- | ------------------------------------------------ |
| `webx:form:submit`  | before sending; cancelable   | `data` — the `FormData` (add to it), `submitter` |
| `webx:form:success` | a 2xx                        | `status`, `response` — the JSON of the answer    |
| `webx:form:error`   | 422, 419 twice, 5xx, offline | `status` (0 offline), `errors`, `message`        |

```js
document.querySelector('#checkout').addEventListener('webx:form:success', (event) => {
  // Counted only with consent to statistics (Site widgets › Cookie consent).
  if (window.webx.consent?.has('statistics')) window.dataLayer?.push({ event: 'order' })
})
```

### In a dialog

Any form in `<x-webx-dialog>` behaves as Inbox's does there: the thank-you takes the place of the
fields with a «Close» button that gets the focus — the dialog never closes by itself, there has to
be time to read — and closed, it is a fresh form again. Without JavaScript a page that comes back
from a form in a dialog prints the dialog `open`, over the page; with it, the dialog opens again as
a modal.

```blade
<a href="#callback" data-webx-dialog="callback">{{ __('Request a call back') }}</a>
<x-webx-dialog id="callback" :title="__('Request a call back')">
    <x-webx-form :action="route('callback')">
        <x-webx-field type="tel" name="phone" :label="__('Phone')" required />
        <x-webx-form.submit>{{ __('Call me') }}</x-webx-form.submit>
    </x-webx-form>
</x-webx-dialog>
```

### The look of the form

| Classes                                          | What                                 |
| ------------------------------------------------ | ------------------------------------ |
| `webx-form`, `is-sending`, `is-sent`             | the form                             |
| `__body`, `__submit`                             | the fields, the button               |
| `__message` > `__answer` > `__heading`, `__text` | the live region and the answer in it |
| `__error` > `__refusals` > `__refusal`           | the alert and what it says           |
| `__progress`, `__done`                           | the upload, «Close» in a dialog      |

Variables on `:root`: `--webx-form-gap`, `--webx-form-button-height`.

## Example: a sign-in

The whole of it — the route, the controller, the view. The rate of attempts and the session belong
to the sign-in, not to the form.

```php
// routes/web.php
use App\Http\Controllers\SignInController;

Route::middleware('web')->group(function () {
    Route::get('/sign-in', [SignInController::class, 'show'])->name('login');
    Route::post('/sign-in', [SignInController::class, 'store'])->middleware('throttle:6,1');
});
```

```php
// app/Http/Controllers/SignInController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebxUi\Widgets\Forms\FormResponse;

final class SignInController
{
    public function show(): View
    {
        return view('auth.sign-in');
    }

    public function store(Request $request): FormResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Under the address, in the page's language — with JavaScript and without.
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return FormResponse::redirect(redirect()->intended(route('account'))->getTargetUrl());
    }
}
```

```blade
{{-- resources/views/auth/sign-in.blade.php --}}
<x-layout>
    <h1>{{ __('Sign in') }}</h1>
    <x-webx-form :action="url('/sign-in')" id="sign-in" class="sign-in">
        <x-webx-field type="email" name="email" :label="__('Email')" required />
        <x-webx-field type="password" name="password" :label="__('Password')" required enterkeyhint="go" />
        <x-webx-field type="checkbox" name="remember" :label="__('Remember me')" />
        <x-webx-form.submit>{{ __('Sign in') }}</x-webx-form.submit>
    </x-webx-form>
</x-layout>
```

What the visitor gets without a line more: the password manager fills the address and the
password (`autocomplete="email"`, `current-password`); a wrong password is said under the address,
in place, and the focus goes there; a second press while the first is on its way sends nothing; a
page left open overnight signs in anyway — the stale token is renewed and the form sent once more;
the seventh attempt in a minute is refused, and the form's alert says it could not be sent; and the same form posts and
comes back with the same words where JavaScript is off.

## See it

**Kitchen sink › Forms › Fields** of the default theme's showcase: every type empty, filled, with
the server's errors and disabled; long German and Polish labels; a column 20rem wide where choices
meant for a row stand one under another.

**Kitchen sink › Forms › Sending**: one form on the page and the same in a dialog, sent to a door
of the demo that answers by the convention above — the validator's errors on an empty form, then a
thank-you, a redirect, the next step in place of the fields or nothing, as you pick. Send it with
JavaScript off and on: the same words in the same places.

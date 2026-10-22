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
| **Inbox**                      | the form builder in the panel, intake, spam protection, captcha, letters            |
| **sign-in, account, checkout** | their own forms in Blade from the same fields                                       |

Below: the fields, the form, the checks in the browser, suggestions while typing, then three forms
written out in full — a sign-in, a checkout step and a form of the Inbox.

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

::: tip A site without a theme
The scripts come with `@webxTheme`, which prints nothing on a site without a theme. Such a site
writes `@webxWidgets` before `</body>` in its layout and runs `php artisan webx:theme:sync` once:
the forms get their scripts — not the stylesheets, which are made of a theme's tokens; the site
styles `webx-field-*` and `webx-form-*` itself. Without the line every form still works, with a
reload, and the audit's `widgets.form_script` names the page. `webx:setup --no-theme` writes both.
:::

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
  (`widgets.form.failed`, `widgets.form.expired`), «Close» of a dialog, «Send another»
  (`widgets.form.again`); props `failed-label`, `expired-label`, `close-label`, `again-label`.

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

To the script it is JSON — `{ "ok": true, "heading", "heading_html", "message", "redirect", "html" }`,
the answer Inbox gave first. The message is HTML and printed as it is: escape what a visitor wrote
into it. The heading is text and printed escaped — unless it is an `HtmlString`: then it is printed
as it is (`heading_html`), and `heading` carries its text alone (`FormResponse::plain()`), for a
reader of only that. Inbox answers so when its thank-you has the visitor's answers in it.

```php
return FormResponse::message(new HtmlString('Thanks, <em class="webx-form__answer-value">'.e($name).'</em>.'));
```

A controller that answers with a plain `redirect()` works too — the browser follows it and the
form goes where it ended.

### Events

On the `<form>`, bubbling — a module hangs its own on them and never writes a `fetch` of its own:

| Event               | When                         | `detail`                                                          |
| ------------------- | ---------------------------- | ----------------------------------------------------------------- |
| `webx:form:submit`  | before sending; cancelable   | `data` — the `FormData` (add to it), `submitter`, `wait(promise)` |
| `webx:form:success` | a 2xx                        | `status`, `response` — the JSON of the answer                     |
| `webx:form:error`   | 422, 419 twice, 5xx, offline | `status` (0 offline), `errors`, `message`                         |

```js
document.querySelector('#checkout').addEventListener('webx:form:success', (event) => {
  // Counted only with consent to statistics (Site widgets › Cookie consent).
  if (window.webx.consent?.has('statistics')) window.dataLayer?.push({ event: 'order' })
})
```

### In a dialog

Any form in `<x-webx-dialog>` — and the Inbox's forms a button opens (`data-webx-form="<slug>"`) — behaves the same: the thank-you takes the place of the
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

### Send another

`<x-webx-form again>` — a form that is filled in more than once: a request after a request. After a
thank-you (`::message()`) the answer takes the place of the fields, and under it a «Send another»
(`webx-form__again`) brings them back empty, with the focus on the first field. Without JavaScript
it is a link to the same page, which comes back without the answer, so the form is empty again. Off by default; hidden until the form is sent.

```blade
<x-webx-form :action="route('request')" again :again-label="__('Send another request')">
    …
</x-webx-form>
```

### The look of the form

| Classes                                          | What                                 |
| ------------------------------------------------ | ------------------------------------ |
| `webx-form`, `is-sending`, `is-sent`             | the form                             |
| `__body`, `__submit`                             | the fields, the button               |
| `__message` > `__answer` > `__heading`, `__text` | the live region and the answer in it |
| `__error` > `__refusals` > `__refusal`           | the alert and what it says           |
| `__progress`, `__done`                           | the upload, «Close» in a dialog      |
| `__again`                                        | «Send another» (`again`)             |
| `__answer-value`                                 | a visitor's answer in the thank-you  |

Variables on `:root`: `--webx-form-gap`, `--webx-form-button-height`.

## Checks in the browser

The rules of a form are written once, on the server, where they are checked always. The browser
checks the part it understands before anything is sent, and says what is wrong in the words the
server would use — not a second dictionary, the validator's own lines:

```blade
<x-webx-form :action="route('checkout.contact')" :rules="FormRules::for($rules, $attributes, $messages)">
    …fields…
</x-webx-form>
```

`WebxUi\Widgets\Forms\FormRules::for()` takes the arrays the controller validates by — a
`FormRequest`'s `rules()`, `attributes()` and `messages()`, or the array of `$request->validate()`.
Every `<x-webx-field>` inside takes its own part by its name (`contact.email`, `lines.0.qty` is
`lines.*.qty`). One field can be given its rules alone: `:rules="['required', 'email']"`.

### What the browser checks

| Rule                                                   | In the browser                                                    |
| ------------------------------------------------------ | ----------------------------------------------------------------- |
| `required`, `accepted`                                 | a value, a tick; also `required` on the control and the `*`       |
| `email`, `url`                                         | the shape — looser than the server, never stricter                |
| `numeric`, `integer`                                   | the number; a text field gets the numeric keyboard                |
| `min`, `max`, `between`, `size`                        | a string's letters, a number, a file's kilobytes, a group's count |
| `regex`, `not_regex`                                   | when the expression means the same in JavaScript                  |
| `in`                                                   | one of the values                                                 |
| `mimes`, `extensions`, `mimetypes`, `image`            | a file's type; also `accept` on the picker                        |
| `confirmed`, `same`, `different`                       | against the other field of the form                               |
| `date`                                                 | a day that cannot be (`2026-02-30`)                               |
| `after`, `before`, `after_or_equal`, `before_or_equal` | with a literal `Y-m-d`; also `min`/`max` of a date picker         |
| `bail`, `nullable`                                     | how the others run, as on the server                              |

Everything else — `unique`, `exists`, `required_if`, a closure, a rule object, `after:tomorrow`
(the server's clock), a PCRE with an atomic group or a possessive quantifier — is the server's
alone. Silence, not an error: the browser lets it through and the server answers it.

The order is the validator's: a field sent empty meets only `required` and `accepted`, unless it is
not `nullable` — then the server holds every rule against it, and so does the browser (an optional
field wants `nullable`). After a failed `required` nothing more is said; under `bail`, after the
first failure. A field with several rules that fail says them all, as the server's 422 does.

The words are asked of Laravel's validator at the page's render, in the page's language: the
form's own `messages`, `validation.custom.*`, the names of `attributes` and
`validation.attributes.*`. A site that wants the validator in Russian or Polish installs its lines
(`lang/ru/validation.php`; Laravel ships English) — the browser follows by itself.

### When

- **A touched field when it is left.** Tabbing through an empty form says nothing.
- **On submit**, every field; nothing is sent while one is wrong, and the focus goes to the first.
- **As a wrong field is corrected**: checked on every keystroke, the error gone the moment it is
  right. A confirmation puts its password right too.
- An error the browser cannot check — the server's `unique` — goes at the first change, as before.

The script sets `novalidate` on the form: the browser's bubbles are off, and the errors look one way
in every language and browser. Without the script the browser's own checks stay — the only ones
before the round trip. A submit button with `formnovalidate` sends without the browser's checks,
straight to the server.

### The phone mask

```blade
<x-webx-field type="tel" name="phone" :label="__('Phone')" mask />
```

International: `+`, the country code, the rest in groups — `+48 512 345 678`,
`+1 212 555 0123`. No library and no list of countries: the length of a country code is told by its
first digits. Nothing is refused on the way in, so a paste, an autofill and a phone's keyboard all
work; the value is put in order after them. A digit typed into an empty field starts with `+`; a
number pasted without `+` is someone's local number and is left alone. The server gets what the
visitor sees (`+48 512 345 678`) — a rule like `regex:/^\+[1-9][0-9 ]{6,18}$/` takes it.
`dist/mask.js` loads only where a field asks for it.

### Files

Every `type="file"` field, by itself (`dist/files.js`): files dropped on the field are taken as
chosen; the chosen are listed under it — a picture's preview, the name, the size in the page's
language — each with Remove; choosing again in a field of several adds rather than replaces. The
form checks the type and the size the moment a file is chosen or dropped, and marks the refused
rows. Sent with a file, the form goes by `XMLHttpRequest` with its progress bar.

| Classes                                                             | What                       |
| ------------------------------------------------------------------- | -------------------------- |
| `webx-field__file.is-dragging`                                      | a file held over the field |
| `__drop`                                                            | «or drop files here»       |
| `__files` > `__files-item` (`is-invalid`)                           | the list, a row            |
| `__files-preview`, `__files-name`, `__files-size`, `__files-remove` | its parts                  |

Words: `widgets.field.remove`, `drop_file`, `drop_files` (props `remove-label`, `drop-label`);
`--webx-files-preview` is the preview's size.

## Suggestions while typing

```blade
<x-webx-suggest :action="route('webx.catalog.search')" :source="route('webx.catalog.suggest')" :recent="5" />
```

`<x-webx-suggest>` is a search box that asks the site as the visitor types (`dist/suggest.js`,
claimed only by the component). The server prints an ordinary GET form to the results page — with
JavaScript off, or before it runs, it is a search like any other: Enter opens the results. The
script makes the field an ARIA combobox, waits for a pause (`delay`, 250 ms) after `min` characters
(2), asks `source?q=…`, cancels a request the next key made stale and drops an answer that still
comes late, and puts what came under the field.

**The content of the panel is the site's.** `source` answers HTML from a view of the site — sections,
headings, counts, cards with a price, «see all» — and the panel shows it as it is. The shell needs
only two marks in it:

| Mark                                                 | What the shell does with it                                                                                                                                          |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `data-webx-option`                                   | an option: `role="option"`, an id, reached by the arrows; a link inside it (or the option itself as `<a>`) is followed; `data-webx-option="<value>"` is searched for |
| `data-webx-section` with a `data-webx-heading` in it | a named group (`role="group"`, `aria-labelledby` the heading; an `<h2>`–`<h6>` will do)                                                                              |

An answer that is empty, or a 204, says «nothing found» in the widget's words; an answer without
an option shows the site's own words and the screen reader hears «nothing found». A failed answer
says so and leaves the search to Enter. Everything else in the answer is the site's to style.

**Keys** — the focus stays in the field: ↓/↑ walk the options (and open the panel), Home/End jump
to the first or the last once an option is chosen (before that they move the caret), Enter takes
the option chosen (without one it sends the form), Escape closes the panel (the second clears the
field, as a search field does), Tab leaves. A screen reader hears `aria-expanded`, the option by
`aria-activedescendant` and, in a status line, «Suggestions: 5». The panel is the width of the box
and never taller than the room above a phone's keyboard.

**Recent searches** — `:recent="5"` keeps the last five in the visitor's browser (`localStorage`,
per results page) and shows them while the field is empty, with «Clear recent searches». Nothing
is kept without it.

**Inside another form** — leave out `action`: the component prints no form of its own (forms do
not nest), only the field, named as any field (`name="delivery[city]"`, `old()` kept). An option
with a value then fills the field and fires `change` on it — the form's checks hear it — and sends
nothing; Enter without a choice is the form's. `param` names the query parameter `source` gets
(`q` by default).

```blade
<x-webx-form :action="route('checkout.delivery')" :rules="$rules">
    <x-webx-suggest :source="route('cities.suggest')" name="delivery[city]" param="city"
                    :label="__('checkout.city')" :show-label="true" />
    <x-webx-form.submit>{{ __('checkout.continue') }}</x-webx-form.submit>
</x-webx-form>
```

The address answers values: `<li data-webx-option="Kraków">Kraków</li>`. The field has no
`webx-field` frame: an error of the server about it comes into the form's alert.

| Prop                                                                                                                  | What                                              |
| --------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| `source`                                                                                                              | the address of suggestions, this site's           |
| `action`                                                                                                              | the results page; without it — a field            |
| `name`, `param`, `value`, `id`                                                                                        | the field; the query parameter; its value; its id |
| `min`, `delay`, `recent`                                                                                              | 2 characters, 250 ms, 0 kept                      |
| `show-label`                                                                                                          | the label shown, not only read                    |
| `label`, `placeholder`, `submit-label`, `nothing-label`, `recent-label`, `clear-label`, `count-label`, `failed-label` | the words (`widgets.suggest.*`, ten languages)    |

**Events** on the component's root: `webx:suggest:select` — cancelable, before an option is taken
(`detail.option`, `value`, `href`, `query`: what was typed); `webx:suggest:results` — the panel
filled (`detail.query`, `count`). A counter of the analytics listens there, with consent. The root carries
`data-webx-suggest="<source>"` and the settings in `data-*`: an overridden view of the component keeps them.

| Classes                                                                  | What                                      |
| ------------------------------------------------------------------------ | ----------------------------------------- |
| `webx-suggest` (`--field`), `is-open`, `is-loading`                      | the box, the panel open, a question asked |
| `__label` (`--shown`), `__box`, `__input` (`--alone`), `__submit`        | its parts                                 |
| `__panel`, `__status`                                                    | the panel, the line a screen reader hears |
| `__section`, `__heading`, `__option` (`aria-selected="true"`), `__clear` | what the shell marks or builds in it      |
| `__empty`                                                                | «nothing found» and «not available»       |

Tokens `--webx-suggest-height`, `-radius`, `-border`, `-bg`, `-focus`, `-panel-bg`,
`-panel-shadow`, `-active-bg`, `-max` (the panel's height), `-z`.

### Example: the catalogue's search

`module-catalog` is the first to use it. Its search page prints the box over its own address:

```blade
{{-- webx-catalog::search --}}
<x-webx-suggest :action="$action" :source="$suggest" :value="$term"
                :label="__('webx-catalog::storefront.search')"
                :placeholder="__('webx-catalog::storefront.search-placeholder')"
                :submit-label="__('webx-catalog::storefront.search-submit')" />
```

`$action` and `$suggest` are `/catalog/search` and `/catalog/suggest` in the page's language.
`GET /catalog/suggest?q=` (`webx.catalog.suggest`) asks the same engine the search page asks — what
is not on the site is not found, a correction is said — and answers the view
`webx-catalog::suggest` (`noindex`, `no-store`; 204 for nothing asked; the code is
`WebxUi\Catalog\Storefront\Suggestions`):

```blade
{{-- webx-catalog::suggest — a theme overrides it --}}
@if ($products->isEmpty())
    <p class="webx-catalog-suggest__nothing">{{ __('webx-catalog::storefront.suggest-nothing', ['q' => $term]) }}</p>
@else
    <div class="webx-catalog-suggest" data-webx-section="products">
        <p class="webx-catalog-suggest__heading" data-webx-heading>{{ __('webx-catalog::storefront.suggest-products', ['count' => $total]) }}</p>
        @foreach ($products as $product)
            <a class="webx-catalog-suggest__product" href="{{ $product->listedUrl() }}" data-webx-option>
                <img class="webx-catalog-suggest__picture" src="…" alt="" width="48" height="48">
                <span class="webx-catalog-suggest__name">{{ $product->displayName() }}</span>
                <span class="webx-catalog-suggest__price"><strong>{{ $product->price }}</strong></span>
            </a>
        @endforeach
    </div>
    <a class="webx-catalog-suggest__all" href="{{ $all }}" data-webx-option>{{ __('webx-catalog::storefront.suggest-all', ['count' => $total]) }}</a>
@endif
```

«Products (52)», five cards with a picture and a price, «See all results: 52» — every card and
«see all» an option. `suggest.limit` (`WEBX_CATALOG_SUGGEST_LIMIT`, 6) is how many cards. A header
of the site puts the same box anywhere — the tag at the top of this section.

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

## Example: a checkout step

The contact and delivery step of a checkout: the rules in a `FormRequest`, the same rules in the
browser, the next step — payment — sent back in place of the fields. The basket, the totals and the
payment belong to the checkout, not to the form.

```php
// routes/web.php
use App\Http\Controllers\CheckoutController;

Route::middleware('web')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout/contact', [CheckoutController::class, 'contact'])->name('checkout.contact');
    Route::post('/checkout/payment', [CheckoutController::class, 'payment'])->name('checkout.payment');
});
```

```php
// app/Http/Requests/CheckoutContactRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact.name' => ['required', 'string', 'max:120'],
            'contact.email' => ['required', 'email', 'max:190'],
            'contact.phone' => ['required', 'regex:/^\+[1-9][0-9 ]{6,18}$/'],
            'delivery' => ['required', Rule::in(['courier', 'pickup'])],
            // The browser cannot know when it is wanted: the server says it.
            'address' => ['required_if:delivery,courier', 'nullable', 'string', 'max:500'],
            'invoice' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'terms' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contact.name' => __('checkout.name'),
            'contact.email' => __('checkout.email'),
            'contact.phone' => __('checkout.phone'),
            'delivery' => __('checkout.delivery'),
            'address' => __('checkout.address'),
            'invoice' => __('checkout.invoice'),
            'terms' => __('checkout.terms'),
        ];
    }
}
```

```php
// app/Http/Controllers/CheckoutController.php
namespace App\Http\Controllers;

use App\Http\Requests\CheckoutContactRequest;
use App\Shop\Basket;
use Illuminate\View\View;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

final class CheckoutController
{
    public function show(Basket $basket): View
    {
        // A FormRequest made with `new` only says its rules; resolved from the container it would
        // validate the request that shows the page.
        $request = new CheckoutContactRequest;

        return view('checkout.contact', [
            'basket' => $basket,
            'rules' => FormRules::for($request->rules(), $request->attributes(), $request->messages()),
        ]);
    }

    public function contact(CheckoutContactRequest $request, Basket $basket): FormResponse
    {
        $order = $basket->order($request->validated(), $request->file('invoice'));

        // The next step in place of the fields; without JavaScript the same page shows it.
        return FormResponse::html(view('checkout.payment', ['order' => $order]));
    }
}
```

```blade
{{-- resources/views/checkout/contact.blade.php --}}
<x-layout>
    <h1>{{ __('checkout.title') }}</h1>
    <x-webx-form :action="route('checkout.contact')" id="checkout" class="checkout" :rules="$rules">
        <x-webx-field name="contact.name" :label="__('checkout.name')" autocomplete="name" />
        <x-webx-field name="contact.email" type="email" :label="__('checkout.email')" />
        <x-webx-field name="contact.phone" type="tel" :label="__('checkout.phone')" mask />
        <x-webx-field name="delivery" type="radio" :label="__('checkout.delivery')" value="courier"
            :choices="['courier' => __('checkout.courier'), 'pickup' => __('checkout.pickup')]" inline />
        <x-webx-field name="address" type="textarea" :label="__('checkout.address')" autocomplete="street-address" rows="3" />
        <x-webx-field name="invoice" type="file" :label="__('checkout.invoice')" :hint="__('checkout.invoice_hint')" optional />
        <x-webx-field name="terms" type="consent">
            {!! __('checkout.terms_sentence', ['link' => route('terms')]) !!}
        </x-webx-field>
        <x-webx-form.submit>{{ __('checkout.to_payment') }}</x-webx-form.submit>
    </x-webx-form>
</x-layout>
```

```blade
{{-- resources/views/checkout/payment.blade.php — the next step, sent in place --}}
<p>{{ __('checkout.total', ['total' => $order->total()]) }}</p>
<x-webx-form :action="route('checkout.payment')" id="checkout-payment">
    <input type="hidden" name="order" value="{{ $order->id }}">
    <x-webx-field name="method" type="radio" :label="__('checkout.method')" :choices="$order->paymentMethods()" required />
    <x-webx-form.submit>{{ __('checkout.pay') }}</x-webx-form.submit>
</x-webx-form>
```

What the visitor gets: an address without `@` or a phone without its country code is said under its
field when the field is left, in the words the server would use; nothing is sent while one is wrong;
the phone is written `+48 512 345 678` however it was typed or pasted; a scan of an invoice dropped
on its field is listed with its size, and a JPEG or a 9 MB PDF is refused before a byte is sent; a
courier without an address is refused by the server — the one rule the browser cannot know — in the
same place and the same look. The payment step takes the fields' place, its own form already
working (`webx.mount`). A module that recomputes the delivery cost listens to `change` on the
radios and `webx:form:success` on the form, and writes no sending of its own.

## Example: a form of the Inbox

A request built in the panel's Inbox from fields, printed with one tag. Nothing of it is written by
hand, and that is the point: it is the same engine as the two forms above.

```blade
<x-webx-inbox::form slug="contact" :values="['product' => $product->id]" placement="footer" />
<x-webx-inbox::form slug="request" again />  {{-- «Send another» after the thank-you --}}
```

What it prints, in short:

```blade
<x-webx-form :action="url('webx/forms/contact')" :csrf="false" id="webx-inbox-contact"
    :rules="FormRules::for($intakeRules, $labels)" data-webx-form="contact"
    class="webx-inbox-form webx-inbox-form--footer">
    {{-- the inbox's own: which form, the page, its language, the honeypot, the timestamp --}}
    <div class="webx-inbox-form__fields">
        <x-webx-field type="text" name="fields.name" :label="…" class="webx-inbox-form__field webx-inbox-form__field--half" />
        <x-webx-field type="email" name="fields.email" :label="…" class="webx-inbox-form__field webx-inbox-form__field--half" />
        <x-webx-field type="consent" name="fields.terms" :label="…">{!! $consentWithALink !!}</x-webx-field>
    </div>
    {{-- the captcha, when the form asks for one: data-webx-field="captcha" --}}
    <div class="webx-inbox-form__actions"><x-webx-form.submit>Send</x-webx-form.submit></div>
</x-webx-form>
```

- **The fields** are `<x-webx-field>` named `fields.<machine name>` — the eleven types of the panel
  are the widgets' own (`text`, `email`, `tel`, `textarea`, `select`, `radio`, `checkbox`,
  `consent`, `date`, `file`, `hidden`); «half width» in the panel is
  `webx-inbox-form__field--half`, two to a row from 32rem of the form.
- **No token:** `:csrf="false"` — the intake guards itself (the origin, the honeypot, the
  timestamp, the limit per address), so a page cached whole for a day still sends.
- **The checks** are the intake's own rules — `required`, `email`, `max` of a line, `in` of the
  choices, `accepted` of a consent, `mimes` and `max` of a file, the dates' bounds, a phone's
  pattern — through `FormRules`, with each field's label: the browser says what a 422 would.
- **The answer** is `FormResponse`: the form's thank-you (`::message()`) with the visitor's answers
  in it ([Inbox](./inbox#the-public-door)), or its address (`::redirect()`) when the form has one; an antispam refusal in the form's alert, a captcha's
  under the captcha.
- **The Inbox's own** stays the Inbox's: the builder, the intake, the honeypot, the timestamp and
  the captcha — reCAPTCHA or Turnstile, run by `inbox.js` on `webx:form:submit` and reset after
  `webx:form:success` or `webx:form:error`; the script comes only with a captcha.
- **Analytics** of a request: `webx:form:success` on `form[data-webx-form="contact"]`, the way
  `webx:form:*` is heard on any form.

```js
document.addEventListener('webx:form:success', (event) => {
  const slug = event.target.dataset.webxForm
  if (slug && window.webx.consent?.has('statistics'))
    window.dataLayer?.push({ event: 'request', form: slug })
})
```

**A site that painted the Inbox's form before W6.4** (`wx-form*`, a published `form.blade.php`):
its view keeps working for an alias period — the module still hands it what it used to and its old
script — and the audit's `inbox.legacy_views` names it until it is moved. The classes map one to
one: [the table in the Inbox guide](./inbox#the-look-of-the-form).

## See it

**Kitchen sink › Forms › Fields** of the default theme's showcase: every type empty, filled, with
the server's errors and disabled; long German and Polish labels; a column 20rem wide where choices
meant for a row stand one under another.

**Kitchen sink › Forms › Sending**: one form on the page and the same in a dialog, sent to a door
of the demo that answers by the convention above — the validator's errors on an empty form, then a
thank-you, a redirect, the next step in place of the fields or nothing, as you pick. Send it with
JavaScript off and on: the same words in the same places.

**Kitchen sink › Forms › Validation**: one form with a rule of every kind, checked in the browser
and, with «Ask the server», past it — the same words both ways, in English, Russian and Polish.
The name `admin` passes the browser and not the server: that rule is the server's alone. The phone
mask, and photos dropped, listed, refused by type and size and removed before sending.

**Kitchen sink › Forms › Inbox**: a form built in the panel, with a field of every kind of the
Inbox, on the same engine — send it empty with JavaScript on and off: the same words under the same
fields, the browser's the intake's.

**Kitchen sink › Forms › Suggestions**: a search box over goods of the demo — type «oak», walk the
panel with the arrows, Enter; leave it empty for the recent searches; with JavaScript off, Enter
opens the results — and a city field inside a delivery form, which a suggestion fills without
sending it.

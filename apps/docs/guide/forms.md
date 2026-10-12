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

This page grows with the parts. Today: **the fields**. The form itself (`<x-webx-form>`, sending
without a reload, the answer of the server) and the checks in the browser follow; until then a
field stands in any `<form method="post">` with `@csrf`, and Laravel's redirect back shows its
errors in place.

The specification is §17 of
[`docs/architecture/WEBX_UI_WIDGETS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_WIDGETS.md).

## Fields

```blade
<form method="post" action="{{ route('login') }}">
    @csrf
    <x-webx-field type="email" name="email" :label="__('auth.email')" required />
    <x-webx-field type="password" name="password" :label="__('auth.password')" required />
    <x-webx-field type="checkbox" name="remember" :label="__('auth.remember')" />
    <button type="submit">{{ __('auth.sign_in') }}</button>
</form>
```

That is the whole of it. A field claims its own files (`dist/field.js|css`, about 2 KB gzip), and
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
there, empty and hidden, before there is anything to say — the form's script will write into it.
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

## See it

**Kitchen sink › Forms › Fields** of the default theme's showcase: every type empty, filled, with
the server's errors and disabled; long German and Polish labels; a column 20rem wide where choices
meant for a row stand one under another.

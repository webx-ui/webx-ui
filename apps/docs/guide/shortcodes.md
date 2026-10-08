# Shortcodes

A shortcode is a name in brackets that content keeps as typed and the site prints as something
else: `Call us on [phone]`. Two jobs:

- **One value, typed once.** A phone number, an e-mail, an address typed by hand into forty
  blocks goes stale in thirty-nine of them. Typed as `[phone]`, it changes on every page the day
  the setting does — nothing in the content is rewritten.
- **A piece of the site's own markup an editor cannot break.** `[dot]` can be a full stop the
  site's CSS colours, `[badge]` a small label; the editor types a word in brackets, never HTML.

The content always stores the bracket. The page, the preview in the panel and `blocks_render`
over MCP print the same result, because they share one renderer.

## Data shortcodes from the panel

«Settings» → «Shortcodes» is a list anybody with `settings.manage` can extend — no developer
needed. Each row has:

| Field   | What it is                                                                                   |
| ------- | -------------------------------------------------------------------------------------------- |
| Name    | Lowercase letters, digits, `-` and `_`. Typed in content as `[name]`                         |
| Setting | Optional: the key of a setting to read, e.g. `contacts.phone` — the value follows that field |
| Value   | What it prints when it reads no setting; one per language                                    |

What a value looks like decides how it is printed:

| Value looks like | HTML                                               | Plain text         |
| ---------------- | -------------------------------------------------- | ------------------ |
| a phone number   | `<a href="tel:+442079460958">+44 20 7946 0958</a>` | `+44 20 7946 0958` |
| an e-mail        | `<a href="mailto:…">…</a>`                         | the address        |
| anything else    | the text, escaped, line breaks as `<br>`           | the text           |

Arguments: `[phone link=no]` prints the number without the link, `[phone format=intl]` prints
`+442079460958`, `[phone format=digits]` the digits alone.

## A shortcode of the site's own

A site registers its own in a provider — the package never ships one like this, because the
markup and its class are the site's:

```php
// app/Providers/AppServiceProvider.php
use WebxUi\Admin\Facades\Shortcodes;

public function boot(): void
{
    Shortcodes::register(
        'dot',
        '<span class="accent-dot">.</span>',   // HTML, printed as it is
        plain: '.',                             // for <title>, meta, JSON-LD, mail
        description: 'The accented full stop of headings',
    );

    // With arguments: [badge text="New"]
    Shortcodes::register(
        'badge',
        fn (array $args): string => '<span class="badge">'.e($args['text'] ?? '').'</span>',
        plain: fn (array $args): string => $args['text'] ?? '',
    );
}
```

```css
/* resources/css/site.css — the site's styles, not a block's */
.accent-dot {
  color: var(--site-accent);
}
```

The HTML of a registered shortcode is trusted: it is written by the site's developer and printed
raw. Anything built from an argument goes through `e()`, as above. A name registered in code wins
over a panel row with the same name.

## The rules, everywhere

- **Only a registered name is replaced.** `[1]`, `[sic]`, `[citation needed]` stay as typed. The
  audit reports brackets that look like a slip of a real name (see below).
- **`[[phone]]` prints `[phone]`**, for a page that has to show the bracket itself.
- **Escaping first, then the shortcode.** In a text field the editor's text is escaped and only
  the shortcode's own HTML goes in raw. A shortcode never makes `<script>` typed next to it
  trusted, and its output is never escaped a second time.
- **Plain text gets the plain rendering.** `<title>`, the meta description, Open Graph, JSON-LD,
  the inbox notification, the blog's RSS and the outline an agent reads take `plain` — the
  number, the full stop — never markup.
- **Rich text** (`wx-rich-text`, inline too) is resolved between its tags only: a bracket in an
  attribute or inside `<code>`/`<pre>` stays as written.

## In a block template

Nothing to do. A text field holding a shortcode reaches the template resolved:

```blade
<h1>{{ $heading }}</h1>          {{-- escaped text with the shortcode's HTML in it --}}
<div>{!! $body !!}</div>         {{-- rich text, shortcodes replaced between the tags --}}
<img alt="@shortcodesPlain($heading)" src="{{ $image['url'] }}">
```

A text field without a shortcode is the same string it always was. One with a shortcode is a
`ShortcodeText`: `{{ }}` prints its HTML without escaping it again, `->plain()` is the text and
`->text()` what the editor typed. In an attribute use `@shortcodesPlain(...)`, which takes either.
A `wx-input` of type `email`, `url` or `tel` is never resolved: it goes into an attribute, and its
rules refuse a bracket anyway.

## In the site's own templates

For a field a block does not print — an article's title, a product's description:

| Directive                 | Prints                                                             |
| ------------------------- | ------------------------------------------------------------------ |
| `@shortcodes($text)`      | an editor's text escaped, the shortcodes as HTML                   |
| `@shortcodesIn($html)`    | trusted HTML (a rich text field) with the shortcodes replaced      |
| `@shortcodesPlain($text)` | the text with the shortcodes as plain values, escaped — attributes |

In PHP: `Shortcodes::html($text)`, `Shortcodes::htmlIn($html)`, `Shortcodes::plain($text)`,
`Shortcodes::text($html)` (tags out, then plain), `Shortcodes::list()`.

## An accent mark or a shortcode?

Both put a coloured full stop into a heading; they are for different things.

- **The inline accent mark** (`wx-rich-text` with `props.inline`, see
  [Blocks](/guide/blocks#a-block-type)) is formatting the editor chooses per heading — any word
  may be marked, and the result is a bare `<span>` the theme styles.
- **A shortcode** is the same snippet everywhere, the site's exact markup with its class, and a
  value it reads from one place. Use it for data (`[phone]`, `[email]`) and for a fixed symbol
  the brand repeats, typed the same way in a plain input, a textarea or rich text.

## In the panel

Typing `[` in a text field of a block offers the shortcodes with their current values; a
shortcode already in the text is drawn as a chip, and the field's help lists them all. The
preview prints them as the site does.

## For an agent

`blocks://shortcodes` lists every shortcode with what it prints in a page and in plain text, and
the rules; `settings://content-rules` names them too. An agent writes `[phone]`, never the number,
and keeps `[dot]` and every other shortcode a text already has.

## The audit

With `webx-ui/module-audit` installed, two checks read the text fields of every entity's blocks,
live and draft:

| Check                       | Finds                                                                                                     |
| --------------------------- | --------------------------------------------------------------------------------------------------------- |
| `blocks.unknown_shortcodes` | a bracket a letter or two off a registered name (`[phnoe]`), or with arguments, that would print as typed |
| `blocks.hardcoded_values`   | a value a panel shortcode holds, typed by hand — a phone found however it is spaced                       |

---
'@webx-ui/php': minor
---

Shortcodes in block content: `Call us on [phone]` prints the number from one place, and `[dot]`
prints a site's own snippet. A site registers its own with `Shortcodes::register('dot', '<span
class="accent-dot">.</span>', plain: '.')`; «Settings» → «Shortcodes» defines data shortcodes in
the panel — a name reading a setting or holding its value, printed as a `tel:` or `mailto:` link
when it is a phone or an e-mail, with `link=no` and `format=intl|digits`. Blocks resolve them in
every text, textarea and rich text field (repeater items too): the editor's text escaped first,
the shortcode's HTML raw, never escaped twice; only registered names, `[[name]]` for a literal.
Titles, meta descriptions, Open Graph, JSON-LD, the inbox mail, the blog's RSS and the MCP
outline get the plain rendering. `@shortcodes`, `@shortcodesIn` and `@shortcodesPlain` resolve a
site template's own fields. MCP gains `blocks://shortcodes`, and `settings://content-rules`
lists them; the audit gains `blocks.unknown_shortcodes` and `blocks.hardcoded_values`.

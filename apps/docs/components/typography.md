<script setup>
import TypographyDemo from '../components/demos/TypographyDemo.vue'
</script>

# Typography

Four components cover the text in an admin panel: `WxHeading` for titles, `WxText` for everything
you write in a template, `WxLink` for links, and `WxProse` for HTML that arrives from the editor or
the CMS.

<TypographyDemo />

## Heading

```vue
<template>
  <wx-heading :level="1">Page title</wx-heading>
  <wx-heading :level="2" size="md" tone="muted">A section that has to look small</wx-heading>
</template>
```

`level` decides the tag — keep it truthful to the document outline — and `size` decides how it
looks, so an `h2` can read as a small label without breaking the outline for a screen reader.

Headings carry no margin: spacing belongs to the layout around them, not to the heading itself.

| Prop       | Type                                             | Default    | Description               |
| ---------- | ------------------------------------------------ | ---------- | ------------------------- |
| `level`    | `1 \| 2 \| 3 \| 4 \| 5 \| 6`                     | `2`        | Which `<h*>` is rendered  |
| `size`     | `'sm' \| 'md' \| 'lg' \| 'xl' \| '2xl' \| '3xl'` | by level   | Visual size               |
| `tone`     | `TextTone`                                       | `'strong'` | Colour role               |
| `align`    | `'start' \| 'center' \| 'end' \| 'justify'`      | —          | Text alignment            |
| `truncate` | `boolean`                                        | `false`    | One line with an ellipsis |

## Text

```vue
<template>
  <wx-text as="p">Body text.</wx-text>
  <wx-text as="p" size="sm" tone="muted">A hint under a field.</wx-text>
  <wx-text mono>ORD-2026-000431</wx-text>
  <wx-text :truncate="2">Two lines, then an ellipsis…</wx-text>
</template>
```

`truncate` takes `true` for one line or a number of lines to clamp to. Either way the element needs
a width to cut against — a grid cell, a card, a `max-width`.

| Prop       | Type                                            | Default     | Description                    |
| ---------- | ----------------------------------------------- | ----------- | ------------------------------ |
| `as`       | `string`                                        | `'span'`    | Element to render              |
| `size`     | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'`          | `'md'`      | Text size                      |
| `weight`   | `'regular' \| 'medium' \| 'semibold' \| 'bold'` | `'regular'` | Font weight                    |
| `tone`     | `TextTone`                                      | `'default'` | Colour role                    |
| `align`    | `'start' \| 'center' \| 'end' \| 'justify'`     | —           | Text alignment                 |
| `truncate` | `boolean \| number`                             | `false`     | One line, or that many lines   |
| `italic`   | `boolean`                                       | `false`     | Italic                         |
| `mono`     | `boolean`                                       | `false`     | Monospace with tabular figures |

`TextTone` is `'default' | 'strong' | 'muted' | 'placeholder' | 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'inverse'`.

## Link

```vue
<template>
  <wx-link href="/admin/pages">Pages</wx-link>
  <wx-link href="https://vuejs.org" external>Vue docs</wx-link>
  <wx-link :as="RouterLink" to="/admin/pages">Pages</wx-link>
</template>
```

`external` opens the link in a new tab with `rel="noopener noreferrer"` and appends a small arrow.
`as` renders through another component — `RouterLink`, say, with `to` passed straight through.

A disabled link drops its `href` and swallows clicks, so it cannot be followed by keyboard either.

| Prop        | Type                                                                                | Default     | Description                      |
| ----------- | ----------------------------------------------------------------------------------- | ----------- | -------------------------------- |
| `href`      | `string`                                                                            | —           | Target                           |
| `type`      | `'default' \| 'primary' \| 'success' \| 'warning' \| 'danger' \| 'info' \| 'muted'` | `'default'` | Colour role                      |
| `underline` | `'hover' \| 'always' \| 'never'`                                                    | `'hover'`   | When the underline shows         |
| `size`      | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'`                                              | inherited   | Text size                        |
| `weight`    | `'regular' \| 'medium' \| 'semibold' \| 'bold'`                                     | inherited   | Font weight                      |
| `disabled`  | `boolean`                                                                           | `false`     | Blocks the link                  |
| `external`  | `boolean`                                                                           | `false`     | New tab, safe `rel`, arrow icon  |
| `as`        | `string \| Component`                                                               | `'a'`       | Render through another component |

**Events:** `click` (`MouseEvent`). **Slots:** `default`, `icon`.

## Prose

`WxProse` styles a block of HTML you did not write in the template — the output of
[RichText](/components/rich-text), a CMS field, a rendered Markdown file. Headings, paragraphs,
lists, quotes, code, images, tables: all from the tokens, in both themes.

```vue
<template>
  <wx-prose :html="page.body" />
</template>
```

Or with content from the template:

```vue
<template>
  <wx-prose>
    <h2>Publishing a page</h2>
    <p>Everything inside gets the same typography.</p>
  </wx-prose>
</template>
```

::: warning
`html` is injected as-is. Sanitise it on the server — Laravel's `clean()`, HTMLPurifier, whatever
your stack uses — before it reaches the component.
:::

A pasted table scrolls inside its own box rather than pushing the page sideways, and images are
capped at the column width.

| Prop   | Type                   | Default | Description                            |
| ------ | ---------------------- | ------- | -------------------------------------- |
| `html` | `string`               | —       | Markup to render; omit to use the slot |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'`  | Text scale of the whole block          |
| `as`   | `string`               | `'div'` | Element to render                      |

The rhythm between blocks is one variable, so a caller can tighten it:

```vue
<template>
  <wx-prose :html="body" style="--wx-prose-flow: 10px" />
</template>
```

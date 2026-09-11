<script setup>
import ResultDemo from '../components/demos/ResultDemo.vue'
</script>

# Result

`WxResult` is a whole page saying how something turned out — an order placed, a payment refused, a
route that does not exist.

<ResultDemo />

## Usage

```vue
<template>
  <wx-result status="success" title="Order placed" subtitle="A receipt is on its way.">
    <template #actions>
      <wx-button variant="outline" @click="back">Back to orders</wx-button>
      <wx-button type="primary" @click="open">View order</wx-button>
    </template>
  </wx-result>
</template>
```

## It is a page, not a message

A toast for something that happened while the reader was working; an [Alert](/components/alert) for
something wrong with the form in front of them; this for a page whose whole content is the outcome.
If there is anything else on the screen worth looking at, this is the wrong component.

## Always offer the way out

A result with no `#actions` is a dead end — especially a 404, where the reader arrived by following
something broken and has nowhere to go but Back. One or two buttons: where they were, and the thing
they were trying to do.

## Statuses

`success`, `warning`, `danger` and `info` are outcomes of something the reader just did. `403`,
`404` and `500` are the HTTP replies an admin actually shows a page for; they borrow a colour
rather than having one of their own — a 404 is not an error the reader caused.

## Props

| Prop       | Type                                                                      | Default  | Description                      |
| ---------- | ------------------------------------------------------------------------- | -------- | -------------------------------- |
| `status`   | `'success' \| 'warning' \| 'danger' \| 'info' \| '403' \| '404' \| '500'` | `'info'` | What happened                    |
| `title`    | `string`                                                                  | —        | The outcome in a few words       |
| `subtitle` | `string`                                                                  | —        | What it means, or what to do     |
| `icon`     | `IconName`                                                                | —        | Instead of the one it would pick |

**Slots:** `icon`; `title`; `subtitle`; `actions` — the way out; `default` — anything under them,
such as a reference number or a stack trace behind a disclosure.

## Accessibility

The glyph is `aria-hidden`; the title and subtitle carry the meaning, so the outcome is never
colour alone. The page has changed to show this, so nothing here announces itself — a result that
also shouted into a live region would be read twice.

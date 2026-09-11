<script setup>
import TabsDemo from '../components/demos/TabsDemo.vue'
import TabsEditableDemo from '../components/demos/TabsEditableDemo.vue'
</script>

# Tabs

`WxTabs` and `WxTab` split one screen into panels that share the space: the sections of a page
editor, the settings of an account, the states of an order list.

<TabsDemo />

## Usage

A tab and its panel are written in one place. `WxTabs` reads the `label`, `icon` and `badge` off
each `WxTab` and builds the strip from them.

```vue
<script setup>
import { ref } from 'vue'

const section = ref('general')
</script>

<template>
  <wx-tabs v-model="section" aria-label="Page sections">
    <wx-tab value="general" label="General">Name, slug, template.</wx-tab>
    <wx-tab value="seo" label="SEO" icon="search">Title and description.</wx-tab>
    <wx-tab value="comments" label="Comments" :badge="12">Twelve waiting.</wx-tab>
  </wx-tabs>
</template>
```

Without `v-model` the first tab that is not disabled opens by itself, and that also holds when the
tabs arrive later — from a request, say. `v-model` is the way to drive them from a route, a wizard
step or a saved preference.

Tabs from an array work the same way:

```vue
<template>
  <wx-tabs v-model="active">
    <wx-tab v-for="tab in tabs" :key="tab.id" :value="tab.id" :label="tab.title" :badge="tab.count">
      <component :is="tab.panel" />
    </wx-tab>
  </wx-tabs>
</template>
```

## On a phone

The strip never wraps into a second row that would push the panel down the screen. Instead it
scrolls sideways, and the rest follows from that:

- the open tab is scrolled into view whenever it changes, including when something else changed it;
- the end that has more behind it fades out, so the strip reads as scrollable without spending any
  width on saying so;
- arrows appear for a mouse and stay out of the tab order — on a touch screen there are none,
  because a finger swipes, and every tab is at least 44px tall to be hit with one;
- a swipe along the strip cannot turn into a page swipe or a browser back gesture.

`orientation="vertical"` puts the tabs in a column beside the panel. A column and a panel do not
both fit across a phone, so below 480px the column lies back down into an ordinary strip. That
width is measured on the tabs themselves, not on the window: the same squeeze happens inside a
narrow drawer on a wide monitor.

## Variants

`variant="line"` is the default — a rule under the strip with the open tab standing on it.
`"pill"` is a segmented control, good for switching a range or a mode. `"card"` draws folder
tabs. `align="stretch"` splits the width between the tabs, which suits two or three of them.

```vue
<template>
  <wx-tabs variant="pill" size="sm" align="stretch">
    <wx-tab value="day" label="Day">…</wx-tab>
    <wx-tab value="week" label="Week">…</wx-tab>
  </wx-tabs>
</template>
```

## What happens to a hidden panel

By default a hidden panel is not in the DOM: its content is built when the tab is opened. That is
what you want for a panel that loads something or renders a long list. `keep-alive` on the tabs
keeps every panel mounted, and on a single `WxTab` keeps just that one — reach for it when a panel
holds a half-filled form or a scroll position worth preserving.

```vue
<template>
  <wx-tabs keep-alive>
    <wx-tab value="form" label="Draft">
      <!-- what was typed here survives a switch -->
    </wx-tab>
  </wx-tabs>
</template>
```

## Editing the tabs

Tabs that the user maintains — sections of a page, saved filters — are edited through the `extra`
slot: a pencil that works on the tab that is open and a plus that adds one, each opening a
[`WxPopover`](/components/popover) with the form.

<TabsEditableDemo />

The controls sit beside the strip rather than on the tabs themselves, and that is not only about
width. A tab **is** a button; a second button inside it is invalid HTML and unreachable for a
screen reader, and a pencil on every tab adds a tab stop per tab to the keyboard walk. Beside the
strip there is one pencil, it always means "this tab", and the strip stays the width of its labels.

An editor in a panel also beats an input in the tab: the strip measures itself to decide whether to
scroll and where the fades go, and a field growing as it is typed into would move that ground under
every keystroke.

```vue
<template>
  <wx-tabs v-model="active">
    <template #extra>
      <wx-popover v-model:open="editing" title="Tab" :width="300" align="end">
        <template #trigger>
          <wx-action type="edit" size="sm" title="Edit this tab" />
        </template>

        <wx-form gap="sm">
          <wx-form-item label="Name">
            <wx-input v-model="draft.title" size="sm" />
          </wx-form-item>
        </wx-form>

        <template #footer="{ close }">
          <wx-button size="sm" type="danger" variant="text" @click="remove">Delete</wx-button>
          <wx-button size="sm" @click="close">Cancel</wx-button>
          <wx-button size="sm" type="primary" @click="save">Save</wx-button>
        </template>
      </wx-popover>
    </template>

    <wx-tab v-for="s in sections" :key="s.id" :value="s.id" :label="s.title">{{ s.body }}</wx-tab>
  </wx-tabs>
</template>
```

The form belongs to the application — it is the application that knows what a tab is made of and
where it is saved. The tabs only hold the place for it, and take care of two things around it:
deleting the open tab moves the selection to the first one left, and opening a tab that was just
added scrolls the strip to it.

`WxAccordionItem` has the same slot, per item, and there it is already outside the header button —
so a pencil per section needs nothing said about it.

## Keyboard

Arrow keys move between tabs and wrap around at the ends; <kbd>Home</kbd> and <kbd>End</kbd> jump
to the first and last. By default the panel follows the focus. `activation-mode="manual"` moves
the focus only, and <kbd>Enter</kbd> or <kbd>Space</kbd> opens the tab — the right choice when
opening a panel is expensive.

## Tabs props

| Prop             | Type                                        | Default        | Description                                      |
| ---------------- | ------------------------------------------- | -------------- | ------------------------------------------------ |
| `modelValue`     | `string \| number`                          | first tab      | The open tab; use with `v-model`                 |
| `variant`        | `'line' \| 'pill' \| 'card'`                | `'line'`       | Underlined strip, segmented control, folder tabs |
| `size`           | `'sm' \| 'md'`                              | `'md'`         | Height and text size of the strip                |
| `orientation`    | `'horizontal' \| 'vertical'`                | `'horizontal'` | A strip above the panel, or a column beside it   |
| `align`          | `'start' \| 'center' \| 'end' \| 'stretch'` | `'start'`      | Where the tabs sit while they fit                |
| `activationMode` | `'automatic' \| 'manual'`                   | `'automatic'`  | Whether arrow keys open a panel as they move     |
| `keepAlive`      | `boolean`                                   | `false`        | Keep hidden panels in the DOM                    |
| `loop`           | `boolean`                                   | `true`         | Arrow keys wrap around at the ends               |
| `ariaLabel`      | `string`                                    | —              | Accessible name for the strip                    |

**Events:** `change` — the value of the tab that was opened.
**Slots:** `default` — the `WxTab`s; `extra` — content at the end of the strip.
**Exposed:** `measure()` and `revealActive()`, for when the strip is resized by something the
component cannot see.

## Tab props

| Prop        | Type               | Default | Description                                |
| ----------- | ------------------ | ------- | ------------------------------------------ |
| `value`     | `string \| number` | —       | Required, unique within the tabs           |
| `label`     | `string`           | —       | Text of the tab                            |
| `icon`      | `string`           | —       | Icon before the label                      |
| `badge`     | `string \| number` | —       | A count or marker after the label          |
| `disabled`  | `boolean`          | `false` | The tab cannot be opened                   |
| `keepAlive` | `boolean`          | `false` | Keep this panel mounted while it is hidden |

**Slots:** `default` — the panel; `label` — replaces `label` in the strip.

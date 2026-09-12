<script setup>
import TooltipDemo from '../components/demos/TooltipDemo.vue'
</script>

# Tooltip

`WxTooltip` says what a control is. That is all it does.

<TooltipDemo />

## Usage

```vue
<template>
  <wx-tooltip content="Delete">
    <wx-action type="remove" @click="remove" />
  </wx-tooltip>
</template>
```

The slot takes exactly one element, and the tip attaches to it directly — no wrapper goes into the
layout, and the tip belongs to the thing that is actually focused.

## A tip, not a panel

It cannot be clicked into, it never holds a button, and it is gone the moment the pointer leaves.
Anything a reader has to reach for is a [Popover](/components/popover); anything they have to
answer is a [Popconfirm](/components/popconfirm).

Which also means: **never put anything in a tooltip that is only in a tooltip**. A touch screen has
no hover, and a keyboard reaches it only by focusing the control. If it matters, it belongs on the
page.

## What it is good for

Icon-only buttons, mainly. A row of glyphs at the end of a table row is unreadable without them —
and a `title` attribute is not a substitute: it appears after a second of stillness, in the
browser's own styling, and never at all on a touch screen.

## On a touch screen it never opens

That is deliberate, not a gap. A tooltip answers hovering, and a finger does not hover: the only
gesture it has is the tap, which already belongs to the control the tip is attached to. Opening one
on tap would mean every icon button did nothing the first time it was pressed.

So a phone gets no tips at all, which is why nothing may live only in one. What replaces it depends
on what the tip was doing:

| The tip said                   | On a phone                                                     |
| ------------------------------ | -------------------------------------------------------------- |
| What an icon-only button is    | Show the label. There is room in a sheet or a menu row         |
| Something worth reading        | A [Popover](/components/popover), which a tap opens and closes |
| Something the control must say | `aria-label`, which is what a screen reader was reading anyway |

## Delays, and the group

The first tip in a group waits 150 ms before appearing; the ones after it come at once. By the time
a reader has hovered one icon and moved to the next, they have said what they are doing, and making
them wait again for each one is how a toolbar comes to feel slow.

The grouping is per component, which is the right scope: a toolbar's tips are a group, a page's
tips are not.

## Props

| Prop       | Type                                     | Default    | Description                                    |
| ---------- | ---------------------------------------- | ---------- | ---------------------------------------------- |
| `content`  | `string`                                 | —          | The text                                       |
| `side`     | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'`    | Preferred side; it flips when there is no room |
| `align`    | `'start' \| 'center' \| 'end'`           | `'center'` | How it lines up along that side                |
| `offset`   | `number`                                 | `6`        | Distance from the trigger                      |
| `delay`    | `number`                                 | `150`      | How long the pointer rests before it opens     |
| `arrow`    | `boolean`                                | `true`     | The little pointer                             |
| `maxWidth` | `number \| string`                       | `260`      | Before it wraps                                |
| `disabled` | `boolean`                                | `false`    | Nothing opens                                  |
| `teleport` | `boolean`                                | `true`     | Escapes `overflow: hidden`                     |

**Models:** `v-model:open` — for showing one from elsewhere, such as a hint after a failed save.

**Slots:** `default` — the control, exactly one element; `content` — the tip.

## Accessibility

The tip is bound to its trigger, so it is announced when the control is focused as well as hovered,
and Escape closes it. It takes no pointer events of its own: the tip belongs to whatever is under
the pointer, never to the pointer.

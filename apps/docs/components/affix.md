<script setup>
import AffixDemo from '../components/demos/AffixDemo.vue'
</script>

# Affix

`WxAffix` keeps something in view as its container scrolls past — a toolbar over a long table, a
summary beside a long form. `WxBacktop` is the button that takes you back up.

<AffixDemo />

## Usage

```vue
<template>
  <wx-affix :offset="0">
    <div class="toolbar">…</div>
  </wx-affix>
</template>
```

## Sticky does the sticking

The only JavaScript here reports what happened; `position: sticky` does the work.

That is the whole design. Every version of this component that measures scroll offsets and switches
to `position: fixed` inherits the same two bugs: the page jumps by the height of the element the
moment it leaves the flow, and it sticks to the **window** rather than to whatever is actually
scrolling — which in an admin is the main column, not the page. Sticky has neither, and it needs no
scroll listener at all.

## Knowing when it stuck

A sticky element cannot be styled differently while it is stuck — CSS has no selector for it. So a
one-pixel sentinel sits where the element comes to rest, and an `IntersectionObserver` watches it:
the moment it leaves the viewport, the element has stuck.

```vue
<wx-affix @change="stuck = $event">
  <template #default="{ stuck }">
    <div class="toolbar" :class="{ 'is-stuck': stuck }">…</div>
  </template>
</wx-affix>
```

A shadow that appears only once it is stuck is what tells a reader the toolbar is floating over the
rows rather than sitting on top of them.

## Backtop

```vue
<wx-backtop target="#main" :visibility-height="200" />
```

**Name the target in an admin.** The page there rarely scrolls — the main column does — so a button
watching the window would appear when nothing has happened and do nothing when pressed.

## Affix

| Prop       | Type                | Default | Description                    |
| ---------- | ------------------- | ------- | ------------------------------ |
| `offset`   | `number`            | `0`     | How far from the edge it rests |
| `position` | `'top' \| 'bottom'` | `'top'` | Which edge it sticks to        |
| `disabled` | `boolean`           | `false` | Puts it back in the flow       |
| `zIndex`   | `number`            | —       | Layer it sits on once stuck    |

**Events:** `change` (`stuck: boolean`) — reported once per change, not per scroll.

**Slots:** `default` — with `{ stuck }`.

## Backtop

| Prop               | Type                            | Default         | Description                     |
| ------------------ | ------------------------------- | --------------- | ------------------------------- |
| `target`           | `string \| HTMLElement \| null` | the window      | What scrolls                    |
| `visibilityHeight` | `number`                        | `200`           | How far down before it appears  |
| `right`            | `number`                        | `24`            | Distance from the trailing edge |
| `bottom`           | `number`                        | `24`            | Distance from the bottom        |
| `smooth`           | `boolean`                       | `true`          | Glides rather than jumps        |
| `ariaLabel`        | `string`                        | `'Back to top'` | Name of the button              |

**Events:** `click` (`MouseEvent`).

**Slots:** `default` — replaces the arrow.

## Accessibility

An affixed element keeps its place in the document, so reading order and focus order are untouched
— which is the other thing a `fixed` switch gets wrong. Backtop is a real button with a name, and
reduced motion turns the glide into a jump.

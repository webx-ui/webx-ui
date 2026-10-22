<script setup>
import PopconfirmDemo from '../components/demos/PopconfirmDemo.vue'
</script>

# Popconfirm

`WxPopconfirm` asks _are you sure_ where the answer will land, instead of in the middle of the
screen.

<PopconfirmDemo />

## Usage

```vue
<template>
  <wx-popconfirm
    title="Delete this order?"
    description="It cannot be brought back."
    confirm-text="Delete"
    confirm-type="danger"
    @confirm="remove(order)"
  >
    <template #trigger>
      <wx-action type="remove" />
    </template>
  </wx-popconfirm>
</template>
```

## Why not a dialog

Deleting one row out of forty is a small decision made in one place. A modal blanks the page to ask
about it, and takes away the row the reader was looking at — which is exactly the thing they were
checking before answering.

Use a [Dialog](/components/dialog) when the decision is worth stopping for: deleting an account,
publishing to production, anything that needs more than a sentence to explain.

And use neither when the action can simply be undone. A [toast with an Undo](/components/toast) is
better than a confirmation for anything reversible — it costs the reader nothing when they meant
it, which is most of the time.

## On a row action

An icon button at the end of a row does not need the wrapper: [`WxAction`](/components/actions#asking-first)
takes the question as its `confirm` prop and reports `click` only on a yes.

```vue
<wx-action type="remove" confirm="Delete?" @click="remove(row)" />
```

## Keyboard and screen readers

The focus lands on the button that keeps things as they were — the question is one click away from
what it would destroy, and a second press of the key that opened it must not be the answer.
**Enter** goes ahead from anywhere in the panel, the way a dialog's default button does; **Escape**
and a click outside are a cancel. The panel is a `dialog` named after the control that opened it,
with the question and its explanation read out as the description.

## Ask about the consequence

`Delete this order?` with `It cannot be brought back.` — not `Are you sure?`. The question should
say what will happen, and the confirming button should say what it does: `Delete`, not `OK`. A
reader skimming a red button labelled `Delete` cannot mistake it for anything else.

## An answer that takes time

`loading` keeps the panel open and the confirming button busy while the request is in flight. The
caller closes it when the work is done:

```vue
<wx-popconfirm v-model:open="open" :loading="saving" @confirm="archive">…</wx-popconfirm>
```

Without `loading` the panel closes as soon as the question is answered.

## Props

| Prop          | Type                | Default     | Description                              |
| ------------- | ------------------- | ----------- | ---------------------------------------- |
| `title`       | `string`            | —           | The question                             |
| `description` | `string`            | —           | What else the reader should know         |
| `confirmText` | `string`            | `'Yes'`     | Label of the button that goes ahead      |
| `cancelText`  | `string`            | `'Cancel'`  | Label of the one that does not           |
| `confirmType` | `ButtonType`        | `'primary'` | `danger` for anything that destroys      |
| `icon`        | `IconName \| false` | `'warning'` | The glyph beside the question            |
| `side`        | `PopoverSide`       | `'top'`     | Preferred side                           |
| `align`       | `PopoverAlign`      | `'center'`  | How it lines up                          |
| `offset`      | `number`            | `8`         | Distance from the trigger                |
| `arrow`       | `boolean`           | `true`      | The little pointer                       |
| `width`       | `number \| string`  | `260`       | Width of the panel                       |
| `disabled`    | `boolean`           | `false`     | The trigger asks nothing                 |
| `loading`     | `boolean`           | `false`     | Keeps it open while the answer is worked |

**Models:** `v-model:open`.

**Events:** `confirm`, `cancel`. Dismissing by Escape or a click outside counts as a cancel — and
is reported once, not twice.

**Slots:** `trigger` — exactly one element; `default` — replaces the question; `actions` — replaces
the buttons, with `{ confirm, cancel }`.

<script setup>
import AccordionDemo from '../components/demos/AccordionDemo.vue'
import AccordionEditableDemo from '../components/demos/AccordionEditableDemo.vue'
</script>

# Accordion

`WxAccordion` and `WxAccordionItem` fold a long page into headed sections that open one at a time:
the parts of an order, a settings form nobody fills in top to bottom, a list of questions.

<AccordionDemo />

## Usage

```vue
<script setup>
import { ref } from 'vue'

const open = ref('shipping')
</script>

<template>
  <wx-accordion v-model="open">
    <wx-accordion-item value="shipping" title="Shipping" subtitle="Nova Poshta, courier">
      Parcel 59000123456789, handed over on 13 March.
    </wx-accordion-item>
    <wx-accordion-item value="payment" title="Payment">Paid in full on 12 March.</wx-accordion-item>
  </wx-accordion>
</template>
```

`v-model` holds the value of the open item, or `undefined` when everything is closed. Without it
the accordion starts closed and keeps the state itself.

`multiple` lets several stand open at once, and the model becomes an array:

```vue
<template>
  <wx-accordion v-model="opened" multiple>
    <wx-accordion-item value="what" title="What is included?">…</wx-accordion-item>
    <wx-accordion-item value="long" title="How long does it take?">…</wx-accordion-item>
  </wx-accordion>
</template>
```

With one item at a time, clicking the open header closes it. `:collapsible="false"` keeps one
section open at all times — for a form where something must always be on screen.

## Headings

Each header is a real heading, `<h3>` by default, because headings are how a screen reader skims a
page. Set `heading-tag` to the level that follows the heading above the accordion:

```vue
<template>
  <h2>Order 1042</h2>
  <wx-accordion heading-tag="h3">…</wx-accordion>
</template>
```

## Controls in the header

The `extra` slot sits at the end of the header but outside the button, so a switch, a badge or a
menu there can be used without the section opening under the pointer.

```vue
<template>
  <wx-accordion-item value="comments" title="Comments">
    <template #extra>
      <wx-switch v-model="allowed" size="sm" aria-label="Allow comments" />
    </template>
    …
  </wx-accordion-item>
</template>
```

## Editing the sections

Sections the user maintains — a list of questions, the blocks of a page — are edited from the
`extra` slot: a pencil per section, each opening a [`WxPopover`](/components/popover) with the
form. Adding is a button under the list.

<AccordionEditableDemo />

Unlike a tab strip, an accordion has room for a control per section, and `extra` already sits
outside the header button: the pencil is reachable on its own, pressing it does not open the
section under the pointer, and the heading stays exactly its own text for anyone skimming the page
by headings.

```vue
<template>
  <wx-accordion v-model="open" variant="separated">
    <wx-accordion-item
      v-for="q in questions"
      :key="q.id"
      :value="q.id"
      :title="q.title"
      :subtitle="q.subtitle"
    >
      {{ q.answer }}

      <template #extra>
        <wx-popover
          :open="editingId === q.id"
          title="Question"
          :width="320"
          align="end"
          @update:open="edit(q.id, $event)"
        >
          <template #trigger>
            <wx-action type="edit" size="sm" title="Edit this question" />
          </template>

          <wx-form gap="sm">
            <wx-form-item label="Question">
              <wx-input v-model="draft.title" size="sm" />
            </wx-form-item>
          </wx-form>

          <template #footer="{ close }">
            <wx-button size="sm" type="danger" variant="text" @click="remove(q.id)">
              Delete
            </wx-button>
            <wx-button size="sm" @click="close">Cancel</wx-button>
            <wx-button size="sm" type="primary" @click="save(q.id)">Save</wx-button>
          </template>
        </wx-popover>
      </template>
    </wx-accordion-item>
  </wx-accordion>
</template>
```

One editor at a time: `v-model:open` per item would need a flag per item, so hold the id of the
section being edited instead and bind `:open` and `@update:open` to it. The panel is drawn in a
portal, so the `separated` variant clipping its own corners does not clip the form.

The form belongs to the application — it knows what a section is made of and where it is saved.
Deleting the open section leaves the accordion closed, and that is the right end state here: a tab
strip must always have a tab open, an accordion is perfectly fine with nothing open at all.

## Variants

`variant="bordered"` is the default: one box with a rule between the items. `"separated"` gives
each item its own card with air around it — the shape for an FAQ. `"plain"` drops the box and
leaves the rules, for an accordion that sits inside a card of its own.

On a touch screen every header is at least 48px tall, and the open and close animation is dropped
for anyone who asked their system for less motion.

## Accordion props

| Prop           | Type                                   | Default      | Description                             |
| -------------- | -------------------------------------- | ------------ | --------------------------------------- |
| `modelValue`   | `string \| string[]`                   | —            | What is open; use with `v-model`        |
| `multiple`     | `boolean`                              | `false`      | Several open at once; model is an array |
| `collapsible`  | `boolean`                              | `true`       | Clicking the open header closes it      |
| `variant`      | `'bordered' \| 'separated' \| 'plain'` | `'bordered'` | One box, a card each, or bare rules     |
| `size`         | `'sm' \| 'md'`                         | `'md'`       | Padding and title size                  |
| `iconPosition` | `'start' \| 'end'`                     | `'end'`      | Which side the chevron sits on          |
| `headingTag`   | `string`                               | `'h3'`       | Element each header is written as       |
| `disabled`     | `boolean`                              | `false`      | Nothing opens or closes                 |

**Events:** `change` — what is open after the click.
**Slots:** `default` — the `WxAccordionItem`s.

## AccordionItem props

| Prop       | Type      | Default | Description                           |
| ---------- | --------- | ------- | ------------------------------------- |
| `value`    | `string`  | —       | Required, unique within the accordion |
| `title`    | `string`  | —       | Text of the header                    |
| `subtitle` | `string`  | —       | A second line under the title         |
| `icon`     | `string`  | —       | Icon before the title                 |
| `disabled` | `boolean` | `false` | The item cannot be opened             |

**Slots:** `default` — the panel; `title` — replaces `title`; `extra` — the end of the header,
outside the button.

<script setup>
import KanbanDemo from '../components/demos/KanbanDemo.vue'
</script>

# Kanban

`WxKanban` is a board of columns you drag cards between: tasks by status, orders by stage, pages
on their way to being published.

<KanbanDemo />

## Usage

The board takes columns that carry their own cards. What a card looks like is yours — the `card`
slot gets the card, its column and its index.

```vue
<script setup>
const columns = ref([
  { id: 'todo', title: 'To do', items: [{ id: 1, title: 'Rewrite the pricing page' }] },
  { id: 'doing', title: 'In progress', tone: 'primary', limit: 3, items: [] },
  { id: 'done', title: 'Done', tone: 'success', items: [] },
])
</script>

<template>
  <wx-kanban :columns="columns" aria-label="Website tasks" @move="save">
    <template #card="{ card }">
      <wx-entity-card :title="card.title" variant="card" bordered size="sm" />
    </template>
  </wx-kanban>
</template>
```

A card needs an `id` and nothing else; everything else on it is yours to read in the slot.

## Saving a move

The board moves the card between the arrays it was given — that is what makes it land where it was
dropped — and then says what happened:

```ts
function save(move) {
  // { card, from: { column, index }, to: { column, index }, via: 'pointer' | 'keyboard' }
  axios.patch(`/tasks/${move.card.id}`, { status: move.to.column, position: move.to.index })
}
```

`to.index` is the position within its column, counted from zero, which is what a Laravel
`->update(['position' => ...])` wants. If the request fails, move the card back yourself — the
board holds no state of its own to roll back.

## Work-in-progress limits

`limit` on a column is the point of a board rather than a decoration: over it the count turns red
**and** the column stops accepting cards, whether they are dragged or moved with the keyboard.
Reordering inside a full column still works — it does not make the column any fuller.

```vue
<template>
  <wx-kanban :columns="[{ id: 'doing', title: 'In progress', limit: 3, items }]" />
</template>
```

`disabled` on a column freezes it both ways; on the board it freezes everything.

## The keyboard

SortableJS is a pointer library and has nothing to say to a keyboard, so the board carries its own.
Tab to a card, then:

| Key                                 | What happens                                             |
| ----------------------------------- | -------------------------------------------------------- |
| <kbd>Space</kbd> / <kbd>Enter</kbd> | Pick the card up, or drop it                             |
| <kbd>↑</kbd> <kbd>↓</kbd>           | Move it up or down its column                            |
| <kbd>←</kbd> <kbd>→</kbd>           | Move it to the previous or next column that will take it |
| <kbd>Esc</kbd>                      | Put it back where it came from                           |

Every move is read out through a live region, and the focus follows the card, so a board is usable
without ever touching a mouse. Keys pressed on a button or a link inside a card are left alone.

## On a phone

The columns scroll sideways and the swipe snaps to one at a time below 560px — measured on the
board, not on the window, so a board in a narrow panel behaves the same way. A drag starts after a
short press rather than immediately, which is what tells it apart from a scroll.

The board is as tall as it is given. Put a height on it and each column scrolls its own cards:

```vue
<template>
  <wx-kanban class="board" :columns="columns" />
</template>

<style>
.board {
  height: calc(100vh - 200px);
}
</style>
```

## Props

| Prop          | Type               | Default              | Description                            |
| ------------- | ------------------ | -------------------- | -------------------------------------- |
| `columns`     | `KanbanColumn[]`   | —                    | Required; each carries its own `items` |
| `group`       | `string`           | —                    | Boards sharing a name exchange cards   |
| `size`        | `'sm' \| 'md'`     | `'md'`               | Padding and gaps                       |
| `columnWidth` | `number \| string` | `288`                | A number means pixels                  |
| `disabled`    | `boolean`          | `false`              | Nothing moves                          |
| `handle`      | `string`           | —                    | CSS selector of the part that drags    |
| `addable`     | `boolean`          | `false`              | Adds a button under each column        |
| `addLabel`    | `string`           | `'Add a card'`       | Its label                              |
| `emptyText`   | `string`           | `'Nothing here yet'` | Shown in a column with no cards        |
| `ariaLabel`   | `string`           | —                    | Accessible name for the board          |

**Column:** `{ id, title?, items, limit?, tone?, disabled? }`, where `tone` is
`default | primary | success | warning | danger | info` and colours the rule above the column.

**Events:** `move` — a card changed position or column; `add` — the button under a column was
pressed.

**Slots:** `card` (`{ card, column, index }`); `column-header` (`{ column, count, overLimit }`);
`column-footer` (`{ column }`); `empty` (`{ column }`); `default` — after the last column, for an
"add a column" button.

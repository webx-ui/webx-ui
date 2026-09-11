<script setup lang="ts">
import { ref } from 'vue'
import {
  WxAction,
  WxBadge,
  WxDropdown,
  WxDropdownItem,
  WxEntityCard,
  WxKanban,
  type KanbanColumn,
  type KanbanColumnMove,
  type KanbanMove,
} from '@webx-ui/core'

interface Task {
  id: string
  title: string
  assignee: string
  tag: string
  tone: 'primary' | 'warning' | 'danger' | 'info'
  [key: string]: unknown
}

const columns = ref<KanbanColumn<Task>[]>([
  {
    id: 'backlog',
    title: 'Backlog',
    items: [
      {
        id: 't1',
        title: 'Rewrite the pricing page',
        assignee: 'Maria',
        tag: 'Content',
        tone: 'info',
      },
      {
        id: 't2',
        title: 'Collect the photos for the gallery',
        assignee: 'Oleh',
        tag: 'Media',
        tone: 'primary',
      },
      {
        id: 't3',
        title: 'Decide on the new domain',
        assignee: 'Kate',
        tag: 'Admin',
        tone: 'warning',
      },
    ],
  },
  {
    id: 'doing',
    title: 'In progress',
    tone: 'primary',
    limit: 2,
    items: [
      { id: 't4', title: 'Migrate the blog posts', assignee: 'Oleh', tag: 'Content', tone: 'info' },
    ],
  },
  {
    id: 'review',
    title: 'On review',
    tone: 'warning',
    items: [
      { id: 't5', title: 'Checkout on a phone', assignee: 'Maria', tag: 'QA', tone: 'danger' },
    ],
  },
  { id: 'done', title: 'Done', tone: 'success', items: [] },
])

const collapsed = ref<string[]>(['done'])
const log = ref('Drag a card or a column heading, or focus one and press space.')

function onMove(move: KanbanMove<Task>) {
  const to = columns.value.find((column) => column.id === move.to.column)
  log.value = `${move.card.title} → ${to?.title}, position ${move.to.index + 1} (${move.via})`
}

function onColumnMove(move: KanbanColumnMove<Task>) {
  log.value = `Column ${move.column.title} → position ${move.to + 1} (${move.via})`
}

function add(column: KanbanColumn<Task>) {
  const id = `t${Math.random().toString(36).slice(2, 7)}`
  column.items.push({ id, title: 'New task', assignee: 'Unassigned', tag: 'Admin', tone: 'info' })
  log.value = `Added a card to ${column.title}`
}

function addColumn() {
  const id = `c${columns.value.length + 1}`
  columns.value.push({ id, title: 'New status', items: [] })
  log.value = 'Added a column'
}

function clear(column: KanbanColumn<Task>) {
  column.items = []
  log.value = `Emptied ${column.title}`
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <span class="wx-demo__label">{{ log }}</span>

    <wx-kanban
      v-model:collapsed="collapsed"
      :columns="columns"
      class="demo-board"
      collapsible
      reorder-columns
      column-addable
      addable
      add-label="Add a task"
      add-column-label="Add a status"
      aria-label="Website tasks"
      @move="onMove"
      @column-move="onColumnMove"
      @add="add"
      @add-column="addColumn"
    >
      <template #column-actions="{ column }">
        <wx-action type="add" size="sm" title="Add a task" @click="add(column)" />
        <wx-dropdown align="end">
          <template #trigger>
            <wx-action type="more" size="sm" title="Column menu" />
          </template>
          <wx-dropdown-item icon="trash" tone="danger" @click="clear(column)">
            Empty the column
          </wx-dropdown-item>
        </wx-dropdown>
      </template>

      <template #card="{ card }">
        <wx-entity-card :title="card.title" variant="card" bordered size="sm">
          <template #meta>
            <wx-badge :type="card.tone" size="sm">{{ card.tag }}</wx-badge>
            <span class="demo-board__who">{{ card.assignee }}</span>
          </template>
        </wx-entity-card>
      </template>
    </wx-kanban>
  </div>
</template>

<style scoped>
/* The board is as tall as it is given — here, a screenful of a phone. */
.demo-board {
  height: 460px;
}

.demo-board__who {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>

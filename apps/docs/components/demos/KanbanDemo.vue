<script setup lang="ts">
import { ref } from 'vue'
import { WxBadge, WxEntityCard, WxKanban, type KanbanColumn, type KanbanMove } from '@webx-ui/core'

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

const log = ref('Drag a card, or focus one and press space.')

function onMove(move: KanbanMove<Task>) {
  const to = columns.value.find((column) => column.id === move.to.column)
  log.value = `${move.card.title} → ${to?.title}, position ${move.to.index + 1} (${move.via})`
}

function onAdd(column: KanbanColumn<Task>) {
  const id = `t${Math.random().toString(36).slice(2, 7)}`
  column.items.push({ id, title: 'New task', assignee: 'Unassigned', tag: 'Admin', tone: 'info' })
  log.value = `Added a card to ${column.title}`
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <span class="wx-demo__label">{{ log }}</span>

    <wx-kanban
      :columns="columns"
      addable
      add-label="Add a task"
      aria-label="Website tasks"
      class="demo-board"
      @move="onMove"
      @add="onAdd"
    >
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
  height: 420px;
}

.demo-board__who {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>

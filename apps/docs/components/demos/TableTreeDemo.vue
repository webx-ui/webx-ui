<script setup lang="ts">
import { ref } from 'vue'
import {
  WxAction,
  WxActions,
  WxBadge,
  WxTable,
  type TableColumn,
  type TableNodeDropEvent,
} from '@webx-ui/core'

interface Page extends Record<string, unknown> {
  id: number
  title: string
  slug: string
  status: 'live' | 'draft' | 'hidden'
  updated: string
  has_children?: boolean
  children?: Page[]
}

const columns: TableColumn<Page>[] = [
  { key: 'title', label: 'Title', minWidth: 260 },
  { key: 'slug', label: 'URL', minWidth: 160 },
  { key: 'status', label: 'Status', width: 120 },
  { key: 'updated', label: 'Updated', width: 120, align: 'right' },
  { key: 'actions', label: '', width: 120, align: 'right' },
]

/* The roots are what the screen opens with — one request, three rows. */
const pages = ref<Page[]>([
  {
    id: 1,
    title: 'Home',
    slug: '/',
    status: 'live',
    updated: '11.09.26',
    has_children: false,
  },
  {
    id: 2,
    title: 'About the company',
    slug: '/about',
    status: 'live',
    updated: '02.09.26',
    has_children: true,
  },
  {
    id: 3,
    title: 'Services',
    slug: '/services',
    status: 'live',
    updated: '28.08.26',
    has_children: true,
  },
  {
    id: 4,
    title: 'Contacts',
    slug: '/contacts',
    status: 'draft',
    updated: '19.08.26',
    has_children: false,
  },
])

/* What the backend would answer with, a level at a time. */
const branches: Record<number, Page[]> = {
  2: [
    {
      id: 21,
      title: 'History',
      slug: '/about/history',
      status: 'live',
      updated: '02.09.26',
      has_children: false,
    },
    {
      id: 22,
      title: 'Team',
      slug: '/about/team',
      status: 'live',
      updated: '30.08.26',
      has_children: false,
    },
    {
      id: 23,
      title: 'Vacancies',
      slug: '/about/vacancies',
      status: 'draft',
      updated: '27.08.26',
      has_children: true,
    },
  ],
  3: [
    {
      id: 31,
      title: 'Delivery',
      slug: '/services/delivery',
      status: 'live',
      updated: '28.08.26',
      has_children: false,
    },
    {
      id: 32,
      title: 'Payment',
      slug: '/services/payment',
      status: 'live',
      updated: '26.08.26',
      has_children: false,
    },
    {
      id: 33,
      title: 'Warranty',
      slug: '/services/warranty',
      status: 'hidden',
      updated: '14.08.26',
    },
  ],
  23: [
    {
      id: 231,
      title: 'Sales manager',
      slug: '/about/vacancies/sales',
      status: 'draft',
      updated: '27.08.26',
    },
  ],
}

function load(row: Page) {
  return new Promise<Page[]>((resolve) => {
    setTimeout(() => resolve(branches[row.id] ?? []), 500)
  })
}

const expanded = ref<Array<string | number>>([])
const last = ref('')

function onDrop(event: TableNodeDropEvent<Page>) {
  const where = event.parent ? `inside ${event.parent.title}` : 'at the top level'
  last.value = `${event.row.title} → ${where}, position ${event.index + 1}`
}

const tone = { live: 'success', draft: 'warning', hidden: 'default' } as const
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">
        A branch is fetched when it opens, and dragging one onto another opens that one too
      </span>

      <wx-table
        v-model:expanded="expanded"
        :columns="columns"
        :data="pages"
        :tree="{ lazy: true, load, draggable: true }"
        row-key="id"
        @node-drop="onDrop"
      >
        <template #cell-status="{ row }">
          <wx-badge :type="tone[(row as Page).status]" size="sm">{{ row.status }}</wx-badge>
        </template>

        <template #cell-actions>
          <wx-actions size="sm">
            <wx-action type="add" />
            <wx-action type="edit" />
            <wx-action type="remove" />
          </wx-actions>
        </template>
      </wx-table>

      <span class="wx-demo__note">
        {{ last || 'Drag a row before, after or onto another one — hold it over a closed branch.' }}
      </span>
    </div>
  </div>
</template>

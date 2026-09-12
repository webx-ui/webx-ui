<script setup lang="ts">
import { ref } from 'vue'
import {
  WxAction,
  WxActions,
  WxInput,
  WxTree,
  type TreeDropEvent,
  type TreeNode,
} from '@webx-ui/core'

interface Category extends TreeNode {
  id: number
  label: string
  children?: Category[]
}

const categories = ref<Category[]>([
  {
    id: 1,
    label: 'Engine',
    children: [
      {
        id: 11,
        label: 'Engine parts',
        children: [
          { id: 111, label: 'Cylinder block' },
          { id: 112, label: 'Pistons, rings and liners' },
          { id: 113, label: 'Crankshaft and bearings' },
        ],
      },
      { id: 12, label: 'Lubrication' },
      { id: 13, label: 'Cooling' },
    ],
  },
  {
    id: 2,
    label: 'Brakes',
    children: [
      { id: 21, label: 'Pads' },
      { id: 22, label: 'Discs and drums' },
      { id: 23, label: 'Calipers' },
    ],
  },
  { id: 3, label: 'Suspension', children: [{ id: 31, label: 'Shock absorbers' }] },
  { id: 4, label: 'Tyres and wheels' },
])

const expanded = ref<Array<string | number>>([1, 11])
const selected = ref<string | number | null>(111)
const filter = ref('')
const last = ref('')

function onDrop(event: TreeDropEvent<Category>) {
  const where = event.parent ? `inside ${event.parent.label}` : 'at the top level'
  last.value = `${event.node.label} → ${where}, position ${event.index + 1} (${event.via})`
}

/* --- permissions: the same tree, ticked rather than dragged --- */

const sections = ref<TreeNode[]>([
  {
    id: 'content',
    label: 'Content',
    children: [
      { id: 'pages', label: 'Pages' },
      { id: 'posts', label: 'Blog posts' },
      { id: 'media', label: 'Media library' },
    ],
  },
  {
    id: 'shop',
    label: 'Shop',
    children: [
      { id: 'products', label: 'Products' },
      { id: 'orders', label: 'Orders' },
      { id: 'stock', label: 'Stock', disabled: true },
    ],
  },
])

const granted = ref<Array<string | number>>(['pages', 'media'])

/* --- lazy: a branch that arrives when it is opened --- */

const remote = ref<TreeNode[]>([
  { id: 'ua', label: 'Ukraine' },
  { id: 'pl', label: 'Poland' },
  { id: 'de', label: 'Germany', leaf: true },
])

const cities: Record<string, string[]> = {
  ua: ['Kyiv', 'Lviv', 'Odesa'],
  pl: ['Warsaw', 'Kraków'],
}

function load(node: TreeNode) {
  return new Promise<TreeNode[]>((resolve) => {
    setTimeout(() => {
      const list = cities[String(node.id)] ?? []
      resolve(list.map((city) => ({ id: `${node.id}-${city}`, label: city, leaf: true })))
    }, 600)
  })
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Drag a branch, or move it with Alt and the arrow keys</span>

      <wx-input v-model="filter" placeholder="Filter categories" clearable size="sm" />

      <div class="tree-frame">
        <wx-tree
          v-model="categories"
          v-model:expanded="expanded"
          v-model:selected="selected"
          :filter="filter"
          draggable
          aria-label="Categories"
          @drop="onDrop"
        >
          <template #actions="{ node }">
            <wx-actions size="sm">
              <wx-action type="add" />
              <wx-action type="edit" />
              <wx-action type="remove" :disabled="Boolean(node.children?.length)" />
            </wx-actions>
          </template>
        </wx-tree>
      </div>

      <span class="wx-demo__note">
        {{ last || 'Drop a branch before, after or inside another one.' }}
      </span>
    </div>

    <div>
      <span class="wx-demo__label">Checkboxes that follow the branch they are on</span>

      <div class="tree-frame">
        <wx-tree
          v-model="sections"
          v-model:checked="granted"
          checkable
          default-expand-all
          aria-label="Permissions"
        />
      </div>

      <span class="wx-demo__note">Granted: {{ granted.join(', ') || 'nothing yet' }}</span>
    </div>

    <div>
      <span class="wx-demo__label">Branches fetched when they open</span>

      <div class="tree-frame">
        <wx-tree v-model="remote" lazy :load="load" aria-label="Regions" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.tree-frame {
  margin-top: var(--wx-space-8);
  padding: var(--wx-space-8);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
}
</style>

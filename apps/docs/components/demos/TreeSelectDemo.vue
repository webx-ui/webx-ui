<script setup lang="ts">
import { ref } from 'vue'
import { WxTreeSelect, type TreeKey, type TreeNode } from '@webx-ui/core'

const categories: TreeNode[] = [
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
  {
    id: 3,
    label: 'Suspension',
    children: [
      { id: 31, label: 'Shock absorbers' },
      { id: 32, label: 'Springs' },
    ],
  },
  { id: 4, label: 'Tyres and wheels' },
  { id: 5, label: 'Discontinued', disabled: true },
]

const parent = ref<TreeKey | null>(112)
const chosen = ref<TreeKey[]>([21, 22])
const strict = ref<TreeKey[]>([])

/* --- lazy: a branch that arrives when it is opened --- */

const regions: TreeNode[] = [
  { id: 'ua', label: 'Ukraine' },
  { id: 'pl', label: 'Poland' },
  { id: 'de', label: 'Germany', leaf: true },
]

const cities: Record<string, string[]> = {
  ua: ['Kyiv', 'Lviv', 'Odesa'],
  pl: ['Warsaw', 'Kraków'],
}

const region = ref<TreeKey | null>(null)

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
    <div class="fields">
      <div>
        <span class="wx-demo__label">One category, with the path it sits on</span>
        <wx-tree-select
          v-model="parent"
          :nodes="categories"
          show-path
          filterable
          clearable
          placeholder="Parent category"
        />
        <span class="wx-demo__note">Model: {{ parent ?? 'null' }}</span>
      </div>

      <div>
        <span class="wx-demo__label">Several, ticked</span>
        <wx-tree-select
          v-model="chosen"
          :nodes="categories"
          multiple
          filterable
          clearable
          placeholder="Where to publish"
        />
        <span class="wx-demo__note">Model: [{{ chosen.join(', ') || '—' }}]</span>
      </div>

      <div>
        <span class="wx-demo__label">Several, and a tick stays where it was made</span>
        <wx-tree-select
          v-model="strict"
          :nodes="categories"
          multiple
          check-strictly
          placeholder="Exactly these branches"
        />
        <span class="wx-demo__note">Model: [{{ strict.join(', ') || '—' }}]</span>
      </div>

      <div>
        <span class="wx-demo__label">Branches fetched when they open</span>
        <wx-tree-select
          v-model="region"
          :nodes="regions"
          lazy
          :load="load"
          placeholder="Region"
          size="sm"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.fields {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: var(--wx-space-24);
}
</style>

<script setup lang="ts">
import { ref } from 'vue'
import { WxCascader, type CascaderOption } from '@webx-ui/core'

const options: CascaderOption[] = [
  {
    label: 'Guide',
    value: 'guide',
    children: [
      { label: 'Disciplines', value: 'disciplines' },
      {
        label: 'Navigation',
        value: 'navigation',
        children: [
          { label: 'Side Navigation', value: 'side' },
          { label: 'Top Navigation', value: 'top' },
        ],
      },
    ],
  },
  {
    label: 'Component',
    value: 'component',
    children: [
      { label: 'Form', value: 'form', children: [{ label: 'Input', value: 'input' }] },
      { label: 'Data', value: 'data', children: [{ label: 'Table', value: 'table' }] },
    ],
  },
  { label: 'Resource', value: 'resource', disabled: true },
]

const onClick = ref<(string | number)[]>([])
const onHover = ref<(string | number)[]>(['guide', 'navigation', 'top'])
const anyLevel = ref<(string | number)[]>([])
const lazyValue = ref<(string | number)[]>([])

/* Stands in for the backend: each level arrives when it is opened. */
const regions: Record<string, { label: string; value: string; leaf?: boolean }[]> = {
  root: [
    { label: 'Kyiv region', value: 'kyiv' },
    { label: 'Lviv region', value: 'lviv' },
  ],
  kyiv: [
    { label: 'Bucha', value: 'bucha', leaf: true },
    { label: 'Irpin', value: 'irpin', leaf: true },
  ],
  lviv: [
    { label: 'Drohobych', value: 'drohobych', leaf: true },
    { label: 'Stryi', value: 'stryi', leaf: true },
  ],
}

function load(option: CascaderOption | null): Promise<CascaderOption[]> {
  const key = option ? String(option.value) : 'root'
  return new Promise((resolve) => setTimeout(() => resolve(regions[key] ?? []), 500))
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Child options open on click (default)</span>
      <wx-cascader v-model="onClick" :options="options" placeholder="Pick a section" clearable />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 14px">
        Model: <code>{{ JSON.stringify(onClick) }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Child options open on hover</span>
      <wx-cascader v-model="onHover" :options="options" expand-trigger="hover" />
    </div>

    <div>
      <span class="wx-demo__label">Any level may be picked</span>
      <wx-cascader
        v-model="anyLevel"
        :options="options"
        check-strictly
        placeholder="Pick a section or a group"
      />
    </div>

    <div>
      <span class="wx-demo__label">Levels loaded from the backend</span>
      <wx-cascader
        v-model="lazyValue"
        lazy
        :load="load"
        placeholder="Pick a town"
        empty-text="Nothing in this branch"
      />
    </div>
  </div>
</template>

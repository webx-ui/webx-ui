<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { WxTabs, type TabValue } from '@webx-ui/core'
import { screenErrorsKey } from './render'

/**
 * The tabs of a described screen: `WxTabs`, and the one thing a form of tabs needs that a strip
 * of tabs does not — a refused save opens the tab the refusal is on.
 *
 * Without it a 422 can land on a tab nobody is looking at: the message is drawn under a field
 * that is not on screen, and the form looks as if it refused for no reason. A page hosting a
 * screen cannot do this itself, because the tabs are the description's and the page never sees
 * which field sits on which of them; the registry does, and hands the answer in as `fields`.
 *
 * The tab being looked at wins when it has a failing field of its own: the editor is already
 * where the problem is, and moving them would be moving them away from it.
 */
defineOptions({ name: 'WxScreenTabs', inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    /** The names of the fields under each tab, in the order the tabs are read. */
    fields?: Record<string, string[]>
  }>(),
  { fields: () => ({}) },
)

const errors = inject(screenErrorsKey, null)

const active = ref<TabValue | undefined>(undefined)

/** The tabs holding a failing field, in the order they are read. The renderer has already put
    `slug.en` under `slug`, so a name is looked up as it is. */
const failing = computed<string[]>(() => {
  const names = new Set(Object.keys(errors?.value ?? {}))

  return Object.entries(props.fields)
    .filter(([, fields]) => fields.some((field) => names.has(field)))
    .map(([tab]) => tab)
})

watch(failing, (tabs) => {
  const first = tabs[0]

  if (first === undefined) return
  if (active.value !== undefined && tabs.includes(String(active.value))) return

  active.value = first
})
</script>

<template>
  <wx-tabs v-model="active" v-bind="$attrs">
    <slot />
  </wx-tabs>
</template>

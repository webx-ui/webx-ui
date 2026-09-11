<script setup lang="ts">
import { TabsContent } from 'reka-ui'
import type { TabProps } from './types'

defineOptions({ name: 'WxTab' })

/*
 * A tab is written once and read twice: `WxTabs` looks at these props to build the
 * button in the strip, and the component itself renders only the panel. That is why
 * `label`, `icon` and `badge` live here rather than on a separate list of items —
 * a tab and its content stay together in the markup.
 */
withDefaults(defineProps<TabProps>(), {
  label: undefined,
  icon: undefined,
  badge: undefined,
  disabled: false,
  keepAlive: false,
})

defineSlots<{
  /** The panel. */
  default?: () => unknown
  /** Replaces `label` in the strip — an avatar, a coloured dot, anything. */
  label?: () => unknown
}>()
</script>

<template>
  <tabs-content class="wx-tab" :value="value" :force-mount="keepAlive || undefined">
    <slot />
  </tabs-content>
</template>

<style scoped>
.wx-tab:focus-visible {
  outline: none;
}

/* Reka hides an inactive panel with the attribute; our own display would win. */
.wx-tab[hidden] {
  display: none;
}
</style>

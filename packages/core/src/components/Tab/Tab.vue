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
/*
 * A panel is a column of things — cards, fields, a table — and it owes them the air between
 * them: they carry no margins of their own, so without this they stand flush and two cards
 * read as one with a line through it (§6.2 of the visual spec). The step is the panel's;
 * outside one it is the desktop step.
 *
 * The column is also how a page tells a tab to be as tall as the screen: a page that fills
 * adds `flex: 1` on top of this, and that only works on a flex child of a flex parent.
 */
.wx-tab {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
}

.wx-tab:focus-visible {
  outline: none;
}

/*
 * Reka hides an inactive panel with the attribute, and the display above would take that
 * away — kept alive, the hidden panels are still in the document, and each would claim its
 * share of the height (CLAUDE.md §4). The attribute selector is the more specific of the two.
 */
.wx-tab[hidden] {
  display: none;
}
</style>

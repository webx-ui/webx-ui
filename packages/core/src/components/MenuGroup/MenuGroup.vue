<script setup lang="ts">
import { computed } from 'vue'
import { useMenu, useSubmenu } from '../../composables/useMenu'
import '../../styles/menu.css'
import type { MenuGroupProps } from './types'

defineOptions({ name: 'WxMenuGroup' })

const props = withDefaults(defineProps<MenuGroupProps>(), {
  title: undefined,
})

const menu = useMenu()
const submenu = useSubmenu()

/*
 * A group labels entries; it does not contain them the way a submenu does. It adds
 * no depth, so the entries under it line up with everything else at their level.
 */
const collapsed = computed(() => menu?.collapsed.value ?? false)
const hasTitle = computed(() => Boolean(props.title || !collapsed.value))
</script>

<template>
  <li class="wx-menu-group" :class="{ 'wx-menu-group--collapsed': collapsed }">
    <span
      v-if="hasTitle && !collapsed"
      class="wx-menu-group__title"
      :style="{ '--wx-menu-depth': submenu.depth }"
    >
      <slot name="title">{{ title }}</slot>
    </span>
    <!-- On the rail there is no room for a heading, so the group becomes a rule. -->
    <span v-else class="wx-menu-group__rule" role="separator" />

    <ul class="wx-menu-group__list">
      <slot />
    </ul>
  </li>
</template>

<style scoped>
.wx-menu-group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-menu-gap, 2px);
  min-width: 0;
  /*
   * Air above the heading, so a group reads as a break in the list — and nothing but
   * that air: a scoped rule, to outweigh a host stylesheet spacing `li + li`.
   */
  margin: var(--wx-space-8) 0 0;
}

.wx-menu-group:first-child {
  margin-top: 0;
}

.wx-menu-group__title {
  padding-inline: calc(
      var(--wx-menu-row-padding, 10px) + var(--wx-menu-depth, 0) * var(--wx-menu-indent, 16px)
    )
    var(--wx-menu-row-padding, 10px);
  padding-block: var(--wx-space-4);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-medium);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.wx-menu-group__rule {
  height: 1px;
  margin-block: var(--wx-space-4);
  margin-inline: var(--wx-space-6);
  background: var(--wx-border-muted);
}

.wx-menu-group__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-menu-gap, 2px);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-menu-group--collapsed .wx-menu-group__list {
  align-items: center;
}
</style>

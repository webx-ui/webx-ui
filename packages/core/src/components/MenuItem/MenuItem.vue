<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import { useMenu, useSubmenu } from '../../composables/useMenu'
import '../../styles/menu.css'
import type { MenuItemEmits, MenuItemProps } from './types'

defineOptions({ name: 'WxMenuItem', inheritAttrs: false })

const props = withDefaults(defineProps<MenuItemProps>(), {
  value: undefined,
  icon: undefined,
  label: undefined,
  href: undefined,
  target: undefined,
  as: undefined,
  disabled: false,
})

const emit = defineEmits<MenuItemEmits>()

const menu = useMenu()
const submenu = useSubmenu()

const collapsed = computed(() => menu?.collapsed.value ?? false)
const isActive = computed(() => props.value !== undefined && menu?.active.value === props.value)

const tag = computed(() => props.as ?? (props.href ? 'a' : 'button'))

const nativeAttrs = computed(() => {
  const attrs: Record<string, unknown> = {}

  if (props.href) {
    attrs.href = props.disabled ? undefined : props.href
    attrs.target = props.target
    if (props.target === '_blank') attrs.rel = 'noopener noreferrer'
  } else if (!props.as) {
    attrs.type = 'button'
    attrs.disabled = props.disabled
  }

  if (props.disabled) {
    attrs['aria-disabled'] = 'true'
    if (props.href || props.as) attrs.tabindex = -1
  }

  /*
   * `aria-current` on a link, `aria-pressed` on a button: the entry is either the
   * page you are on or a control that is switched on, and a screen reader is told
   * which of the two this menu is made of.
   */
  if (isActive.value) {
    if (props.href || props.as) attrs['aria-current'] = 'page'
    else attrs['aria-pressed'] = 'true'
  }

  return attrs
})

const classes = computed(() => [
  'wx-menu-row',
  { 'is-active': isActive.value, 'is-disabled': props.disabled },
])

/*
 * The entry reports where it sits so its branch can be highlighted and expanded
 * without the menu walking the component tree to find out.
 */
watch(
  () => props.value,
  (value, previous) => {
    if (previous !== undefined) menu?.unregister(previous)
    if (value !== undefined) menu?.register(value, submenu.ancestors)
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (props.value !== undefined) menu?.unregister(props.value)
})

function onClick(event: MouseEvent) {
  if (props.disabled) {
    event.preventDefault()
    event.stopPropagation()
    return
  }

  emit('click', event)
  if (props.value !== undefined) menu?.select(props.value, event)

  /*
   * Choosing an entry is what a flyout was opened for, so it closes here rather than
   * on any click inside the panel — the branches in a panel are opened by clicking
   * too, and a panel that shut on that could never be opened past its first level.
   */
  menu?.closeFlyouts()
}
</script>

<template>
  <li class="wx-menu-item">
    <component
      :is="tag"
      v-bind="{ ...nativeAttrs, ...$attrs }"
      :class="classes"
      :style="{ '--wx-menu-depth': submenu.depth }"
      :title="collapsed ? label : undefined"
      @click="onClick"
    >
      <span v-if="icon || $slots.icon" class="wx-menu-row__icon">
        <slot name="icon">
          <wx-icon v-if="icon" :name="icon" />
        </slot>
      </span>

      <span class="wx-menu-row__label">
        <slot>{{ label }}</slot>
      </span>

      <span v-if="$slots.trailing" class="wx-menu-row__trailing">
        <slot name="trailing" />
      </span>
    </component>
  </li>
</template>

<style scoped>
/*
 * The entry owns its margins, and says so from a scoped rule so that it outweighs a
 * host stylesheet spacing list items — VitePress puts 8px between every `li + li`,
 * and a CMS theme will have its own. Left alone, that walks a menu bar down the page
 * like a staircase and pulls a sidebar's rows apart.
 */
.wx-menu-item {
  min-width: 0;
  margin: 0;
}
</style>

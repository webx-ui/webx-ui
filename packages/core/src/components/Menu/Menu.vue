<script setup lang="ts">
import { computed, provide, reactive, watch } from 'vue'
import { menuKey, type MenuValue } from '../../composables/useMenu'
import type { MenuEmits, MenuProps } from './types'

defineOptions({ name: 'WxMenu' })

const props = withDefaults(defineProps<MenuProps>(), {
  mode: 'vertical',
  size: 'md',
  collapsed: false,
  accordion: false,
  autoExpand: true,
  label: undefined,
})

const emit = defineEmits<MenuEmits>()

const active = defineModel<MenuValue | undefined>({ default: undefined })
const openKeys = defineModel<MenuValue[]>('open', { default: () => [] })

/** Where every entry sits: its value against the submenus above it. */
const positions = reactive(new Map<MenuValue, MenuValue[]>())

const collapsed = computed(() => props.collapsed && props.mode === 'vertical')

const trail = computed(() => {
  if (active.value === undefined) return []
  return positions.get(active.value) ?? []
})

function setOpen(value: MenuValue, open: boolean, ancestors: MenuValue[]) {
  if (!open) {
    openKeys.value = openKeys.value.filter((key) => key !== value)
    return
  }

  /*
   * In accordion mode the branch that opens is the whole answer: its own ancestors
   * stay open, everything else closes. Listing the ancestors beats hunting for
   * siblings, which would mean knowing the depth of every open key.
   */
  openKeys.value = props.accordion
    ? [...ancestors, value]
    : [...openKeys.value.filter((key) => key !== value), value]
}

provide(menuKey, {
  mode: computed(() => props.mode),
  size: computed(() => props.size),
  collapsed,
  active: computed(() => active.value),
  trail,
  select: (value, event) => {
    active.value = value
    emit('select', value, event)
  },
  isOpen: (value) => openKeys.value.includes(value),
  setOpen,
  register: (value, ancestors) => positions.set(value, ancestors),
  unregister: (value) => positions.delete(value),
})

/*
 * Following the active entry rather than the click: a sidebar is normally rendered
 * against the current route, so the branch has to open for a value that arrived
 * with the page instead of through a trigger.
 */
watch(
  [trail, () => props.autoExpand],
  ([ancestors, autoExpand]) => {
    if (!autoExpand || ancestors.length === 0) return
    const missing = ancestors.filter((key) => !openKeys.value.includes(key))
    if (missing.length === 0) return
    openKeys.value = props.accordion ? [...ancestors] : [...openKeys.value, ...missing]
  },
  { immediate: true },
)

const classes = computed(() => [
  'wx-menu',
  `wx-menu--${props.mode}`,
  `wx-menu--${props.size}`,
  { 'wx-menu--collapsed': collapsed.value },
])
</script>

<template>
  <ul :class="classes" :aria-label="label">
    <slot />
  </ul>
</template>

<style scoped>
/*
 * A list of links in a list of items, not `role="menu"`. That role promises the
 * keyboard model of a desktop application menu — arrow keys, type-ahead, one tab
 * stop — and navigation that is really a list of links is easier to use, and to
 * read out, exactly as what it is.
 */
.wx-menu {
  --wx-menu-row-height: 36px;
  --wx-menu-row-padding: var(--wx-space-10);
  --wx-menu-indent: var(--wx-space-16);
  --wx-menu-gap: 2px;

  display: flex;
  box-sizing: border-box;
  margin: 0;
  padding: var(--wx-space-8);
  list-style: none;
  background: transparent;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

.wx-menu--sm {
  --wx-menu-row-height: 30px;
  --wx-menu-row-padding: var(--wx-space-8);
  --wx-menu-indent: var(--wx-space-12);

  font-size: var(--wx-font-size-xs);
}

.wx-menu--vertical {
  flex-direction: column;
  gap: var(--wx-menu-gap);
  width: 100%;
}

.wx-menu--horizontal {
  flex-direction: row;
  align-items: center;
  gap: var(--wx-space-2);
  overflow-x: auto;
  /* A bar that has to scroll should not show a bar of its own doing it. */
  scrollbar-width: none;
}

.wx-menu--horizontal::-webkit-scrollbar {
  display: none;
}

.wx-menu--collapsed {
  align-items: center;
}
</style>

<script setup lang="ts">
import { computed, provide, ref, useId, watch } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import WxDropdown from '../Dropdown/Dropdown.vue'
import { submenuKey, useMenu, useSubmenu } from '../../composables/useMenu'
import '../../styles/menu.css'
import type { SubmenuEmits, SubmenuProps } from './types'

defineOptions({ name: 'WxSubmenu' })

const props = withDefaults(defineProps<SubmenuProps>(), {
  icon: undefined,
  title: undefined,
  disabled: false,
})

const emit = defineEmits<SubmenuEmits>()

const menu = useMenu()
const parent = useSubmenu()

const panelId = useId()

const ancestors = computed(() => [...parent.ancestors, props.value])

provide(submenuKey, {
  /*
   * A submenu is an ancestor of everything below it, so it puts itself on the list
   * it hands down. The entries indent by one and report the branch they sit in.
   */
  get ancestors() {
    return ancestors.value
  },
  depth: parent.depth + 1,
})

const collapsed = computed(() => menu?.collapsed.value ?? false)
const isTrail = computed(() => menu?.trail.value.includes(props.value) ?? false)

/**
 * Inline the branch drops open under its title; there is no room for that in a bar
 * or on an icon rail, so those open it as a panel beside the trigger instead.
 */
const asFlyout = computed(() => collapsed.value || menu?.mode.value === 'horizontal')

/*
 * A flyout is transient — it belongs to the pointer, not to the menu — so it keeps
 * its own state rather than joining `v-model:open`. Otherwise collapsing a sidebar
 * would throw open a panel for every branch that happened to be expanded.
 */
const flyoutOpen = ref(false)

const isOpen = computed(() =>
  asFlyout.value ? flyoutOpen.value : (menu?.isOpen(props.value) ?? false),
)

watch(asFlyout, () => {
  flyoutOpen.value = false
})

const flyoutSide = computed(() =>
  menu?.mode.value === 'horizontal' && parent.depth === 0 ? 'bottom' : 'right',
)

const chevron = computed(() => {
  if (!asFlyout.value) return 'chevron-down'
  return flyoutSide.value === 'bottom' ? 'chevron-down' : 'chevron-right'
})

const triggerClasses = computed(() => [
  'wx-menu-row',
  {
    'is-open': isOpen.value,
    'is-trail': isTrail.value && !isOpen.value,
    'is-disabled': props.disabled,
  },
])

function setOpen(open: boolean) {
  if (props.disabled) return
  if (asFlyout.value) flyoutOpen.value = open
  else menu?.setOpen(props.value, open, parent.ancestors)
  emit('toggle', open)
}

function onTriggerClick() {
  setOpen(!isOpen.value)
}
</script>

<template>
  <li class="wx-submenu" :class="{ 'is-open': isOpen }">
    <wx-dropdown
      v-if="asFlyout"
      class="wx-menu-flyout"
      :open="isOpen"
      :side="flyoutSide"
      align="start"
      :disabled="disabled"
      @update:open="setOpen"
    >
      <template #trigger>
        <button
          type="button"
          :class="triggerClasses"
          :disabled="disabled"
          :style="{ '--wx-menu-depth': parent.depth }"
          :title="collapsed ? title : undefined"
        >
          <span v-if="icon || $slots.icon" class="wx-menu-row__icon">
            <slot name="icon">
              <wx-icon v-if="icon" :name="icon" />
            </slot>
          </span>
          <span class="wx-menu-row__label">
            <slot name="title">{{ title }}</slot>
          </span>
          <wx-icon class="wx-menu-row__chevron" :name="chevron" size="0.9em" />
        </button>
      </template>

      <ul class="wx-menu-flyout__list">
        <slot />
      </ul>
    </wx-dropdown>

    <template v-else>
      <button
        type="button"
        :class="triggerClasses"
        :disabled="disabled"
        :style="{ '--wx-menu-depth': parent.depth }"
        :aria-expanded="isOpen"
        :aria-controls="panelId"
        @click="onTriggerClick"
      >
        <span v-if="icon || $slots.icon" class="wx-menu-row__icon">
          <slot name="icon">
            <wx-icon v-if="icon" :name="icon" />
          </slot>
        </span>
        <span class="wx-menu-row__label">
          <slot name="title">{{ title }}</slot>
        </span>
        <wx-icon class="wx-menu-row__chevron" :name="chevron" size="0.9em" />
      </button>

      <!--
        The panel animates between `0fr` and `1fr` rather than a measured height, so
        a branch of any length opens without JavaScript measuring it. `inert` keeps
        the links inside a closed branch out of the tab order while it is shut.
      -->
      <div :id="panelId" class="wx-submenu__panel" :class="{ 'is-open': isOpen }" :inert="!isOpen">
        <ul class="wx-submenu__list">
          <slot />
        </ul>
      </div>
    </template>
  </li>
</template>

<style scoped>
.wx-submenu {
  min-width: 0;
}

.wx-submenu__panel {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-submenu__panel.is-open {
  grid-template-rows: 1fr;
}

.wx-submenu__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-menu-gap, 2px);
  /* The grid row is what collapses; the list has to be allowed to be clipped by it. */
  min-height: 0;
  overflow: hidden;
  margin: 0;
  padding: 0;
  list-style: none;
}

@media (prefers-reduced-motion: reduce) {
  .wx-submenu__panel {
    transition: none;
  }
}
</style>

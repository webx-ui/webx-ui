<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import WxAction from '../Action/Action.vue'
import WxDropdown from '../Dropdown/Dropdown.vue'
import { actionsKey } from '../../composables/useActions'
import type { ActionsEmits, ActionsProps } from './types'

defineOptions({ name: 'WxActions' })

const props = withDefaults(defineProps<ActionsProps>(), {
  align: 'start',
  size: undefined,
  collapse: false,
  ariaLabel: undefined,
})

const emit = defineEmits<ActionsEmits>()

provide(actionsKey, { size: computed(() => props.size) })

const root = ref<HTMLElement | null>(null)
const row = ref<HTMLElement | null>(null)
const collapsed = ref(false)

/**
 * The row is measured against the space its container gives it. When it collapses
 * the row is not unmounted, only taken out of the flow — it keeps its natural width,
 * which is the only way to know when there is room for it again.
 */
function measure() {
  const rowEl = row.value
  const parent = root.value?.parentElement
  if (!props.collapse || !rowEl || !parent) return

  /* `|| 0`: a padding that was never set reads as an empty string, not as `0px`. */
  const style = getComputedStyle(parent)
  const padding = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0)
  const available = parent.clientWidth - padding

  /* jsdom and a container that has not been laid out yet both report zero. */
  if (available <= 0) return

  collapsed.value = rowEl.scrollWidth > available
}

let observer: ResizeObserver | undefined

function observe() {
  observer?.disconnect()
  if (!props.collapse) return

  observer = new ResizeObserver(() => measure())
  if (root.value?.parentElement) observer.observe(root.value.parentElement)
  if (row.value) observer.observe(row.value)
  measure()
}

onMounted(observe)
watch(() => props.collapse, observe)
watch(collapsed, (value) => emit('collapse', value))
onBeforeUnmount(() => observer?.disconnect())

const classes = computed(() => [
  'wx-actions',
  `wx-actions--${props.align}`,
  { 'is-collapsed': collapsed.value },
])

defineExpose({ collapsed, measure })
</script>

<template>
  <div ref="root" :class="classes" role="group" :aria-label="ariaLabel">
    <div ref="row" class="wx-actions__row">
      <slot />
    </div>

    <!-- The dropdown teleports its panel, so the class that hides it lives on a wrapper. -->
    <span v-if="collapse" class="wx-actions__menu">
      <wx-dropdown align="end">
        <template #trigger>
          <slot name="trigger">
            <wx-action type="more" :size="size" />
          </slot>
        </template>

        <!-- Falls back to the row itself, so a plain list of actions needs no second copy. -->
        <slot name="collapsed">
          <slot />
        </slot>
      </wx-dropdown>
    </span>
  </div>
</template>

<style scoped>
.wx-actions {
  display: flex;
  align-items: center;
  /* The row is taken out of the flow when collapsed, so it anchors here. */
  position: relative;
}

.wx-actions--start {
  justify-content: flex-start;
}

.wx-actions--center {
  justify-content: center;
}

.wx-actions--end {
  justify-content: flex-end;
}

.wx-actions__row {
  display: flex;
  align-items: center;
  gap: var(--wx-actions-gap, var(--wx-space-6));
}

/* Hidden, but still measurable: that is what lets the row come back. */
.wx-actions.is-collapsed .wx-actions__row {
  position: absolute;
  z-index: -1;
  visibility: hidden;
  height: 0;
  overflow: hidden;
  pointer-events: none;
}

.wx-actions:not(.is-collapsed) .wx-actions__menu {
  display: none;
}
</style>

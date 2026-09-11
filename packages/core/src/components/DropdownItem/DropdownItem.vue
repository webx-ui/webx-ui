<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import { useDropdown } from '../../composables/useDropdown'
import type { DropdownItemEmits, DropdownItemProps } from './types'

defineOptions({ name: 'WxDropdownItem', inheritAttrs: false })

const props = withDefaults(defineProps<DropdownItemProps>(), {
  icon: undefined,
  href: undefined,
  target: undefined,
  as: undefined,
  tone: 'default',
  active: false,
  disabled: false,
})

const emit = defineEmits<DropdownItemEmits>()

const dropdown = useDropdown()

const tag = computed(() => props.as ?? (props.href ? 'a' : 'button'))

const nativeAttrs = computed(() => {
  if (props.href) {
    return {
      href: props.disabled ? undefined : props.href,
      target: props.target,
      'aria-disabled': props.disabled ? 'true' : undefined,
      tabindex: props.disabled ? -1 : undefined,
    }
  }
  if (props.as) return { 'aria-disabled': props.disabled ? 'true' : undefined }
  return { type: 'button' as const, disabled: props.disabled }
})

const classes = computed(() => [
  'wx-dropdown-item',
  `wx-dropdown-item--${props.tone}`,
  { 'is-active': props.active, 'is-disabled': props.disabled },
])

function onClick(event: MouseEvent) {
  if (props.disabled) {
    event.preventDefault()
    event.stopPropagation()
    return
  }

  emit('click', event)

  /*
   * The panel closes here as well as on its own click handler: an item rendered
   * outside the dropdown's panel — in a collapsed actions row, say — still behaves.
   */
  if (dropdown?.closeOnSelect()) dropdown.close()
}
</script>

<template>
  <component :is="tag" v-bind="{ ...nativeAttrs, ...$attrs }" :class="classes" @click="onClick">
    <span v-if="icon || $slots.before" class="wx-dropdown-item__before">
      <slot name="before">
        <wx-icon v-if="icon" :name="icon" />
      </slot>
    </span>

    <span class="wx-dropdown-item__label"><slot /></span>

    <span v-if="$slots.after" class="wx-dropdown-item__after">
      <slot name="after" />
    </span>
  </component>
</template>

<style scoped>
.wx-dropdown-item {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  box-sizing: border-box;
  width: 100%;
  padding: var(--wx-space-6) var(--wx-space-10);
  background: none;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-dropdown-item-color, var(--wx-text-default));
  font-family: inherit;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-normal);
  text-align: start;
  text-decoration: none;
  cursor: pointer;
  transition: background-color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-dropdown-item:hover:not(.is-disabled),
.wx-dropdown-item.is-active {
  background: var(--wx-dropdown-item-bg, var(--wx-bg-fill));
}

.wx-dropdown-item:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-dropdown-item--default {
  --wx-dropdown-item-color: var(--wx-text-default);
}

.wx-dropdown-item--default:hover:not(.is-disabled),
.wx-dropdown-item--default.is-active {
  --wx-dropdown-item-color: var(--wx-color-primary);
}

.wx-dropdown-item--primary {
  --wx-dropdown-item-color: var(--wx-color-primary);
  --wx-dropdown-item-bg: var(--wx-color-primary-soft);
}

.wx-dropdown-item--danger {
  --wx-dropdown-item-color: var(--wx-color-danger);
  --wx-dropdown-item-bg: var(--wx-color-danger-soft);
}

.wx-dropdown-item--success {
  --wx-dropdown-item-color: var(--wx-color-success-active);
  --wx-dropdown-item-bg: var(--wx-color-success-soft);
}

.wx-dropdown-item--warning {
  --wx-dropdown-item-color: var(--wx-color-warning-active);
  --wx-dropdown-item-bg: var(--wx-color-warning-soft);
}

.wx-dropdown-item.is-disabled,
.wx-dropdown-item:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-dropdown-item__before,
.wx-dropdown-item__after {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  font-size: 16px;
}

.wx-dropdown-item__after {
  margin-left: auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-dropdown-item__label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

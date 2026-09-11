<script setup lang="ts">
import { provide, watch } from 'vue'
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import { dropdownKey } from '../../composables/useDropdown'
import type { DropdownEmits, DropdownProps } from './types'

defineOptions({ name: 'WxDropdown', inheritAttrs: false })

const props = withDefaults(defineProps<DropdownProps>(), {
  side: 'bottom',
  align: 'start',
  offset: 6,
  alignOffset: 0,
  closeOnClick: true,
  matchTriggerWidth: false,
  teleport: true,
  modal: false,
  disabled: false,
})

const emit = defineEmits<DropdownEmits>()

const open = defineModel<boolean>('open', { default: false })

function close() {
  open.value = false
}

provide(dropdownKey, { close, closeOnSelect: () => props.closeOnClick })

watch(open, (value) => {
  if (value) emit('open')
  else emit('close')
})

/**
 * A click anywhere in the panel closes it, the way a menu behaves. Items that run
 * their own logic still close through the same path, and a panel that should stay
 * open while it is worked in turns this off with `:close-on-click="false"`.
 */
function onPanelClick() {
  if (props.closeOnClick) close()
}
</script>

<template>
  <popover-root v-model:open="open" :modal="modal">
    <!--
      `as-child` rather than a button of our own: the trigger is nearly always a
      control that is already a button — WxButton, WxAction — and nesting one button
      inside another is invalid HTML. The slot must hold exactly one element.
    -->
    <popover-trigger as-child :disabled="disabled">
      <slot name="trigger" :open="open" />
    </popover-trigger>

    <popover-portal :disabled="!teleport">
      <popover-content
        v-bind="$attrs"
        class="wx-dropdown"
        :class="{ 'wx-dropdown--match-width': matchTriggerWidth }"
        :side="side"
        :align="align"
        :side-offset="offset"
        :align-offset="alignOffset"
        @click="onPanelClick"
      >
        <slot :close="close" />
      </popover-content>
    </popover-portal>
  </popover-root>
</template>

<style>
/* The panel is teleported, so its styles cannot be scoped to the component. */
.wx-dropdown {
  z-index: var(--wx-z-index-dropdown);
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 160px;
  max-width: min(90vw, 320px);
  padding: var(--wx-space-6);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

.wx-dropdown:focus-visible {
  outline: none;
}

.wx-dropdown--match-width {
  min-width: var(--reka-popover-trigger-width);
}

/* A rule between groups of items. */
.wx-dropdown hr,
.wx-dropdown .wx-dropdown__divider {
  height: 0;
  margin: var(--wx-space-4) 0;
  border: none;
  border-top: 1px solid var(--wx-border-muted);
}
</style>

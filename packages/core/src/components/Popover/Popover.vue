<script setup lang="ts">
import { computed, useSlots, watch } from 'vue'
import {
  PopoverArrow,
  PopoverClose,
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'reka-ui'
import WxIcon from '../Icon/Icon.vue'
import type { PopoverEmits, PopoverProps } from './types'

defineOptions({ name: 'WxPopover', inheritAttrs: false })

/*
 * A panel, not a menu. `WxDropdown` is the menu — it closes on any click inside,
 * because picking an item is the whole interaction. Here the click is part of the work
 * being done in the panel, so the panel stays until something closes it: the ×, a
 * button in the footer, Escape, or a click outside.
 */
const props = withDefaults(defineProps<PopoverProps>(), {
  side: 'bottom',
  align: 'center',
  offset: 8,
  alignOffset: 0,
  arrow: true,
  title: undefined,
  width: undefined,
  teleport: true,
  modal: false,
  disabled: false,
  closable: false,
  closeLabel: 'Close',
  ariaLabel: undefined,
})

const emit = defineEmits<PopoverEmits>()

defineSlots<{
  /** The control the panel hangs off. Exactly one element. */
  trigger?: (props: { open: boolean }) => unknown
  /** The panel. */
  default?: (props: { close: () => void }) => unknown
  /** Replaces `title`. */
  title?: () => unknown
  /** A row under the panel — where the Save and Cancel usually go. */
  footer?: (props: { close: () => void }) => unknown
}>()

const open = defineModel<boolean>('open', { default: false })

const slots = useSlots()

function close() {
  open.value = false
}

watch(open, (value) => {
  if (value) emit('open')
  else emit('close')
})

const hasHeader = computed(() => Boolean(props.title || slots.title || props.closable))

const panelStyle = computed(() => {
  const { width } = props
  if (width === undefined) return undefined

  /*
   * `width="280"` in a template is the string "280", which is not a CSS length and
   * would be dropped without a word. A bare number means pixels, however it arrives.
   */
  const bare = typeof width === 'number' || /^\d+(\.\d+)?$/.test(width)
  return { width: bare ? `${width}px` : width }
})

defineExpose({ close })
</script>

<template>
  <popover-root v-model:open="open" :modal="modal">
    <!--
      `as-child`: the trigger is whatever the caller already has — a button, an icon
      button, a tab — and wrapping it in a button of ours would nest one inside another.
    -->
    <popover-trigger as-child :disabled="disabled">
      <slot name="trigger" :open="open" />
    </popover-trigger>

    <popover-portal :disabled="!teleport">
      <popover-content
        v-bind="$attrs"
        class="wx-popover"
        :style="panelStyle"
        :side="side"
        :align="align"
        :side-offset="offset"
        :align-offset="alignOffset"
        :aria-label="ariaLabel"
      >
        <header v-if="hasHeader" class="wx-popover__header">
          <div class="wx-popover__title">
            <slot name="title">{{ title }}</slot>
          </div>

          <popover-close v-if="closable" class="wx-popover__close" :aria-label="closeLabel">
            <wx-icon name="close" />
          </popover-close>
        </header>

        <div class="wx-popover__body">
          <slot :close="close" />
        </div>

        <footer v-if="$slots.footer" class="wx-popover__footer">
          <slot name="footer" :close="close" />
        </footer>

        <popover-arrow v-if="arrow" class="wx-popover__arrow" :width="12" :height="6" />
      </popover-content>
    </popover-portal>
  </popover-root>
</template>

<style>
/* The panel is teleported, so its styles cannot be scoped to the component. */
.wx-popover {
  /*
   * Every floating panel of ours sits on this one layer — a select opened inside a
   * popover has to cover it, and the panels are added to the document as they open, so
   * the last one opened is the one on top. Separate layers would rank them by kind
   * instead, and a list would disappear behind the panel it was opened from.
   */
  z-index: var(--wx-z-index-popover);
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-10);
  width: max-content;
  min-width: 200px;
  max-width: min(90vw, 360px);
  padding: var(--wx-space-14) var(--wx-space-16);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

/* Reka focuses the panel when it opens; the ring belongs on what is inside it. */
.wx-popover:focus,
.wx-popover:focus-visible {
  outline: none;
}

.wx-popover__header {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
}

.wx-popover__title {
  flex: 1 1 auto;
  min-width: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-popover__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  /* Pulled into the padding so the × sits in the corner, not inside the text block. */
  margin: -4px -6px 0 0;
  width: 24px;
  height: 24px;
  padding: 0;
  background: none;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-md);
  cursor: pointer;
}

.wx-popover__close:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-popover__close:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/*
 * A column rather than a plain block: two fields dropped into a panel with nothing
 * around them would otherwise sit against each other. A `WxForm` inside keeps its own
 * rhythm — one child has nothing to be spaced from.
 */
.wx-popover__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-10);
  min-width: 0;
}

.wx-popover__footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--wx-space-8);
}

/*
 * The arrow is an open path — filled it is the triangle, stroked it is only the two
 * slanted edges, which is exactly the panel's border continuing around the point. The
 * pixel of overlap hides the border line that would otherwise run across its base.
 */
.wx-popover__arrow {
  transform: translateY(-1px);
  fill: var(--wx-bg-surface);
  stroke: var(--wx-border-default);
  stroke-width: 1px;
}
</style>

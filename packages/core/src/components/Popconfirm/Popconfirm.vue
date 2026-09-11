<script setup lang="ts">
import { computed, watch } from 'vue'
import { PopoverArrow, PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import WxButton from '../Button/Button.vue'
import WxIcon from '../Icon/Icon.vue'
import type { PopconfirmEmits, PopconfirmProps } from './types'

defineOptions({ name: 'WxPopconfirm', inheritAttrs: false })

/*
 * The question asked where the answer will land, rather than in a dialog in the
 * middle of the screen. Deleting one row out of forty is a small decision made in
 * one place, and a modal that blanks the page to ask about it loses the row the
 * reader was looking at. `WxDialog` is for the decisions worth stopping for.
 */
const props = withDefaults(defineProps<PopconfirmProps>(), {
  title: undefined,
  description: undefined,
  confirmText: 'Yes',
  cancelText: 'Cancel',
  confirmType: 'primary',
  icon: 'warning',
  side: 'top',
  align: 'center',
  offset: 8,
  arrow: true,
  width: 260,
  disabled: false,
  loading: false,
})

const emit = defineEmits<PopconfirmEmits>()

defineSlots<{
  /** The control that asks. Exactly one element. */
  trigger?: (props: { open: boolean }) => unknown
  /** Replaces the question and its explanation. */
  default?: () => unknown
  /** Replaces the two buttons. */
  actions?: (props: { confirm: () => void; cancel: () => void }) => unknown
}>()

const open = defineModel<boolean>('open', { default: false })

/* Whether this closing has already been reported, so it is not reported twice. */
let answered = false

function confirm() {
  answered = true
  emit('confirm')
  /* An answer that takes time keeps the panel open; the caller closes it when done. */
  if (!props.loading) open.value = false
}

function cancel() {
  answered = true
  emit('cancel')
  open.value = false
}

/*
 * Dismissed by Escape or by a click outside — which is a cancel, and says so. The
 * buttons above have already spoken for themselves, so the flag keeps this from
 * being a second answer to the same question.
 */
watch(open, (value, was) => {
  if (value) {
    answered = false
    return
  }

  if (was && !answered && !props.loading) emit('cancel')
})

const panelStyle = computed(() => {
  const { width } = props
  const bare = typeof width === 'number' || /^\d+(\.\d+)?$/.test(width)
  return { width: bare ? `${width}px` : width }
})

defineExpose({ close: cancel })
</script>

<template>
  <popover-root v-model:open="open">
    <popover-trigger as-child :disabled="disabled">
      <slot name="trigger" :open="open" />
    </popover-trigger>

    <popover-portal>
      <popover-content
        v-bind="$attrs"
        class="wx-popconfirm"
        :side="side"
        :align="align"
        :side-offset="offset"
        :style="panelStyle"
      >
        <div class="wx-popconfirm__body">
          <wx-icon v-if="icon" class="wx-popconfirm__icon" :name="icon" />

          <div class="wx-popconfirm__text">
            <slot>
              <p v-if="title" class="wx-popconfirm__title">{{ title }}</p>
              <p v-if="description" class="wx-popconfirm__description">{{ description }}</p>
            </slot>
          </div>
        </div>

        <div class="wx-popconfirm__actions">
          <slot name="actions" :confirm="confirm" :cancel="cancel">
            <wx-button size="sm" variant="text" :disabled="loading" @click="cancel">
              {{ cancelText }}
            </wx-button>
            <wx-button size="sm" :type="confirmType" :loading="loading" @click="confirm">
              {{ confirmText }}
            </wx-button>
          </slot>
        </div>

        <popover-arrow v-if="arrow" class="wx-popconfirm__arrow" :width="12" :height="6" />
      </popover-content>
    </popover-portal>
  </popover-root>
</template>

<style>
/* Teleported, so the styles cannot be scoped to the component. */
.wx-popconfirm {
  z-index: var(--wx-z-index-popover);
  box-sizing: border-box;
  padding: var(--wx-space-12);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-popconfirm__body {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
}

.wx-popconfirm__icon {
  flex: 0 0 auto;
  margin-top: 1px;
  color: var(--wx-color-warning);
}

.wx-popconfirm__text {
  min-width: 0;
}

.wx-popconfirm__title {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-popconfirm__description {
  margin: var(--wx-space-4) 0 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-normal);
}

.wx-popconfirm__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--wx-space-6);
  margin-top: var(--wx-space-12);
}

.wx-popconfirm__arrow {
  fill: var(--wx-bg-surface);
  stroke: var(--wx-border-default);
  stroke-width: 1px;
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import {
  ToastAction,
  ToastClose,
  ToastDescription,
  ToastProvider,
  ToastRoot,
  ToastTitle,
  ToastViewport,
} from 'reka-ui'
import WxIcon from '../Icon/Icon.vue'
import type { IconName } from '../Icon/types'
import { dismissToast, toastQueue, type ToastType } from '../../composables/useToast'
import type { ToasterProps } from './types'

defineOptions({ name: 'WxToaster' })

/*
 * The one component that shows the queue. Put it once, at the root of the app: it
 * renders nothing of its own until something is raised, and having two of them means
 * every toast appears twice.
 */
const props = withDefaults(defineProps<ToasterProps>(), {
  placement: 'bottom-end',
  width: 380,
  label: 'Notifications',
  max: 5,
  hotkey: () => ['F8'],
})

const GLYPH: Record<ToastType, IconName> = {
  default: 'info',
  success: 'check-circle',
  warning: 'warning',
  danger: 'close-circle',
  info: 'info',
}

/*
 * `setTimeout` turns `Infinity` into zero, so a toast asked to stay forever would
 * leave at once. The largest delay a timer actually honours is the way to say never.
 */
const FOREVER = 2_147_483_647

const queue = toastQueue()

/* The oldest are shown; anything past the limit waits for room. */
const shown = computed(() => (props.max > 0 ? queue.slice(0, props.max) : queue))

const width = computed(() => (typeof props.width === 'number' ? `${props.width}px` : props.width))

function iconOf(type: ToastType | undefined, icon: IconName | false | undefined) {
  if (icon === false) return undefined
  return icon ?? GLYPH[type ?? 'default']
}
</script>

<template>
  <toast-provider :label="label" :swipe-direction="placement.endsWith('start') ? 'left' : 'right'">
    <toast-root
      v-for="item in shown"
      :key="item.id"
      class="wx-toast"
      :class="`wx-toast--${item.type ?? 'default'}`"
      :open="item.open"
      :duration="item.duration === 0 ? FOREVER : item.duration"
      :style="{ '--wx-toast-width': width }"
      :aria-live="item.type === 'danger' ? 'assertive' : 'polite'"
      @update:open="(open: boolean) => !open && dismissToast(item.id)"
      @escape-key-down="dismissToast(item.id)"
    >
      <div class="wx-toast__body">
        <wx-icon
          v-if="iconOf(item.type, item.icon)"
          class="wx-toast__icon"
          :name="iconOf(item.type, item.icon)!"
        />

        <div class="wx-toast__text">
          <toast-title v-if="item.title" class="wx-toast__title">{{ item.title }}</toast-title>
          <toast-description v-if="item.description" class="wx-toast__description">
            {{ item.description }}
          </toast-description>
        </div>

        <toast-action
          v-if="item.action"
          class="wx-toast__action"
          :alt-text="item.action.label"
          @click="item.action.onClick()"
        >
          {{ item.action.label }}
        </toast-action>

        <toast-close v-if="item.closable" class="wx-toast__close" aria-label="Dismiss">
          <wx-icon name="close" />
        </toast-close>
      </div>
    </toast-root>

    <toast-viewport
      class="wx-toaster"
      :class="`wx-toaster--${placement}`"
      :hotkey="hotkey"
      :label="label"
    />
  </toast-provider>
</template>

<style>
/* Teleported by Reka, so the styles cannot be scoped to the component. */
.wx-toaster {
  position: fixed;
  z-index: var(--wx-z-index-toast);
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  box-sizing: border-box;
  max-width: 100vw;
  margin: 0;
  padding: var(--wx-space-16);
  list-style: none;
  /* The region is a hole in the page: only the toasts in it take a click. */
  pointer-events: none;
}

.wx-toaster > * {
  pointer-events: auto;
}

.wx-toaster--top-start {
  top: 0;
  inset-inline-start: 0;
}

.wx-toaster--top-center {
  top: 0;
  left: 50%;
  transform: translateX(-50%);
}

.wx-toaster--top-end {
  top: 0;
  inset-inline-end: 0;
}

.wx-toaster--bottom-start {
  bottom: 0;
  inset-inline-start: 0;
  flex-direction: column-reverse;
}

.wx-toaster--bottom-center {
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  flex-direction: column-reverse;
}

.wx-toaster--bottom-end {
  bottom: 0;
  inset-inline-end: 0;
  flex-direction: column-reverse;
}

.wx-toast {
  --wx-toast-accent: var(--wx-text-muted);

  box-sizing: border-box;
  width: var(--wx-toast-width, 380px);
  max-width: calc(100vw - var(--wx-space-32));
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-toast--success {
  --wx-toast-accent: var(--wx-color-success-active);
}

.wx-toast--warning {
  --wx-toast-accent: var(--wx-color-warning-active);
}

.wx-toast--danger {
  --wx-toast-accent: var(--wx-color-danger);
}

.wx-toast--info {
  --wx-toast-accent: var(--wx-color-info-active);
}

.wx-toast__body {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12);
}

.wx-toast__icon {
  flex: 0 0 auto;
  margin-top: 1px;
  color: var(--wx-toast-accent);
}

.wx-toast__text {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-toast__title {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-toast__description {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

/* With a title above it the message is the second line, and indents under it. */
.wx-toast__title + .wx-toast__description {
  margin-top: 2px;
}

.wx-toast__action {
  flex: 0 0 auto;
  align-self: center;
  padding: var(--wx-space-4) var(--wx-space-8);
  background: transparent;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-default);
  font: inherit;
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-medium);
  cursor: pointer;
}

.wx-toast__action:hover {
  background: var(--wx-bg-fill);
}

.wx-toast__close {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-toast__close:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-toast__close:focus-visible,
.wx-toast__action:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-toast[data-state='open'] .wx-toast__body {
  animation: wx-toast-in var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-toast[data-state='closed'] .wx-toast__body {
  animation: wx-toast-out var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-toast[data-swipe='move'] {
  transform: translateX(var(--reka-toast-swipe-move-x));
}

.wx-toast[data-swipe='end'] {
  animation: wx-toast-out var(--wx-duration-fast) var(--wx-easing-standard);
}

@keyframes wx-toast-in {
  from {
    opacity: 0;
    transform: translateY(8px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes wx-toast-out {
  from {
    opacity: 1;
  }

  to {
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-toast .wx-toast__body {
    animation: none;
  }
}
</style>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { BacktopProps } from './types'

defineOptions({ name: 'WxBacktop' })

/*
 * The scrolling thing is named rather than assumed. In an admin the page rarely
 * scrolls — the main column does — so a control that only ever listens to the window
 * would be a button that appears when nothing has happened and does nothing when
 * pressed.
 */
const props = withDefaults(defineProps<BacktopProps>(), {
  target: undefined,
  visibilityHeight: 200,
  right: 24,
  bottom: 24,
  smooth: true,
  ariaLabel: 'Back to top',
})

const emit = defineEmits<{ click: [event: MouseEvent] }>()

defineSlots<{ default?: () => unknown }>()

const visible = ref(false)

function scroller(): HTMLElement | Window | null {
  if (typeof document === 'undefined') return null
  if (!props.target) return window
  return typeof props.target === 'string'
    ? document.querySelector<HTMLElement>(props.target)
    : (props.target ?? null)
}

function offsetOf(element: HTMLElement | Window) {
  return element === window ? window.scrollY : (element as HTMLElement).scrollTop
}

function onScroll() {
  const element = scroller()
  if (!element) return
  visible.value = offsetOf(element) > props.visibilityHeight
}

function toTop(event: MouseEvent) {
  const element = scroller()
  if (!element) return

  element.scrollTo({ top: 0, behavior: props.smooth ? 'smooth' : 'auto' })
  emit('click', event)
}

let watched: HTMLElement | Window | null = null

onMounted(() => {
  watched = scroller()
  watched?.addEventListener('scroll', onScroll, { passive: true })
  onScroll()
})

onBeforeUnmount(() => {
  watched?.removeEventListener('scroll', onScroll)
  watched = null
})

const style = computed(() => ({
  insetInlineEnd: `${props.right}px`,
  bottom: `${props.bottom}px`,
}))
</script>

<template>
  <Transition name="wx-backtop">
    <button
      v-if="visible"
      type="button"
      class="wx-backtop"
      :style="style"
      :aria-label="ariaLabel"
      @click="toTop"
    >
      <slot>
        <wx-icon name="arrow-up" />
      </slot>
    </button>
  </Transition>
</template>

<style scoped>
.wx-backtop {
  position: fixed;
  z-index: var(--wx-z-index-sticky);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-muted);
  cursor: pointer;
  transition:
    color var(--wx-duration-fast) var(--wx-easing-standard),
    transform var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-backtop:hover {
  color: var(--wx-color-primary);
  transform: translateY(-2px);
}

.wx-backtop:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-backtop-enter-active,
.wx-backtop-leave-active {
  transition:
    opacity var(--wx-duration-fast) var(--wx-easing-standard),
    transform var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-backtop-enter-from,
.wx-backtop-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

@media (prefers-reduced-motion: reduce) {
  .wx-backtop,
  .wx-backtop-enter-active,
  .wx-backtop-leave-active {
    transition: none;
  }

  .wx-backtop:hover {
    transform: none;
  }
}
</style>

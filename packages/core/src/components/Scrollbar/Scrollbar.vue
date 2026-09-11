<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ScrollbarEmits, ScrollbarProps, ScrollMotion, ScrollTarget } from './types'

defineOptions({ name: 'WxScrollbar' })

const props = withDefaults(defineProps<ScrollbarProps>(), {
  height: undefined,
  maxHeight: undefined,
  axis: 'y',
  size: 'md',
  always: false,
  chainScroll: false,
})

const emit = defineEmits<ScrollbarEmits>()

const viewport = ref<HTMLElement | null>(null)

function toLength(value: number | string | undefined) {
  if (value === undefined) return undefined
  return typeof value === 'number' ? `${value}px` : value
}

const style = computed(() => {
  const vars: Record<string, string> = {}
  const height = toLength(props.height)
  const maxHeight = toLength(props.maxHeight)
  if (height) vars['--wx-scrollbar-height'] = height
  if (maxHeight) vars['--wx-scrollbar-max-height'] = maxHeight
  return vars
})

const classes = computed(() => [
  'wx-scrollbar',
  `wx-scrollbar--${props.axis}`,
  `wx-scrollbar--${props.size}`,
  {
    'wx-scrollbar--always': props.always,
    'wx-scrollbar--chain': props.chainScroll,
  },
])

/*
 * The element itself is exposed alongside the helpers: a virtual list or a chat
 * panel needs `scrollHeight` and `scrollTop` directly, and hiding them behind a
 * wrapper would only mean re-inventing them one method at a time.
 */
defineExpose({
  /** The scrolling element. */
  el: viewport,
  scrollTo: (options: ScrollTarget) => viewport.value?.scrollTo(options),
  scrollToTop: (behavior: ScrollMotion = 'auto') => viewport.value?.scrollTo({ top: 0, behavior }),
  scrollToBottom: (behavior: ScrollMotion = 'auto') =>
    viewport.value?.scrollTo({ top: viewport.value.scrollHeight, behavior }),
})
</script>

<template>
  <div ref="viewport" :class="classes" :style="style" @scroll="emit('scroll', $event)">
    <slot />
  </div>
</template>

<style scoped>
/*
 * Native scrolling, themed — not a pair of divs moved about in JavaScript. The
 * browser keeps keyboard scrolling, momentum and the trackpad overlay bar; all this
 * adds is the colour and the thickness.
 */
.wx-scrollbar {
  --wx-scrollbar-thickness: 10px;
  --wx-scrollbar-thumb: color-mix(in srgb, var(--wx-text-muted) 45%, transparent);
  --wx-scrollbar-thumb-hover: color-mix(in srgb, var(--wx-text-muted) 70%, transparent);

  box-sizing: border-box;
  height: var(--wx-scrollbar-height, auto);
  max-height: var(--wx-scrollbar-max-height, none);
  /* The thin bar in Firefox takes layout space; reserving it keeps text from jumping. */
  scrollbar-gutter: stable;
  scrollbar-width: thin;
  scrollbar-color: var(--wx-scrollbar-thumb) transparent;
  overscroll-behavior: contain;
}

.wx-scrollbar--chain {
  overscroll-behavior: auto;
}

.wx-scrollbar--sm {
  --wx-scrollbar-thickness: 6px;
}

.wx-scrollbar--y {
  overflow-x: hidden;
  overflow-y: auto;
}

.wx-scrollbar--x {
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-gutter: auto;
}

.wx-scrollbar--both {
  overflow: auto;
}

/* WebKit and Blink ignore `scrollbar-color`, so the same look is spelled out again. */
.wx-scrollbar::-webkit-scrollbar {
  width: var(--wx-scrollbar-thickness);
  height: var(--wx-scrollbar-thickness);
}

.wx-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.wx-scrollbar::-webkit-scrollbar-thumb {
  background: var(--wx-scrollbar-thumb);
  /* Clipping to the padding box insets the thumb without a track of its own. */
  background-clip: padding-box;
  border: 2px solid transparent;
  border-radius: var(--wx-radius-full);
}

.wx-scrollbar::-webkit-scrollbar-thumb:hover {
  background: var(--wx-scrollbar-thumb-hover);
  background-clip: padding-box;
}

.wx-scrollbar::-webkit-scrollbar-corner {
  background: transparent;
}

/*
 * Quiet until the pointer is in the box or something inside it has focus — the bar
 * reports a scroll position, it is not a border, and a page of panels reads calmer
 * without a stack of them.
 */
.wx-scrollbar:not(.wx-scrollbar--always) {
  scrollbar-color: transparent transparent;
}

.wx-scrollbar:not(.wx-scrollbar--always):hover,
.wx-scrollbar:not(.wx-scrollbar--always):focus-within {
  scrollbar-color: var(--wx-scrollbar-thumb) transparent;
}

.wx-scrollbar:not(.wx-scrollbar--always)::-webkit-scrollbar-thumb {
  background: transparent;
}

.wx-scrollbar:not(.wx-scrollbar--always):hover::-webkit-scrollbar-thumb,
.wx-scrollbar:not(.wx-scrollbar--always):focus-within::-webkit-scrollbar-thumb {
  background: var(--wx-scrollbar-thumb);
  background-clip: padding-box;
}
</style>

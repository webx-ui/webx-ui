<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { AffixEmits, AffixProps } from './types'

defineOptions({ name: 'WxAffix' })

/*
 * `position: sticky` does the sticking; the JavaScript here is only to report it.
 *
 * That is the whole design. Every implementation that measures scroll offsets and
 * switches to `position: fixed` inherits the same two bugs — the page jumps by the
 * height of the element the moment it leaves the flow, and it sticks to the window
 * rather than to whatever is actually scrolling. Sticky has neither, and it works
 * without a single listener.
 */
const props = withDefaults(defineProps<AffixProps>(), {
  offset: 0,
  position: 'top',
  disabled: false,
  zIndex: undefined,
})

const emit = defineEmits<AffixEmits>()

defineSlots<{ default?: (props: { stuck: boolean }) => unknown }>()

const stuck = ref(false)

/*
 * A sentinel one pixel outside the sticky element, watched by an observer: while it
 * is on screen the element is in its place, and the moment it is not, the element
 * has stuck. It is the one way to be told about sticky without polling the scroll.
 */
const sentinel = ref<HTMLElement | null>(null)

let observer: IntersectionObserver | null = null

function watchSentinel() {
  observer?.disconnect()
  observer = null

  if (props.disabled || !sentinel.value || typeof IntersectionObserver === 'undefined') {
    if (stuck.value) {
      stuck.value = false
      emit('change', false)
    }
    return
  }

  /*
   * The viewport is shrunk by the resting offset, so the sentinel stops intersecting
   * at exactly the moment the element above it would have been pushed past its
   * resting place — which is the moment it sticks.
   */
  const margin =
    props.position === 'top'
      ? `${-props.offset - 1}px 0px 0px 0px`
      : `0px 0px ${-props.offset - 1}px 0px`

  observer = new IntersectionObserver(
    ([entry]) => {
      const next = !entry.isIntersecting
      if (next === stuck.value) return
      stuck.value = next
      emit('change', next)
    },
    { rootMargin: margin, threshold: 0 },
  )

  observer.observe(sentinel.value)
}

onMounted(watchSentinel)
watch([() => props.disabled, () => props.offset, () => props.position], watchSentinel)

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
})

const style = computed(() => {
  if (props.disabled) return undefined
  return {
    position: 'sticky' as const,
    [props.position]: `${props.offset}px`,
    zIndex: props.zIndex,
  }
})
</script>

<template>
  <div class="wx-affix">
    <div
      v-if="!disabled"
      ref="sentinel"
      class="wx-affix__sentinel"
      :class="`wx-affix__sentinel--${position}`"
      aria-hidden="true"
    />

    <div class="wx-affix__content" :class="{ 'is-stuck': stuck }" :style="style">
      <slot :stuck="stuck" />
    </div>
  </div>
</template>

<style scoped>
.wx-affix {
  position: relative;
}

/*
 * A pixel tall and invisible, sitting exactly where the element will come to rest.
 * It is only ever looked at by the observer.
 */
.wx-affix__sentinel {
  position: absolute;
  inset-inline: 0;
  height: 1px;
  pointer-events: none;
  visibility: hidden;
}

.wx-affix__sentinel--top {
  top: 0;
}

.wx-affix__sentinel--bottom {
  bottom: 0;
}
</style>

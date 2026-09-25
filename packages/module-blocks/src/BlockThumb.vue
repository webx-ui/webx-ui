<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useElementWidth, WxIcon } from '@webx-ui/core'
import { stageDocument, thumbDocument } from './frame'
import { useSiteShell } from './shell'
import type { BlockThumbnail } from './types'

/**
 * A block drawn small: the sample render in an iframe, scaled down to the width it is given.
 *
 * An iframe rather than inline markup because a block's styles are a block's business — a
 * stray `h2 { }` in one type would otherwise restyle the whole list of cards — and because
 * the thumbnail is a picture of the site, not a part of the panel.
 *
 * Drawn in the site's own clothes when the site has a stage: its stylesheets and fonts, and
 * the wrappers the block sits in on a page, without the header, the footer or any script
 * (see `siteShell`). Bare until that arrives, and bare where there is none.
 *
 * `fit` is for components. A block is a band of a page, and the top of it at full width is the
 * right picture; a component is a whole thing — a card taller than it is wide — and cut to the
 * height of the box it shows only its photo. So with `fit` the root of what was drawn is measured
 * inside the frame and scaled to fit the box both ways, in the middle of it.
 */
const props = withDefaults(
  defineProps<{
    thumbnail: BlockThumbnail | null
    /** The width the block is drawn at, before scaling — a desktop. */
    width?: number
    /** How tall the picture is, in the panel's pixels. */
    height?: number
    /** Scale what was drawn to fit the box, rather than the width to fill it. */
    fit?: boolean
  }>(),
  { width: 1280, height: 120, fit: false },
)

const box = ref<HTMLElement | null>(null)
const boxWidth = useElementWidth(box)

/** The root of what was drawn, in the frame's pixels; null until the frame has loaded. */
const drawn = ref<{ left: number; top: number; width: number; height: number } | null>(null)

/*
 * How tall the frame is before scaling, when fitting. Tall enough for a card to lay itself out
 * at its own height rather than squeezed into the box's; a layout in `vh` sees this too.
 */
const TALL = 1600

const widthScale = computed(() => (boxWidth.value > 0 ? boxWidth.value / props.width : 0.2))

const fitted = computed(() => {
  const rect = drawn.value

  if (!props.fit || !rect || rect.width <= 0 || rect.height <= 0 || boxWidth.value <= 0) {
    return null
  }

  // Never larger than the width scale: a small badge stays small rather than blown up.
  const scale = Math.min(widthScale.value, boxWidth.value / rect.width, props.height / rect.height)

  return {
    scale,
    x: (boxWidth.value - rect.width * scale) / 2 - rect.left * scale,
    y: (props.height - rect.height * scale) / 2 - rect.top * scale,
  }
})

const shell = useSiteShell()

const srcdoc = computed(() => {
  if (!props.thumbnail) return ''

  const input = { html: props.thumbnail.html, styles: props.thumbnail.styles }

  return shell.value ? thumbDocument(shell.value, input) : stageDocument(input)
})

const frameStyle = computed(() => {
  const fit = fitted.value

  const rect = drawn.value

  if (fit && rect) {
    return {
      width: `${props.width}px`,
      height: `${TALL}px`,
      transform: `translate(${fit.x}px, ${fit.y}px) scale(${fit.scale})`,
      // The component alone: the rest of the frame is a white page around it, and moved off
      // the corner it shows as a white slab beside a small badge.
      clipPath: `inset(${rect.top}px ${props.width - rect.left - rect.width}px ${TALL - rect.top - rect.height}px ${rect.left}px)`,
    }
  }

  return {
    width: `${props.width}px`,
    height: props.fit ? `${TALL}px` : `${Math.round(props.height / widthScale.value)}px`,
    transform: `scale(${widthScale.value})`,
    // Until it is measured, a fitted frame would flash its top at the wrong scale; measured and
    // found to have no size (a root with `display: contents`), it is drawn the ordinary way.
    visibility: props.fit && drawn.value === null ? ('hidden' as const) : undefined,
  }
})

let observer: ResizeObserver | null = null

/*
 * Measured inside the frame, and watched from inside it: a ResizeObserver belongs to its own
 * window and does not see another document's elements, and fonts and pictures arriving late
 * change the card's height after the load event.
 */
function onLoad(event: Event): void {
  if (!props.fit) return

  observer?.disconnect()

  const frame = event.target as HTMLIFrameElement
  const view = frame.contentWindow as (Window & typeof globalThis) | null
  const doc = frame.contentDocument

  if (!view || !doc?.body) return

  const root = doc.querySelector<HTMLElement>('[data-wx-block]') ?? doc.body

  const measure = (): void => {
    const rect = root.getBoundingClientRect()

    drawn.value = { left: rect.left, top: rect.top, width: rect.width, height: rect.height }
  }

  measure()
  observer = new view.ResizeObserver(measure)
  observer.observe(root)
}

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div ref="box" class="wx-block-thumb" :style="{ height: `${height}px` }">
    <iframe
      v-if="thumbnail"
      class="wx-block-thumb__frame"
      :srcdoc="srcdoc"
      :style="frameStyle"
      sandbox="allow-same-origin"
      tabindex="-1"
      aria-hidden="true"
      @load="onLoad"
    />
    <div v-else class="wx-block-thumb__empty">
      <wx-icon name="grid" />
    </div>
  </div>
</template>

<style scoped>
.wx-block-thumb {
  position: relative;
  overflow: hidden;
  background: var(--wx-bg-subtle);
}

.wx-block-thumb__frame {
  position: absolute;
  inset-block-start: 0;
  inset-inline-start: 0;
  border: 0;
  transform-origin: top left;
  pointer-events: none;
  background: #fff;
}

.wx-block-thumb__empty {
  display: grid;
  place-items: center;
  height: 100%;
  color: var(--wx-text-muted);
}
</style>

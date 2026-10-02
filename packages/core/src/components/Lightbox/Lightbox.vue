<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import WxIcon from '../Icon/Icon.vue'
import { clamp } from '../../composables/useOverlayPanel'
import { counter, resolveItem } from './items'
import type { LightboxEmits, LightboxProps } from './types'

defineOptions({ name: 'WxLightbox' })

/*
 * A gallery over the whole screen: one item at a time, arrows and keys between them, a
 * strip of the rest, zoom and pan on a picture, and a player behind a video's poster.
 *
 * Reka's dialog brings what has no look of its own — the focus trap, Escape, the scroll
 * lock, the portal, giving focus back to whatever opened it. Everything that is seen is ours,
 * and the dimming is its own token: a photograph judged on a page that shows through it is
 * judged on the wrong colours.
 */
const props = withDefaults(defineProps<LightboxProps>(), {
  loop: false,
  /* `undefined` and not `false`: absent means "when there is more than one". */
  thumbnails: undefined,
  maxZoom: 4,
  original: true,
  ariaLabel: 'Gallery',
  prevLabel: 'Previous',
  nextLabel: 'Next',
  closeLabel: 'Close',
  zoomInLabel: 'Zoom in',
  zoomOutLabel: 'Zoom out',
  originalLabel: 'Open the original',
  playLabel: 'Play',
  counterText: '{index} of {total}',
})

const emit = defineEmits<LightboxEmits>()

const open = defineModel<boolean>('open', { default: false })
const index = defineModel<number>('index', { default: 0 })

const list = computed(() => props.items.map(resolveItem))
const total = computed(() => list.value.length)
const position = computed(() => clamp(index.value, 0, Math.max(0, total.value - 1)))
const current = computed(() => list.value[position.value])
const many = computed(() => total.value > 1)
const strip = computed(() => (props.thumbnails ?? many.value) && many.value)

const canPrev = computed(() => many.value && (props.loop || position.value > 0))
const canNext = computed(() => many.value && (props.loop || position.value < total.value - 1))

const counterLabel = computed(() => counter(props.counterText, position.value + 1, total.value))

function go(to: number) {
  if (!many.value) return
  const next = props.loop ? (to + total.value) % total.value : clamp(to, 0, total.value - 1)
  if (next === position.value) return
  index.value = next
  emit('change', next)
}

const prev = () => go(position.value - 1)
const next = () => go(position.value + 1)

function close() {
  open.value = false
}

/* ---------------------------------------------------------------- the item on screen */

const loaded = ref(false)
const failed = ref(false)
const playing = ref(false)

const isVideo = computed(() => Boolean(current.value?.video))
const zoomable = computed(() => !isVideo.value && loaded.value && !failed.value)

/* ---------------------------------------------------------------- zoom and pan */

const stage = ref<HTMLElement | null>(null)
const picture = ref<HTMLImageElement | null>(null)

/*
 * The picture is laid out to fit, and everything after that is a transform on top: `scale`
 * is a multiple of the fitted size, and `x`/`y` move the picture's centre away from the
 * stage's. Nothing is re-laid out while a finger moves.
 */
const view = reactive({ scale: 1, x: 0, y: 0 })

/* A swipe in progress: how far the picture follows the finger before it lets go. */
const drag = reactive({ x: 0, y: 0, active: false })

function resetView() {
  view.scale = 1
  view.x = 0
  view.y = 0
}

/** Actual pixels, as a multiple of the fitted size: what "100%" is for this picture. */
function actualScale() {
  const img = picture.value
  if (!img || !img.offsetWidth || !img.naturalWidth) return 1
  return img.naturalWidth / img.offsetWidth
}

const maxScale = () => Math.max(props.maxZoom, actualScale())

/** Keeps the picture from being dragged off its own edge. */
function clampPan() {
  const img = picture.value
  const box = stage.value
  if (!img || !box) return
  const spareX = Math.max(0, (img.offsetWidth * view.scale - box.clientWidth) / 2)
  const spareY = Math.max(0, (img.offsetHeight * view.scale - box.clientHeight) / 2)
  view.x = clamp(view.x, -spareX, spareX)
  view.y = clamp(view.y, -spareY, spareY)
}

/**
 * Zooms keeping the point `(px, py)` — measured from the stage's centre — where it is on
 * screen, which is what makes zooming at the cursor and between two fingers feel anchored.
 */
function zoomTo(scale: number, px = 0, py = 0) {
  if (!zoomable.value) return
  const next = clamp(scale, 1, maxScale())
  const ratio = next / view.scale
  view.x = px - (px - view.x) * ratio
  view.y = py - (py - view.y) * ratio
  view.scale = next
  clampPan()
}

const ZOOM_STEP = 1.5

const zoomIn = () => zoomTo(view.scale * ZOOM_STEP)
const zoomOut = () => zoomTo(view.scale / ZOOM_STEP)
const canZoomIn = computed(() => zoomable.value && view.scale < maxScale() - 0.001)
const canZoomOut = computed(() => zoomable.value && view.scale > 1)

function fromCentre(clientX: number, clientY: number) {
  const box = stage.value?.getBoundingClientRect()
  if (!box) return { px: 0, py: 0 }
  return { px: clientX - box.left - box.width / 2, py: clientY - box.top - box.height / 2 }
}

/** Fitted ↔ close up: actual pixels, or twice the fit for a picture smaller than the screen. */
function toggleZoom(clientX: number, clientY: number) {
  if (view.scale > 1) {
    resetView()
    return
  }
  const { px, py } = fromCentre(clientX, clientY)
  zoomTo(Math.max(actualScale(), 2), px, py)
}

function onWheel(event: WheelEvent) {
  if (!zoomable.value) return
  event.preventDefault()
  const { px, py } = fromCentre(event.clientX, event.clientY)
  zoomTo(view.scale * Math.exp(-event.deltaY * 0.002), px, py)
}

/* ---------------------------------------------------------------- pointers */

const pointers = new Map<number, { x: number; y: number }>()

/* A finger or a button is down: the picture follows it with no easing. */
const moving = ref(false)

/** Where a one-finger gesture started; the pinch keeps its own. */
let gesture: { x: number; y: number; viewX: number; viewY: number; moved: boolean } | null = null
let pinch: { distance: number } | null = null
let lastTap = { time: 0, x: 0, y: 0 }

/** A tap moves less than this; a swipe needs more than the other. */
const TAP_SLOP = 6
const SWIPE = 60
const SWIPE_CLOSE = 110
const DOUBLE_TAP_MS = 300

function spread() {
  const [a, b] = [...pointers.values()]
  if (!a || !b) return { distance: 0, x: 0, y: 0 }
  return {
    distance: Math.hypot(a.x - b.x, a.y - b.y),
    x: (a.x + b.x) / 2,
    y: (a.y + b.y) / 2,
  }
}

function onPointerDown(event: PointerEvent) {
  /* A playing video keeps its own controls: a drag across them is a seek, not a swipe. */
  if (playing.value || event.button !== 0) return
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })
  ;(event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId)
  moving.value = true

  if (pointers.size === 2 && zoomable.value) {
    pinch = { distance: spread().distance }
    gesture = null
    drag.active = false
    drag.x = drag.y = 0
    return
  }

  if (pointers.size === 1) {
    gesture = { x: event.clientX, y: event.clientY, viewX: view.x, viewY: view.y, moved: false }
  }
}

function onPointerMove(event: PointerEvent) {
  if (!pointers.has(event.pointerId)) return
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (pinch && pointers.size === 2) {
    const now = spread()
    if (!pinch.distance) return
    const { px, py } = fromCentre(now.x, now.y)
    zoomTo(view.scale * (now.distance / pinch.distance), px, py)
    pinch.distance = now.distance
    return
  }

  if (!gesture) return
  const dx = event.clientX - gesture.x
  const dy = event.clientY - gesture.y
  if (!gesture.moved && Math.hypot(dx, dy) < TAP_SLOP) return
  gesture.moved = true

  if (view.scale > 1) {
    view.x = gesture.viewX + dx
    view.y = gesture.viewY + dy
    clampPan()
  } else {
    drag.active = true
    drag.x = dx
    /* Only downwards: that is the gesture that closes. */
    drag.y = Math.max(0, dy)
  }
}

function onPointerUp(event: PointerEvent) {
  if (!pointers.delete(event.pointerId)) return
  moving.value = pointers.size > 0

  if (pinch) {
    if (pointers.size < 2) pinch = null
    return
  }

  const done = gesture
  gesture = null
  if (!done) return

  if (!done.moved) {
    onTap(event)
    return
  }

  if (drag.active) {
    const { x, y } = drag
    drag.active = false
    drag.x = drag.y = 0
    if (Math.abs(x) > SWIPE && Math.abs(x) > y) {
      if (x < 0) next()
      else prev()
    } else if (y > SWIPE_CLOSE && y > Math.abs(x)) {
      close()
    }
  }
}

function onPointerCancel(event: PointerEvent) {
  pointers.delete(event.pointerId)
  moving.value = pointers.size > 0
  gesture = null
  pinch = null
  drag.active = false
  drag.x = drag.y = 0
}

/*
 * Double-tap is counted here rather than left to `dblclick`: with `touch-action: none` a
 * phone does not send one, and a mouse sending both would zoom in and straight back out.
 */
function onTap(event: PointerEvent) {
  const now = event.timeStamp
  const near = Math.hypot(event.clientX - lastTap.x, event.clientY - lastTap.y) < 24
  if (now - lastTap.time < DOUBLE_TAP_MS && near) {
    lastTap = { time: 0, x: 0, y: 0 }
    toggleZoom(event.clientX, event.clientY)
    return
  }
  lastTap = { time: now, x: event.clientX, y: event.clientY }
}

const pictureStyle = computed(() => {
  const x = view.x + drag.x
  const y = view.y + drag.y
  return {
    transform: `translate3d(${x}px, ${y}px, 0) scale(${view.scale})`,
    /* The page shows through a little more the further a picture is pulled down to close. */
    opacity: drag.y ? String(Math.max(0.4, 1 - drag.y / 400)) : undefined,
  }
})

/* ---------------------------------------------------------------- keys */

function onKeydown(event: KeyboardEvent) {
  if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return
  /* A focused player uses the arrows to seek. */
  if (event.target instanceof HTMLVideoElement) return

  const actions: Record<string, () => void> = {
    ArrowLeft: prev,
    ArrowRight: next,
    Home: () => go(0),
    End: () => go(total.value - 1),
    '+': zoomIn,
    '=': zoomIn,
    '-': zoomOut,
    '0': resetView,
  }

  const action = actions[event.key]
  if (!action) return
  event.preventDefault()
  action()
}

/* ---------------------------------------------------------------- moving between items */

/* Every item starts fitted, stopped and unknown. */
watch(
  () => [position.value, current.value?.src, open.value],
  () => {
    loaded.value = false
    failed.value = false
    playing.value = false
    resetView()
  },
)

/* The items list shrank under an open lightbox: stay on the last one there is. */
watch(total, () => {
  if (total.value && index.value !== position.value) index.value = position.value
})

/* The neighbours are fetched before they are asked for, so an arrow is never a wait. */
watch(
  () => [open.value, position.value],
  () => {
    if (!open.value || typeof Image === 'undefined') return
    for (const step of [1, -1]) {
      const neighbour = list.value[(position.value + step + total.value) % total.value]
      if (neighbour?.src) new Image().src = neighbour.src
    }
  },
  { immediate: true },
)

const thumbs = ref<HTMLElement | null>(null)

/*
 * The strip is scrolled by hand: `scrollIntoView` would scroll every scrollable ancestor
 * too, the page under the lightbox — and a page in a frame — included.
 */
watch(
  () => [open.value, position.value],
  async () => {
    if (!open.value) return
    await nextTick()
    const row = thumbs.value
    const thumb = row?.children[position.value] as HTMLElement | undefined
    if (!row || !thumb) return
    row.scrollLeft = thumb.offsetLeft - (row.clientWidth - thumb.offsetWidth) / 2
  },
  { immediate: true },
)

const root = ref<HTMLElement | null>(null)

/* Focus goes to the gallery itself, so the arrows work at once and no button looks pressed. */
function onOpenAutoFocus(event: Event) {
  event.preventDefault()
  root.value?.focus()
}
</script>

<template>
  <dialog-root v-model:open="open">
    <dialog-portal>
      <dialog-overlay class="wx-lightbox__overlay" />

      <dialog-content
        class="wx-lightbox"
        :aria-describedby="undefined"
        @open-auto-focus="onOpenAutoFocus"
      >
        <div ref="root" class="wx-lightbox__frame" tabindex="-1" @keydown="onKeydown">
          <dialog-title class="wx-lightbox__title">
            {{ current?.caption || ariaLabel }}
          </dialog-title>

          <div class="wx-lightbox__bar">
            <span v-if="many" class="wx-lightbox__chip wx-lightbox__counter" aria-live="polite">
              {{ counterLabel }}
            </span>
            <span v-else />

            <div class="wx-lightbox__chip wx-lightbox__tools">
              <template v-if="!isVideo">
                <button
                  type="button"
                  class="wx-lightbox__tool"
                  :aria-label="zoomOutLabel"
                  :title="zoomOutLabel"
                  :disabled="!canZoomOut"
                  @click="zoomOut"
                >
                  <wx-icon name="zoom-out" />
                </button>
                <button
                  type="button"
                  class="wx-lightbox__tool"
                  :aria-label="zoomInLabel"
                  :title="zoomInLabel"
                  :disabled="!canZoomIn"
                  @click="zoomIn"
                >
                  <wx-icon name="zoom-in" />
                </button>
              </template>
              <a
                v-if="original && current?.original"
                class="wx-lightbox__tool"
                :href="current.original"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="originalLabel"
                :title="originalLabel"
              >
                <wx-icon name="external-link" />
              </a>
              <button
                type="button"
                class="wx-lightbox__tool"
                :aria-label="closeLabel"
                :title="closeLabel"
                @click="close"
              >
                <wx-icon name="close" />
              </button>
            </div>
          </div>

          <div
            ref="stage"
            class="wx-lightbox__stage"
            :class="{
              'is-zoomed': view.scale > 1,
              'is-moving': moving,
              'is-zoomable': zoomable,
            }"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerCancel"
            @wheel="onWheel"
          >
            <template v-if="current">
              <template v-if="current.video && playing">
                <iframe
                  v-if="current.video.kind === 'embed'"
                  :key="`embed-${position}`"
                  class="wx-lightbox__player"
                  :src="current.video.src"
                  :title="current.caption || ariaLabel"
                  allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
                  allowfullscreen
                />
                <video
                  v-else
                  :key="`video-${position}`"
                  class="wx-lightbox__player"
                  :src="current.video.src"
                  :poster="current.src || undefined"
                  controls
                  autoplay
                  playsinline
                />
              </template>

              <template v-else>
                <!--
                  The thumbnail stretched underneath, as `WxImage` does: on a slow line the
                  picture arrives into its own blurred outline instead of into nothing.
                -->
                <img
                  v-if="!loaded && !failed && current.thumb && current.thumb !== current.src"
                  class="wx-lightbox__blur"
                  :src="current.thumb"
                  alt=""
                  aria-hidden="true"
                />
                <img
                  v-if="current.src && !failed"
                  ref="picture"
                  :key="`picture-${position}`"
                  class="wx-lightbox__picture"
                  :class="{ 'is-loaded': loaded }"
                  :src="current.src"
                  :alt="current.alt"
                  :style="pictureStyle"
                  draggable="false"
                  @load="loaded = true"
                  @error="failed = true"
                />
                <div v-else-if="!current.video" class="wx-lightbox__failed">
                  <wx-icon name="image" />
                </div>

                <button
                  v-if="current.video"
                  type="button"
                  class="wx-lightbox__play"
                  :aria-label="playLabel"
                  @pointerdown.stop
                  @click="playing = true"
                >
                  <wx-icon name="play" />
                </button>
              </template>
            </template>
          </div>

          <button
            v-if="many"
            type="button"
            class="wx-lightbox__arrow wx-lightbox__arrow--prev"
            :aria-label="prevLabel"
            :title="prevLabel"
            :disabled="!canPrev"
            @click="prev"
          >
            <wx-icon name="chevron-left" />
          </button>
          <button
            v-if="many"
            type="button"
            class="wx-lightbox__arrow wx-lightbox__arrow--next"
            :aria-label="nextLabel"
            :title="nextLabel"
            :disabled="!canNext"
            @click="next"
          >
            <wx-icon name="chevron-right" />
          </button>

          <div class="wx-lightbox__foot">
            <p v-if="current?.caption" class="wx-lightbox__chip wx-lightbox__caption">
              {{ current.caption }}
            </p>

            <div v-if="strip" ref="thumbs" class="wx-lightbox__thumbs">
              <button
                v-for="(item, i) in list"
                :key="i"
                type="button"
                class="wx-lightbox__thumb"
                :class="{ 'is-current': i === position }"
                :aria-label="counter(counterText, i + 1, total)"
                :aria-current="i === position ? 'true' : undefined"
                @click="go(i)"
              >
                <img v-if="item.thumb" :src="item.thumb" alt="" loading="lazy" draggable="false" />
                <wx-icon v-else :name="item.video ? 'play' : 'image'" />
                <span v-if="item.video && item.thumb" class="wx-lightbox__thumb-play">
                  <wx-icon name="play" />
                </span>
              </button>
            </div>
          </div>
        </div>
      </dialog-content>
    </dialog-portal>
  </dialog-root>
</template>

<style scoped>
.wx-lightbox__overlay {
  position: fixed;
  inset: 0;
  z-index: var(--wx-z-index-overlay);
  background: var(--wx-bg-lightbox);
}

.wx-lightbox {
  position: fixed;
  inset: 0;
  z-index: var(--wx-z-index-dialog);
  outline: none;
}

.wx-lightbox__frame {
  position: relative;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: 100%;
  height: 100%;
  box-sizing: border-box;
  font-family: var(--wx-font-family-sans);
  outline: none;
}

.wx-lightbox__title {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

/* ------------------------------------------------------------ bar and chips */

.wx-lightbox__bar {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  padding: var(--wx-space-12) var(--wx-space-16);
}

/* Everything with words or buttons on it sits on a surface, never straight on the dimming. */
.wx-lightbox__chip {
  box-sizing: border-box;
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-popover);
}

.wx-lightbox__counter {
  padding: var(--wx-space-6) var(--wx-space-14);
  font-size: var(--wx-font-size-sm);
  font-variant-numeric: tabular-nums;
}

.wx-lightbox__tools {
  display: flex;
  gap: var(--wx-space-2);
  padding: var(--wx-space-4);
}

.wx-lightbox__tool,
.wx-lightbox__arrow,
.wx-lightbox__play {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: none;
  color: inherit;
  cursor: pointer;
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    opacity var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-lightbox__tool {
  width: var(--wx-size-control-sm);
  height: var(--wx-size-control-sm);
  background: transparent;
  border-radius: var(--wx-radius-full);
}

.wx-lightbox__tool:hover:not(:disabled) {
  background: var(--wx-bg-fill);
}

.wx-lightbox__tool:disabled,
.wx-lightbox__arrow:disabled {
  cursor: default;
  opacity: 0.4;
}

.wx-lightbox__tool:focus-visible,
.wx-lightbox__arrow:focus-visible,
.wx-lightbox__play:focus-visible,
.wx-lightbox__thumb:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/* ------------------------------------------------------------ stage */

.wx-lightbox__stage {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
  overflow: hidden;
  padding: 0 var(--wx-space-64);
  /* Every gesture here is ours: the phone must not scroll or zoom the page underneath. */
  touch-action: none;
  user-select: none;
}

.wx-lightbox__stage.is-zoomable {
  cursor: zoom-in;
}

.wx-lightbox__stage.is-zoomed {
  cursor: grab;
}

.wx-lightbox__stage.is-zoomed.is-moving {
  cursor: grabbing;
}

.wx-lightbox__picture,
.wx-lightbox__blur {
  display: block;
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.wx-lightbox__picture {
  position: relative;
  opacity: 0;
  transform-origin: center;
  transition:
    opacity var(--wx-duration-normal) var(--wx-easing-standard),
    transform var(--wx-duration-normal) var(--wx-easing-standard);
  will-change: transform;
  -webkit-user-drag: none;
}

.wx-lightbox__picture.is-loaded {
  opacity: 1;
}

/* Under a finger the picture follows at once; the easing is for letting go and for buttons. */
.wx-lightbox__stage.is-moving .wx-lightbox__picture {
  transition: none;
}

.wx-lightbox__blur {
  position: absolute;
  width: 100%;
  height: 100%;
  filter: blur(16px);
  opacity: 0.6;
}

.wx-lightbox__failed {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 160px;
  height: 160px;
  border-radius: var(--wx-radius-lg);
  background: var(--wx-bg-surface);
  color: var(--wx-text-placeholder);
}

.wx-lightbox__failed :deep(.wx-icon) {
  width: 40px;
  height: 40px;
}

.wx-lightbox__player {
  display: block;
  width: min(100%, calc((100vh - 220px) * 16 / 9));
  max-height: 100%;
  aspect-ratio: 16 / 9;
  border: none;
  background: var(--wx-color-black);
}

video.wx-lightbox__player {
  width: auto;
  max-width: 100%;
  aspect-ratio: auto;
}

.wx-lightbox__play {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 72px;
  height: 72px;
  margin: -36px 0 0 -36px;
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-dialog);
}

.wx-lightbox__play:hover {
  background: var(--wx-bg-subtle);
}

.wx-lightbox__play :deep(.wx-icon) {
  width: 30px;
  height: 30px;
  /* A triangle is heavier on its left; nudged, it looks centred. */
  margin-left: 4px;
}

/* ------------------------------------------------------------ arrows */

.wx-lightbox__arrow {
  position: absolute;
  top: 50%;
  z-index: 1;
  width: var(--wx-size-control-lg);
  height: var(--wx-size-control-lg);
  margin-top: calc(var(--wx-size-control-lg) / -2);
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-popover);
}

.wx-lightbox__arrow:hover:not(:disabled) {
  background: var(--wx-bg-subtle);
}

.wx-lightbox__arrow--prev {
  left: var(--wx-space-12);
}

.wx-lightbox__arrow--next {
  right: var(--wx-space-12);
}

/* ------------------------------------------------------------ caption and strip */

.wx-lightbox__foot {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-10);
  min-width: 0;
  padding: var(--wx-space-12) var(--wx-space-16) var(--wx-space-16);
}

.wx-lightbox__caption {
  max-width: min(720px, 100%);
  margin: 0;
  padding: var(--wx-space-6) var(--wx-space-16);
  border-radius: var(--wx-radius-lg);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
  text-align: center;
}

.wx-lightbox__thumbs {
  position: relative;
  display: flex;
  gap: var(--wx-space-8);
  max-width: 100%;
  padding: var(--wx-space-4);
  overflow-x: auto;
  scrollbar-width: none;
  scroll-behavior: smooth;
}

.wx-lightbox__thumb {
  position: relative;
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 48px;
  padding: 0;
  overflow: hidden;
  background: var(--wx-bg-surface);
  color: var(--wx-text-placeholder);
  border: 2px solid transparent;
  border-radius: var(--wx-radius-md);
  cursor: pointer;
  opacity: 0.55;
  transition:
    opacity var(--wx-duration-fast) var(--wx-easing-standard),
    border-color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-lightbox__thumb:hover {
  opacity: 0.85;
}

.wx-lightbox__thumb.is-current {
  border-color: var(--wx-color-primary);
  opacity: 1;
}

.wx-lightbox__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.wx-lightbox__thumb-play {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--wx-color-white);
  background: color-mix(in srgb, var(--wx-color-black) 30%, transparent);
}

/* ------------------------------------------------------------ small screens */

@media (max-width: 640px) {
  .wx-lightbox__stage {
    padding: 0;
  }

  /* A phone swipes; the arrows would only cover the picture. */
  .wx-lightbox__arrow,
  .wx-lightbox__thumbs {
    display: none;
  }
}

/* ------------------------------------------------------------ motion */

.wx-lightbox__overlay[data-state='open'],
.wx-lightbox[data-state='open'] {
  animation: wx-lightbox-in var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-lightbox__overlay[data-state='closed'],
.wx-lightbox[data-state='closed'] {
  animation: wx-lightbox-out var(--wx-duration-fast) var(--wx-easing-standard);
}

@keyframes wx-lightbox-in {
  from {
    opacity: 0;
  }
}

@keyframes wx-lightbox-out {
  to {
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-lightbox__overlay,
  .wx-lightbox,
  .wx-lightbox__picture,
  .wx-lightbox__thumb,
  .wx-lightbox__thumbs {
    animation: none !important;
    transition: none !important;
    scroll-behavior: auto;
  }
}
</style>
